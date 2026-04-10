package apache

import (
	"bytes"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"text/template"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/nginx"
)

const maxRawApacheVhostBytes = 512 << 10

func apachePrevPath(main string) string {
	return main + ".hostvim-prev"
}

const tplHTTP = `# Hostvim — {{.Domain}} (Apache HTTP)
<VirtualHost *:{{.HTTPPort}}>
    ServerName {{.Domain}}
    ServerAlias {{.ServerAliasLine}}
    DocumentRoot {{.DocRoot}}
    RewriteEngine On
    RewriteRule ^/admin/assets/(.*)$ /assets/$1 [L]

    <Directory {{.DocRoot}}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Dotfile/Dotdir erişimi kapat (örn. .env, .git, .hostvim). ACME challenge hariç.
    <LocationMatch "(^|/)\.(?!well-known(?:/|$))">
        Require all denied
    </LocationMatch>

    SetEnvIfNoCase Authorization "(.+)" HTTP_AUTHORIZATION=$1

    <FilesMatch "\.php$">
        SetHandler "proxy:unix:{{.PHPSocket}}|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/hostvim-{{.Domain}}-error.log
    CustomLog ${APACHE_LOG_DIR}/hostvim-{{.Domain}}-access.log combined
</VirtualHost>
`

const tplHTTPS = `# Hostvim — {{.Domain}} (Apache HTTPS)
<VirtualHost *:{{.HTTPPort}}>
    ServerName {{.Domain}}
    ServerAlias {{.ServerAliasLine}}
    Redirect permanent / https://%{HTTP_HOST}%{REQUEST_URI}
</VirtualHost>

<VirtualHost *:443>
    ServerName {{.Domain}}
    ServerAlias {{.ServerAliasLine}}
    DocumentRoot {{.DocRoot}}
    RewriteEngine On
    RewriteRule ^/admin/assets/(.*)$ /assets/$1 [L]

    SSLEngine on
    SSLCertificateFile {{.SSLFullChain}}
    SSLCertificateKeyFile {{.SSLPrivKey}}

    Header always set Strict-Transport-Security "max-age=31536000"

    <Directory {{.DocRoot}}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Dotfile/Dotdir erişimi kapat (örn. .env, .git, .hostvim). ACME challenge hariç.
    <LocationMatch "(^|/)\.(?!well-known(?:/|$))">
        Require all denied
    </LocationMatch>

    SetEnvIfNoCase Authorization "(.+)" HTTP_AUTHORIZATION=$1

    <FilesMatch "\.php$">
        SetHandler "proxy:unix:{{.PHPSocket}}|fcgi://localhost"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/hostvim-{{.Domain}}-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/hostvim-{{.Domain}}-ssl-access.log combined
</VirtualHost>
`

type vhostVars struct {
	HTTPPort       int
	Domain         string
	ServerAliasLine string
	DocRoot        string
	PHPSocket      string
	SSLFullChain   string
	SSLPrivKey     string
}

func buildApacheServerAliasLine(primary string, aliases []string) string {
	primary = strings.ToLower(strings.TrimSpace(primary))
	if primary == "" {
		return ""
	}
	seen := map[string]struct{}{}
	var parts []string
	add := func(s string) {
		s = strings.ToLower(strings.TrimSpace(s))
		if s == "" {
			return
		}
		if _, ok := seen[s]; ok {
			return
		}
		seen[s] = struct{}{}
		parts = append(parts, s)
	}
	add("www." + primary)
	for _, a := range aliases {
		al := strings.ToLower(strings.TrimSpace(a))
		if al == "" || al == primary {
			continue
		}
		if !nginx.DomainSafe(al) {
			continue
		}
		add(al)
		if !strings.HasPrefix(al, "www.") {
			add("www." + al)
		}
	}
	return strings.Join(parts, " ")
}

