package api

import (
	"encoding/base64"
	"errors"
	"mime"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"

	"github.com/gin-gonic/gin"
	"hostvim/engine/internal/config"
	"hostvim/engine/internal/daemon"
	"hostvim/engine/internal/middleware"
	"hostvim/engine/internal/files"
	"hostvim/engine/internal/installer"
	"hostvim/engine/internal/monitoring"
	"hostvim/engine/internal/panelmirror"
	"hostvim/engine/internal/phpfpm"
	"hostvim/engine/internal/phpini"
	"hostvim/engine/internal/security"
	"hostvim/engine/internal/stack"
	"hostvim/engine/internal/tools"
	"github.com/sirupsen/logrus"
)

func registerModuleRoutes(cfg *config.Config, d *daemon.Daemon, api *gin.RouterGroup, log *logrus.Logger) {
	phpVerRe := regexp.MustCompile(`^[0-9]+\.[0-9]+$`)

	api.GET("/system/stats", func(c *gin.Context) {
		ext := monitoring.CollectExtended(cfg.Paths.WebRoot)
		c.JSON(http.StatusOK, gin.H{
			"data": gin.H{
				"cpu_usage":            ext.CPUUsagePercent,
				"memory_total":         ext.MemoryTotal,
				"memory_used":          ext.MemoryUsed,
				"memory_percent":       ext.MemoryPercent,
				"disk_total":           ext.DiskTotal,
				"disk_used":            ext.DiskUsed,
				"disk_percent":         ext.DiskPercent,
				"uptime":               ext.Uptime,
				"hostname":             ext.Hostname,
				"os":                   ext.OS,
				"cpu_model":            ext.CPUModel,
				"cpu_cores_logical":    ext.CPUCoresLogical,
				"memory_available":     ext.MemoryAvailable,
				"swap_total":           ext.SwapTotal,
				"swap_used":            ext.SwapUsed,
				"swap_percent":         ext.SwapPercent,
				"top_cpu_processes":    ext.TopCPUProcesses,
				"top_memory_processes": ext.TopMemoryProcesses,
				"top_disk_mounts":      ext.TopDiskMounts,
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
	api.POST("/files/copy", handleFileCopy(cfg))
	api.POST("/files/chmod", handleFileChmod(cfg))
	api.POST("/files/zip", handleFileZip(cfg))
	api.POST("/files/unzip", handleFileUnzip(cfg))
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
			Domain        string  `json:"domain" binding:"required"`
			Type          string  `json:"type"`
			PanelBackupID float64 `json:"panel_backup_id"`
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
		res, err := panelmirror.BackupRestore(cfg, c.Param("id"))
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, res)
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
	api.POST("/mail/:domain/forwarder", func(c *gin.Context) {
		var body map[string]interface{}
		if err := c.ShouldBindJSON(&body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := panelmirror.MailAddForwarder(cfg, c.Param("domain"), body); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusCreated, gin.H{"message": "forwarder stored"})
	})
	api.DELETE("/mail/:domain/forwarder", func(c *gin.Context) {
		source := strings.TrimSpace(c.Query("source"))
		destination := strings.TrimSpace(c.Query("destination"))
		if source == "" || destination == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "source and destination query required"})
			return
		}
		if err := panelmirror.MailDeleteForwarder(cfg, c.Param("domain"), source, destination); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "forwarder removed"})
	})
	api.DELETE("/mail/:domain", func(c *gin.Context) {
		if err := panelmirror.MailDeleteDomain(cfg, c.Param("domain")); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "mail state removed"})
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
		fail2banOn, fail2banErr := security.EnabledStatus("fail2ban")
		modsecOn, modsecErr := security.EnabledStatus("modsec")
		clamavOn, clamavErr := security.EnabledStatus("clamav")
		clamavLast, _ := panelmirror.SecurityGetValue(cfg, "clamav_last_scan")

		c.JSON(http.StatusOK, gin.H{
			"fail2ban": gin.H{
				"enabled": fail2banOn,
				"jails":   []string{"sshd", "hostvim-auth", "panelsar-auth"}, // panelsar-auth: eski fail2ban jail adı
				"settings": func() gin.H {
					b, f, m, e := security.Fail2banJailGet()
					return gin.H{
						"bantime":  b,
						"findtime": f,
						"maxretry": m,
						"error":    errMsg(e),
					}
				}(),
				"error": errMsg(fail2banErr),
			},
			"firewall": gin.H{"backend": "iptables", "default_policy": "DROP", "recent_rules": rules},
			"modsecurity": gin.H{
				"enabled": modsecOn,
				"error":   errMsg(modsecErr),
			},
			"clamav": gin.H{
				"enabled":   clamavOn,
				"last_scan": nullIfEmpty(clamavLast),
				"error":     errMsg(clamavErr),
			},
		})
	})
	api.POST("/security/fail2ban/toggle", func(c *gin.Context) {
		var req struct {
			Enabled bool `json:"enabled"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		enabled, err := security.SetEnabled("fail2ban", req.Enabled)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "fail2ban updated", "enabled": enabled})
	})
	api.POST("/security/fail2ban/install", func(c *gin.Context) {
		if err := security.InstallFail2ban(); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "fail2ban installed"})
	})
	api.POST("/security/fail2ban/jail", func(c *gin.Context) {
		var req struct {
			Bantime  int `json:"bantime" binding:"required"`
			Findtime int `json:"findtime" binding:"required"`
			Maxretry int `json:"maxretry" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := security.Fail2banJailSet(req.Bantime, req.Findtime, req.Maxretry); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "fail2ban jail updated"})
	})
	api.POST("/security/modsecurity/toggle", func(c *gin.Context) {
		var req struct {
			Enabled bool `json:"enabled"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		enabled, err := security.SetEnabled("modsec", req.Enabled)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "modsecurity updated", "enabled": enabled})
	})
	api.POST("/security/modsecurity/install", func(c *gin.Context) {
		if err := security.InstallModSecurity(); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "modsecurity installed"})
	})
	api.POST("/security/clamav/toggle", func(c *gin.Context) {
		var req struct {
			Enabled bool `json:"enabled"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		enabled, err := security.SetEnabled("clamav", req.Enabled)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "clamav updated", "enabled": enabled})
	})
	api.POST("/security/clamav/scan", func(c *gin.Context) {
		var req struct {
			Target string `json:"target"`
		}
		_ = c.ShouldBindJSON(&req)
		out, err := security.RunClamAVScan(req.Target)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error(), "output": out})
			return
		}
		ts := time.Now().UTC().Format(time.RFC3339)
		_ = panelmirror.SecuritySetValue(cfg, "clamav_last_scan", ts)
		c.JSON(http.StatusOK, gin.H{
			"message":   "clamav scan completed",
			"last_scan": ts,
			"output":    out,
		})
	})
	api.POST("/security/mail/reconcile", func(c *gin.Context) {
		var req struct {
			DryRun  *bool  `json:"dry_run"`
			Confirm string `json:"confirm"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		dry := true
		if req.DryRun != nil {
			dry = *req.DryRun
		}
		if !dry && strings.TrimSpace(req.Confirm) != "DELETE_ORPHAN_MAIL_STATE" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "confirmation phrase required"})
			return
		}
		report, err := panelmirror.MailReconcile(cfg, dry)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{
			"message": "mail reconcile completed",
			"report":  report,
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

	api.GET("/webserver/settings", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"settings": gin.H{
				"nginx_manage_vhosts":       cfg.Hosting.NginxManageVhosts,
				"nginx_reload_after_vhost":  cfg.Hosting.NginxReloadAfterVhost,
				"apache_manage_vhosts":      cfg.Hosting.ApacheManageVhosts,
				"apache_reload_after_vhost": cfg.Hosting.ApacheReloadAfterVhost,
				"php_fpm_manage_pools":      cfg.Hosting.PHPFPMmanagePools,
				"php_fpm_reload_after_pool": cfg.Hosting.PHPFPMreloadAfterPool,
				"php_fpm_socket":            cfg.Hosting.PHPFPMsocket,
				"php_fpm_listen_dir":        cfg.Hosting.PHPFPMlistenDir,
				"php_fpm_pool_dir_template": cfg.Hosting.PHPFPMpoolDirTemplate,
				"php_fpm_pool_user":         cfg.Hosting.PHPFPMpoolUser,
				"php_fpm_pool_group":        cfg.Hosting.PHPFPMpoolGroup,
			},
		})
	})

	api.GET("/webserver/services", func(c *gin.Context) {
		// Linux: systemctl tabanlı kontrol.
		// macOS/XAMPP: systemctl yok, o yüzden binary+process fallback.
		if _, err := exec.LookPath("systemctl"); err == nil {
			check := func(name string) gin.H {
				installed := false
				active := false
				unit := name + ".service"
				if out, _ := exec.Command("systemctl", "list-unit-files", "--type=service", unit).CombinedOutput(); strings.Contains(string(out), unit) {
					installed = true
				}
				if err := exec.Command("systemctl", "is-active", "--quiet", name).Run(); err == nil {
					active = true
				}
				return gin.H{"installed": installed, "active": active}
			}

			c.JSON(http.StatusOK, gin.H{
				"services": gin.H{
					"nginx":  check("nginx"),
					"apache": check("apache2"),
				},
			})
			return
		}

		pgrepHasProc := func(name string) bool {
			if _, err := exec.LookPath("pgrep"); err != nil {
				return false
			}
			return exec.Command("pgrep", "-x", name).Run() == nil
		}

		fileExists := func(path string) bool {
			if strings.TrimSpace(path) == "" {
				return false
			}
			_, err := os.Stat(path)
			return err == nil
		}

		// Nginx.
		nginxBin := strings.TrimSpace(os.Getenv("HOSTVIM_NGINX_BIN"))
		if nginxBin == "" {
			nginxBin = strings.TrimSpace(os.Getenv("PANELSAR_NGINX_BIN"))
		}
		if nginxBin == "" && fileExists("/Applications/XAMPP/xamppfiles/bin/nginx") {
			nginxBin = "/Applications/XAMPP/xamppfiles/bin/nginx"
		}
		nginxInstalled := fileExists(nginxBin) || (func() bool { _, err := exec.LookPath("nginx"); return err == nil })()
		nginxActive := pgrepHasProc("nginx")

		// Apache.
		httpdBin := strings.TrimSpace(os.Getenv("HOSTVIM_HTTPD_BIN"))
		if httpdBin == "" {
			httpdBin = strings.TrimSpace(os.Getenv("PANELSAR_HTTPD_BIN"))
		}
		if httpdBin == "" && fileExists("/Applications/XAMPP/xamppfiles/bin/httpd") {
			httpdBin = "/Applications/XAMPP/xamppfiles/bin/httpd"
		}
		apacheInstalled := fileExists(httpdBin) || (func() bool { _, err := exec.LookPath("httpd"); return err == nil })()
		apacheActive := pgrepHasProc("httpd") || pgrepHasProc("apache2")

		c.JSON(http.StatusOK, gin.H{
			"services": gin.H{
				"nginx":  gin.H{"installed": nginxInstalled, "active": nginxActive},
				"apache": gin.H{"installed": apacheInstalled, "active": apacheActive},
			},
		})
	})

	api.PATCH("/webserver/settings", func(c *gin.Context) {
		var req struct {
			NginxManageVhosts      *bool   `json:"nginx_manage_vhosts"`
			NginxReloadAfterVhost  *bool   `json:"nginx_reload_after_vhost"`
			ApacheManageVhosts     *bool   `json:"apache_manage_vhosts"`
			ApacheReloadAfterVhost *bool   `json:"apache_reload_after_vhost"`
			PhpFpmManagePools      *bool   `json:"php_fpm_manage_pools"`
			PhpFpmReloadAfterPool  *bool   `json:"php_fpm_reload_after_pool"`
			PhpFpmSocket           *string `json:"php_fpm_socket"`
			PhpFpmListenDir        *string `json:"php_fpm_listen_dir"`
			PhpFpmPoolDirTemplate  *string `json:"php_fpm_pool_dir_template"`
			PhpFpmPoolUser         *string `json:"php_fpm_pool_user"`
			PhpFpmPoolGroup        *string `json:"php_fpm_pool_group"`
			Reload                 *bool   `json:"reload"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		reload := true
		if req.Reload != nil {
			reload = *req.Reload
		}

		oldNginxManage := cfg.Hosting.NginxManageVhosts
		oldNginxReload := cfg.Hosting.NginxReloadAfterVhost
		oldApacheManage := cfg.Hosting.ApacheManageVhosts
		oldApacheReload := cfg.Hosting.ApacheReloadAfterVhost
		oldPhpManage := cfg.Hosting.PHPFPMmanagePools
		oldPhpReload := cfg.Hosting.PHPFPMreloadAfterPool

		if req.NginxManageVhosts != nil {
			cfg.Hosting.NginxManageVhosts = *req.NginxManageVhosts
		}
		if req.NginxReloadAfterVhost != nil {
			cfg.Hosting.NginxReloadAfterVhost = *req.NginxReloadAfterVhost
		}
		if req.ApacheManageVhosts != nil {
			cfg.Hosting.ApacheManageVhosts = *req.ApacheManageVhosts
		}
		if req.ApacheReloadAfterVhost != nil {
			cfg.Hosting.ApacheReloadAfterVhost = *req.ApacheReloadAfterVhost
		}
		if req.PhpFpmManagePools != nil {
			cfg.Hosting.PHPFPMmanagePools = *req.PhpFpmManagePools
		}
		if req.PhpFpmReloadAfterPool != nil {
			cfg.Hosting.PHPFPMreloadAfterPool = *req.PhpFpmReloadAfterPool
		}
		if req.PhpFpmSocket != nil {
			cfg.Hosting.PHPFPMsocket = strings.TrimSpace(*req.PhpFpmSocket)
		}
		if req.PhpFpmListenDir != nil {
			cfg.Hosting.PHPFPMlistenDir = strings.TrimSpace(*req.PhpFpmListenDir)
		}
		if req.PhpFpmPoolDirTemplate != nil {
			cfg.Hosting.PHPFPMpoolDirTemplate = strings.TrimSpace(*req.PhpFpmPoolDirTemplate)
		}
		if req.PhpFpmPoolUser != nil {
			cfg.Hosting.PHPFPMpoolUser = strings.TrimSpace(*req.PhpFpmPoolUser)
		}
		if req.PhpFpmPoolGroup != nil {
			cfg.Hosting.PHPFPMpoolGroup = strings.TrimSpace(*req.PhpFpmPoolGroup)
		}

		nginxChanged := oldNginxManage != cfg.Hosting.NginxManageVhosts || oldNginxReload != cfg.Hosting.NginxReloadAfterVhost
		apacheChanged := oldApacheManage != cfg.Hosting.ApacheManageVhosts || oldApacheReload != cfg.Hosting.ApacheReloadAfterVhost
		phpChanged := oldPhpManage != cfg.Hosting.PHPFPMmanagePools || oldPhpReload != cfg.Hosting.PHPFPMreloadAfterPool

		reloadResult := gin.H{}
		if reload && nginxChanged {
			out, err := exec.Command("nginx", "-t").CombinedOutput()
			ok := err == nil
			if ok {
				_ = exec.Command("systemctl", "reload", "nginx").Run()
			}
			reloadResult["nginx"] = gin.H{"ok": ok, "nginx_test": strings.TrimSpace(string(out))}
		}

		if reload && apacheChanged {
			ok := false
			if _, err := exec.LookPath("apache2ctl"); err == nil {
				ok = exec.Command("apache2ctl", "graceful").Run() == nil
			} else {
				ok = exec.Command("apachectl", "graceful").Run() == nil
			}
			reloadResult["apache"] = gin.H{"ok": ok}
		}

		if reload && phpChanged {
			// Bu panel şu an standart PHP sürümü için pool tasarlıyor (8.2). Gerekirse genişletilir.
			phpErr := phpfpm.Reload("8.2")
			reloadResult["php_fpm"] = gin.H{"ok": phpErr == nil}
		}

		c.JSON(http.StatusOK, gin.H{
			"message": "webserver settings updated",
			"settings": gin.H{
				"nginx_manage_vhosts":       cfg.Hosting.NginxManageVhosts,
				"nginx_reload_after_vhost":  cfg.Hosting.NginxReloadAfterVhost,
				"apache_manage_vhosts":      cfg.Hosting.ApacheManageVhosts,
				"apache_reload_after_vhost": cfg.Hosting.ApacheReloadAfterVhost,
				"php_fpm_manage_pools":      cfg.Hosting.PHPFPMmanagePools,
				"php_fpm_reload_after_pool": cfg.Hosting.PHPFPMreloadAfterPool,
				"php_fpm_socket":            cfg.Hosting.PHPFPMsocket,
				"php_fpm_listen_dir":        cfg.Hosting.PHPFPMlistenDir,
				"php_fpm_pool_dir_template": cfg.Hosting.PHPFPMpoolDirTemplate,
				"php_fpm_pool_user":         cfg.Hosting.PHPFPMpoolUser,
				"php_fpm_pool_group":        cfg.Hosting.PHPFPMpoolGroup,
			},
			"reload": reloadResult,
		})
	})

	api.GET("/webserver/apache/modules", func(c *gin.Context) {
		rows, err := security.ApacheModulesList()
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		sort.Slice(rows, func(i, j int) bool { return rows[i].Name < rows[j].Name })
		c.JSON(http.StatusOK, gin.H{"modules": rows})
	})

	api.POST("/webserver/apache/modules/:name/toggle", func(c *gin.Context) {
		var req struct {
			Enabled bool `json:"enabled"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		name := strings.TrimSpace(c.Param("name"))
		if name == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "module name required"})
			return
		}
		enabled, err := security.ApacheModuleSet(name, req.Enabled)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"module": name, "enabled": enabled})
	})

	api.GET("/webserver/nginx/config", func(c *gin.Context) {
		scope := strings.TrimSpace(c.Query("scope"))
		if scope == "" {
			scope = "main"
		}
		content, err := security.NginxConfigGet(scope)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"scope": scope, "content": content})
	})

	api.POST("/webserver/nginx/config", func(c *gin.Context) {
		var req struct {
			Scope      string `json:"scope"`
			Content    string `json:"content" binding:"required"`
			TestReload *bool  `json:"test_reload"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		scope := strings.TrimSpace(req.Scope)
		if scope == "" {
			scope = "main"
		}
		reload := true
		if req.TestReload != nil {
			reload = *req.TestReload
		}
		if err := security.NginxConfigSet(scope, req.Content, reload); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "nginx config updated", "scope": scope})
	})

	api.GET("/php/versions", func(c *gin.Context) {
		entries, err := os.ReadDir("/etc/php")
		if err == nil {
			var versions []string
			for _, e := range entries {
				if !e.IsDir() {
					continue
				}
				name := e.Name()
				if phpVerRe.MatchString(name) {
					versions = append(versions, name)
				}
			}
			sort.Strings(versions)
			c.JSON(http.StatusOK, gin.H{"versions": versions})
			return
		}

		// Fallback: /etc/php yok (macOS/XAMPP). php CLI'den ana+minör sürümü çek.
		phpBin := strings.TrimSpace(os.Getenv("HOSTVIM_PHP_BIN"))
		if phpBin == "" {
			phpBin = strings.TrimSpace(os.Getenv("PANELSAR_PHP_BIN"))
		}
		if phpBin == "" {
			if _, err := os.Stat("/Applications/XAMPP/xamppfiles/bin/php"); err == nil {
				phpBin = "/Applications/XAMPP/xamppfiles/bin/php"
			}
		}
		if phpBin == "" {
			phpBin = "php"
		}

		verOut, verErr := exec.Command(phpBin, "-r", `echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;`).CombinedOutput()
		if verErr != nil {
			c.JSON(http.StatusOK, gin.H{"versions": []string{}})
			return
		}

		ver := strings.TrimSpace(string(verOut))
		if !phpVerRe.MatchString(ver) {
			c.JSON(http.StatusOK, gin.H{"versions": []string{}})
			return
		}

		c.JSON(http.StatusOK, gin.H{"versions": []string{ver}})
	})

	phpIniPath := func(version string) (string, error) {
		// Linux standart yollar (fpm + cli).
		candidates := []string{
			filepath.Join("/etc/php", version, "fpm", "php.ini"),
			filepath.Join("/etc/php", version, "php.ini"),
			filepath.Join("/etc/php", version, "cli", "php.ini"),
		}
		for _, p := range candidates {
			if _, err := os.Stat(p); err == nil {
				return p, nil
			}
		}

		// macOS/XAMPP fallback: php --ini içinden Loaded Configuration File parse et.
		phpBin := strings.TrimSpace(os.Getenv("HOSTVIM_PHP_BIN"))
		if phpBin == "" {
			phpBin = strings.TrimSpace(os.Getenv("PANELSAR_PHP_BIN"))
		}
		if phpBin == "" {
			if _, err := os.Stat("/Applications/XAMPP/xamppfiles/bin/php"); err == nil {
				phpBin = "/Applications/XAMPP/xamppfiles/bin/php"
			}
		}
		if phpBin == "" {
			phpBin = "php"
		}

		out, err := exec.Command(phpBin, "--ini").CombinedOutput()
		if err != nil {
			return "", errors.New("php.ini yolu bulunamadı")
		}

		// Örn: "Loaded Configuration File: /Applications/XAMPP/xamppfiles/etc/php.ini"
		re := regexp.MustCompile(`Loaded Configuration File:\s*(.+)`)
		m := re.FindStringSubmatch(string(out))
		if len(m) < 2 {
			return "", errors.New("Loaded Configuration File bulunamadı")
		}

		p := strings.TrimSpace(m[1])
		if p == "" {
			return "", errors.New("php.ini boş")
		}
		return p, nil
	}

	moduleLineRe := regexp.MustCompile(`^(\s*)(;?)(\s*)(extension|zend_extension)\s*=\s*([^\s#;]+)`)

	api.GET("/php/:version/ini", func(c *gin.Context) {
		version := strings.TrimSpace(c.Param("version"))
		if !phpVerRe.MatchString(version) {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid php version"})
			return
		}

		path, err := phpIniPath(version)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": ""})
			return
		}

		b, err := phpini.Read(path)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": path})
			return
		}

		c.JSON(http.StatusOK, gin.H{
			"path": path,
			"ini":  string(b),
		})
	})

	api.PATCH("/php/:version/ini", func(c *gin.Context) {
		version := strings.TrimSpace(c.Param("version"))
		if !phpVerRe.MatchString(version) {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid php version"})
			return
		}

		var req struct {
			Ini    string `json:"ini" binding:"required"`
			Reload *bool  `json:"reload"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		reload := true
		if req.Reload != nil {
			reload = *req.Reload
		}

		path, err := phpIniPath(version)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": ""})
			return
		}
		if err := phpini.Write(path, []byte(req.Ini), 0o644); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error(), "path": path})
			return
		}

		if reload {
			_ = phpfpm.Reload(version)
		}

		c.JSON(http.StatusOK, gin.H{
			"message": "php.ini updated",
			"path":    path,
		})
	})

	api.GET("/php/:version/modules", func(c *gin.Context) {
		version := strings.TrimSpace(c.Param("version"))
		if !phpVerRe.MatchString(version) {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid php version"})
			return
		}

		path, err := phpIniPath(version)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": ""})
			return
		}

		b, err := phpini.Read(path)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": path})
			return
		}

		// module key = directive + ":" + moduleName
		type mod struct {
			Directive string `json:"directive"`
			Name      string `json:"name"`
			RawValue  string `json:"raw_value"`
			Enabled   bool   `json:"enabled"`
		}
		found := map[string]mod{}

		lines := strings.Split(string(b), "\n")
		for _, line := range lines {
			m := moduleLineRe.FindStringSubmatch(line)
			if m == nil {
				continue
			}
			indent := m[1]
			semi := m[2]
			_ = indent
			directive := m[4]
			rawVal := strings.Trim(m[5], "\"'")
			rawVal = strings.TrimSpace(rawVal)

			moduleName := rawVal
			moduleName = strings.TrimSuffix(moduleName, ".so")
			moduleName = filepath.Base(moduleName)

			key := directive + ":" + moduleName
			enabled := strings.TrimSpace(semi) == ""
			found[key] = mod{
				Directive: directive,
				Name:      moduleName,
				RawValue:  rawVal,
				Enabled:   enabled,
			}
		}

		var out []mod
		for _, v := range found {
			out = append(out, v)
		}
		// deterministic
		sort.Slice(out, func(i, j int) bool {
			if out[i].Directive == out[j].Directive {
				return out[i].Name < out[j].Name
			}
			return out[i].Directive < out[j].Directive
		})

		c.JSON(http.StatusOK, gin.H{
			"path":    path,
			"modules": out,
		})
	})

	api.PATCH("/php/:version/modules", func(c *gin.Context) {
		version := strings.TrimSpace(c.Param("version"))
		if !phpVerRe.MatchString(version) {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid php version"})
			return
		}

		var req struct {
			Reload  *bool `json:"reload"`
			Modules []struct {
				Directive string `json:"directive" binding:"required"`
				Name      string `json:"name" binding:"required"`
				Enabled   bool   `json:"enabled"`
			} `json:"modules" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}

		reload := true
		if req.Reload != nil {
			reload = *req.Reload
		}

		path, err := phpIniPath(version)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": ""})
			return
		}

		b, err := phpini.Read(path)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error(), "path": path})
			return
		}

		enabledMap := map[string]bool{}
		for _, m := range req.Modules {
			key := m.Directive + ":" + m.Name
			enabledMap[key] = m.Enabled
		}

		// rewrite php.ini: toggle leading comment char for matching module directives.
		lines := strings.Split(string(b), "\n")
		for i := range lines {
			line := lines[i]
			m := moduleLineRe.FindStringSubmatch(line)
			if m == nil {
				continue
			}
			indent := m[1]
			// m[2] is semicolon marker (optional)
			directive := m[4]
			rawVal := strings.Trim(m[5], "\"'")
			rawVal = strings.TrimSpace(rawVal)

			moduleName := rawVal
			moduleName = strings.TrimSuffix(moduleName, ".so")
			moduleName = filepath.Base(moduleName)
			key := directive + ":" + moduleName
			enabled, ok := enabledMap[key]
			if !ok {
				continue
			}

			if enabled {
				lines[i] = indent + directive + "=" + rawVal
			} else {
				lines[i] = indent + ";" + directive + "=" + rawVal
			}
		}

		newContent := strings.Join(lines, "\n")
		if err := phpini.Write(path, []byte(newContent), 0o644); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error(), "path": path})
			return
		}

		if reload {
			_ = phpfpm.Reload(version)
		}

		c.JSON(http.StatusOK, gin.H{
			"message": "php modules updated",
			"path":    path,
		})
	})

	api.GET("/system/processes", func(c *gin.Context) {
		procs, err := listProcesses()
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"processes": procs})
	})

	engineInternal := api.Group("")
	engineInternal.Use(middleware.RequireInternalRole())
	{
		engineInternal.POST("/system/processes/kill", func(c *gin.Context) {
			var req struct {
				PID int `json:"pid" binding:"required"`
			}
			if err := c.ShouldBindJSON(&req); err != nil {
				c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
				return
			}
			if req.PID <= 1 {
				c.JSON(http.StatusBadRequest, gin.H{"error": "invalid pid"})
				return
			}
			out, err := exec.Command("kill", "-TERM", strconv.Itoa(req.PID)).CombinedOutput()
			if err != nil {
				c.JSON(http.StatusBadRequest, gin.H{
					"error":  err.Error(),
					"output": strings.TrimSpace(string(out)),
				})
				return
			}
			c.JSON(http.StatusOK, gin.H{"message": "process kill signal sent", "pid": req.PID})
		})

		engineInternal.POST("/system/reboot", func(c *gin.Context) {
			// Basit ve açık: sistem bazlı reboot. Çoğu dağıtımda systemctl reboot veya shutdown -r now çalışır.
			go func() {
				if _, err := exec.LookPath("systemctl"); err == nil {
					_ = exec.Command("systemctl", "reboot").Run()
					return
				}
				_ = exec.Command("shutdown", "-r", "now").Run()
			}()
			c.JSON(http.StatusAccepted, gin.H{"message": "reboot requested"})
		})

		engineInternal.POST("/stack/install", func(c *gin.Context) {
			var req struct {
				BundleID string `json:"bundle_id" binding:"required"`
			}
			if err := c.ShouldBindJSON(&req); err != nil {
				c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
				return
			}
			out, err := stack.InstallBundle(cfg, req.BundleID)
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
	}

	api.GET("/stack/modules", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"modules": stack.ModulesWithStatus()})
	})

	_ = d
	_ = log
}

