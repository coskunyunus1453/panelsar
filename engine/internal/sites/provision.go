package sites

import (
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
