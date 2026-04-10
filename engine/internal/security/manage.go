package security

import (
	"errors"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"

	"hostvim/engine/internal/config"
)

const helperPath = "/usr/local/sbin/hostvim-security"

type Overview struct {
	Fail2banEnabled bool   `json:"fail2ban_enabled"`
	ModsecEnabled   bool   `json:"modsec_enabled"`
	ClamavEnabled   bool   `json:"clamav_enabled"`
	ClamavLastScan  string `json:"clamav_last_scan,omitempty"`
}

type NginxRateLimitProfile struct {
	Profile string `json:"profile"`
	Limits  string `json:"limits"`
}

type ModSecSiteRule struct {
	ID     string `json:"id"`
	Domain string `json:"domain"`
	Mode   string `json:"mode"`
	Target string `json:"target"`
}

func run(args ...string) (string, error) {
	out, err := exec.Command("sudo", append([]string{"-n", helperPath}, args...)...).CombinedOutput()
	s := strings.TrimSpace(string(out))
	if err != nil {
		return s, fmt.Errorf("%w: %s", err, s)
	}
	return s, nil
}

func EnabledStatus(name string) (bool, error) {
	out, err := run(name + "-status")
	if err != nil {
		return false, err
	}
	return strings.EqualFold(strings.TrimSpace(out), "enabled"), nil
}

func SetEnabled(name string, enabled bool) (bool, error) {
	state := "off"
	if enabled {
		state = "on"
	}
	out, err := run(name+"-set", state)
	if err != nil {
		return false, err
	}
	return strings.EqualFold(strings.TrimSpace(out), "enabled"), nil
}

// RunClamAVScan çalıştırır. clamscan virüs bulunca çıkış kodu 1 döner; bu normal başarıdır.
func RunClamAVScan(cfg *config.Config, target string) (string, error) {
	t := strings.TrimSpace(target)
	if t == "" {
		t = "/var/www"
	}
	if !filepath.IsAbs(t) {
		return "", fmt.Errorf("target must be absolute path")
	}
	abs := filepath.Clean(t)
	if err := VerifyClamAllowedDir(cfg, abs); err != nil {
		return "", err
	}
	cmd := exec.Command("sudo", "-n", helperPath, "clamav-scan", abs)
	out, err := cmd.CombinedOutput()
	s := strings.TrimSpace(string(out))
	if err != nil {
		var ee *exec.ExitError
		if errors.As(err, &ee) && ee.ExitCode() == 1 {
			return s, nil
		}
		if s != "" {
			return s, fmt.Errorf("%w: %s", err, s)
		}
		return s, err
	}
	return s, nil
}

// QuarantineResult taşınan dosyanın kaynak ve karantina yolu.
type QuarantineResult struct {
	Source string `json:"source"`
	Dest   string `json:"dest"`
}

// QuarantineClamFiles şüpheli dosyaları hostvim-quarantine dizinine taşır (silme değil).
func QuarantineClamFiles(cfg *config.Config, paths []string) ([]QuarantineResult, error) {
	if len(paths) > MaxQuarantinePaths {
		return nil, fmt.Errorf("too many paths (max %d)", MaxQuarantinePaths)
	}
	results := make([]QuarantineResult, 0, len(paths))
	for _, p := range paths {
		p = strings.TrimSpace(p)
		if p == "" {
			continue
		}
		if !filepath.IsAbs(p) {
			return nil, fmt.Errorf("path must be absolute: %s", p)
		}
		p = filepath.Clean(p)
		if err := VerifyClamAllowedFile(cfg, p); err != nil {
			return nil, fmt.Errorf("%s: %w", p, err)
		}
		line, err := run("clamav-quarantine", p)
		if err != nil {
			return nil, fmt.Errorf("%s: %w", p, err)
		}
		src, dest, ok := parseQuarantineLine(line)
		if !ok {
			return nil, fmt.Errorf("%s: unexpected quarantine output: %s", p, line)
		}
		results = append(results, QuarantineResult{Source: src, Dest: dest})
	}
	return results, nil
}

func parseQuarantineLine(line string) (src, dest string, ok bool) {
	line = strings.TrimSpace(line)
	const pref = "ok "
	if !strings.HasPrefix(line, pref) {
		return "", "", false
	}
	rest := strings.TrimSpace(strings.TrimPrefix(line, pref))
	parts := strings.SplitN(rest, " -> ", 2)
	if len(parts) != 2 {
		return "", "", false
	}
	return strings.TrimSpace(parts[0]), strings.TrimSpace(parts[1]), true
}

// RunMaldetScan Linux Malware Detect ile tarama (kurulu değilse hata).
func RunMaldetScan(cfg *config.Config, target string) (string, error) {
	t := strings.TrimSpace(target)
	if t == "" {
		t = "/var/www"
	}
	if !filepath.IsAbs(t) {
		return "", fmt.Errorf("target must be absolute path")
	}
	abs := filepath.Clean(t)
	if err := VerifyClamAllowedDir(cfg, abs); err != nil {
		return "", err
	}
	cmd := exec.Command("sudo", "-n", helperPath, "maldet-scan", abs)
	out, err := cmd.CombinedOutput()
	s := strings.TrimSpace(string(out))
	if err != nil {
		var ee *exec.ExitError
		// maldet/clamscan benzeri: bulgu/bitmap döndüğünde 1 olabilir; çıktı yine rapordur.
		if errors.As(err, &ee) && ee.ExitCode() == 1 {
			return s, nil
		}
		if s != "" {
			return s, fmt.Errorf("%w: %s", err, s)
		}
		return s, err
	}
	return s, nil
}

