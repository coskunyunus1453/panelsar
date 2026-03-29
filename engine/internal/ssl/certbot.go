package ssl

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/panelsar/engine/internal/config"
)

// LetsEncryptDirs certbot --config-dir / --work-dir / --logs-dir için yollar.
func LetsEncryptDirs(sslRoot string) (configDir, workDir, logsDir string) {
	base := filepath.Join(sslRoot, "letsencrypt")
	return filepath.Join(base, "config"), filepath.Join(base, "work"), filepath.Join(base, "logs")
}

// LiveCertPaths certbot canlı sertifika dosyaları (config-dir altında).
func LiveCertPaths(cfg *config.Config, domain string) (fullchain, privkey string) {
	cd, _, _ := LetsEncryptDirs(cfg.Paths.SSLDir)
	d := filepath.Join(cd, "live", domain)
	return filepath.Join(d, "fullchain.pem"), filepath.Join(d, "privkey.pem")
}

// CertsExist her iki PEM dosyası var mı.
func CertsExist(fullchain, privkey string) bool {
	st1, err1 := os.Stat(fullchain)
	st2, err2 := os.Stat(privkey)
	return err1 == nil && err2 == nil && !st1.IsDir() && !st2.IsDir()
}

func certbotBin(cfg *config.Config) string {
	s := strings.TrimSpace(cfg.Hosting.CertbotPath)
	if s == "" {
		return "certbot"
	}
	return s
}

// Issue webroot doğrulaması ile sertifika alır (domain + www.domain).
func Issue(cfg *config.Config, domain, webroot, email string) error {
	if !cfg.Hosting.ManageSSL {
		return fmt.Errorf("manage_ssl devre dışı")
	}
	email = strings.TrimSpace(email)
	if email == "" {
		email = strings.TrimSpace(cfg.Hosting.LetsEncryptEmail)
	}
	if email == "" {
		return fmt.Errorf("lets_encrypt_email veya istekte email gerekli")
	}
	cd, wd, ld := LetsEncryptDirs(cfg.Paths.SSLDir)
	for _, d := range []string{cd, wd, ld} {
		if err := os.MkdirAll(d, 0o700); err != nil {
			return fmt.Errorf("ssl dizini: %w", err)
		}
	}
	args := []string{
		"certonly", "--webroot", "-w", webroot,
		"-d", domain, "-d", "www." + domain,
		"--email", email,
		"--agree-tos", "-n", "--non-interactive",
		"--config-dir", cd, "--work-dir", wd, "--logs-dir", ld,
	}
	if cfg.Hosting.LetsEncryptStaging {
		args = append(args, "--staging")
	}
	out, err := exec.Command(certbotBin(cfg), args...).CombinedOutput()
	if err != nil {
		return fmt.Errorf("certbot: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// Renew mevcut sertifikayı yeniler (cert-name = birincil alan adı).
func Renew(cfg *config.Config, domain string) error {
	if !cfg.Hosting.ManageSSL {
		return fmt.Errorf("manage_ssl devre dışı")
	}
	cd, wd, ld := LetsEncryptDirs(cfg.Paths.SSLDir)
	args := []string{
		"renew", "--cert-name", domain, "-n", "--non-interactive",
		"--config-dir", cd, "--work-dir", wd, "--logs-dir", ld,
	}
	out, err := exec.Command(certbotBin(cfg), args...).CombinedOutput()
	if err != nil {
		return fmt.Errorf("certbot renew: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

// Delete certbot sertifika kaydını siler (dosyalar kaldırılır).
func Delete(cfg *config.Config, domain string) error {
	cd, wd, ld := LetsEncryptDirs(cfg.Paths.SSLDir)
	args := []string{
		"delete", "--cert-name", domain, "-n", "--non-interactive",
		"--config-dir", cd, "--work-dir", wd, "--logs-dir", ld,
	}
	_, _ = exec.Command(certbotBin(cfg), args...).CombinedOutput()
	return nil
}
