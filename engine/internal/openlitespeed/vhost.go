// Package openlitespeed — OpenLiteSpeed sanal host parçaları (vhconf + listener map + include indeksi).
// Kurulum: ana httpd_config.conf içinde (bir kez) şunları ekleyin:
//   include conf/conf.d/hostvim-ols-vhosts.conf
// HTTP listener bloğu içinde:  include conf/conf.d/hostvim-ols-http-maps.conf
// HTTPS listener bloğu içinde: include conf/conf.d/hostvim-ols-https-maps.conf
package openlitespeed

import (
	"bytes"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"text/template"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/nginx"
)

const (
	olsVhostsIndex   = "hostvim-ols-vhosts.conf"
	olsHTTPMapsFile  = "hostvim-ols-http-maps.conf"
	olsHTTPSMapsFile = "hostvim-ols-https-maps.conf"
)

func confRoot(cfg *config.Config) string {
	s := strings.TrimSpace(cfg.Hosting.OLSConfRoot)
	if s == "" {
		return "/usr/local/lsws"
	}
	return s
}

func ctrlBin(cfg *config.Config) string {
	s := strings.TrimSpace(cfg.Hosting.OLSCtrlPath)
	if s != "" {
		return s
	}
	p := filepath.Join(confRoot(cfg), "bin", "lswsctrl")
	if _, err := os.Stat(p); err == nil {
		return p
	}
	if path, err := exec.LookPath("lswsctrl"); err == nil {
		return path
	}
	return p
}

// VhostID sanal host adı (map + virtualhost bloğu); noktasız güvenli kimlik.
func VhostID(domain string) string {
	d := strings.ToLower(strings.TrimSpace(domain))
	d = strings.ReplaceAll(d, ".", "-")
	return "hostvim-" + d
}

func vhConfDir(cfg *config.Config, domain string) string {
	return filepath.Join(confRoot(cfg), "conf", "vhosts", "hostvim-"+strings.ToLower(strings.TrimSpace(domain)))
}

func vhConfPath(cfg *config.Config, domain string) string {
	return filepath.Join(vhConfDir(cfg, domain), "vhconf.conf")
}

func fragmentPath(cfg *config.Config, vhid string) string {
	return filepath.Join(confRoot(cfg), "conf", "conf.d", vhid+".conf")
}

func vhostsIndexPath(cfg *config.Config) string {
	return filepath.Join(confRoot(cfg), "conf", "conf.d", olsVhostsIndex)
}

func httpMapsPath(cfg *config.Config) string {
	return filepath.Join(confRoot(cfg), "conf", "conf.d", olsHTTPMapsFile)
}

func httpsMapsPath(cfg *config.Config) string {
	return filepath.Join(confRoot(cfg), "conf", "conf.d", olsHTTPSMapsFile)
}

func olsUDS(sock string) string {
	s := strings.TrimSpace(sock)
	s = strings.TrimPrefix(s, "unix:")
	s = strings.TrimPrefix(s, "unix://")
	s = strings.TrimSpace(s)
	s = strings.TrimPrefix(s, "/")
	return "uds://" + s
}

func buildAliasLine(primary string, aliases []string) string {
	primary = strings.ToLower(strings.TrimSpace(primary))
	if primary == "" {
		return ""
	}
	seen := map[string]struct{}{}
	var parts []string
	add := func(s string) {
		s = strings.ToLower(strings.TrimSpace(s))
		if s == "" || s == primary {
			return
		}
		if _, ok := seen[s]; ok {
			return
		}
		if !nginx.DomainSafe(s) {
			return
		}
		seen[s] = struct{}{}
		parts = append(parts, s)
	}
	add("www." + primary)
	for _, a := range aliases {
		al := strings.ToLower(strings.TrimSpace(a))
		if al == "" || al == primary {
			continue
		}
		if !nginx.DomainSafe(al) {
			continue
		}
		add(al)
		if !strings.HasPrefix(al, "www.") {
			add("www." + al)
		}
	}
	return strings.Join(parts, ", ")
}

func mapDomains(primary string, aliases []string) []string {
	line := strings.TrimSpace(nginx.BuildServerNamesLine(primary, aliases))
	if line == "" {
		return nil
	}
	return strings.Fields(line)
}

