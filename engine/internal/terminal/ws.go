// Package terminal — tarayıcıdan WebSocket + PTY (Linux/macOS; Windows’ta derlenmez).
package terminal

import (
	"encoding/json"
	"net/http"
	"os"
	"os/exec"
	"sync/atomic"
	"syscall"

	"github.com/creack/pty"
	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
	"github.com/gorilla/websocket"
	"github.com/panelsar/engine/internal/config"
	"github.com/sirupsen/logrus"
)

const jwtTypTerminal = "terminal_ws"

// Kurulumda /usr/local/sbin/panelsar-terminal-root + sudoers ile root login kabuğu (www-data → sudo NOPASSWD).
const terminalRootLauncher = "/usr/local/sbin/panelsar-terminal-root"

var upgrader = websocket.Upgrader{
	ReadBufferSize:  4096,
	WriteBufferSize: 4096,
	CheckOrigin: func(r *http.Request) bool {
		return true
	},
}

type resizeMsg struct {
	Type string `json:"type"`
	Cols uint16 `json:"cols"`
	Rows uint16 `json:"rows"`
}

// HandleWS — ?token=JWT (jwt_secret ile imzalı, typ=terminal_ws, role=admin).
func HandleWS(cfg *config.Config, log *logrus.Logger) gin.HandlerFunc {
	return func(c *gin.Context) {
		if cfg.Security.JWTSecret == "" {
			c.JSON(http.StatusServiceUnavailable, gin.H{"error": "jwt_secret tanımlı değil; terminal kapalı."})
			return
		}

		tokenStr := c.Query("token")
		if tokenStr == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "token gerekli"})
			return
		}

		claims, err := parseTerminalClaims(tokenStr, cfg.Security.JWTSecret)
		if err != nil {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "geçersiz token"})
			return
		}

		typ, _ := claims["typ"].(string)
		if typ != jwtTypTerminal {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "token türü uyumsuz"})
			return
		}

		role, _ := claims["role"].(string)
		if role != "admin" {
			c.JSON(http.StatusForbidden, gin.H{"error": "yalnızca admin"})
			return
		}

		jwtUseRoot := jwtClaimUseRoot(claims)

		conn, err := upgrader.Upgrade(c.Writer, c.Request, nil)
		if err != nil {
			log.WithError(err).Warn("terminal websocket upgrade")
			return
		}

		shell := os.Getenv("PANELSAR_TERMINAL_SHELL")
		if shell == "" {
			shell = "/bin/bash"
		}

		var cmd *exec.Cmd
		wantRoot := jwtUseRoot && os.Getenv("PANELSAR_TERMINAL_NO_ROOT") != "1"
		if !wantRoot {
			cmd = exec.Command(shell, "-i")
		} else if fi, err := os.Stat(terminalRootLauncher); err == nil && !fi.IsDir() && fi.Mode()&0o111 != 0 {
			cmd = exec.Command("sudo", "-n", terminalRootLauncher)
		} else {
			cmd = exec.Command(shell, "-i")
		}

		cmd.Env = append(os.Environ(),
			"TERM=xterm-256color",
			"SHELL="+shell,
		)
		cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}

		ptmx, err := pty.Start(cmd)
		if err != nil {
			log.WithError(err).Error("pty start")
			_ = conn.WriteMessage(websocket.TextMessage, []byte("Kabuk başlatılamadı.\r\n"))
			_ = conn.Close()
			return
		}

		if err := pty.Setsize(ptmx, &pty.Winsize{Rows: 40, Cols: 120}); err != nil {
			log.WithError(err).Debug("pty initial size")
		}

		var closed int32

		defer func() {
			_ = ptmx.Close()
			if cmd.Process != nil {
				_ = cmd.Process.Kill()
			}
			_ = conn.Close()
		}()

		go func() {
			buf := make([]byte, 32*1024)
			for atomic.LoadInt32(&closed) == 0 {
				n, rerr := ptmx.Read(buf)
				if n > 0 {
					if werr := conn.WriteMessage(websocket.BinaryMessage, buf[:n]); werr != nil {
						atomic.StoreInt32(&closed, 1)
						return
					}
				}
				if rerr != nil {
					atomic.StoreInt32(&closed, 1)
					return
				}
			}
		}()

		for atomic.LoadInt32(&closed) == 0 {
			mt, payload, rerr := conn.ReadMessage()
			if rerr != nil {
				atomic.StoreInt32(&closed, 1)
				break
			}
			if mt == websocket.TextMessage {
				var rm resizeMsg
				if json.Unmarshal(payload, &rm) == nil && rm.Type == "resize" && rm.Cols > 0 && rm.Rows > 0 {
					_ = pty.Setsize(ptmx, &pty.Winsize{Rows: rm.Rows, Cols: rm.Cols})
				}
				continue
			}
			if mt == websocket.BinaryMessage && len(payload) > 0 {
				if _, werr := ptmx.Write(payload); werr != nil {
					atomic.StoreInt32(&closed, 1)
					break
				}
			}
		}
	}
}

// jwtClaimUseRoot — panel JWT içindeki use_root; yoksa veya tanınmazsa true (geriye uyum).
func jwtClaimUseRoot(claims jwt.MapClaims) bool {
	v, ok := claims["use_root"]
	if !ok {
		return true
	}
	switch x := v.(type) {
	case bool:
		return x
	case float64:
		return x != 0
	case string:
		return x == "1" || x == "true" || x == "TRUE" || x == "True"
	default:
		return true
	}
}

func parseTerminalClaims(tokenString, secret string) (jwt.MapClaims, error) {
	token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
		if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
			return nil, jwt.ErrSignatureInvalid
		}
		return []byte(secret), nil
	})
	if err != nil || !token.Valid {
		return nil, err
	}
	claims, ok := token.Claims.(jwt.MapClaims)
	if !ok {
		return nil, jwt.ErrSignatureInvalid
	}
	return claims, nil
}
