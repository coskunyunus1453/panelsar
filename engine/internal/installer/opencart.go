package installer

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/sites"
)

// OpenCart resmi dağıtım (upload/ öneki); sürüm sabit, güvenilir indirme için GitHub release.
const defaultOpenCartZipURL = "https://github.com/opencart/opencart/releases/download/4.0.2.3/opencart-4.0.2.3.zip"

func installOpenCart(cfg *config.Config, domain string, db *DBConfig) error {
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, domain)
	if err != nil || meta == nil {
		return fmt.Errorf("site not found")
	}
	docRoot := filepath.Clean(meta.DocumentRoot)
	if !strings.HasPrefix(docRoot, filepath.Clean(cfg.Paths.WebRoot)) {
		return fmt.Errorf("invalid document root")
	}

	if _, err := os.Stat(filepath.Join(docRoot, "system", "startup.php")); err == nil {
		return fmt.Errorf("OpenCart dosyaları zaten mevcut (system/startup.php)")
	}
	if _, err := os.Stat(filepath.Join(docRoot, "config.php")); err == nil {
		return fmt.Errorf("config.php mevcut; kurulum tamamlanmış veya manuel devam edin")
	}

	if db == nil || strings.TrimSpace(db.Name) == "" || strings.TrimSpace(db.User) == "" {
		return fmt.Errorf("opencart için db_name ve db_user gerekli (kurulum sihirbazında kullanılacak)")
	}

	zipURL := strings.TrimSpace(cfg.Hosting.OpenCartZipURL)
	if zipURL == "" {
		zipURL = defaultOpenCartZipURL
	}

	if err := os.MkdirAll(cfg.Paths.TempDir, 0o755); err != nil {
		return fmt.Errorf("temp dir: %w", err)
	}
	zipFile := filepath.Join(cfg.Paths.TempDir, "hostvim-opencart-"+domain+".zip")
	if err := downloadFile(zipURL, zipFile, 20*time.Minute); err != nil {
		return err
	}
	defer os.Remove(zipFile)

	if err := UnzipOpenCartUpload(zipFile, docRoot); err != nil {
		return err
	}

	return writeOpenCartInstallHint(cfg.Paths.WebRoot, domain, db)
}

func sanitizeHintLine(s string) string {
	s = strings.TrimSpace(s)
	s = strings.ReplaceAll(s, "\n", "")
	s = strings.ReplaceAll(s, "\r", "")
	return s
}

// writeOpenCartInstallHint DB özetini site özel dizinine yazar (web kökünde değil — HTTP ile sızdırılmaz). Şifre yazılmaz.
func writeOpenCartInstallHint(webRoot, domain string, db *DBConfig) error {
	priv := sites.SitePrivateDir(webRoot, domain)
	if priv == "" {
		return fmt.Errorf("opencart hint: geçersiz alan adı")
	}
	if err := os.MkdirAll(priv, 0o750); err != nil {
		return fmt.Errorf("opencart hint dir: %w", err)
	}
	host := sanitizeHintLine(db.Host)
	if host == "" {
		host = "127.0.0.1"
	}
	port := db.Port
	if port <= 0 {
		port = 3306
	}
	dbName := sanitizeHintLine(db.Name)
	dbUser := sanitizeHintLine(db.User)
	if dbName == "" || dbUser == "" {
		return fmt.Errorf("opencart hint: db adı veya kullanıcı boş")
	}
	body := fmt.Sprintf(
		"# Hostvim — OpenCart kurulum sihirbazı için veritabanı özeti (şifre yok).\n"+
			"# Konum: alan_adı/.hostvim/opencart-db-hint.txt (genelde public_html dışında)\n"+
			"# Kurulumdan sonra silebilirsiniz.\n\n"+
			"DB_HOST=%s\nDB_PORT=%d\nDB_NAME=%s\nDB_USER=%s\n",
		host, port, dbName, dbUser,
	)
	path := filepath.Join(priv, "opencart-db-hint.txt")
	return os.WriteFile(path, []byte(body), 0o600)
}