func readLines(path string) ([]string, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}
		return nil, err
	}
	var out []string
	for _, ln := range strings.Split(string(b), "\n") {
		out = append(out, ln)
	}
	if len(out) > 0 && out[len(out)-1] == "" {
		out = out[:len(out)-1]
	}
	return out, nil
}

func writeLines(path string, lines []string) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return err
	}
	body := strings.Join(lines, "\n")
	if body != "" {
		body += "\n"
	}
	return os.WriteFile(path, []byte(body), 0o644)
}

func upsertMapFile(path, vhid string, domains []string) error {
	lines, err := readLines(path)
	if err != nil {
		return err
	}
	if len(lines) == 0 {
		lines = []string{
			"# Hostvim — OpenLiteSpeed HTTP(S) listener map parçası.",
			"# Bu dosyayı ilgili listener bloğunun içine include edin (httpd_config.conf).",
		}
	}
	var kept []string
	prefix := "map " + vhid + " "
	for _, ln := range lines {
		t := strings.TrimSpace(ln)
		if t == "" || strings.HasPrefix(t, "#") {
			kept = append(kept, ln)
			continue
		}
		if strings.HasPrefix(t, prefix) || (strings.HasPrefix(t, "map ") && len(strings.Fields(t)) > 1 && strings.Fields(t)[1] == vhid) {
			continue
		}
		kept = append(kept, ln)
	}
	if len(domains) > 0 {
		kept = append(kept, prefix+strings.Join(domains, " "))
	}
	return writeLines(path, kept)
}

func removeMapLines(path, vhid string) error {
	lines, err := readLines(path)
	if err != nil || len(lines) == 0 {
		return err
	}
	var kept []string
	prefix := "map " + vhid + " "
	for _, ln := range lines {
		t := strings.TrimSpace(ln)
		if strings.HasPrefix(t, prefix) || (strings.HasPrefix(t, "map ") && len(strings.Fields(t)) > 1 && strings.Fields(t)[1] == vhid) {
			continue
		}
		kept = append(kept, ln)
	}
	return writeLines(path, kept)
}

func upsertIncludeIndex(cfg *config.Config, vhid string) error {
	idx := vhostsIndexPath(cfg)
	rel := "conf/conf.d/" + vhid + ".conf"
	line := "include " + rel
	lines, err := readLines(idx)
	if err != nil {
		return err
	}
	if len(lines) == 0 {
		lines = []string{
			"# Hostvim — sanal host parçaları. Ana httpd_config sonuna bir kez ekleyin:",
			"# include conf/conf.d/" + olsVhostsIndex,
		}
	}
	for _, ln := range lines {
		if strings.TrimSpace(ln) == line {
			return nil
		}
	}
	lines = append(lines, line)
	return writeLines(idx, lines)
}

func removeIncludeIndex(cfg *config.Config, vhid string) error {
	idx := vhostsIndexPath(cfg)
	rel := "conf/conf.d/" + vhid + ".conf"
	line := "include " + rel
	lines, err := readLines(idx)
	if err != nil || len(lines) == 0 {
		return err
	}
	var kept []string
	for _, ln := range lines {
		if strings.TrimSpace(ln) == line {
			continue
		}
		kept = append(kept, ln)
	}
	return writeLines(idx, kept)
}

const vhconfTpl = `# Hostvim — {{.Primary}}
docRoot                   {{.DocRoot}}
vhDomain                  {{.Primary}}
vhAliases                 {{.AliasLine}}

index  {
  useServer               0
  indexFiles              index.php, index.html
  autoIndex               0
}

extprocessor hostvim-fpm {
  type                    fcgi
  address                 {{.UDS}}
  maxConns                10
  initTimeout             60
  retryTimeout            0
  respBuffer              0
  autoStart               0
}

scripthandler  {
  add                     hostvim-fpm php
}

context /.well-known/acme-challenge {
  location                {{.DocRoot}}/.well-known/acme-challenge/
  allowBrowse             1
  addDefaultCharset       off
}

context / {
  type                    directory
  location                {{.DocRoot}}/
  allowBrowse             1
  addDefaultCharset       off
  indexFiles              index.php, index.html
  rewrite  {
    enable                  1
    autoLoadHtaccess        1
  }
}
{{if .RewriteHTTPS}}
rewrite  {
  enable                  1
  logLevel                0
  rules                   <<<END_HVIM_HTTPS_REDIRECT
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
END_HVIM_HTTPS_REDIRECT
}
{{end}}
{{if .SSLBlock}}
vhssl  {
  keyFile                 {{.SSLKey}}
  certFile                {{.SSLCert}}
  certChain               1
}
{{end}}

errorlog {{.ErrLog}} {
  logLevel                WARN
  rollingSize             10M
  useServer               0
}

accesslog {{.AccLog}} {
  rollingSize             10M
  compressArchive         1
  useServer               0
}
`