func Fail2banJailGet() (bantime, findtime, maxretry int, err error) {
	out, err := run("fail2ban-jail-get")
	if err != nil {
		return 0, 0, 0, err
	}
	parts := strings.Fields(strings.TrimSpace(out))
	vals := map[string]int{
		"bantime":  600,
		"findtime": 600,
		"maxretry": 5,
	}
	for _, p := range parts {
		kv := strings.SplitN(p, "=", 2)
		if len(kv) != 2 {
			continue
		}
		if n, conv := strconv.Atoi(strings.TrimSpace(kv[1])); conv == nil {
			vals[strings.TrimSpace(kv[0])] = n
		}
	}
	return vals["bantime"], vals["findtime"], vals["maxretry"], nil
}

func Fail2banJailSet(bantime, findtime, maxretry int) error {
	if bantime < 60 || bantime > 604800 || findtime < 60 || findtime > 604800 || maxretry < 1 || maxretry > 20 {
		return fmt.Errorf("invalid fail2ban jail settings")
	}
	_, err := run("fail2ban-jail-set", strconv.Itoa(bantime), strconv.Itoa(findtime), strconv.Itoa(maxretry))
	return err
}

type ApacheModuleRow struct {
	Name    string `json:"name"`
	Enabled bool   `json:"enabled"`
}

func ApacheModulesList() ([]ApacheModuleRow, error) {
	out, err := run("apache-modules-list")
	if err != nil {
		return nil, err
	}
	lines := strings.Split(strings.TrimSpace(out), "\n")
	rows := make([]ApacheModuleRow, 0, len(lines))
	for _, ln := range lines {
		p := strings.Fields(strings.TrimSpace(ln))
		if len(p) < 2 {
			continue
		}
		rows = append(rows, ApacheModuleRow{
			Name:    p[0],
			Enabled: strings.EqualFold(p[1], "enabled"),
		})
	}
	return rows, nil
}

func ApacheModuleSet(name string, enabled bool) (bool, error) {
	state := "off"
	if enabled {
		state = "on"
	}
	out, err := run("apache-module-set", strings.TrimSpace(name), state)
	if err != nil {
		return false, err
	}
	return strings.EqualFold(strings.TrimSpace(out), "enabled"), nil
}

func NginxConfigGet(scope string) (string, error) {
	if scope == "" {
		scope = "main"
	}
	return run("nginx-config-get", scope)
}

func NginxConfigSet(scope, content string, reload bool) error {
	if scope == "" {
		scope = "main"
	}
	tmp, err := os.CreateTemp("", "hostvim-nginx-*.conf")
	if err != nil {
		return err
	}
	tmpPath := tmp.Name()
	defer os.Remove(tmpPath)
	if _, err := tmp.WriteString(content); err != nil {
		_ = tmp.Close()
		return err
	}
	_ = tmp.Close()

	reloadArg := "0"
	if reload {
		reloadArg = "1"
	}
	_, err = run("nginx-config-set", scope, tmpPath, reloadArg)
	return err
}

func InstallFail2ban() error {
	_, err := run("fail2ban-install")
	return err
}

func InstallModSecurity() error {
	_, err := run("modsec-install")
	return err
}

func NginxRateLimitProfileGet() (NginxRateLimitProfile, error) {
	out, err := run("nginx-rate-limit-get")
	if err != nil {
		return NginxRateLimitProfile{}, err
	}
	prof := NginxRateLimitProfile{Profile: "none", Limits: ""}
	for _, line := range strings.Split(strings.TrimSpace(out), "\n") {
		kv := strings.SplitN(strings.TrimSpace(line), "=", 2)
		if len(kv) != 2 {
			continue
		}
		switch strings.TrimSpace(kv[0]) {
		case "profile":
			prof.Profile = strings.TrimSpace(kv[1])
		case "limits":
			prof.Limits = strings.TrimSpace(kv[1])
		}
	}
	return prof, nil
}

func NginxRateLimitProfileSet(profile string) (NginxRateLimitProfile, error) {
	if _, err := run("nginx-rate-limit-set", strings.TrimSpace(profile)); err != nil {
		return NginxRateLimitProfile{}, err
	}
	return NginxRateLimitProfileGet()
}

func ModSecSiteRuleAdd(domain, mode, target string) (ModSecSiteRule, error) {
	out, err := run("modsec-site-rule-add", strings.TrimSpace(domain), strings.TrimSpace(mode), strings.TrimSpace(target))
	if err != nil {
		return ModSecSiteRule{}, err
	}
	r := ModSecSiteRule{}
	for _, part := range strings.Fields(strings.TrimSpace(out)) {
		kv := strings.SplitN(part, "=", 2)
		if len(kv) != 2 {
			continue
		}
		switch kv[0] {
		case "id":
			r.ID = kv[1]
		case "domain":
			r.Domain = kv[1]
		case "mode":
			r.Mode = kv[1]
		case "target":
			r.Target = kv[1]
		}
	}
	return r, nil
}

func ModSecSiteRuleList() ([]ModSecSiteRule, error) {
	out, err := run("modsec-site-rule-list")
	if err != nil {
		return nil, err
	}
	out = strings.TrimSpace(out)
	if out == "" {
		return []ModSecSiteRule{}, nil
	}
	rows := []ModSecSiteRule{}
	for _, line := range strings.Split(out, "\n") {
		p := strings.Split(line, "\t")
		if len(p) < 4 {
			continue
		}
		rows = append(rows, ModSecSiteRule{
			ID:     strings.TrimSpace(p[0]),
			Domain: strings.TrimSpace(p[1]),
			Mode:   strings.TrimSpace(p[2]),
			Target: strings.TrimSpace(p[3]),
		})
	}
	return rows, nil
}

func ModSecSiteRuleRemove(id string) error {
	_, err := run("modsec-site-rule-remove", strings.TrimSpace(id))
	return err
}
