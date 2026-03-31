package security

import (
	"fmt"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
)

const helperPath = "/usr/local/sbin/panelsar-security"

type Overview struct {
	Fail2banEnabled bool   `json:"fail2ban_enabled"`
	ModsecEnabled   bool   `json:"modsec_enabled"`
	ClamavEnabled   bool   `json:"clamav_enabled"`
	ClamavLastScan  string `json:"clamav_last_scan,omitempty"`
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

func RunClamAVScan(target string) (string, error) {
	t := strings.TrimSpace(target)
	if t == "" {
		t = "/var/www"
	}
	if !filepath.IsAbs(t) {
		return "", fmt.Errorf("target must be absolute path")
	}
	return run("clamav-scan", t)
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