type vhTplData struct {
	Primary       string
	DocRoot       string
	AliasLine     string
	UDS           string
	RewriteHTTPS  bool
	SSLBlock      bool
	SSLKey        string
	SSLCert       string
	ErrLog        string
	AccLog        string
}

const fragTpl = `virtualhost {{.VhostID}} {
  vhRoot                  {{.VHRoot}}
  configFile              {{.ConfigRel}}
  allowSymbolLink         1
  enableScript            1
  restrained              0
}
`

// ApplyVhost vhconf + virtualhost parçası + map + include indeksini yazar; istenirse lswsctrl configtest/reload.
func ApplyVhost(cfg *config.Config, domain, docRoot, phpSocket, sslFullchain, sslPrivkey string, aliases []string) error {
	if !cfg.Hosting.OLSManageVhosts {
		return nil
	}
	domain = strings.ToLower(strings.TrimSpace(domain))
	if !nginx.DomainSafe(domain) {
		return fmt.Errorf("invalid domain for openlitespeed vhost")
	}
	if strings.Contains(docRoot, "..") {
		return fmt.Errorf("invalid document root")
	}
	docRoot = filepath.Clean(docRoot)

	sock := strings.TrimSpace(phpSocket)
	if sock == "" {
		sock = "/run/php/php8.2-fpm.sock"
	}
	chain := strings.TrimSpace(sslFullchain)
	key := strings.TrimSpace(sslPrivkey)
	useSSL := chain != "" && key != ""

	vhid := VhostID(domain)
	vhRoot := filepath.Join(cfg.Paths.WebRoot, domain, ".hostvim", "ols")
	if err := os.MkdirAll(filepath.Join(vhRoot, "logs"), 0o755); err != nil {
		return fmt.Errorf("ols vh root: %w", err)
	}

	aliasLine := buildAliasLine(domain, aliases)
	if aliasLine == "" {
		return fmt.Errorf("invalid openlitespeed aliases")
	}

	tpl, err := template.New("ols").Parse(vhconfTpl)
	if err != nil {
		return err
	}
	data := vhTplData{
		Primary:      domain,
		DocRoot:      docRoot,
		AliasLine:    aliasLine,
		UDS:          olsUDS(sock),
		RewriteHTTPS: useSSL,
		SSLBlock:     useSSL,
		SSLKey:       key,
		SSLCert:      chain,
		ErrLog:       filepath.Join(vhRoot, "logs", "error.log"),
		AccLog:       filepath.Join(vhRoot, "logs", "access.log"),
	}
	var vhbuf bytes.Buffer
	if err := tpl.Execute(&vhbuf, data); err != nil {
		return err
	}

	confDir := vhConfDir(cfg, domain)
	if err := os.MkdirAll(confDir, 0o755); err != nil {
		return err
	}
	vhPath := vhConfPath(cfg, domain)
	if err := os.MkdirAll(filepath.Join(confRoot(cfg), "conf", "conf.d"), 0o755); err != nil {
		return err
	}

	fp := fragmentPath(cfg, vhid)

	if err := os.WriteFile(vhPath, vhbuf.Bytes(), 0o644); err != nil {
		return fmt.Errorf("write ols vhconf: %w", err)
	}

	configRel := "conf/vhosts/hostvim-" + domain + "/vhconf.conf"
	ft, err := template.New("frag").Parse(fragTpl)
	if err != nil {
		_ = os.Remove(vhPath)
		return err
	}
	var fbuf bytes.Buffer
	if err := ft.Execute(&fbuf, map[string]string{
		"VhostID":   vhid,
		"VHRoot":    vhRoot,
		"ConfigRel": configRel,
	}); err != nil {
		_ = os.Remove(vhPath)
		return err
	}
	if err := os.WriteFile(fp, fbuf.Bytes(), 0o644); err != nil {
		_ = os.Remove(vhPath)
		return fmt.Errorf("write ols virtualhost fragment: %w", err)
	}

	if err := upsertIncludeIndex(cfg, vhid); err != nil {
		removeVhostFiles(cfg, domain)
		return fmt.Errorf("ols vhosts index: %w", err)
	}

	doms := mapDomains(domain, aliases)
	if len(doms) == 0 {
		removeVhostFiles(cfg, domain)
		return fmt.Errorf("invalid map domains")
	}
	if err := upsertMapFile(httpMapsPath(cfg), vhid, doms); err != nil {
		removeVhostFiles(cfg, domain)
		return fmt.Errorf("ols http maps: %w", err)
	}
	if useSSL {
		if err := upsertMapFile(httpsMapsPath(cfg), vhid, doms); err != nil {
			removeVhostFiles(cfg, domain)
			return fmt.Errorf("ols https maps: %w", err)
		}
	} else {
		_ = removeMapLines(httpsMapsPath(cfg), vhid)
	}

	if cfg.Hosting.OLSReloadAfterVhost {
		if err := olsConfigTest(cfg); err != nil {
			removeVhostFiles(cfg, domain)
			return err
		}
		if err := olsReload(cfg); err != nil {
			return err
		}
	}
	return nil
}

