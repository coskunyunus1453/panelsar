package hosting

import (
	"os"
	"path/filepath"
	"strings"

	"hostvim/engine/internal/apache"
	"hostvim/engine/internal/config"
	"hostvim/engine/internal/nginx"
	"hostvim/engine/internal/openlitespeed"
	"hostvim/engine/internal/phpfpm"
	"hostvim/engine/internal/sites"
	"hostvim/engine/internal/ssl"
)

func poolSettings(cfg *config.Config) phpfpm.HostingPoolSettings {
	return phpfpm.HostingPoolSettings{
		PoolDirTemplate: cfg.Hosting.PHPFPMpoolDirTemplate,
		SocketListenDir: cfg.Hosting.PHPFPMlistenDir,
		FPMUser:         cfg.Hosting.PHPFPMpoolUser,
		FPMGroup:        cfg.Hosting.PHPFPMpoolGroup,
	}
}

func serverTypeOf(meta *sites.SiteMeta) string {
	if meta == nil {
		return "nginx"
	}
	s := strings.ToLower(strings.TrimSpace(meta.ServerType))
	if s == "apache" {
		return "apache"
	}
	return "nginx"
}

func resolvePHPSocket(cfg *config.Config, domain string, meta *sites.SiteMeta, explicit string) string {
	if strings.TrimSpace(explicit) != "" {
		return strings.TrimSpace(explicit)
	}
	v := "8.2"
	if meta != nil && strings.TrimSpace(meta.PHPVersion) != "" {
		v = meta.PHPVersion
	}
	if cfg.Hosting.PHPFPMmanagePools {
		return poolSettings(cfg).SocketForDomain(domain)
	}
	return nginx.EffectivePHPSocket(v, cfg.Hosting.PHPFPMsocket)
}

func sslPathsIfEnabled(cfg *config.Config, domain string, meta *sites.SiteMeta) (chain, key string) {
	if meta == nil || !meta.SSLEnabled {
		return "", ""
	}
	chain, key = ssl.LiveCertPaths(cfg, domain)
	if !ssl.CertsExist(chain, key) {
		return "", ""
	}
	return chain, key
}

// ApplyWebServer site meta’sına göre nginx, apache veya openlitespeed sanal host yazar; SSL PEM varsa HTTPS + 301 (nginx/ols).
func ApplyWebServer(cfg *config.Config, domain, docRoot string, meta *sites.SiteMeta, phpSocket string) error {
	st := serverTypeOf(meta)
	if st != "nginx" {
		nginx.RemoveVhostBestEffort(cfg, domain)
	}
	if st != "apache" {
		apache.RemoveVhostBestEffort(cfg, domain)
	}
	if st != "openlitespeed" {
		openlitespeed.RemoveVhostBestEffort(cfg, domain)
	}

	sock := resolvePHPSocket(cfg, domain, meta, phpSocket)
	chain, key := sslPathsIfEnabled(cfg, domain, meta)

	switch st {
	case "apache":
		var aliases []string
		if meta != nil {
			aliases = append([]string(nil), meta.Aliases...)
		}
		return apache.ApplyVhost(cfg, domain, docRoot, sock, chain, key, aliases)
	default:
		var extras []string
		if meta != nil {
			extras = append([]string(nil), meta.Aliases...)
		}
		sn := nginx.BuildServerNamesLine(domain, extras)
		perf := ""
		if meta != nil {
			perf = meta.PerformanceMode
		}
		return nginx.ApplyVhost(cfg, domain, docRoot, sock, chain, key, sn, perf)
	case "openlitespeed":
		var aliases []string
		if meta != nil {
			aliases = append([]string(nil), meta.Aliases...)
		}
		return openlitespeed.ApplyVhost(cfg, domain, docRoot, sock, chain, key, aliases)
	}
}

// ApplySubdomainVhost ana site FPM havuzu ile alt FQDN için sanal host (HTTP; SSL ayrı sertifika ile sonradan).
func ApplySubdomainVhost(cfg *config.Config, parentPrimary, hostname, docRoot string, subMeta *sites.SiteMeta) error {
	parentMeta, _ := sites.ReadSiteMeta(cfg.Paths.WebRoot, parentPrimary)
	sock := resolvePHPSocket(cfg, parentPrimary, parentMeta, "")
	st := serverTypeOf(subMeta)
	if st == "apache" {
		return apache.ApplyVhost(cfg, hostname, docRoot, sock, "", "", nil)
	}
	if st == "openlitespeed" {
		h := strings.ToLower(strings.TrimSpace(hostname))
		return openlitespeed.ApplyVhost(cfg, h, docRoot, sock, "", "", nil)
	}
	h := strings.ToLower(strings.TrimSpace(hostname))
	perf := ""
	if subMeta != nil {
		perf = subMeta.PerformanceMode
	}
	return nginx.ApplyVhost(cfg, h, docRoot, sock, "", "", h, perf)
}

// RemoveWebServer meta’daki server_type’a göre ilgili vhost’u kaldırır.
func RemoveWebServer(cfg *config.Config, domain string, meta *sites.SiteMeta) error {
	switch serverTypeOf(meta) {
	case "apache":
		return apache.RemoveVhost(cfg, domain)
	case "openlitespeed":
		return openlitespeed.RemoveVhost(cfg, domain)
	default:
		return nginx.RemoveVhost(cfg, domain)
	}
}

func removePanelSiteLogs(cfg *config.Config, domain string) {
	d := strings.ToLower(strings.TrimSpace(domain))
	if d == "" || strings.Contains(d, "..") {
		return
	}
	if err := os.MkdirAll(cfg.Paths.LogDir, 0o755); err != nil {
		return
	}
	base := filepath.Join(cfg.Paths.LogDir, d)
	_ = os.Remove(base + "_access.log")
	_ = os.Remove(base + "_error.log")
}

// RemoveWebServerForSiteDeletion ana siteyi sunucudan kaldırırken nginx, apache ve openlitespeed kalıntılarını (yönetim bayrakları kapalı olsa bile) ve nginx log dosyalarını temizler.
func RemoveWebServerForSiteDeletion(cfg *config.Config, domain string) {
	if domain == "" || strings.Contains(domain, "..") {
		return
	}
	nginx.RemoveVhostBestEffort(cfg, domain)
	apache.RemoveVhostBestEffort(cfg, domain)
	openlitespeed.RemoveVhostBestEffort(cfg, domain)
	removePanelSiteLogs(cfg, domain)
}

// RemoveSubdomainVhost alt FQDN için yazılmış sanal hostu kaldırır.
func RemoveSubdomainVhost(cfg *config.Config, hostname string, meta *sites.SiteMeta) error {
	h := strings.ToLower(strings.TrimSpace(hostname))
	if h == "" {
		return nil
	}
	switch serverTypeOf(meta) {
	case "apache":
		apache.RemoveVhostBestEffort(cfg, h)
	case "openlitespeed":
		openlitespeed.RemoveVhostBestEffort(cfg, h)
	default:
		nginx.RemoveVhostBestEffort(cfg, h)
	}
	removePanelSiteLogs(cfg, h)
	return nil
}
