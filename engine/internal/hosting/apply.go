package hosting

import (
	"strings"

	"github.com/panelsar/engine/internal/apache"
	"github.com/panelsar/engine/internal/config"
	"github.com/panelsar/engine/internal/nginx"
	"github.com/panelsar/engine/internal/phpfpm"
	"github.com/panelsar/engine/internal/sites"
	"github.com/panelsar/engine/internal/ssl"
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
	return nginx.PHPSocketPath(v, cfg.Hosting.PHPFPMsocket)
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

// ApplyWebServer site meta’sına göre nginx veya apache sanal host yazar; SSL PEM varsa HTTPS + 301.
func ApplyWebServer(cfg *config.Config, domain, docRoot string, meta *sites.SiteMeta, phpSocket string) error {
	st := serverTypeOf(meta)
	sock := resolvePHPSocket(cfg, domain, meta, phpSocket)
	chain, key := sslPathsIfEnabled(cfg, domain, meta)

	switch st {
	case "apache":
		return apache.ApplyVhost(cfg, domain, docRoot, sock, chain, key)
	default:
		return nginx.ApplyVhost(cfg, domain, docRoot, sock, chain, key)
	}
}

// RemoveWebServer meta’daki server_type’a göre ilgili vhost’u kaldırır.
func RemoveWebServer(cfg *config.Config, domain string, meta *sites.SiteMeta) error {
	switch serverTypeOf(meta) {
	case "apache":
		return apache.RemoveVhost(cfg, domain)
	default:
		return nginx.RemoveVhost(cfg, domain)
	}
}