func olsConfigTest(cfg *config.Config) error {
	bin := ctrlBin(cfg)
	if _, err := os.Stat(bin); err != nil {
		return nil
	}
	out, err := exec.Command(bin, "configtest").CombinedOutput()
	if err != nil {
		return fmt.Errorf("openlitespeed configtest: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

func olsReload(cfg *config.Config) error {
	bin := ctrlBin(cfg)
	if _, err := os.Stat(bin); err != nil {
		if err := exec.Command("systemctl", "reload", "lshttpd").Run(); err == nil {
			return nil
		}
		_ = exec.Command("systemctl", "reload", "openlitespeed").Run()
		return nil
	}
	out, err := exec.Command(bin, "reload").CombinedOutput()
	if err != nil {
		out2, err2 := exec.Command(bin, "restart").CombinedOutput()
		if err2 != nil {
			return fmt.Errorf("openlitespeed reload: %w — %s; restart: %w — %s", err, strings.TrimSpace(string(out)), err2, strings.TrimSpace(string(out2)))
		}
	}
	return nil
}

// RemoveVhost yönetim açıksa dosyaları ve map girdilerini kaldırır.
func RemoveVhost(cfg *config.Config, domain string) error {
	if !cfg.Hosting.OLSManageVhosts {
		return nil
	}
	removeVhostFiles(cfg, domain)
	if cfg.Hosting.OLSReloadAfterVhost {
		_ = olsReload(cfg)
	}
	return nil
}

func removeVhostFiles(cfg *config.Config, domain string) {
	domain = strings.ToLower(strings.TrimSpace(domain))
	if domain == "" || strings.Contains(domain, "..") {
		return
	}
	vhid := VhostID(domain)
	_ = removeMapLines(httpMapsPath(cfg), vhid)
	_ = removeMapLines(httpsMapsPath(cfg), vhid)
	_ = removeIncludeIndex(cfg, vhid)
	_ = os.Remove(fragmentPath(cfg, vhid))
	_ = os.RemoveAll(vhConfDir(cfg, domain))
}

// RemoveVhostBestEffort site silme / tip değişimi; bayrak kapalı olsa da temizler.
func RemoveVhostBestEffort(cfg *config.Config, domain string) {
	removeVhostFiles(cfg, domain)
	if cfg.Hosting.OLSManageVhosts && cfg.Hosting.OLSReloadAfterVhost {
		_ = olsReload(cfg)
	}
}