func confBaseName(domain string) string {
	return "hostvim-" + strings.ToLower(domain) + ".conf"
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
func ApplyVhost(cfg *config.Config, domain, docRoot, phpSocket, sslFullchain, sslPrivkey string, aliases []string) error {
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
	sal := buildApacheServerAliasLine(domain, aliases)
	if sal == "" {
		return fmt.Errorf("invalid server aliases")
	}
	vars := vhostVars{
		HTTPPort:        httpPort,
		Domain:          domain,
		ServerAliasLine: sal,
		DocRoot:         docRoot,
		PHPSocket:       sock,
		SSLFullChain:    chain,
		SSLPrivKey:      key,
	}
	var buf bytes.Buffer
	if err := tpl.Execute(&buf, vars); err != nil {
		return err
	}

	base := confBaseName(domain)
	avail := filepath.Join(availDir, base)
	enabled := filepath.Join(enDir, base)

	oldAvail, readAvailErr := os.ReadFile(avail)
	hadAvail := readAvailErr == nil

	var oldLinkTarget string
	hadOldLink := false
	if fi, err := os.Lstat(enabled); err == nil && fi.Mode()&os.ModeSymlink != 0 {
		if tgt, err := os.Readlink(enabled); err == nil && tgt != "" {
			oldLinkTarget = tgt
			hadOldLink = true
		}
	}

	rollback := func() {
		_ = os.Remove(enabled)
		if hadOldLink && oldLinkTarget != "" {
			_ = os.Symlink(oldLinkTarget, enabled)
		}
		if hadAvail {
			_ = os.WriteFile(avail, oldAvail, 0o644)
		} else {
			_ = os.Remove(avail)
		}
	}

	if err := os.WriteFile(avail, buf.Bytes(), 0o644); err != nil {
		return fmt.Errorf("write apache vhost: %w", err)
	}
	_ = os.Remove(enabled)
	if err := os.Symlink(avail, enabled); err != nil {
		rollback()
		return fmt.Errorf("apache symlink: %w", err)
	}

	if err := apacheTestConfig(); err != nil {
		rollback()
		return err
	}

	if cfg.Hosting.ApacheReloadAfterVhost {
		if err := reloadApacheErr(); err != nil {
			return err
		}
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
		_ = reloadApacheErr()
	}
	return nil
}

// RemoveVhostBestEffort site silme yolu: hostvim ve eski panelsar-* apache vhost dosyalarını kaldırmayı dener.
func RemoveVhostBestEffort(cfg *config.Config, domain string) {
	if domain == "" || strings.Contains(domain, "..") {
		return
	}
	base := confBaseName(domain)
	_ = os.Remove(filepath.Join(sitesEnabled(cfg), base))
	_ = os.Remove(filepath.Join(sitesAvailable(cfg), base))
	leg := "panelsar-" + strings.ToLower(domain) + ".conf"
	_ = os.Remove(filepath.Join(sitesEnabled(cfg), leg))
	_ = os.Remove(filepath.Join(sitesAvailable(cfg), leg))
	if cfg.Hosting.ApacheReloadAfterVhost {
		_ = reloadApacheErr()
	}
}

func apacheTestConfig() error {
	var out []byte
	var err error
	if _, e := exec.LookPath("apache2ctl"); e == nil {
		out, err = exec.Command("apache2ctl", "configtest").CombinedOutput()
	} else {
		out, err = exec.Command("apachectl", "configtest").CombinedOutput()
	}
	if err != nil {
		return fmt.Errorf("apache configtest: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

func reloadApacheErr() error {
	if _, err := exec.LookPath("apache2ctl"); err == nil {
		out, err2 := exec.Command("apache2ctl", "graceful").CombinedOutput()
		if err2 != nil {
			return fmt.Errorf("apache2ctl graceful: %w — %s", err2, strings.TrimSpace(string(out)))
		}
		return nil
	}
	out, err := exec.Command("apachectl", "graceful").CombinedOutput()
	if err != nil {
		return fmt.Errorf("apachectl graceful: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// VhostFilePath sites-available altındaki hostvim-<domain>.conf mutlak yolu.
func VhostFilePath(cfg *config.Config, domain string) (string, error) {
	domain = strings.ToLower(strings.TrimSpace(domain))
	if domain == "" || strings.Contains(domain, "..") || !nginx.DomainSafe(domain) {
		return "", fmt.Errorf("invalid domain")
	}
	availDir := sitesAvailable(cfg)
	availClean, err := filepath.Abs(filepath.Clean(availDir))
	if err != nil {
		return "", fmt.Errorf("apache sites-available: %w", err)
	}
	base := confBaseName(domain)
	p := filepath.Join(availClean, base)
	p = filepath.Clean(p)
	rel, err := filepath.Rel(availClean, p)
	if err != nil || rel == ".." || strings.HasPrefix(rel, "../") {
		return "", fmt.Errorf("invalid vhost path")
	}
	return p, nil
}

// VhostCanRevert son başarılı kayıttan önceki içerik dosyası var mı.
func VhostCanRevert(cfg *config.Config, domain string) (bool, error) {
	p, err := VhostFilePath(cfg, domain)
	if err != nil {
		return false, err
	}
	fi, err := os.Stat(apachePrevPath(p))
	if err != nil {
		if os.IsNotExist(err) {
			return false, nil
		}
		return false, err
	}
	return fi.Size() > 0, nil
}

// ReadVhostFile mevcut Apache vhost dosyasını okur.
func ReadVhostFile(cfg *config.Config, domain string) ([]byte, error) {
	if !cfg.Hosting.ApacheManageVhosts {
		return nil, fmt.Errorf("apache vhost management is disabled")
	}
	p, err := VhostFilePath(cfg, domain)
	if err != nil {
		return nil, err
	}
	return os.ReadFile(p)
}

// WriteVhostRaw Apache vhost içeriğini yazar, configtest ve istenirse graceful reload uygular.
func WriteVhostRaw(cfg *config.Config, domain string, content []byte) error {
	if !cfg.Hosting.ApacheManageVhosts {
		return fmt.Errorf("apache vhost management is disabled")
	}
	if len(content) > maxRawApacheVhostBytes {
		return fmt.Errorf("vhost content too large (max %d bytes)", maxRawApacheVhostBytes)
	}
	if bytes.IndexByte(content, 0) >= 0 {
		return fmt.Errorf("invalid vhost content")
	}
	p, err := VhostFilePath(cfg, domain)
	if err != nil {
		return err
	}
	base := confBaseName(strings.ToLower(strings.TrimSpace(domain)))
	availDir := sitesAvailable(cfg)
	enDir := sitesEnabled(cfg)
	if err := os.MkdirAll(availDir, 0o755); err != nil {
		return fmt.Errorf("apache sites-available: %w", err)
	}
	if err := os.MkdirAll(enDir, 0o755); err != nil {
		return fmt.Errorf("apache sites-enabled: %w", err)
	}
	enabled := filepath.Join(enDir, base)

	oldAvail, readAvailErr := os.ReadFile(p)
	hadAvail := readAvailErr == nil

	var oldLinkTarget string
	hadOldLink := false
	if fi, err := os.Lstat(enabled); err == nil && fi.Mode()&os.ModeSymlink != 0 {
		if tgt, err := os.Readlink(enabled); err == nil && tgt != "" {
			oldLinkTarget = tgt
			hadOldLink = true
		}
	}

	rollback := func() {
		_ = os.Remove(enabled)
		if hadOldLink && oldLinkTarget != "" {
			_ = os.Symlink(oldLinkTarget, enabled)
		}
		if hadAvail {
			_ = os.WriteFile(p, oldAvail, 0o644)
		} else {
			_ = os.Remove(p)
		}
	}

	if err := os.WriteFile(p, content, 0o644); err != nil {
		return fmt.Errorf("write apache vhost: %w", err)
	}
	_ = os.Remove(enabled)
	if err := os.Symlink(p, enabled); err != nil {
		rollback()
		return fmt.Errorf("apache symlink: %w", err)
	}
	if err := apacheTestConfig(); err != nil {
		rollback()
		return err
	}
	if cfg.Hosting.ApacheReloadAfterVhost {
		if err := reloadApacheErr(); err != nil {
			rollback()
			return err
		}
	}
	prev := apachePrevPath(p)
	if hadAvail && len(oldAvail) > 0 {
		_ = os.WriteFile(prev, oldAvail, 0o600)
	} else {
		_ = os.Remove(prev)
	}
	return nil
}

// RevertVhostRaw son başarılı kayıttan önceki içeriği geri yükler.
func RevertVhostRaw(cfg *config.Config, domain string) error {
	if !cfg.Hosting.ApacheManageVhosts {
		return fmt.Errorf("apache vhost management is disabled")
	}
	p, err := VhostFilePath(cfg, domain)
	if err != nil {
		return err
	}
	prev := apachePrevPath(p)
	prevBody, err := os.ReadFile(prev)
	if err != nil || len(bytes.TrimSpace(prevBody)) == 0 {
		return fmt.Errorf("no saved previous version to restore")
	}
	domain = strings.ToLower(strings.TrimSpace(domain))
	base := confBaseName(domain)
	enDir := sitesEnabled(cfg)
	enabled := filepath.Join(enDir, base)

	var curContent []byte
	hadCur := false
	if b, rerr := os.ReadFile(p); rerr == nil {
		curContent = b
		hadCur = true
	}

	var oldLinkTarget string
	hadOldLink := false
	if fi, err := os.Lstat(enabled); err == nil && fi.Mode()&os.ModeSymlink != 0 {
		if tgt, err := os.Readlink(enabled); err == nil && tgt != "" {
			oldLinkTarget = tgt
			hadOldLink = true
		}
	}

	revertRollback := func() {
		_ = os.Remove(enabled)
		if hadOldLink && oldLinkTarget != "" {
			_ = os.Symlink(oldLinkTarget, enabled)
		}
		if hadCur {
			_ = os.WriteFile(p, curContent, 0o644)
		}
	}

	if err := os.WriteFile(p, prevBody, 0o644); err != nil {
		return fmt.Errorf("write apache vhost: %w", err)
	}
	_ = os.Remove(enabled)
	if err := os.Symlink(p, enabled); err != nil {
		if hadCur {
			_ = os.WriteFile(p, curContent, 0o644)
		}
		_ = os.Remove(enabled)
		if hadOldLink && oldLinkTarget != "" {
			_ = os.Symlink(oldLinkTarget, enabled)
		}
		return fmt.Errorf("apache symlink: %w", err)
	}
	if err := apacheTestConfig(); err != nil {
		revertRollback()
		return err
	}
	if cfg.Hosting.ApacheReloadAfterVhost {
		if err := reloadApacheErr(); err != nil {
			revertRollback()
			return err
		}
	}
	if hadCur && len(curContent) > 0 {
		_ = os.WriteFile(prev, curContent, 0o600)
	} else {
		_ = os.Remove(prev)
	}
	return nil
}
