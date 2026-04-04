package middleware

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/golang-jwt/jwt/v5"
	"hostvim/engine/internal/config"
)

func isOriginAllowed(origin string, allowedCSV string) bool {
	origin = strings.TrimSpace(origin)
	if origin == "" {
		return false
	}
	for _, item := range strings.Split(allowedCSV, ",") {
		v := strings.TrimSpace(item)
		if v == "" {
			continue
		}
		if strings.EqualFold(v, origin) {
			return true
		}
	}
	return false
}

func internalAPIHeader(c *gin.Context) string {
	h := strings.TrimSpace(c.GetHeader("X-Hostvim-Engine-Key"))
	if h != "" {
		return h
	}
	return strings.TrimSpace(c.GetHeader("X-Panelsar-Engine-Key"))
}

func AuthRequired(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		if cfg.Security.InternalAPIKey != "" && internalAPIHeader(c) == cfg.Security.InternalAPIKey {
			c.Set("user_id", float64(0))
			c.Set("role", "internal")
			c.Next()
			return
		}

		authHeader := c.GetHeader("Authorization")
		if authHeader == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "Authorization header required"})
			c.Abort()
			return
		}

		parts := strings.SplitN(authHeader, " ", 2)
		if len(parts) != 2 || parts[0] != "Bearer" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "Invalid authorization format"})
			c.Abort()
			return
		}

		tokenString := parts[1]

		token, err := jwt.Parse(tokenString, func(token *jwt.Token) (interface{}, error) {
			if _, ok := token.Method.(*jwt.SigningMethodHMAC); !ok {
				return nil, jwt.ErrSignatureInvalid
			}
			return []byte(cfg.Security.JWTSecret), nil
		})

		if err != nil || !token.Valid {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "Invalid token"})
			c.Abort()
			return
		}

		if claims, ok := token.Claims.(jwt.MapClaims); ok {
			c.Set("user_id", claims["user_id"])
			c.Set("role", claims["role"])
		}

		c.Next()
	}
}

// RequireInternalRole yalnızca dahili API anahtarı (X-Hostvim-Engine-Key veya eski X-Panelsar-Engine-Key) ile gelen isteklere izin verir.
// Aynı JWT imza anahtarıyla üretilen terminal veya diğer kullanıcı JWT'leri bu uçlara erişemez.
func RequireInternalRole() gin.HandlerFunc {
	return func(c *gin.Context) {
		roleVal, exists := c.Get("role")
		if !exists {
			c.JSON(http.StatusForbidden, gin.H{"error": "forbidden"})
			c.Abort()
			return
		}
		role, ok := roleVal.(string)
		if !ok || role != "internal" {
			c.JSON(http.StatusForbidden, gin.H{"error": "forbidden"})
			c.Abort()
			return
		}
		c.Next()
	}
}

func CORS(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		origin := c.GetHeader("Origin")
		allowedCSV := cfg.Security.AllowedOrigins
		if allowedCSV == "" {
			allowedCSV = "http://localhost,http://127.0.0.1"
		}

		if isOriginAllowed(origin, allowedCSV) {
			c.Header("Access-Control-Allow-Origin", origin)
			c.Header("Vary", "Origin")
		}
		c.Header("Access-Control-Allow-Methods", "GET, POST, PUT, PATCH, DELETE, OPTIONS")
		c.Header("Access-Control-Allow-Headers", "Origin, Content-Type, Accept, Authorization, X-Requested-With, X-Hostvim-Engine-Key, X-Panelsar-Engine-Key")
		c.Header("Access-Control-Max-Age", "86400")

		if c.Request.Method == "OPTIONS" {
			if !isOriginAllowed(origin, allowedCSV) {
				c.AbortWithStatus(http.StatusForbidden)
				return
			}
			c.AbortWithStatus(http.StatusNoContent)
			return
		}

		c.Next()
	}
}
