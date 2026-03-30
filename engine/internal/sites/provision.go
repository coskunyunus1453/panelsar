package sites

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

func Provision(webRoot, domain, phpVersion, serverType string) (documentRoot string, err error) {
	if domain == "" || domain == "." || strings.Contains(domain, "..") {
		return "", fmt.Errorf("invalid domain")
	}
	if phpVersion == "" {
		phpVersion = "8.2"
	}
	oldMeta, _ := ReadSiteMeta(webRoot, domain)
	st := strings.ToLower(strings.TrimSpace(serverType))
	if st == "" && oldMeta != nil && oldMeta.ServerType != "" {
		st = strings.ToLower(strings.TrimSpace(oldMeta.ServerType))
	}
	if st != "apache" {
		st = "nginx"
	}
	sslEn := false
	if oldMeta != nil {
		sslEn = oldMeta.SSLEnabled
	}
	docRoot := filepath.Join(webRoot, domain, "public_html")
	if err := os.MkdirAll(docRoot, 0o755); err != nil {
		return "", err
	}
	html := fmt.Sprintf(`<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>%s</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;max-width:42rem;margin:3rem auto;padding:0 1.25rem;line-height:1.5;color:#1e293b}
h1{color:#2563eb}
</style>
</head>
<body>
<h1>%s</h1>
<p>Bu site <strong>Panelsar</strong> kontrol paneli ile oluşturuldu.</p>
<p>Belge kökü: <code>%s</code></p>
<p>PHP (panel ayarı): <strong>%s</strong></p>
</body>
</html>`, domain, domain, docRoot, phpVersion)
	index := filepath.Join(docRoot, "index.html")
	if err := os.WriteFile(index, []byte(html), 0o644); err != nil {
		return "", err
	}
	if err := WriteSiteMeta(webRoot, domain, &SiteMeta{
		PHPVersion:   phpVersion,
		DocumentRoot: docRoot,
		ServerType:   st,
		SSLEnabled:   sslEn,
	}); err != nil {
		return "", err
	}
	return docRoot, nil
}

func Remove(webRoot, domain string) error {
	if domain == "" || strings.Contains(domain, "..") {
		return fmt.Errorf("invalid domain")
	}
	return os.RemoveAll(filepath.Join(webRoot, domain))
}

// ProvisionSubdomain parent ör. example.com altında pathSegment ör. blog → webRoot/example.com/blog/public_html
func ProvisionSubdomain(webRoot, parentDomain, hostname, pathSegment, phpVersion, serverType string) (documentRoot string, err error) {
	parentDomain = strings.ToLower(strings.TrimSpace(parentDomain))
	hostname = strings.ToLower(strings.TrimSpace(hostname))
	pathSegment = strings.TrimSpace(pathSegment)
	if parentDomain == "" || strings.Contains(parentDomain, "..") {
		return "", fmt.Errorf("invalid parent domain")
	}
	if hostname == "" || strings.Contains(hostname, "..") {
		return "", fmt.Errorf("invalid hostname")
	}
	if pathSegment == "" || strings.Contains(pathSegment, "/") || strings.Contains(pathSegment, "..") {
		return "", fmt.Errorf("invalid path segment")
	}
	if phpVersion == "" {
		phpVersion = "8.2"
	}
	st := strings.ToLower(strings.TrimSpace(serverType))
	if st != "apache" {
		st = "nginx"
	}
	base := filepath.Join(webRoot, parentDomain, pathSegment)
	docRoot := filepath.Join(base, "public_html")
	if err := os.MkdirAll(docRoot, 0o755); err != nil {
		return "", err
	}
	html := fmt.Sprintf(`<!DOCTYPE html>
<html lang="tr">
<head><meta charset="utf-8"><title>%s</title></head>
<body><h1>%s</h1><p>Alt alan — Panelsar</p><p><code>%s</code></p></body>
</html>`, hostname, hostname, docRoot)
	if err := os.WriteFile(filepath.Join(docRoot, "index.html"), []byte(html), 0o644); err != nil {
		return "", err
	}
	meta := &SiteMeta{
		Hostname:     hostname,
		PHPVersion:   phpVersion,
		DocumentRoot: docRoot,
		ServerType:   st,
		SSLEnabled:   false,
	}
	subMetaDir := filepath.Join(webRoot, parentDomain, ".panelsar", "subdomains")
	if err := os.MkdirAll(subMetaDir, 0o750); err != nil {
		return "", err
	}
	metaPath := filepath.Join(subMetaDir, pathSegment+".json")
	b, err := json.MarshalIndent(meta, "", "  ")
	if err != nil {
		return "", err
	}
	if err := os.WriteFile(metaPath, b, 0o640); err != nil {
		return "", err
	}
	return docRoot, nil
}

// ReadSubdomainMeta alt site meta json (silmeden önce okumak için).
func ReadSubdomainMeta(webRoot, parentDomain, pathSegment string) (*SiteMeta, error) {
	parentDomain = strings.ToLower(strings.TrimSpace(parentDomain))
	pathSegment = strings.TrimSpace(pathSegment)
	if parentDomain == "" || pathSegment == "" {
		return nil, nil
	}
	metaPath := filepath.Join(webRoot, parentDomain, ".panelsar", "subdomains", pathSegment+".json")
	b, err := os.ReadFile(metaPath)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}
		return nil, err
	}
	var m SiteMeta
	if err := json.Unmarshal(b, &m); err != nil {
		return nil, err
	}
	return &m, nil
}

// RemoveSubdomain alt dizini ve meta dosyasını siler (pathSegment = klasör adı, örn. blog).
func RemoveSubdomain(webRoot, parentDomain, pathSegment string) (hostname string, err error) {
	parentDomain = strings.ToLower(strings.TrimSpace(parentDomain))
	pathSegment = strings.TrimSpace(pathSegment)
	if parentDomain == "" || pathSegment == "" || strings.Contains(parentDomain, "..") {
		return "", fmt.Errorf("invalid parent or path segment")
	}
	if strings.Contains(pathSegment, "/") || strings.Contains(pathSegment, "..") {
		return "", fmt.Errorf("invalid path segment")
	}
	metaPath := filepath.Join(webRoot, parentDomain, ".panelsar", "subdomains", pathSegment+".json")
	b, rerr := os.ReadFile(metaPath)
	if rerr == nil {
		var m SiteMeta
		if json.Unmarshal(b, &m) == nil && strings.TrimSpace(m.Hostname) != "" {
			hostname = strings.ToLower(strings.TrimSpace(m.Hostname))
		}
	}
	base := filepath.Join(webRoot, parentDomain, pathSegment)
	if err := os.RemoveAll(base); err != nil {
		return hostname, err
	}
	_ = os.Remove(metaPath)
	return hostname, nil
}

func ListDomains(webRoot string) ([]string, error) {
	entries, err := os.ReadDir(webRoot)
	if err != nil {
		if os.IsNotExist(err) {
			if mk := os.MkdirAll(webRoot, 0o755); mk != nil {
				return nil, mk
			}
			return []string{}, nil
		}
		return nil, err
	}
	var out []string
	for _, e := range entries {
		if e.IsDir() && !strings.HasPrefix(e.Name(), ".") {
			out = append(out, e.Name())
		}
	}
	return out, nil
}
