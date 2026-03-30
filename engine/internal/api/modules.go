package api

import (
	"encoding/base64"
	"errors"
	"mime"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/panelsar/engine/internal/config"
	"github.com/panelsar/engine/internal/daemon"
	"github.com/panelsar/engine/internal/files"
	"github.com/panelsar/engine/internal/installer"
	"github.com/panelsar/engine/internal/monitoring"
	"github.com/panelsar/engine/internal/panelmirror"
	"github.com/panelsar/engine/internal/stack"
	"github.com/panelsar/engine/internal/tools"
	"github.com/sirupsen/logrus"
)

func registerModuleRoutes(cfg *config.Config, d *daemon.Daemon, api *gin.RouterGroup, log *logrus.Logger) {
	api.GET("/system/stats", func(c *gin.Context) {
		ext := monitoring.CollectExtended(cfg.Paths.WebRoot)
		c.JSON(http.StatusOK, gin.H{
			"data": gin.H{
				"cpu_usage":      ext.CPUUsagePercent,
				"memory_total":   ext.MemoryTotal,
				"memory_used":    ext.MemoryUsed,
				"memory_percent": ext.MemoryPercent,
				"disk_total":     ext.DiskTotal,
				"disk_used":      ext.DiskUsed,
				"disk_percent":   ext.DiskPercent,
				"uptime":         ext.Uptime,
				"hostname":       ext.Hostname,
				"os":             ext.OS,
				"cpu_model":      ext.CPUModel,
				"cpu_cores_logical": ext.CPUCoresLogical,
				"memory_available":  ext.MemoryAvailable,
				"swap_total":        ext.SwapTotal,
				"swap_used":         ext.SwapUsed,
				"swap_percent":      ext.SwapPercent,
				"top_cpu_processes":     ext.TopCPUProcesses,
				"top_memory_processes":  ext.TopMemoryProcesses,
				"top_disk_mounts":       ext.TopDiskMounts,
			},
		})
	})

	api.GET("/files/search", handleFileSearch(cfg))
	api.GET("/files", handleFileList(cfg))
	api.POST("/files/mkdir", handleFileMkdir(cfg))
	api.DELETE("/files", handleFileDelete(cfg))
	api.GET("/files/read", handleFileRead(cfg))
	api.POST("/files/write", handleFileWrite(cfg))
	api.POST("/files/create", handleFileCreate(cfg))
	api.POST("/files/rename", handleFileRename(cfg))
	api.POST("/files/move", handleFileMove(cfg))
	api.GET("/files/download", handleFileDownload(cfg))
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
	api.PATCH("/mail/:domain/mailbox", func(c *gin.Context) {
		var body struct {
			Email    string  `json:"email" binding:"required"`
			Password *string `json:"password"`
			QuotaMb  *int    `json:"quota_mb"`
		}
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		patch := make(map[string]interface{})
		if body.Password != nil {
			patch["password"] = *body.Password
		}
		if body.QuotaMb != nil {
			patch["quota_mb"] = *body.QuotaMb
		}
		if len(patch) == 0 {
			c.JSON(http.StatusBadRequest, gin.H{"error": "password or quota_mb required"})
			return
		}
		if err := panelmirror.MailPatchMailbox(cfg, c.Param("domain"), body.Email, patch); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "mailbox updated"})
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
	api.PATCH("/cron/:id", func(c *gin.Context) {
		var req struct {
			Schedule    string `json:"schedule" binding:"required"`
			Command     string `json:"command" binding:"required"`
			Description string `json:"description"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := panelmirror.CronUpdate(cfg, c.Param("id"), req.Schedule, req.Command, req.Description); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "cron job updated"})
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

	api.GET("/stack/modules", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"modules": stack.ModulesWithStatus()})
	})
	api.POST("/stack/install", func(c *gin.Context) {
		var req struct {
			BundleID string `json:"bundle_id" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		out, err := stack.InstallBundle(req.BundleID)
		if err != nil {
			if errors.Is(err, stack.ErrUnknownBundle) {
				c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
				return
			}
			c.JSON(http.StatusInternalServerError, gin.H{
				"error":  err.Error(),
				"output": out,
			})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "ok", "output": out, "modules": stack.ModulesWithStatus()})
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

func handleFileSearch(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		q := c.Query("q")
		if domain == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain required"})
			return
		}
		root := cfg.Paths.WebRoot + "/" + domain
		hits, err := files.SearchText(root, path, q, 200, 14, 2<<20)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"hits": hits})
	}
}

func handleFileList(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		limitStr := c.Query("limit")
		offsetStr := c.Query("offset")
		sortKey := strings.ToLower(strings.TrimSpace(c.Query("sort")))
		order := strings.ToLower(strings.TrimSpace(c.Query("order")))
		if domain == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain required"})
			return
		}

		limit := 200
		offset := 0
		if limitStr != "" {
			if v, err := strconv.Atoi(limitStr); err == nil && v > 0 && v <= 5000 {
				limit = v
			}
		}
		if offsetStr != "" {
			if v, err := strconv.Atoi(offsetStr); err == nil && v >= 0 {
				offset = v
			}
		}

		switch sortKey {
		case "name", "size", "mtime":
		default:
			sortKey = "name"
		}
		if order != "desc" {
			order = "asc"
		}
		root := cfg.Paths.WebRoot + "/" + domain
		list, total, err := files.ListPaged(root, path, offset, limit, sortKey, order)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{
			"entries": list,
			"total":   total,
			"offset":  offset,
			"limit":   limit,
		})
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
		b, err := files.ReadFileForEditor(root, path)
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

func handleFileCreate(cfg *config.Config) gin.HandlerFunc {
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
		if err := files.CreateFile(root, req.Path, []byte(req.Content), 0o644); err != nil {
			c.JSON(http.StatusUnprocessableEntity, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "created"})
	}
}

func handleFileRename(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
			From   string `json:"from" binding:"required"`
			To     string `json:"to" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		root := cfg.Paths.WebRoot + "/" + req.Domain
		if err := files.Rename(root, req.From, req.To); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		c.JSON(http.StatusOK, gin.H{"message": "renamed"})
	}
}

func handleFileMove(cfg *config.Config) gin.HandlerFunc {
	// Currently “move” is the same as rename within the engine root.
	return handleFileRename(cfg)
}

func handleFileDownload(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		if domain == "" || path == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain and path required"})
			return
		}

		root := cfg.Paths.WebRoot + "/" + domain
		b, err := files.ReadFileForDownload(root, path)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		// Return base64 so the panel can stream it safely.
		encoded := base64.StdEncoding.EncodeToString(b)
		ext := strings.ToLower(filepath.Ext(path))
		mimeType := mime.TypeByExtension(ext)
		if mimeType == "" {
			mimeType = "application/octet-stream"
		}
		filename := filepath.Base(path)

		c.JSON(http.StatusOK, gin.H{
			"content_base64": encoded,
			"filename":       filename,
			"mime":           mimeType,
			"size":           len(b),
		})
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
		if files.IsExecutionRiskFile(name) {
			c.JSON(http.StatusUnprocessableEntity, gin.H{"error": "upload of risky file types is blocked"})
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
