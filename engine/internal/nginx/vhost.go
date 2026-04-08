package nginx

import (
	"bytes"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"text/template"

	"hostvim/engine/internal/config"
)

var domainSafe = regexp.MustCompile(`^[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?)*$`)

// DomainSafe alan adı nginx/apache vhost için güvenli mi.
func DomainSafe(domain string) bool {
	return domainSafe.MatchString(domain)
}

// BuildServerNamesLine birincil alan adı ve ek adlar için nginx server_name satırı üretir.
func BuildServerNamesLine(primary string, aliases []string) string {
	primary = strings.ToLower(strings.TrimSpace(primary))
	if primary == "" || !domainSafe.MatchString(primary) {
		return ""
	}
	seen := map[string]struct{}{}
	var parts []string
	add := func(s string) {
		s = strings.ToLower(strings.TrimSpace(s))
		if s == "" || !domainSafe.MatchString(s) {
			return
		}
		if _, ok := seen[s]; ok {
			return
		}
		seen[s] = struct{}{}
		parts = append(parts, s)
	}
	add(primary)
	add("www." + primary)
	for _, a := range aliases {
		al := strings.ToLower(strings.TrimSpace(a))
		if al == "" || al == primary {
			continue
		}
		if !domainSafe.MatchString(al) {
			continue
		}
		add(al)
		if !strings.HasPrefix(al, "www.") {
			add("www." + al)
		}
	}
	return strings.Join(parts, " ")
}

const vhostTemplateSSL = `# Hostvim — {{.PrimaryLabel}} (HTTPS)
server {
    listen 80;
    listen [::]:80;
    server_name {{.ServerNames}};

    # Let's Encrypt HTTP-01 challenge must stay reachable on plain HTTP.
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root {{.DocRoot}};
        try_files $uri =404;
        allow all;
    }

    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name {{.ServerNames}};

    ssl_certificate {{.SSLFullChain}};
    ssl_certificate_key {{.SSLPrivKey}};
    ssl_session_timeout 1d;
    ssl_session_cache shared:HostvimSSL:10m;

    add_header Strict-Transport-Security "max-age=31536000" always;
{{if eq .PerfMode "standard"}}
    # Hostvim Performance Mode (standard)
    gzip on;
    gzip_static on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml application/xml+rss text/javascript image/svg+xml;
    etag on;
    if_modified_since exact;

    location ~* \.(?:css|js|mjs|map|json|xml|txt|ico|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot)$ {
        expires modified 30d;
        add_header Cache-Control "public, max-age=2592000, immutable" always;
        access_log off;
        try_files $uri =404;
    }
    add_header X-Hostvim-Perf "standard" always;
{{end}}

    root {{.DocRoot}};
    index index.php index.html;

    access_log {{.AccessLog}};
    error_log {{.ErrorLog}};

    client_max_body_size 128m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Wrong VITE base + /admin URL → browser requests /admin/admin/assets/* ; map to real /assets/*
    location ^~ /admin/admin/assets/ {
        rewrite ^/admin/admin/assets/(.*)$ /assets/$1 break;
        try_files $uri =404;
        access_log off;
    }

    # SPA static fallback: if build produced relative asset paths and user is on /admin/*
    # serve /admin/assets/* from actual /assets/* to avoid 404 on hashed bundles.
    location ^~ /admin/assets/ {
        rewrite ^/admin/assets/(.*)$ /assets/$1 break;
        try_files $uri =404;
        access_log off;
    }

    location ^~ /assets/ {
        try_files $uri =404;
        access_log off;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:{{.PHPSocket}};
    }

    location ~ /\. {
        deny all;
    }
}
`

