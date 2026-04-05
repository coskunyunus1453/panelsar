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
	return "hostvim-" + poolSlug(domain)
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

// ReadPoolSnapshot mevcut pool dosyası varsa içeriğini döner (geri alma / sürüm değişimi yedeği).
func ReadPoolSnapshot(h HostingPoolSettings, domain, phpVersion string) (data []byte, ok bool) {
	domain = strings.TrimSpace(domain)
	if domain == "" || strings.Contains(domain, "..") {
		return nil, false
	}
	p := poolConfPath(h, phpVersion, domain)
	b, err := os.ReadFile(p)
	if err != nil {
		return nil, false
	}
	return b, true
}

func fpmTestBinary(phpVersion string) string {
	v := NormalizeVersion(phpVersion)
	name := "php-fpm" + v
	if p, err := exec.LookPath(name); err == nil {
		return p
	}
	alt := filepath.Join("/usr/sbin", name)
	if st, err := os.Stat(alt); err == nil && !st.IsDir() {
		return alt
	}
	return ""
}

// TestFPMConfig php-fpm sürüm ikilisini bulursa `php-fpmX.Y -t` çalıştırır. İkili yoksa hata dönmez (ör. geliştirme ortamı).
func TestFPMConfig(phpVersion string) error {
	bin := fpmTestBinary(phpVersion)
	if bin == "" {
		return nil
	}
	out, err := exec.Command(bin, "-t").CombinedOutput()
	if err != nil {
		return fmt.Errorf("php-fpm -t: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// RestorePoolConf başarısız reload veya vhost sonrası pool dosyasını önceki duruma getirir; hadPrevious false ise dosyayı siler.
func RestorePoolConf(h HostingPoolSettings, domain, phpVersion string, previous []byte, hadPrevious bool) error {
	domain = strings.TrimSpace(domain)
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	p := poolConfPath(h, phpVersion, domain)
	if hadPrevious {
		return os.WriteFile(p, previous, 0o644)
	}
	return os.Remove(p)
}

// WritePool pool dosyasını yazar; önceki içerik varsa geri alma için döner.
// Yazdıktan sonra mümkünse `php-fpm -t` ile doğrular; test başarısızsa dosyayı eski haline getirir.
func WritePool(h HostingPoolSettings, domain, phpVersion, docRoot string) (socket string, previous []byte, hadPrevious bool, err error) {
	domain = strings.TrimSpace(domain)
	if domain == "" || strings.Contains(domain, "..") {
		return "", nil, false, fmt.Errorf("invalid domain")
	}
	docRoot = filepath.Clean(docRoot)
	if strings.Contains(docRoot, "..") {
		return "", nil, false, fmt.Errorf("invalid document root")
	}

	socket = SocketPath(h.listenDir(), domain)
	confPath := poolConfPath(h, phpVersion, domain)
	dir := filepath.Dir(confPath)

	if err := os.MkdirAll(dir, 0o755); err != nil {
		return "", nil, false, fmt.Errorf("pool.d mkdir: %w", err)
	}
	if err := os.MkdirAll(filepath.Dir(socket), 0o755); err != nil {
		return "", nil, false, fmt.Errorf("run dir: %w", err)
	}

	if b, rerr := os.ReadFile(confPath); rerr == nil {
		previous = append([]byte(nil), b...)
		hadPrevious = true
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
		return "", previous, hadPrevious, fmt.Errorf("write pool: %w", err)
	}

	if terr := TestFPMConfig(phpVersion); terr != nil {
		_ = RestorePoolConf(h, domain, phpVersion, previous, hadPrevious)
		return "", nil, false, terr
	}

	return socket, previous, hadPrevious, nil
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

// RemovePoolBestEffortAllVersions /etc/php altındaki X.Y sürüm dizinlerinde hostvim (ve eski panelsar) pool dosyasını arar, varsa siler.
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
		if _, err := os.Stat(p); err == nil {
			_ = os.Remove(p)
			removed = append(removed, ver)
			continue
		}
		leg := filepath.Join(filepath.Dir(p), "panelsar-"+poolSlug(domain)+".conf")
		if _, err := os.Stat(leg); err == nil {
			_ = os.Remove(leg)
			removed = append(removed, ver)
		}
	}
	return removed
}

// Reload debian/ubuntu: systemctl reload php8.2-fpm
func Reload(phpVersion string) error {
	v := NormalizeVersion(phpVersion)
	svc := "php" + v + "-fpm"
	if _, err := exec.LookPath("systemctl"); err == nil {
		out, err := exec.Command("systemctl", "reload", svc).CombinedOutput()
		if err != nil {
			return fmt.Errorf("systemctl reload %s: %w — %s", svc, err, strings.TrimSpace(string(out)))
		}
		return nil
	}
	if _, err := exec.LookPath("service"); err == nil {
		out, err := exec.Command("service", svc, "reload").CombinedOutput()
		if err != nil {
			return fmt.Errorf("service %s reload: %w — %s", svc, err, strings.TrimSpace(string(out)))
		}
		return nil
	}
	return nil
}
