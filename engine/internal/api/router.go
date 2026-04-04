package api

import (
	"io"
	"os"
	"net/http"
	"path/filepath"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	"hostvim/engine/internal/config"
	"hostvim/engine/internal/daemon"
	"hostvim/engine/internal/hosting"
	"hostvim/engine/internal/middleware"
	"hostvim/engine/internal/nginx"
	"hostvim/engine/internal/phpfpm"
	"hostvim/engine/internal/sites"
	"hostvim/engine/internal/ssl"
	"hostvim/engine/internal/terminal"
	"github.com/sirupsen/logrus"
)

func NewRouter(cfg *config.Config, d *daemon.Daemon, log *logrus.Logger) *gin.Engine {
	if !cfg.Server.Debug {
		gin.SetMode(gin.ReleaseMode)
	}

	r := gin.New()
	r.Use(gin.Recovery())
	r.Use(middleware.Logger(log))
	r.Use(middleware.CORS(cfg))

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"status":  "healthy",
			"engine":  "hostvim",
			"version": "0.1.0",
			"running": d.IsRunning(),
		})
	})

	r.GET("/ws/terminal", terminal.HandleWS(cfg, log))

	api := r.Group("/api/v1")
	api.Use(middleware.AuthRequired(cfg))
	{
		svc := api.Group("/services")
		{
			svc.GET("", handleListServices(d))
			svc.GET("/:name", handleGetService(d))
			svc.POST("/:name/start", handleStartService(d))
			svc.POST("/:name/stop", handleStopService(d))
			svc.POST("/:name/restart", handleRestartService(d))
		}

		site := api.Group("/sites")
		{
			site.POST("", handleCreateSite(cfg, d))
			site.POST("/:domain/suspend", handleSuspendSite(cfg, d))
			site.POST("/:domain/activate", handleActivateSite(cfg, d))
			site.DELETE("/:domain", handleDeleteSite(cfg, d))
			site.GET("", handleListSites(cfg, d))
			site.GET("/:domain/logs", handleSiteLogs(cfg, d))
			site.POST("/:domain/subdomains", handleAddSubdomain(cfg, d))
			site.DELETE("/:domain/subdomains", handleRemoveSubdomain(cfg, d))
			site.POST("/:domain/aliases", handleAddSiteAlias(cfg, d))
			site.DELETE("/:domain/aliases", handleRemoveSiteAlias(cfg, d))
		}

		ssl := api.Group("/ssl")
		{
			ssl.POST("/issue", handleIssueSSL(cfg))
			ssl.POST("/renew", handleRenewSSL(cfg))
			ssl.POST("/revoke", handleRevokeSSL(cfg))
			ssl.POST("/manual", handleManualSSL(cfg))
		}

		registerModuleRoutes(cfg, d, api, log)
	}

	return r
}

func handleListServices(d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		services := d.ServiceManager().GetAllServices()
		c.JSON(http.StatusOK, gin.H{"services": services})
	}
}

func handleGetService(d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		name := c.Param("name")
		svc, err := d.ServiceManager().GetService(name)
		if err != nil {
			c.JSON(http.StatusNotFound, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, svc)
	}
}

func handleStartService(d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		name := c.Param("name")
		if err := d.ServiceManager().StartService(name); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "Service started"})
	}
}

func handleStopService(d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		name := c.Param("name")
		if err := d.ServiceManager().StopService(name); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "Service stopped"})
	}
}

func handleRestartService(d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		name := c.Param("name")
		if err := d.ServiceManager().RestartService(name); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "Service restarted"})
	}
}