const vhostTemplateHTTP = `# Hostvim — {{.PrimaryLabel}}
server {
    listen 80;
    listen [::]:80;
    server_name {{.ServerNames}};
{{if eq .PerfMode "standard"}}
    # Hostvim Performance Mode (standard)
    gzip on;
    gzip_static on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml application/xml+rss text/javascript image/svg+xml;
    etag on;
    if_modified_since exact;

    location ~* \.(?:css|js|mjs|map|json|xml|txt|ico|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot)$ {
        expires modified 30d;
        add_header Cache-Control "public, max-age=2592000, immutable" always;
        access_log off;
        try_files $uri =404;
    }
    add_header X-Hostvim-Perf "standard" always;
{{end}}
    root {{.DocRoot}};
    index index.php index.html;

    access_log {{.AccessLog}};
    error_log {{.ErrorLog}};

    client_max_body_size 128m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ^~ /admin/admin/assets/ {
        rewrite ^/admin/admin/assets/(.*)$ /assets/$1 break;
        try_files $uri =404;
        access_log off;
    }

    # SPA static fallback: if build produced relative asset paths and user is on /admin/*
    # serve /admin/assets/* from actual /assets/* to avoid 404 on hashed bundles.
    location ^~ /admin/assets/ {
        rewrite ^/admin/assets/(.*)$ /assets/$1 break;
        try_files $uri =404;
        access_log off;
    }

    location ^~ /assets/ {
        try_files $uri =404;
        access_log off;
    }

    # Let's Encrypt HTTP-01 challenge endpoint.
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root {{.DocRoot}};
        try_files $uri =404;
        allow all;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:{{.PHPSocket}};
    }

    location ~ /\. {
        deny all;
    }
}
`

type vhostVars struct {
	PrimaryLabel string
	ServerNames  string
	DocRoot      string
	AccessLog    string
	ErrorLog     string
	PHPSocket    string
	SSLFullChain string
	SSLPrivKey   string
	PerfMode     string
}

// PHPSocketPath üretir: override doluysa onu; değilse /run/php/php{version}-fpm.sock (örn. 8.2).
func PHPSocketPath(phpVersion, override string) string {
	if strings.TrimSpace(override) != "" {
		return strings.TrimSpace(override)
	}
	v := strings.TrimSpace(phpVersion)
	if v == "" {
		v = "8.2"
	}
	return fmt.Sprintf("/run/php/php%s-fpm.sock", v)
}

// EffectivePHPSocket panelde seçilen PHP sürümü için gerçekten kullanılacak FPM soketini seçer.
// Kurulumda engine.yaml içine yazılan php_fpm_socket tek bir sokete kilitlenir; bu durumda
// PHPSocketPath sürüm değişimini yok sayar. Sunucuda /run/php/php{X.Y}-fpm.sock mevcutsa
// önce onu kullanır, böylece nginx/apache vhost gerçekten yeni FPM sürümüne yönlendirilir.
func EffectivePHPSocket(phpVersion, socketOverride string) string {
	verPath := PHPSocketPath(phpVersion, "")
	o := strings.TrimSpace(socketOverride)
	if o == "" {
		return verPath
	}
	if verPath == o {
		return o
	}
	if fi, err := os.Stat(verPath); err == nil && !fi.IsDir() {
		return verPath
	}
	return PHPSocketPath(phpVersion, socketOverride)
}

func confBaseName(domain string) string {
	return "hostvim-" + strings.ToLower(domain) + ".conf"
}