func errMsg(err error) string {
	if err == nil {
		return ""
	}
	return err.Error()
}

func nullIfEmpty(s string) interface{} {
	s = strings.TrimSpace(s)
	if s == "" {
		return nil
	}
	return s
}

func listProcesses() ([]gin.H, error) {
	out, err := exec.Command("ps", "-eo", "pid,ppid,pcpu,pmem,comm,args", "--no-headers").CombinedOutput()
	if err != nil {
		return nil, err
	}
	lines := strings.Split(strings.ReplaceAll(string(out), "\r\n", "\n"), "\n")
	procs := make([]gin.H, 0, len(lines))
	for _, ln := range lines {
		ln = strings.TrimSpace(ln)
		if ln == "" {
			continue
		}
		fields := strings.Fields(ln)
		if len(fields) < 6 {
			continue
		}
		pid, _ := strconv.Atoi(fields[0])
		ppid, _ := strconv.Atoi(fields[1])
		pcpu, _ := strconv.ParseFloat(fields[2], 64)
		pmem, _ := strconv.ParseFloat(fields[3], 64)
		command := fields[4]
		args := strings.Join(fields[5:], " ")
		procs = append(procs, gin.H{
			"pid":     pid,
			"ppid":    ppid,
			"cpu":     pcpu,
			"memory":  pmem,
			"command": command,
			"args":    args,
		})
		if len(procs) >= 250 {
			break
		}
	}
	sort.Slice(procs, func(i, j int) bool {
		ai := procs[i]["cpu"].(float64)
		aj := procs[j]["cpu"].(float64)
		if ai == aj {
			return procs[i]["pid"].(int) < procs[j]["pid"].(int)
		}
		return ai > aj
	})
	return procs, nil
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
		root, err := resolveFileManagerRoot(cfg, domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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

		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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

func handleFileCopy(cfg *config.Config) gin.HandlerFunc {
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
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := files.Copy(root, req.From, req.To); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "copied"})
	}
}

