package ssl

import (
	"crypto/tls"
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

// clearChallengeWorkDir — yarım kalmış HTTP-01 / webroot state.
func clearChallengeWorkDir(workDir string) {
	for _, sub := range []string{"http-01", "webroot", "manual", "standalone"} {
		_ = os.RemoveAll(filepath.Join(workDir, sub))
	}
}

// resetCertbotWorkDir — certbot work/ altındaki tüm geçici ACME durumunu siler.
// Let's Encrypt "No such authorization" / malformed order kalıntılarında en güvenilir temizlik (config/ korunur).
func resetCertbotWorkDir(workDir string) error {
	if err := os.RemoveAll(workDir); err != nil {
		return err
	}
	return os.MkdirAll(workDir, 0o700)
}

// certbotRecoverableStaleOrderError — logda bazen yalnızca urn:malformed görünür; retry şart.
func certbotRecoverableStaleOrderError(out []byte) bool {
	s := strings.ToLower(string(out))
	if strings.Contains(s, "no such authorization") {
		return true
	}
	if strings.Contains(s, "urn:ietf:params:acme:error:malformed") {
		return true
	}
	return false
}

func runCertbot(cfg *config.Config, args []string) ([]byte, error) {
	out, err := exec.Command(certbotBin(cfg), args...).CombinedOutput()
	return out, err
}

// Issue webroot doğrulaması ile sertifika alır. İlk SAN birincil domain'dir.
// Issue öncesi mevcut cert satırı silinir; böylece başarısız denemelerden kalan ACME durumu temizlenir.
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

	// Canlı PEM yoksa: bozuk satır + eski ACME work state (malformed / no such authorization).
	chain, key := LiveCertPaths(cfg, domain)
	hasLive := CertsExist(chain, key)
	if !hasLive {
		_ = Delete(cfg, domain)
		if err := resetCertbotWorkDir(wd); err != nil {
			return fmt.Errorf("certbot work dizini sıfırlanamadı: %w", err)
		}
	} else {
		clearChallengeWorkDir(wd)
	}

	args := []string{
		"certonly", "--webroot", "-w", webroot,
		"--preferred-challenges", "http",
		"--email", email,
		"--agree-tos", "-n", "--non-interactive",
		"--config-dir", cd, "--work-dir", wd, "--logs-dir", ld,
	}
	args = append(args, "-d", domain)
	if cfg.Hosting.LetsEncryptIncludeWww {
		args = append(args, "-d", "www."+domain)
	}
	if cfg.Hosting.LetsEncryptStaging {
		args = append(args, "--staging")
	}

	out, err := runCertbot(cfg, args)
	if err == nil {
		return nil
	}
	if certbotRecoverableStaleOrderError(out) {
		_ = Delete(cfg, domain)
		if errW := resetCertbotWorkDir(wd); errW != nil {
			return fmt.Errorf("certbot (retry öncesi work sıfırlama): %w — ilk çıktı: %s", errW, strings.TrimSpace(string(out)))
		}
		out2, err2 := runCertbot(cfg, args)
		if err2 != nil {
			return fmt.Errorf("certbot: %w — %s", err2, strings.TrimSpace(string(out2)))
		}
		return nil
	}
	return fmt.Errorf("certbot: %w — %s", err, strings.TrimSpace(string(out)))
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
	PurgeLocalCertTree(cfg, domain)
	return nil
}

// PurgeLocalCertTree certbot live/archive/renewal altındaki cert-name ile eşleşen kalıntıları siler (certbot başarısız kalsa bile).
func PurgeLocalCertTree(cfg *config.Config, certName string) {
	certName = strings.ToLower(strings.TrimSpace(certName))
	if certName == "" {
		return
	}
	cd, _, _ := LetsEncryptDirs(cfg.Paths.SSLDir)
	_ = os.RemoveAll(filepath.Join(cd, "live", certName))
	_ = os.Remove(filepath.Join(cd, "renewal", certName+".conf"))
	ar := filepath.Join(cd, "archive")
	if entries, err := os.ReadDir(ar); err == nil {
		for _, e := range entries {
			name := e.Name()
			if name == certName || strings.HasPrefix(name, certName+"-") {
				_ = os.RemoveAll(filepath.Join(ar, name))
			}
		}
	}
}

// UploadManual cert ve private key PEM içeriklerini domain live dizinine yazar.
func UploadManual(cfg *config.Config, domain, certPEM, keyPEM string) error {
	domain = strings.ToLower(strings.TrimSpace(domain))
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	certPEM = strings.TrimSpace(certPEM)
	keyPEM = strings.TrimSpace(keyPEM)
	if certPEM == "" || keyPEM == "" {
		return fmt.Errorf("certificate and private_key required")
	}
	if _, err := tls.X509KeyPair([]byte(certPEM), []byte(keyPEM)); err != nil {
		return fmt.Errorf("invalid certificate/key pair: %w", err)
	}
	chain, key := LiveCertPaths(cfg, domain)
	liveDir := filepath.Dir(chain)
	if err := os.MkdirAll(liveDir, 0o700); err != nil {
		return err
	}
	if err := os.WriteFile(chain, []byte(certPEM+"\n"), 0o644); err != nil {
		return err
	}
	if err := os.WriteFile(key, []byte(keyPEM+"\n"), 0o600); err != nil {
		return err
	}
	return nil
}