// ApplyVhost sites-available altına conf yazar ve istenirse sites-enabled’a sembolik bağ oluşturur.
// confName: dosya adı / log öneki (örn. example.com veya blog.example.com).
// serverNamesLine boşsa confName + www.confName kullanılır.
// sslFullchain ve sslPrivkey doluysa 443 + 80’de HTTPS yönlendirmesi üretilir.
func ApplyVhost(cfg *config.Config, confName, docRoot, phpSocket, sslFullchain, sslPrivkey, serverNamesLine, perfMode string) error {
	if !cfg.Hosting.NginxManageVhosts {
		return nil
	}
	confName = strings.ToLower(strings.TrimSpace(confName))
	if !domainSafe.MatchString(confName) {
		return fmt.Errorf("invalid domain for vhost")
	}
	if strings.Contains(docRoot, "..") {
		return fmt.Errorf("invalid document root")
	}
	docRoot = filepath.Clean(docRoot)

	sn := strings.TrimSpace(serverNamesLine)
	if sn == "" {
		sn = BuildServerNamesLine(confName, nil)
	}
	if sn == "" {
		return fmt.Errorf("invalid server_name")
	}

	if err := os.MkdirAll(cfg.Paths.LogDir, 0o755); err != nil {
		return fmt.Errorf("log dir: %w", err)
	}
	if err := os.MkdirAll(cfg.Paths.VhostsDir, 0o755); err != nil {
		return fmt.Errorf("vhosts dir: %w", err)
	}

	sock := strings.TrimSpace(phpSocket)
	if sock == "" {
		sock = "/run/php/php8.2-fpm.sock"
	}

	chain := strings.TrimSpace(sslFullchain)
	key := strings.TrimSpace(sslPrivkey)
	useSSL := chain != "" && key != ""

	tplStr := vhostTemplateHTTP
	if useSSL {
		tplStr = vhostTemplateSSL
	}
	tpl, err := template.New("vhost").Parse(tplStr)
	if err != nil {
		return err
	}

	vars := vhostVars{
		PrimaryLabel: confName,
		ServerNames:  sn,
		DocRoot:      docRoot,
		AccessLog:    filepath.Join(cfg.Paths.LogDir, confName+"_access.log"),
		ErrorLog:     filepath.Join(cfg.Paths.LogDir, confName+"_error.log"),
		PHPSocket:    sock,
		SSLFullChain: chain,
		SSLPrivKey:   key,
		PerfMode:     strings.TrimSpace(perfMode),
	}

	var buf bytes.Buffer
	if err := tpl.Execute(&buf, vars); err != nil {
		return err
	}

	base := confBaseName(confName)
	avail := filepath.Join(cfg.Paths.VhostsDir, base)

	var oldContent []byte
	hadOld := false
	if _, statErr := os.Stat(avail); statErr == nil {
		b, rerr := os.ReadFile(avail)
		if rerr != nil {
			return fmt.Errorf("read existing vhost: %w", rerr)
		}
		oldContent = b
		hadOld = true
	}

	if err := os.WriteFile(avail, buf.Bytes(), 0o644); err != nil {
		return fmt.Errorf("write vhost: %w", err)
	}

	// www-data /etc/nginx/sites-enabled altına yazamaz; sudo + deploy/host/hostvim-nginx-vhost
	if err := runNginxVhostHelper(cfg, "enable", avail); err != nil {
		_ = runNginxVhostHelper(cfg, "disable", base)
		if hadOld {
			if werr := os.WriteFile(avail, oldContent, 0o644); werr != nil {
				return fmt.Errorf("%w; önceki vhost geri yazılamadı: %v", err, werr)
			}
			if e2 := runNginxVhostHelper(cfg, "enable", avail); e2 != nil {
				return fmt.Errorf("%w; önceki vhost yeniden etkinleştirilemedi: %v", err, e2)
			}
		} else {
			_ = os.Remove(avail)
		}
		return err
	}

	return nil
}

const defaultNginxVhostHelper = "/usr/local/sbin/hostvim-nginx-vhost"

func nginxVhostHelperPath(cfg *config.Config) string {
	if cfg == nil {
		return defaultNginxVhostHelper
	}
	s := strings.TrimSpace(cfg.Hosting.NginxVhostHelper)
	if s != "" {
		return s
	}
	return defaultNginxVhostHelper
}

func runNginxVhostHelper(cfg *config.Config, action, arg string) error {
	helper := nginxVhostHelperPath(cfg)
	out, err := exec.Command("sudo", helper, action, arg).CombinedOutput()
	if err != nil {
		return fmt.Errorf("nginx vhost helper %s: %w: %s", action, err, strings.TrimSpace(string(out)))
	}
	return nil
}

// RemoveVhost conf ve sembolik bağları kaldırır.
func RemoveVhost(cfg *config.Config, domain string) error {
	if !cfg.Hosting.NginxManageVhosts {
		return nil
	}
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	base := confBaseName(domain)
	avail := filepath.Join(cfg.Paths.VhostsDir, base)

	if err := runNginxVhostHelper(cfg, "disable", base); err != nil {
		return err
	}
	_ = os.Remove(avail)
	return nil
}

// RemoveVhostBestEffort site silme yolu: NginxManageVhosts kapalı olsa bile hostvim (veya eski panelsar) conf dosyasını kaldırmayı dener.
func RemoveVhostBestEffort(cfg *config.Config, domain string) {
	if domain == "" || strings.Contains(domain, "..") {
		return
	}
	base := confBaseName(domain)
	avail := filepath.Join(cfg.Paths.VhostsDir, base)
	_ = runNginxVhostHelper(cfg, "disable", base)
	_ = os.Remove(avail)
	legacy := filepath.Join(cfg.Paths.VhostsDir, "panelsar-"+strings.ToLower(domain)+".conf")
	_ = runNginxVhostHelper(cfg, "disable", "panelsar-"+strings.ToLower(domain)+".conf")
	_ = os.Remove(legacy)
}