func handleCreateSite(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain     string `json:"domain" binding:"required"`
			UserID     uint   `json:"user_id" binding:"required"`
			PHP        string `json:"php_version"`
			ServerType string `json:"server_type"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		phpV := strings.TrimSpace(req.PHP)
		if phpV == "" {
			phpV = "8.2"
		}

		oldMeta, _ := sites.ReadSiteMeta(cfg.Paths.WebRoot, req.Domain)
		docRoot, err := sites.Provision(cfg.Paths.WebRoot, req.Domain, phpV, req.ServerType)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		ps := phpfpmSettings(cfg)
		phpSocket := nginx.EffectivePHPSocket(phpV, cfg.Hosting.PHPFPMsocket)
		var poolPrev []byte
		var poolHadPrev bool
		var oldPoolBak []byte
		var oldPoolBakHad bool
		if cfg.Hosting.PHPFPMmanagePools {
			if oldMeta != nil && phpfpm.NormalizeVersion(oldMeta.PHPVersion) != phpfpm.NormalizeVersion(phpV) {
				oldPoolBak, oldPoolBakHad = phpfpm.ReadPoolSnapshot(ps, req.Domain, oldMeta.PHPVersion)
			}
			sock, pprev, phad, perr := phpfpm.WritePool(ps, req.Domain, phpV, docRoot)
			poolPrev, poolHadPrev = pprev, phad
			if perr != nil {
				_ = sites.Remove(cfg.Paths.WebRoot, req.Domain)
				c.JSON(http.StatusInternalServerError, gin.H{"error": perr.Error()})
				return
			}
			phpSocket = sock
			if cfg.Hosting.PHPFPMreloadAfterPool {
				if rerr := phpfpm.Reload(phpV); rerr != nil {
					_ = phpfpm.RestorePoolConf(ps, req.Domain, phpV, poolPrev, poolHadPrev)
					_ = phpfpm.Reload(phpV)
					_ = sites.Remove(cfg.Paths.WebRoot, req.Domain)
					c.JSON(http.StatusInternalServerError, gin.H{"error": rerr.Error()})
					return
				}
			}
			if oldMeta != nil && phpfpm.NormalizeVersion(oldMeta.PHPVersion) != phpfpm.NormalizeVersion(phpV) {
				_ = phpfpm.RemovePool(ps, req.Domain, oldMeta.PHPVersion)
				if cfg.Hosting.PHPFPMreloadAfterPool {
					_ = phpfpm.Reload(oldMeta.PHPVersion)
				}
			}
		}

		metaNow, _ := sites.ReadSiteMeta(cfg.Paths.WebRoot, req.Domain)
		if err := hosting.ApplyWebServer(cfg, req.Domain, docRoot, metaNow, phpSocket); err != nil {
			if cfg.Hosting.PHPFPMmanagePools {
				_ = phpfpm.RestorePoolConf(ps, req.Domain, phpV, poolPrev, poolHadPrev)
				if cfg.Hosting.PHPFPMreloadAfterPool {
					_ = phpfpm.Reload(phpV)
				}
				if oldPoolBakHad && oldMeta != nil {
					_ = phpfpm.RestorePoolConf(ps, req.Domain, oldMeta.PHPVersion, oldPoolBak, true)
					if cfg.Hosting.PHPFPMreloadAfterPool {
						_ = phpfpm.Reload(oldMeta.PHPVersion)
					}
				}
			}
			_ = sites.Remove(cfg.Paths.WebRoot, req.Domain)
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		st := "nginx"
		if metaNow != nil && metaNow.ServerType != "" {
			st = metaNow.ServerType
		}

		c.JSON(http.StatusCreated, gin.H{
			"message":              "Site created",
			"domain":               req.Domain,
			"document_root":        docRoot,
			"web_root":             cfg.Paths.WebRoot,
			"server_type":          st,
			"nginx_vhost":          cfg.Hosting.NginxManageVhosts,
			"apache_vhost":         cfg.Hosting.ApacheManageVhosts,
			"php_fpm_manage_pools": cfg.Hosting.PHPFPMmanagePools,
			"php_fpm_socket":       phpSocket,
		})
	}
}

func phpfpmSettings(cfg *config.Config) phpfpm.HostingPoolSettings {
	return phpfpm.HostingPoolSettings{
		PoolDirTemplate:  cfg.Hosting.PHPFPMpoolDirTemplate,
		SocketListenDir:  cfg.Hosting.PHPFPMlistenDir,
		FPMUser:          cfg.Hosting.PHPFPMpoolUser,
		FPMGroup:         cfg.Hosting.PHPFPMpoolGroup,
	}
}

func handleSuspendSite(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Param("domain")
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, domain)
		if err != nil || meta == nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "site not found"})
			return
		}
		if err := hosting.RemoveWebServer(cfg, domain, meta); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "site suspended", "domain": domain})
	}
}

func handleActivateSite(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Param("domain")
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, domain)
		if err != nil || meta == nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "site not found"})
			return
		}
		ps := phpfpmSettings(cfg)
		phpSocket := ""
		if cfg.Hosting.PHPFPMmanagePools {
			phpSocket = ps.SocketForDomain(domain)
		} else {
			phpSocket = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
		}
		if err := hosting.ApplyWebServer(cfg, domain, meta.DocumentRoot, meta, phpSocket); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "site activated", "domain": domain})
	}
}

func handleDeleteSite(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := c.Param("domain")
		meta, _ := sites.ReadSiteMeta(cfg.Paths.WebRoot, domain)
		ps := phpfpmSettings(cfg)

		_ = ssl.Delete(cfg, domain)
		hosting.RemoveWebServerForSiteDeletion(cfg, domain)
		if cfg.Hosting.PHPFPMmanagePools {
			if meta != nil {
				_ = phpfpm.RemovePool(ps, domain, meta.PHPVersion)
				if cfg.Hosting.PHPFPMreloadAfterPool {
					_ = phpfpm.Reload(meta.PHPVersion)
				}
			} else {
				for _, ver := range phpfpm.RemovePoolBestEffortAllVersions(ps, domain) {
					if cfg.Hosting.PHPFPMreloadAfterPool {
						_ = phpfpm.Reload(ver)
					}
				}
			}
		}
		if err := sites.Remove(cfg.Paths.WebRoot, domain); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "Site deleted", "domain": domain})
	}
}

func handleListSites(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		names, err := sites.ListDomains(cfg.Paths.WebRoot)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		list := make([]gin.H, 0, len(names))
		for _, n := range names {
			list = append(list, gin.H{"domain": n})
		}
		c.JSON(http.StatusOK, gin.H{"sites": list})
	}
}

func handleSiteLogs(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		domain := strings.ToLower(strings.TrimSpace(c.Param("domain")))
		if domain == "" || strings.Contains(domain, "..") || !nginx.DomainSafe(domain) {
			c.JSON(http.StatusBadRequest, gin.H{"error": "invalid domain"})
			return
		}
		limit := 200
		if q := strings.TrimSpace(c.Query("lines")); q != "" {
			if qn, err := strconv.Atoi(q); err == nil {
				if qn < 20 {
					qn = 20
				}
				if qn > 1000 {
					qn = 1000
				}
				limit = qn
			}
		}

		nginxAccess := filepath.Join(cfg.Paths.LogDir, domain+"_access.log")
		nginxError := filepath.Join(cfg.Paths.LogDir, domain+"_error.log")
		apacheAccess := filepath.Join("/var/log/apache2", "panelsar-"+domain+"-access.log")
		apacheError := filepath.Join("/var/log/apache2", "panelsar-"+domain+"-error.log")

		entries := []gin.H{}
		for _, item := range []struct {
			name string
			path string
		}{
			{name: "nginx_access", path: nginxAccess},
			{name: "nginx_error", path: nginxError},
			{name: "apache_access", path: apacheAccess},
			{name: "apache_error", path: apacheError},
		} {
			content, exists, err := tailFile(item.path, limit, 256*1024)
			entries = append(entries, gin.H{
				"type":    item.name,
				"path":    item.path,
				"exists":  exists,
				"content": content,
				"error":   err,
			})
		}

		c.JSON(http.StatusOK, gin.H{
			"domain": domain,
			"logs":   entries,
		})
	}
}

func tailFile(path string, lines int, maxBytes int64) (string, bool, string) {
	st, err := os.Stat(path)
	if err != nil {
		if os.IsNotExist(err) {
			return "", false, ""
		}
		return "", false, err.Error()
	}
	if st.IsDir() {
		return "", false, "is a directory"
	}
	f, err := os.Open(path)
	if err != nil {
		return "", true, err.Error()
	}
	defer f.Close()

	size := st.Size()
	start := int64(0)
	if size > maxBytes {
		start = size - maxBytes
	}
	if _, err := f.Seek(start, 0); err != nil {
		return "", true, err.Error()
	}
	b, err := io.ReadAll(f)
	if err != nil {
		return "", true, err.Error()
	}
	s := string(b)
	if start > 0 {
		if i := strings.IndexByte(s, '\n'); i >= 0 && i+1 < len(s) {
			s = s[i+1:]
		}
	}
	all := strings.Split(strings.ReplaceAll(s, "\r\n", "\n"), "\n")
	for len(all) > 0 && strings.TrimSpace(all[len(all)-1]) == "" {
		all = all[:len(all)-1]
	}
	if len(all) > lines {
		all = all[len(all)-lines:]
	}
	return strings.Join(all, "\n"), true, ""
}

func handleIssueSSL(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
			Email  string `json:"email"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, req.Domain)
		if err != nil || meta == nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "site not found"})
			return
		}
		if err := ssl.Issue(cfg, req.Domain, meta.DocumentRoot, req.Email); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		meta.SSLEnabled = true
		phpSock := ""
		if cfg.Hosting.PHPFPMmanagePools {
			phpSock = phpfpmSettings(cfg).SocketForDomain(req.Domain)
		} else {
			phpSock = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
		}
		if err := hosting.ApplyWebServer(cfg, req.Domain, meta.DocumentRoot, meta, phpSock); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		if err := sites.WriteSiteMeta(cfg.Paths.WebRoot, req.Domain, meta); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "SSL certificate issued", "domain": req.Domain, "ssl_enabled": true})
	}
}

