package installer

import (
	"archive/zip"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/sites"
)

var tablePrefixRe = regexp.MustCompile(`^[a-zA-Z0-9_]{1,16}$`)

func installWordPress(cfg *config.Config, domain string, db *DBConfig) error {
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
	if _, err := os.Stat(filepath.Join(docRoot, "wp-load.php")); err == nil {
		return fmt.Errorf("WordPress zaten kurulu")
	}

	prefix := strings.TrimSpace(db.TablePrefix)
	if prefix == "" {
		prefix = "wp_"
	}
	if !tablePrefixRe.MatchString(prefix) {
		return fmt.Errorf("geçersiz table_prefix")
	}

	zipURL := strings.TrimSpace(cfg.Hosting.WordPressZipURL)
	if zipURL == "" {
		zipURL = "https://wordpress.org/latest.zip"
	}

	if err := os.MkdirAll(cfg.Paths.TempDir, 0o755); err != nil {
		return fmt.Errorf("temp dir: %w", err)
	}
	zipFile := filepath.Join(cfg.Paths.TempDir, "hostvim-wordpress-"+domain+".zip")
	if err := downloadFile(zipURL, zipFile, 15*time.Minute); err != nil {
		return err
	}
	defer os.Remove(zipFile)

	if err := unzipWordPress(zipFile, docRoot); err != nil {
		return err
	}

	dbHost := strings.TrimSpace(db.Host)
	if dbHost == "" {
		dbHost = "127.0.0.1"
	}
	if db.Port > 0 && db.Port != 3306 {
		dbHost = fmt.Sprintf("%s:%d", dbHost, db.Port)
	}

	salts, err := fetchWordPressSalts(30 * time.Second)
	if err != nil {
		return fmt.Errorf("wordpress salts: %w", err)
	}

	cfgBody := buildWpConfig(db.Name, db.User, db.Password, dbHost, prefix, salts)
	wpConfigPath := filepath.Join(docRoot, "wp-config.php")
	if err := os.WriteFile(wpConfigPath, []byte(cfgBody), 0o640); err != nil {
		return fmt.Errorf("wp-config.php: %w", err)
	}

	return nil
}

func downloadFile(url, dest string, timeout time.Duration) error {
	client := &http.Client{Timeout: timeout}
	req, err := http.NewRequest(http.MethodGet, url, nil)
	if err != nil {
		return err
	}
	req.Header.Set("User-Agent", "HostvimEngine/1.0")
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("indirme: %w", err)
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("zip http %d", resp.StatusCode)
	}
	f, err := os.Create(dest)
	if err != nil {
		return err
	}
	defer f.Close()
	if _, err := io.Copy(f, resp.Body); err != nil {
		return err
	}
	return nil
}

func unzipWordPress(srcZip, destDir string) error {
	r, err := zip.OpenReader(srcZip)
	if err != nil {
		return fmt.Errorf("zip: %w", err)
	}
	defer r.Close()

	const prefix = "wordpress/"
	absDest, err := filepath.Abs(destDir)
	if err != nil {
		return err
	}

	for _, f := range r.File {
		if !strings.HasPrefix(f.Name, prefix) {
			continue
		}
		rel := strings.TrimPrefix(f.Name, prefix)
		if rel == "" || strings.HasSuffix(rel, "/") {
			continue
		}
		rel = filepath.FromSlash(rel)
		if strings.Contains(rel, "..") {
			continue
		}
		target := filepath.Join(destDir, rel)
		absTarget, err := filepath.Abs(target)
		if err != nil {
			return err
		}
		if absTarget != absDest && !strings.HasPrefix(absTarget, absDest+string(os.PathSeparator)) {
			continue
		}
		if f.FileInfo().IsDir() {
			if err := os.MkdirAll(target, 0o755); err != nil {
				return err
			}
			continue
		}
		if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
			return err
		}
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.OpenFile(target, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, f.Mode())
		if err != nil {
			rc.Close()
			return err
		}
		_, err = io.Copy(out, rc)
		rc.Close()
		out.Close()
		if err != nil {
			return err
		}
	}
	return nil
}

func fetchWordPressSalts(timeout time.Duration) (string, error) {
	client := &http.Client{Timeout: timeout}
	req, err := http.NewRequest(http.MethodGet, "https://api.wordpress.org/secret-key/1.1/salt/", nil)
	if err != nil {
		return "", err
	}
	req.Header.Set("User-Agent", "HostvimEngine/1.0")
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return "", fmt.Errorf("http %d", resp.StatusCode)
	}
	b, err := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if err != nil {
		return "", err
	}
	return strings.TrimSpace(string(b)), nil
}

func phpQuote(s string) string {
	s = strings.ReplaceAll(s, `\`, `\\`)
	s = strings.ReplaceAll(s, `'`, `\'`)
	return `'` + s + `'`
}

func buildWpConfig(dbName, dbUser, dbPass, dbHost, tablePrefix, saltsBlock string) string {
	var b strings.Builder
	b.WriteString("<?php\n")
	b.WriteString(fmt.Sprintf("define( 'DB_NAME', %s );\n", phpQuote(dbName)))
	b.WriteString(fmt.Sprintf("define( 'DB_USER', %s );\n", phpQuote(dbUser)))
	b.WriteString(fmt.Sprintf("define( 'DB_PASSWORD', %s );\n", phpQuote(dbPass)))
	b.WriteString(fmt.Sprintf("define( 'DB_HOST', %s );\n", phpQuote(dbHost)))
	b.WriteString("define( 'DB_CHARSET', 'utf8' );\n")
	b.WriteString("define( 'DB_COLLATE', '' );\n\n")
	b.WriteString(fmt.Sprintf("$table_prefix = %s;\n\n", phpQuote(tablePrefix)))
	if saltsBlock != "" {
		b.WriteString(saltsBlock)
		b.WriteString("\n\n")
	}
	b.WriteString("define( 'WP_DEBUG', false );\n\n")
	b.WriteString("if ( ! defined( 'ABSPATH' ) ) {\n\tdefine( 'ABSPATH', __DIR__ . '/' );\n}\n\n")
	b.WriteString("require_once ABSPATH . 'wp-settings.php';\n")
	return b.String()
}
