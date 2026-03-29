package apache

import (
	"bytes"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"text/template"

	"github.com/panelsar/engine/internal/config"
	"github.com/panelsar/engine/internal/nginx"
)

const tplHTTP = `# Panelsar — {{.Domain}} (Apache HTTP)
<VirtualHost *:{{.HTTPPort}}>
    ServerName {{.Domain}}
    ServerAlias www.{{.Domain}}
    DocumentRoot {{.DocRoot}}

    <Directory {{.DocRoot}}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "\.php$">
        SetHandler "proxy:unix:{{.PHPSocket}}|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/panelsar-{{.Domain}}-error.log
    CustomLog ${APACHE_LOG_DIR}/panelsar-{{.Domain}}-access.log combined
</VirtualHost>
`

const tplHTTPS = `# Panelsar — {{.Domain}} (Apache HTTPS)
<VirtualHost *:{{.HTTPPort}}>
    ServerName {{.Domain}}
    ServerAlias www.{{.Domain}}
    Redirect permanent / https://%{HTTP_HOST}%{REQUEST_URI}
</VirtualHost>

<VirtualHost *:443>
    ServerName {{.Domain}}
    ServerAlias www.{{.Domain}}
    DocumentRoot {{.DocRoot}}

    SSLEngine on
    SSLCertificateFile {{.SSLFullChain}}
    SSLCertificateKeyFile {{.SSLPrivKey}}

    Header always set Strict-Transport-Security "max-age=31536000"

    <Directory {{.DocRoot}}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "\.php$">
        SetHandler "proxy:unix:{{.PHPSocket}}|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/panelsar-{{.Domain}}-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/panelsar-{{.Domain}}-ssl-access.log combined
</VirtualHost>
`

type vhostVars struct {
	HTTPPort     int
	Domain       string
	DocRoot      string
	PHPSocket    string
	SSLFullChain string
	SSLPrivKey   string
}

func confBaseName(domain string) string {
	return "panelsar-" + strings.ToLower(domain) + ".conf"
}

func sitesAvailable(cfg *config.Config) string {
	s := strings.TrimSpace(cfg.Hosting.ApacheSitesAvailable)
	if s == "" {
		return "/etc/apache2/sites-available"
	}
	return s
}

func sitesEnabled(cfg *config.Config) string {
	s := strings.TrimSpace(cfg.Hosting.ApacheSitesEnabled)
	if s == "" {
		return "/etc/apache2/sites-enabled"
	}
	return s
}

// ApplyVhost Debian/Ubuntu sites-available + sites-enabled sembolik bağ.
func ApplyVhost(cfg *config.Config, domain, docRoot, phpSocket, sslFullchain, sslPrivkey string) error {
	if !cfg.Hosting.ApacheManageVhosts {
		return nil
	}
	if !nginx.DomainSafe(domain) {
		return fmt.Errorf("invalid domain for apache vhost")
	}
	if strings.Contains(docRoot, "..") {
		return fmt.Errorf("invalid document root")
	}
	docRoot = filepath.Clean(docRoot)

	sock := strings.TrimSpace(phpSocket)
	if sock == "" {
		sock = "/run/php/php8.2-fpm.sock"
	}

	availDir := sitesAvailable(cfg)
	enDir := sitesEnabled(cfg)
	if err := os.MkdirAll(availDir, 0o755); err != nil {
		return fmt.Errorf("apache sites-available: %w", err)
	}
	if err := os.MkdirAll(enDir, 0o755); err != nil {
		return fmt.Errorf("apache sites-enabled: %w", err)
	}

	chain := strings.TrimSpace(sslFullchain)
	key := strings.TrimSpace(sslPrivkey)
	useSSL := chain != "" && key != ""

	tplStr := tplHTTP
	if useSSL {
		tplStr = tplHTTPS
	}
	tpl, err := template.New("apache").Parse(tplStr)
	if err != nil {
		return err
	}
	httpPort := cfg.Hosting.ApacheHTTPPort
	if httpPort <= 0 {
		httpPort = 80
	}
	vars := vhostVars{
		HTTPPort:     httpPort,
		Domain:       domain,
		DocRoot:      docRoot,
		PHPSocket:    sock,
		SSLFullChain: chain,
		SSLPrivKey:   key,
	}
	var buf bytes.Buffer
	if err := tpl.Execute(&buf, vars); err != nil {
		return err
	}

	base := confBaseName(domain)
	avail := filepath.Join(availDir, base)
	enabled := filepath.Join(enDir, base)

	if err := os.WriteFile(avail, buf.Bytes(), 0o644); err != nil {
		return fmt.Errorf("write apache vhost: %w", err)
	}
	_ = os.Remove(enabled)
	if err := os.Symlink(avail, enabled); err != nil {
		return fmt.Errorf("apache symlink: %w", err)
	}

	if cfg.Hosting.ApacheReloadAfterVhost {
		reloadApache()
	}
	return nil
}

// RemoveVhost conf ve etkin bağlantıyı kaldırır.
func RemoveVhost(cfg *config.Config, domain string) error {
	if !cfg.Hosting.ApacheManageVhosts {
		return nil
	}
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	base := confBaseName(domain)
	_ = os.Remove(filepath.Join(sitesEnabled(cfg), base))
	_ = os.Remove(filepath.Join(sitesAvailable(cfg), base))
	if cfg.Hosting.ApacheReloadAfterVhost {
		reloadApache()
	}
	return nil
}

func reloadApache() {
	if _, err := exec.LookPath("apache2ctl"); err == nil {
		_ = exec.Command("apache2ctl", "graceful").Run()
		return
	}
	_ = exec.Command("apachectl", "graceful").Run()
}
