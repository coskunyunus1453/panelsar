package security

import (
	"bufio"
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/nginx"
)

const clamScanMaxFilesInResponse = 500

// MaxQuarantinePaths tek istekte taşınabilecek dosya üst sınırı.
const MaxQuarantinePaths = 50

func pathUnderPrefix(child, parent string) bool {
	child = filepath.Clean(child)
	parent = filepath.Clean(parent)
	rel, err := filepath.Rel(parent, child)
	if err != nil {
		return false
	}
	return rel == "." || !strings.HasPrefix(rel, "..")
}

// VerifyClamAllowedDir üretim tarama hedefi için yol politikası (/var/www, /home, paths.web_root).
func VerifyClamAllowedDir(cfg *config.Config, absDir string) error {
	absDir = filepath.Clean(absDir)
	fi, err := os.Stat(absDir)
	if err != nil {
		return err
	}
	if !fi.IsDir() {
		return fmt.Errorf("not a directory")
	}
	wr := strings.TrimSpace(cfg.Paths.WebRoot)
	if wr != "" {
		w := filepath.Clean(wr)
		if filepath.IsAbs(w) && pathUnderPrefix(absDir, w) {
			return nil
		}
	}
	for _, root := range []string{"/var/www", "/home"} {
		r := filepath.Clean(root)
		if pathUnderPrefix(absDir, r) {
			return nil
		}
	}
	return fmt.Errorf("scan path not allowed")
}

// VerifyClamAllowedFile karantina için: düzenli dosya, symlink yok, izinli ağaçta.
func VerifyClamAllowedFile(cfg *config.Config, absFile string) error {
	absFile = filepath.Clean(absFile)
	fi, err := os.Lstat(absFile)
	if err != nil {
		return err
	}
	if fi.Mode()&os.ModeSymlink != 0 {
		return fmt.Errorf("symlinks not allowed")
	}
	if !fi.Mode().IsRegular() {
		return fmt.Errorf("not a regular file")
	}
	if err := VerifyClamAllowedDir(cfg, filepath.Dir(absFile)); err != nil {
		return err
	}
	return nil
}

// ResolveClamScanTarget domain verilmişse site dizinini, yoksa target dizinini döndürür.
func ResolveClamScanTarget(cfg *config.Config, target, domain string) (string, error) {
	domain = strings.ToLower(strings.TrimSpace(domain))
	if domain != "" {
		if strings.Contains(domain, "..") || !nginx.DomainSafe(domain) {
			return "", fmt.Errorf("invalid domain")
		}
		wr := strings.TrimSpace(cfg.Paths.WebRoot)
		if wr == "" || wr == "." {
			return "", fmt.Errorf("paths.web_root is not set")
		}
		w := filepath.Clean(wr)
		if !filepath.IsAbs(w) {
			return "", fmt.Errorf("paths.web_root must be absolute")
		}
		site := filepath.Join(w, domain)
		site = filepath.Clean(site)
		if !pathUnderPrefix(site, w) {
			return "", fmt.Errorf("invalid site path")
		}
		return site, VerifyClamAllowedDir(cfg, site)
	}
	t := strings.TrimSpace(target)
	if t == "" {
		t = "/var/www"
	}
	if !filepath.IsAbs(t) {
		return "", fmt.Errorf("target must be absolute path")
	}
	abs := filepath.Clean(t)
	return abs, VerifyClamAllowedDir(cfg, abs)
}

// ClamScanSummary, tarama çıktısından güvenli özet (sadece rapor; silme yok).
type ClamScanSummary struct {
	InfectedCount int      `json:"infected_count"`
	InfectedFiles []string `json:"infected_files,omitempty"`
	Truncated     bool     `json:"infected_files_truncated,omitempty"`
}

// SummarizeClamScanOutput clamscan stdout satırlarını ayrıştırır.
// Satır biçimi: path: imza FOUND
func SummarizeClamScanOutput(out string) ClamScanSummary {
	s := ClamScanSummary{InfectedFiles: []string{}}
	if strings.TrimSpace(out) == "" {
		return s
	}
	sc := bufio.NewScanner(strings.NewReader(out))
	const maxScanToken = 10 << 20 // 10MB satır sınırı
	buf := make([]byte, 0, 64*1024)
	sc.Buffer(buf, maxScanToken)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" {
			continue
		}
		if !strings.HasSuffix(line, " FOUND") {
			continue
		}
		// "path: VirusName FOUND"
		idx := strings.LastIndex(line, ": ")
		if idx <= 0 {
			continue
		}
		path := strings.TrimSpace(line[:idx])
		if path == "" {
			continue
		}
		s.InfectedCount++
		if len(s.InfectedFiles) < clamScanMaxFilesInResponse {
			s.InfectedFiles = append(s.InfectedFiles, path)
		} else {
			s.Truncated = true
		}
	}
	return s
}