func handleRenewSSL(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := ssl.Renew(cfg, req.Domain); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, req.Domain)
		if err != nil || meta == nil {
			c.JSON(http.StatusOK, gin.H{"message": "SSL certificate renewed", "domain": req.Domain})
			return
		}
		phpSock := ""
		if cfg.Hosting.PHPFPMmanagePools {
			phpSock = phpfpmSettings(cfg).SocketForDomain(req.Domain)
		} else {
			phpSock = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
		}
		if err := hosting.ApplyWebServer(cfg, req.Domain, meta.DocumentRoot, meta, phpSock); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "SSL certificate renewed", "domain": req.Domain})
	}
}

func handleRevokeSSL(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain string `json:"domain" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		_ = ssl.Delete(cfg, req.Domain)
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, req.Domain)
		if err == nil && meta != nil {
			meta.SSLEnabled = false
			_ = sites.WriteSiteMeta(cfg.Paths.WebRoot, req.Domain, meta)
		}
		if meta != nil {
			phpSock := ""
			if cfg.Hosting.PHPFPMmanagePools {
				phpSock = phpfpmSettings(cfg).SocketForDomain(req.Domain)
			} else {
				phpSock = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
			}
			if err := hosting.ApplyWebServer(cfg, req.Domain, meta.DocumentRoot, meta, phpSock); err != nil {
				c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
				return
			}
		}
		c.JSON(http.StatusOK, gin.H{"message": "SSL removed", "domain": req.Domain})
	}
}

func handleManualSSL(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		var req struct {
			Domain     string `json:"domain" binding:"required"`
			CertPEM    string `json:"certificate" binding:"required"`
			PrivateKey string `json:"private_key" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, req.Domain)
		if err != nil || meta == nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "site not found"})
			return
		}
		if err := ssl.UploadManual(cfg, req.Domain, req.CertPEM, req.PrivateKey); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		meta.SSLEnabled = true
		phpSock := ""
		if cfg.Hosting.PHPFPMmanagePools {
			phpSock = phpfpmSettings(cfg).SocketForDomain(req.Domain)
		} else {
			phpSock = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
		}
		if err := hosting.ApplyWebServer(cfg, req.Domain, meta.DocumentRoot, meta, phpSock); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		if err := sites.WriteSiteMeta(cfg.Paths.WebRoot, req.Domain, meta); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "manual ssl uploaded", "domain": req.Domain, "ssl_enabled": true})
	}
}

