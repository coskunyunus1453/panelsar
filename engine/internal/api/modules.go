package api

import (
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/panelsar/engine/internal/config"
	"github.com/panelsar/engine/internal/daemon"
	"github.com/panelsar/engine/internal/files"
	"github.com/panelsar/engine/internal/installer"
	"github.com/panelsar/engine/internal/monitoring"
	"github.com/panelsar/engine/internal/panelmirror"
	"github.com/panelsar/engine/internal/tools"
	"github.com/sirupsen/logrus"
)

func registerModuleRoutes(cfg *config.Config, d *daemon.Daemon, api *gin.RouterGroup, log *logrus.Logger) {
	api.GET("/system/stats", func(c *gin.Context) {
		snap := monitoring.Collect(cfg.Paths.WebRoot)
		c.JSON(http.StatusOK, gin.H{
			"data": gin.H{
				"cpu_usage":     snap.CPUUsagePercent,
				"memory_total":  snap.MemoryTotal,
				"memory_used":   snap.MemoryUsed,
				"memory_percent": snap.MemoryPercent,
				"disk_total":    snap.DiskTotal,
				"disk_used":     snap.DiskUsed,
				"disk_percent":  snap.DiskPercent,
				"uptime":        snap.Uptime,
				"hostname":      snap.Hostname,
				"os":            snap.OS,
			},
		})
	})

	api.GET("/files", handleFileList(cfg))
	api.POST("/files/mkdir", handleFileMkdir(cfg))
	api.DELETE("/files", handleFileDelete(cfg))
	api.GET("/files/read", handleFileRead(cfg))
	api.POST("/files/write", handleFileWrite(cfg))
	api.POST("/files/upload", handleFileUpload(cfg))

	api.GET("/backups", func(c *gin.Context) {
		list, err := panelmirror.BackupsList(cfg)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"backups": list})
	})
	api.POST("/backups", func(c *gin.Context) {
		var req struct {
			Domain          string  `json:"domain" binding:"required"`
			Type            string  `json:"type"`
			PanelBackupID   float64 `json:"panel_backup_id"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		entry, err := panelmirror.BackupQueue(cfg, req.Domain, req.Type, req.PanelBackupID)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusAccepted, entry)
	})
	api.POST("/backups/:id/restore", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"message": "restore started", "id": c.Param("id")})
	})

	api.GET("/dns/:domain", func(c *gin.Context) {
		rows, err := panelmirror.DNSRecords(cfg, c.Param("domain"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"records": rows})
	})
	api.POST("/dns/:domain", func(c *gin.Context) {
		var body map[string]interface{}
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := panelmirror.DNSAdd(cfg, c.Param("domain"), body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusCreated, gin.H{"message": "dns record stored", "record": body})
	})
	api.DELETE("/dns/:domain/:id", func(c *gin.Context) {
		if err := panelmirror.DNSDelete(cfg, c.Param("domain"), c.Param("id")); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "dns record removed"})
	})

	api.GET("/ftp/:domain", func(c *gin.Context) {
		acct, err := panelmirror.FTPAccounts(cfg, c.Param("domain"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"accounts": acct})
	})
	api.POST("/ftp/:domain", func(c *gin.Context) {
		var body map[string]interface{}
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := panelmirror.FTPAdd(cfg, c.Param("domain"), body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusCreated, gin.H{"message": "ftp account stored"})
	})
	api.DELETE("/ftp/:domain/:user", func(c *gin.Context) {
		u := c.Param("user")
		if err := panelmirror.FTPDelete(cfg, c.Param("domain"), u); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "ftp account removed"})
	})

	api.GET("/mail/:domain", func(c *gin.Context) {
		ov, err := panelmirror.MailOverview(cfg, c.Param("domain"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, ov)
	})
	api.POST("/mail/:domain/mailbox", func(c *gin.Context) {
		var body map[string]interface{}
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := panelmirror.MailAddMailbox(cfg, c.Param("domain"), body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusCreated, gin.H{"message": "mailbox stored"})
	})
	api.DELETE("/mail/:domain/mailbox", func(c *gin.Context) {
		email := strings.TrimSpace(c.Query("email"))
		if email == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "email query required"})
			return
		}
		if err := panelmirror.MailDeleteMailbox(cfg, c.Param("domain"), email); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "mailbox removed"})
	})

	api.GET("/security/overview", func(c *gin.Context) {
		rules, _ := panelmirror.FirewallRulesList(cfg)
		c.JSON(http.StatusOK, gin.H{
			"fail2ban":      gin.H{"enabled": cfg.Security.Fail2banEnabled, "jails": []string{"sshd", "panelsar-auth"}},
			"firewall":      gin.H{"backend": "iptables", "default_policy": "DROP", "recent_rules": rules},
			"modsecurity":   gin.H{"enabled": false},
			"clamav":        gin.H{"last_scan": nil},
		})
	})
	api.POST("/security/firewall/rule", func(c *gin.Context) {
		var body gin.H
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := panelmirror.AppendFirewallRule(cfg, body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusAccepted, gin.H{"message": "firewall rule recorded"})
	})

	api.GET("/cron", func(c *gin.Context) {
		jobs, err := panelmirror.CronList(cfg)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"jobs": jobs})
	})
	api.POST("/cron", func(c *gin.Context) {
		var req struct {
			Schedule    string `json:"schedule" binding:"required"`
			Command     string `json:"command" binding:"required"`
			Description string `json:"description"`
			UserID      uint   `json:"user_id"`
			PanelJobID  uint   `json:"panel_job_id"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		id, err := panelmirror.CronAdd(cfg, req.Schedule, req.Command, req.Description, req.UserID, req.PanelJobID)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusCreated, gin.H{"message": "cron job registered", "id": id})
	})
	api.DELETE("/cron/:id", func(c *gin.Context) {
		if err := panelmirror.CronDelete(cfg, c.Param("id")); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "cron job removed"})
	})

	api.GET("/installer/apps", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"apps": []gin.H{
				{"id": "wordpress", "name": "WordPress", "version": "latest", "automated": true},
				{"id": "joomla", "name": "Joomla", "version": "latest", "automated": false},
				{"id": "laravel", "name": "Laravel", "version": "11.x", "automated": false},
				{"id": "drupal", "name": "Drupal", "version": "10.x", "automated": false},
				{"id": "prestashop", "name": "PrestaShop", "version": "8.x", "automated": false},
			},
		})
	})
	api.POST("/installer/install", handleInstallerInstall(cfg))
	api.POST("/sites/:domain/tools", handleSiteTools(cfg))

	api.POST("/license/validate", func(c *gin.Context) {
		var req struct {
			Key string `json:"key" binding:"required"`
		}
		_ = c.ShouldBindJSON(&req)
		c.JSON(http.StatusOK, gin.H{"valid": true, "plan": "enterprise", "expires_at": nil})
	})

	api.POST("/nginx/reload", func(c *gin.Context) {
		out, err := exec.Command("nginx", "-t").CombinedOutput()
		ok := err == nil
		if ok {
			_ = exec.Command("systemctl", "reload", "nginx").Run()
		}
		c.JSON(http.StatusOK, gin.H{"ok": ok, "nginx_test": strings.TrimSpace(string(out))})
	})

	api.GET("/system/processes", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"processes": []gin.H{}})
	})

	_ = d
	_ = log
}

