package sites

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
)

// SiteMeta — alan adı başına engine tarafından saklanan küçük durum (silme / PHP değişimi / SSL için).
type SiteMeta struct {
	PHPVersion   string   `json:"php_version"`
	DocumentRoot string   `json:"document_root"`
	ServerType   string   `json:"server_type"`
	SSLEnabled   bool     `json:"ssl_enabled"`
	// PerformanceMode: boş = kapalı; "standard" = gzip + statik cache header preset (nginx vhost).
	PerformanceMode string `json:"performance_mode,omitempty"`
	Aliases      []string `json:"aliases,omitempty"` // Örn. example.net — aynı belge kökü, vhost server_name
	Hostname     string   `json:"hostname,omitempty"` // Alt site meta dosyalarında FQDN (silme / vhost için)
}

const metaDirName = ".hostvim"
const legacyMetaDirName = ".panelsar"

func metaDir(webRoot, domain string) string {
	return filepath.Join(webRoot, domain, metaDirName)
}

func legacySiteMetaDir(webRoot, domain string) string {
	return filepath.Join(webRoot, domain, legacyMetaDirName)
}

func metaFile(webRoot, domain string) string {
	return filepath.Join(metaDir(webRoot, domain), "site.json")
}

func legacyMetaFile(webRoot, domain string) string {
	return filepath.Join(legacySiteMetaDir(webRoot, domain), "site.json")
}

// ReadSiteMeta mevcut site meta verisini okur; yoksa nil, nil döner.
func ReadSiteMeta(webRoot, domain string) (*SiteMeta, error) {
	if domain == "" || strings.Contains(domain, "..") {
		return nil, nil
	}
	p := metaFile(webRoot, domain)
	b, err := os.ReadFile(p)
	if err != nil {
		if os.IsNotExist(err) {
			p = legacyMetaFile(webRoot, domain)
			b, err = os.ReadFile(p)
		}
		if err != nil {
			if os.IsNotExist(err) {
				return nil, nil
			}
			return nil, err
		}
	}
	var m SiteMeta
	if err := json.Unmarshal(b, &m); err != nil {
		return nil, err
	}
	return &m, nil
}

// WriteSiteMeta .hostvim/site.json yazar.
func WriteSiteMeta(webRoot, domain string, m *SiteMeta) error {
	if domain == "" || strings.Contains(domain, "..") {
		return nil
	}
	dir := metaDir(webRoot, domain)
	if err := os.MkdirAll(dir, 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(m, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(metaFile(webRoot, domain), b, 0o640)
}
