package security

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"hostvim/engine/internal/config"
)

type fimEntry struct {
	Path    string `json:"path"`
	Size    int64  `json:"size"`
	Mode    uint32 `json:"mode"`
	ModUnix int64  `json:"mod_unix"`
	Sha256  string `json:"sha256"`
}

type FimDiff struct {
	Path     string `json:"path"`
	Type     string `json:"type"` // added, removed, modified
	Severity string `json:"severity"`
}

type FimAlert struct {
	ID        string `json:"id"`
	Kind      string `json:"kind"`
	Severity  string `json:"severity"`
	Message   string `json:"message"`
	Path      string `json:"path,omitempty"`
	CreatedAt string `json:"created_at"`
}

type FimStatus struct {
	BaselineExists bool       `json:"baseline_exists"`
	LastBaselineAt string     `json:"last_baseline_at,omitempty"`
	LastScanAt     string     `json:"last_scan_at,omitempty"`
	ChangedCount   int        `json:"changed_count"`
	CriticalCount  int        `json:"critical_count"`
	Alerts         []FimAlert `json:"alerts"`
}

const fimAlertPersistCap = 2000

func fimStateDir(cfg *config.Config) string {
	base := filepath.Clean(cfg.Paths.WebRoot)
	return filepath.Join(filepath.Dir(base), "engine-state", "security", "fim")
}

func fimBaselinePath(cfg *config.Config) string {
	return filepath.Join(fimStateDir(cfg), "baseline.json")
}

func fimStatusPath(cfg *config.Config) string {
	return filepath.Join(fimStateDir(cfg), "status.json")
}

func fimAlertsPath(cfg *config.Config) string {
	return filepath.Join(fimStateDir(cfg), "alerts.json")
}

func fimNow() string {
	return time.Now().UTC().Format(time.RFC3339)
}

func fimCriticalPath(path string) bool {
	p := strings.ToLower(strings.TrimSpace(path))
	return strings.HasSuffix(p, ".env") ||
		strings.HasSuffix(p, "wp-config.php") ||
		strings.Contains(p, "/.ssh/") ||
		strings.Contains(p, "/.git/") ||
		strings.HasSuffix(p, ".htaccess")
}

func fimIsExcluded(path string) bool {
	p := strings.ToLower(path)
	excluded := []string{
		"/cache/",
		"/logs/",
		"/log/",
		"/tmp/",
		"/vendor/",
		"/node_modules/",
		"/storage/framework/cache/",
	}
	for _, x := range excluded {
		if strings.Contains(p, x) {
			return true
		}
	}
	return false
}

func fimHashFile(path string) (string, error) {
	f, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer f.Close()
	h := sha256.New()
	if _, err := io.Copy(h, f); err != nil {
		return "", err
	}
	return hex.EncodeToString(h.Sum(nil)), nil
}

func fimWriteJSON(path string, v interface{}) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(v, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0o640)
}

func fimReadJSON(path string, out interface{}) error {
	b, err := os.ReadFile(path)
	if err != nil {
		return err
	}
	return json.Unmarshal(b, out)
}

func fimScanSnapshot(cfg *config.Config) (map[string]fimEntry, error) {
	webRoot := filepath.Clean(cfg.Paths.WebRoot)
	entries, err := os.ReadDir(webRoot)
	if err != nil {
		return nil, err
	}
	snap := map[string]fimEntry{}
	for _, e := range entries {
		if !e.IsDir() {
			continue
		}
		domain := strings.ToLower(strings.TrimSpace(e.Name()))
		if domain == "" || strings.Contains(domain, "..") {
			continue
		}
		root := filepath.Join(webRoot, domain)
		err := filepath.WalkDir(root, func(path string, d os.DirEntry, walkErr error) error {
			if walkErr != nil {
				return nil
			}
			if d.IsDir() {
				return nil
			}
			rel, err := filepath.Rel(webRoot, path)
			if err != nil {
				return nil
			}
			rel = "/" + filepath.ToSlash(rel)
			if fimIsExcluded(rel) {
				return nil
			}
			info, err := d.Info()
			if err != nil {
				return nil
			}
			sum, err := fimHashFile(path)
			if err != nil {
				return nil
			}
			snap[rel] = fimEntry{
				Path:    rel,
				Size:    info.Size(),
				Mode:    uint32(info.Mode()),
				ModUnix: info.ModTime().Unix(),
				Sha256:  sum,
			}
			return nil
		})
		if err != nil {
			return nil, err
		}
	}
	return snap, nil
}