func handleInstallerInstall(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			App         string `json:"app" binding:"required"`
			Domain      string `json:"domain" binding:"required"`
			DbHost      string `json:"db_host"`
			DbPort      int    `json:"db_port"`
			DbName      string `json:"db_name"`
			DbUser      string `json:"db_user"`
			DbPassword  string `json:"db_password"`
			TablePrefix string `json:"table_prefix"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		var db *installer.DBConfig
		if strings.TrimSpace(req.DbName) != "" {
			db = &installer.DBConfig{
				Host:        req.DbHost,
				Port:        req.DbPort,
				Name:        req.DbName,
				User:        req.DbUser,
				Password:    req.DbPassword,
				TablePrefix: req.TablePrefix,
			}
		}
		if err := installer.Run(cfg, req.App, req.Domain, db); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "installation completed", "app": req.App, "domain": req.Domain})
	}
}

func handleSiteTools(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Param("domain")
		var req struct {
			Tool   string `json:"tool" binding:"required"`
			Action string `json:"action" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		out, err := tools.Run(cfg, domain, req.Tool, req.Action)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error(), "output": out})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "ok", "output": out})
	}
}

func handleFileList(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		if domain == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain required"})
			return
		}
		root := cfg.Paths.WebRoot + "/" + domain
		list, err := files.List(root, path)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"entries": list})
	}
}

func handleFileMkdir(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
			Path   string `json:"path" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		root := cfg.Paths.WebRoot + "/" + req.Domain
		if err := files.Mkdir(root, req.Path); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "created"})
	}
}

func handleFileDelete(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		if domain == "" || path == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain and path required"})
			return
		}
		root := cfg.Paths.WebRoot + "/" + domain
		if err := files.Remove(root, path); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "removed"})
	}
}

func handleFileRead(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		if domain == "" || path == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain and path required"})
			return
		}
		root := cfg.Paths.WebRoot + "/" + domain
		b, err := files.ReadFile(root, path)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"content": string(b)})
	}
}

func handleFileWrite(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain  string `json:"domain" binding:"required"`
			Path    string `json:"path" binding:"required"`
			Content string `json:"content"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		root := cfg.Paths.WebRoot + "/" + req.Domain
		if err := files.WriteFile(root, req.Path, []byte(req.Content), 0o644); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "written"})
	}
}

func handleFileUpload(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.PostForm("domain")
		relDir := c.PostForm("path")
		if domain == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain required"})
			return
		}
		fh, err := c.FormFile("file")
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		name := filepath.Base(fh.Filename)
		if name == "." || name == ".." || name == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid filename"})
			return
		}
		root := cfg.Paths.WebRoot + "/" + domain
		rel := filepath.Join(relDir, name)
		dest, err := files.ResolveUnderRoot(root, rel)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := os.MkdirAll(filepath.Dir(dest), 0o755); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		if err := c.SaveUploadedFile(fh, dest); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "uploaded", "path": rel})
	}
}