func handleAddSubdomain(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		parent := strings.TrimSpace(c.Param("domain"))
		var req struct {
			Hostname    string `json:"hostname" binding:"required"`
			PathSegment string `json:"path_segment" binding:"required"`
			PHPVersion  string `json:"php_version"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		parentMeta, _ := sites.ReadSiteMeta(cfg.Paths.WebRoot, parent)
		phpV := strings.TrimSpace(req.PHPVersion)
		if phpV == "" && parentMeta != nil {
			phpV = parentMeta.PHPVersion
		}
		if phpV == "" {
			phpV = "8.2"
		}
		st := "nginx"
		if parentMeta != nil && parentMeta.ServerType != "" {
			st = strings.ToLower(parentMeta.ServerType)
		}
		docRoot, err := sites.ProvisionSubdomain(cfg.Paths.WebRoot, parent, req.Hostname, req.PathSegment, phpV, st)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		subMeta := &sites.SiteMeta{
			PHPVersion:   phpV,
			DocumentRoot: docRoot,
			ServerType:   st,
		}
		if err := hosting.ApplySubdomainVhost(cfg, parent, req.Hostname, docRoot, subMeta); err != nil {
			_ = hosting.RemoveSubdomainVhost(cfg, req.Hostname, subMeta)
			_, _ = sites.RemoveSubdomain(cfg.Paths.WebRoot, parent, req.PathSegment)
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusCreated, gin.H{
			"message":        "subdomain created",
			"document_root":  docRoot,
			"hostname":       req.Hostname,
			"path_segment":   req.PathSegment,
		})
	}
}

func handleRemoveSubdomain(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		parent := strings.TrimSpace(c.Param("domain"))
		var req struct {
			PathSegment string `json:"path_segment" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		subMeta, _ := sites.ReadSubdomainMeta(cfg.Paths.WebRoot, parent, req.PathSegment)
		if subMeta != nil && strings.TrimSpace(subMeta.Hostname) != "" {
			h := strings.TrimSpace(subMeta.Hostname)
			_ = ssl.Delete(cfg, h)
			_ = hosting.RemoveSubdomainVhost(cfg, h, subMeta)
		}
		_, err := sites.RemoveSubdomain(cfg.Paths.WebRoot, parent, req.PathSegment)
		if err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "subdomain removed"})
	}
}