func FimCreateBaseline(cfg *config.Config) (int, error) {
	snap, err := fimScanSnapshot(cfg)
	if err != nil {
		return 0, err
	}
	if err := fimWriteJSON(fimBaselinePath(cfg), snap); err != nil {
		return 0, err
	}
	status, _ := FimGetStatus(cfg)
	status.BaselineExists = true
	status.LastBaselineAt = fimNow()
	status.ChangedCount = 0
	status.CriticalCount = 0
	status.Alerts = nil
	_ = fimWriteJSON(fimStatusPath(cfg), status)
	_ = fimWriteJSON(fimAlertsPath(cfg), []FimAlert{})
	return len(snap), nil
}

func FimGetStatus(cfg *config.Config) (FimStatus, error) {
	var st FimStatus
	err := fimReadJSON(fimStatusPath(cfg), &st)
	if err != nil {
		if os.IsNotExist(err) {
			return FimStatus{}, nil
		}
		return FimStatus{}, err
	}
	return st, nil
}

func FimListAlerts(cfg *config.Config, limit int) ([]FimAlert, error) {
	var alerts []FimAlert
	err := fimReadJSON(fimAlertsPath(cfg), &alerts)
	if err != nil {
		if os.IsNotExist(err) {
			return []FimAlert{}, nil
		}
		return nil, err
	}
	sort.Slice(alerts, func(i, j int) bool {
		return alerts[i].CreatedAt > alerts[j].CreatedAt
	})
	if limit > 0 && len(alerts) > limit {
		alerts = alerts[:limit]
	}
	return alerts, nil
}

func FimScan(cfg *config.Config) ([]FimDiff, error) {
	var baseline map[string]fimEntry
	if err := fimReadJSON(fimBaselinePath(cfg), &baseline); err != nil {
		if os.IsNotExist(err) {
			return nil, fmt.Errorf("fim baseline not found")
		}
		return nil, err
	}
	current, err := fimScanSnapshot(cfg)
	if err != nil {
		return nil, err
	}
	diffs := make([]FimDiff, 0)
	for path, cur := range current {
		old, ok := baseline[path]
		if !ok {
			diff := FimDiff{Path: path, Type: "added", Severity: "medium"}
			if fimCriticalPath(path) {
				diff.Severity = "critical"
			}
			diffs = append(diffs, diff)
			continue
		}
		if old.Sha256 != cur.Sha256 || old.Size != cur.Size || old.Mode != cur.Mode {
			diff := FimDiff{Path: path, Type: "modified", Severity: "medium"}
			if fimCriticalPath(path) {
				diff.Severity = "critical"
			}
			diffs = append(diffs, diff)
		}
	}
	for path := range baseline {
		if _, ok := current[path]; ok {
			continue
		}
		diff := FimDiff{Path: path, Type: "removed", Severity: "high"}
		if fimCriticalPath(path) {
			diff.Severity = "critical"
		}
		diffs = append(diffs, diff)
	}
	sort.Slice(diffs, func(i, j int) bool {
		if diffs[i].Severity == diffs[j].Severity {
			return diffs[i].Path < diffs[j].Path
		}
		return diffs[i].Severity > diffs[j].Severity
	})

	alerts, _ := FimListAlerts(cfg, 0)
	now := fimNow()
	base := time.Now().UnixNano()
	for i, d := range diffs {
		alerts = append(alerts, FimAlert{
			ID:        fmt.Sprintf("fim_%d_%d", base, i),
			Kind:      "fim_diff",
			Severity:  d.Severity,
			Message:   fmt.Sprintf("FIM %s: %s", d.Type, d.Path),
			Path:      d.Path,
			CreatedAt: now,
		})
	}
	sort.Slice(alerts, func(i, j int) bool {
		return alerts[i].CreatedAt > alerts[j].CreatedAt
	})
	if len(alerts) > fimAlertPersistCap {
		alerts = alerts[:fimAlertPersistCap]
	}
	if err := fimWriteJSON(fimAlertsPath(cfg), alerts); err != nil {
		return nil, err
	}

	st, _ := FimGetStatus(cfg)
	st.BaselineExists = true
	if st.LastBaselineAt == "" {
		st.LastBaselineAt = now
	}
	st.LastScanAt = now
	st.ChangedCount = len(diffs)
	crit := 0
	for _, d := range diffs {
		if d.Severity == "critical" {
			crit++
		}
	}
	st.CriticalCount = crit
	st.Alerts = alerts
	if len(st.Alerts) > 20 {
		st.Alerts = st.Alerts[len(st.Alerts)-20:]
	}
	if err := fimWriteJSON(fimStatusPath(cfg), st); err != nil {
		return nil, err
	}
	return diffs, nil
}
