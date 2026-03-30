package phpfpm

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
)

// NormalizeVersion "8.2" gibi sade sürüm metni döner.
func NormalizeVersion(v string) string {
	s := strings.TrimSpace(v)
	if s == "" {
		return "8.2"
	}
	return s
}

func poolSlug(domain string) string {
	return strings.ReplaceAll(strings.ToLower(strings.TrimSpace(domain)), ".", "-")
}

// PoolName php-fpm [pool] bölümü adı.
func PoolName(domain string) string {
	return "panelsar-" + poolSlug(domain)
}

// SocketPath bu domain için unix soket yolu (nginx ile aynı olmalı).
func SocketPath(listenDir, domain string) string {
	name := PoolName(domain) + ".sock"
	if listenDir == "" {
		listenDir = "/run/php"
	}
	return filepath.Join(listenDir, name)
}

func poolConfPath(h HostingPoolSettings, phpVersion, domain string) string {
	dir := h.poolDirForVersion(phpVersion)
	return filepath.Join(dir, PoolName(domain)+".conf")
}

type HostingPoolSettings struct {
	PoolDirTemplate string
	SocketListenDir string
	FPMUser         string
	FPMGroup        string
}

func (h HostingPoolSettings) poolDirForVersion(phpVersion string) string {
	v := NormalizeVersion(phpVersion)
	tpl := strings.TrimSpace(h.PoolDirTemplate)
	if tpl == "" {
		return filepath.Join("/etc/php", v, "fpm", "pool.d")
	}
	return strings.NewReplacer("{{version}}", v, "{{Version}}", v).Replace(tpl)
}

func (h HostingPoolSettings) listenDir() string {
	if strings.TrimSpace(h.SocketListenDir) == "" {
		return "/run/php"
	}
	return h.SocketListenDir
}

// SocketForDomain bu ayarlarla yazılmış pool’un unix soket yolu (nginx/apache ile aynı olmalı).
func (h HostingPoolSettings) SocketForDomain(domain string) string {
	return SocketPath(h.listenDir(), domain)
}

func (h HostingPoolSettings) poolUser() string {
	if strings.TrimSpace(h.FPMUser) == "" {
		return "www-data"
	}
	return h.FPMUser
}

func (h HostingPoolSettings) poolGroup() string {
	if strings.TrimSpace(h.FPMGroup) == "" {
		return "www-data"
	}
	return h.FPMGroup
}

const poolTemplate = `; Panelsar — %s — PHP %s
[%s]
user = %s
group = %s
listen = %s
listen.owner = %s
listen.group = %s
listen.mode = 0660
pm = ondemand
pm.max_children = 30
pm.process_idle_timeout = 30s
chdir = %s
php_admin_value[open_basedir] = %s:/tmp:/var/tmp
`

// WritePool pool dosyasını yazar ve kullanılacak socket yolunu döner.
func WritePool(h HostingPoolSettings, domain, phpVersion, docRoot string) (socket string, err error) {
	domain = strings.TrimSpace(domain)
	if domain == "" || strings.Contains(domain, "..") {
		return "", fmt.Errorf("invalid domain")
	}
	docRoot = filepath.Clean(docRoot)
	if strings.Contains(docRoot, "..") {
		return "", fmt.Errorf("invalid document root")
	}

	socket = SocketPath(h.listenDir(), domain)
	confPath := poolConfPath(h, phpVersion, domain)
	dir := filepath.Dir(confPath)

	if err := os.MkdirAll(dir, 0o755); err != nil {
		return "", fmt.Errorf("pool.d mkdir: %w", err)
	}
	if err := os.MkdirAll(filepath.Dir(socket), 0o755); err != nil {
		return "", fmt.Errorf("run dir: %w", err)
	}

	body := fmt.Sprintf(
		poolTemplate,
		domain,
		NormalizeVersion(phpVersion),
		PoolName(domain),
		h.poolUser(),
		h.poolGroup(),
		socket,
		h.poolUser(),
		h.poolGroup(),
		docRoot,
		docRoot,
	)

	if err := os.WriteFile(confPath, []byte(body), 0o644); err != nil {
		return "", fmt.Errorf("write pool: %w", err)
	}

	return socket, nil
}

// RemovePool belirtilen PHP sürüm dizinindeki pool dosyasını siler.
func RemovePool(h HostingPoolSettings, domain, phpVersion string) error {
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	p := poolConfPath(h, phpVersion, domain)
	return os.Remove(p)
}

var debianPHPVersionDir = regexp.MustCompile(`^[0-9]+\.[0-9]+$`)

// RemovePoolBestEffortAllVersions /etc/php altındaki X.Y sürüm dizinlerinde panelsar pool dosyasını arar, varsa siler.
// meta eksik site silinirken soket çöplüğünü önlemek için kullanılır. Silinen sürümler (örn. reload için) döner.
func RemovePoolBestEffortAllVersions(h HostingPoolSettings, domain string) []string {
	if domain == "" || strings.Contains(domain, "..") {
		return nil
	}
	entries, err := os.ReadDir("/etc/php")
	if err != nil {
		return nil
	}
	var removed []string
	for _, e := range entries {
		if !e.IsDir() || !debianPHPVersionDir.MatchString(e.Name()) {
			continue
		}
		ver := e.Name()
		p := poolConfPath(h, ver, domain)
		if _, err := os.Stat(p); err != nil {
			continue
		}
		_ = os.Remove(p)
		removed = append(removed, ver)
	}
	return removed
}

// Reload debian/ubuntu: systemctl reload php8.2-fpm
func Reload(phpVersion string) error {
	v := NormalizeVersion(phpVersion)
	svc := "php" + v + "-fpm"
	if _, err := exec.LookPath("systemctl"); err == nil {
		cmd := exec.Command("systemctl", "reload", svc)
		return cmd.Run()
	}
	if _, err := exec.LookPath("service"); err == nil {
		cmd := exec.Command("service", svc, "reload")
		return cmd.Run()
	}
	return nil
}