func handleAddSiteAlias(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		parent := strings.TrimSpace(c.Param("domain"))
		var req struct {
			Hostname string `json:"hostname" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := sites.AppendAlias(cfg.Paths.WebRoot, parent, req.Hostname); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, parent)
		if err != nil || meta == nil {
			c.JSON(http.StatusNotFound, gin.H{"error": "site not found"})
			return
		}
		phpSock := ""
		if cfg.Hosting.PHPFPMmanagePools {
			phpSock = phpfpmSettings(cfg).SocketForDomain(parent)
		} else {
			phpSock = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
		}
		if err := hosting.ApplyWebServer(cfg, parent, meta.DocumentRoot, meta, phpSock); err != nil {
			_ = sites.RemoveAlias(cfg.Paths.WebRoot, parent, req.Hostname)
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "alias added", "hostname": req.Hostname})
	}
}

func handleRemoveSiteAlias(cfg *config.Config, d *daemon.Daemon) gin.HandlerFunc {
	return func(c *gin.Context) {
		parent := strings.TrimSpace(c.Param("domain"))
		var req struct {
			Hostname string `json:"hostname" binding:"required"`
		}
		if err := c.ShouldBindJSON(&req); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		if err := sites.RemoveAlias(cfg.Paths.WebRoot, parent, req.Hostname); err != nil {
			c.JSON(http.StatusBadRequest, gin.H{"error": err.Error()})
			return
		}
		meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, parent)
		if err != nil || meta == nil {
			c.JSON(http.StatusOK, gin.H{"message": "alias removed"})
			return
		}
		phpSock := ""
		if cfg.Hosting.PHPFPMmanagePools {
			phpSock = phpfpmSettings(cfg).SocketForDomain(parent)
		} else {
			phpSock = nginx.EffectivePHPSocket(meta.PHPVersion, cfg.Hosting.PHPFPMsocket)
		}
		if err := hosting.ApplyWebServer(cfg, parent, meta.DocumentRoot, meta, phpSock); err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		c.JSON(http.StatusOK, gin.H{"message": "alias removed"})
	}
}