func handleFileChmod(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
			Path   string `json:"path" binding:"required"`
			Mode   string `json:"mode" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		m, err := strconv.ParseUint(strings.TrimSpace(req.Mode), 8, 32)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid mode"})
			return
		}
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := files.Chmod(root, req.Path, os.FileMode(m)); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "chmod applied"})
	}
}

func handleFileZip(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
			Source string `json:"source" binding:"required"`
			Target string `json:"target" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := files.ZipPath(root, req.Source, req.Target); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "zip created"})
	}
}

func handleFileUnzip(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain    string `json:"domain" binding:"required"`
			Archive   string `json:"archive" binding:"required"`
			TargetDir string `json:"target_dir" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		root, err := resolveFileManagerRoot(cfg, req.Domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := files.UnzipPath(root, req.Archive, req.TargetDir); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "unzip completed"})
	}
}

func handleFileDownload(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Query("domain")
		path := c.Query("path")
		if domain == "" || path == "" {
			c.JSON(http.StatusBadRequest, gin.H{"error": "domain and path required"})
			return
		}

		root, err := resolveFileManagerRoot(cfg, domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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
		root, err := resolveFileManagerRoot(cfg, domain)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
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

var fileDomainRe = regexp.MustCompile(`^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$`)

func resolveFileManagerRoot(cfg *config.Config, domain string) (string, error) {
	d := strings.ToLower(strings.TrimSpace(domain))
	if !fileDomainRe.MatchString(d) {
		return "", errors.New("invalid domain")
	}

	joined := filepath.Join(cfg.Paths.WebRoot, d)
	realRoot, err := filepath.EvalSymlinks(joined)
	if err != nil {
		return "", errors.New("domain root not found")
	}
	realWebRoot, err := filepath.EvalSymlinks(cfg.Paths.WebRoot)
	if err != nil {
		return "", err
	}
	sep := string(os.PathSeparator)
	if realRoot != realWebRoot && !strings.HasPrefix(realRoot, realWebRoot+sep) {
		return "", errors.New("domain root escapes web root")
	}

	return realRoot, nil
}
