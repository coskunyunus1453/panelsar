package panelmirror

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/panelsar/engine/internal/config"
)

var mu sync.Mutex

func dir(cfg *config.Config) string {
	base := filepath.Clean(cfg.Paths.WebRoot)
	return filepath.Join(filepath.Dir(base), "engine-state")
}

func safeDomain(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	if s == "" || strings.Contains(s, "..") || strings.Contains(s, "/") || strings.Contains(s, "\\") {
		return ""
	}
	var b strings.Builder
	for _, r := range s {
		switch {
		case r >= 'a' && r <= 'z', r >= '0' && r <= '9', r == '.', r == '-':
			b.WriteRune(r)
		default:
			b.WriteRune('_')
		}
	}
	return b.String()
}

func readSlice(path string) ([]map[string]interface{}, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return []map[string]interface{}{}, nil
		}
		return nil, err
	}
	var out []map[string]interface{}
	if len(strings.TrimSpace(string(b))) == 0 {
		return []map[string]interface{}{}, nil
	}
	if err := json.Unmarshal(b, &out); err != nil {
		return nil, err
	}
	if out == nil {
		return []map[string]interface{}{}, nil
	}
	return out, nil
}

func writeSlice(path string, v []map[string]interface{}) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(v, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0o640)
}

func idStr(v interface{}) string {
	if v == nil {
		return ""
	}
	switch x := v.(type) {
	case float64:
		return fmt.Sprintf("%.0f", x)
	case string:
		return x
	default:
		return fmt.Sprintf("%v", v)
	}
}

// DNSRecords engine ile panel senkronu (dosya tabanlı).
func DNSRecords(cfg *config.Config, domain string) ([]gin.H, error) {
	d := safeDomain(domain)
	if d == "" {
		return nil, fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "dns", d+".json")
	rows, err := readSlice(path)
	if err != nil {
		return nil, err
	}
	out := make([]gin.H, 0, len(rows))
	for _, r := range rows {
		out = append(out, gin.H(r))
	}
	return out, nil
}

func DNSAdd(cfg *config.Config, domain string, body map[string]interface{}) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "dns", d+".json")
	rows, err := readSlice(path)
	if err != nil {
		return err
	}
	rows = append(rows, body)
	return writeSlice(path, rows)
}

func DNSDelete(cfg *config.Config, domain, id string) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "dns", d+".json")
	rows, err := readSlice(path)
	if err != nil {
		return err
	}
	id = strings.TrimSpace(id)
	var next []map[string]interface{}
	for _, r := range rows {
		if idStr(r["id"]) == id {
			continue
		}
		next = append(next, r)
	}
	return writeSlice(path, next)
}

// Backups listesi.
func BackupsList(cfg *config.Config) ([]gin.H, error) {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "backups.json")
	rows, err := readSlice(path)
	if err != nil {
		return nil, err
	}
	out := make([]gin.H, 0, len(rows))
	for _, r := range rows {
		out = append(out, gin.H(r))
	}
	return out, nil
}

func BackupQueue(cfg *config.Config, domain, typ string, panelID float64) (gin.H, error) {
	d := safeDomain(domain)
	if d == "" {
		return nil, fmt.Errorf("invalid domain")
	}
	if typ == "" {
		typ = "full"
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "backups.json")
	rows, err := readSlice(path)
	if err != nil {
		return nil, err
	}
	id := fmt.Sprintf("bk_%d", time.Now().UnixNano())
	entry := map[string]interface{}{
		"id":              id,
		"domain":          d,
		"type":            typ,
		"status":          "completed",
		"panel_backup_id": panelID,
		"queued_at":       time.Now().UTC().Format(time.RFC3339),
		"path":            filepath.Join(dir(cfg), "backup-files", id+".tar.gz"),
	}
	rows = append(rows, entry)
	if err := writeSlice(path, rows); err != nil {
		return nil, err
	}
	return gin.H{
		"message": "backup recorded",
		"id":      id,
		"path":    entry["path"],
		"status":  "completed",
	}, nil
}

type cronJob struct {
	ID          string `json:"id"`
	Schedule    string `json:"schedule"`
	Command     string `json:"command"`
	Description string `json:"description,omitempty"`
	UserID      uint   `json:"user_id,omitempty"`
	PanelJobID  uint   `json:"panel_job_id,omitempty"`
	CreatedAt   string `json:"created_at"`
}

func readCron(path string) ([]cronJob, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return nil, nil
		}
		return nil, err
	}
	var jobs []cronJob
	if err := json.Unmarshal(b, &jobs); err != nil {
		return nil, err
	}
	return jobs, nil
}

func writeCron(path string, jobs []cronJob) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(jobs, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0o640)
}

func CronList(cfg *config.Config) ([]gin.H, error) {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "cron.json")
	jobs, err := readCron(path)
	if err != nil {
		return nil, err
	}
	out := make([]gin.H, 0, len(jobs))
	for _, j := range jobs {
		out = append(out, gin.H{
			"id": j.ID, "schedule": j.Schedule, "command": j.Command,
			"description": j.Description, "user_id": j.UserID, "panel_job_id": j.PanelJobID,
			"created_at": j.CreatedAt,
		})
	}
	return out, nil
}

func CronAdd(cfg *config.Config, schedule, command, desc string, userID, panelJobID uint) (string, error) {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "cron.json")
	jobs, err := readCron(path)
	if err != nil {
		return "", err
	}
	id := fmt.Sprintf("cj_%d", time.Now().UnixNano())
	jobs = append(jobs, cronJob{
		ID: id, Schedule: schedule, Command: command, Description: desc,
		UserID: userID, PanelJobID: panelJobID, CreatedAt: time.Now().UTC().Format(time.RFC3339),
	})
	if err := writeCron(path, jobs); err != nil {
		return "", err
	}
	return id, nil
}

func CronDelete(cfg *config.Config, id string) error {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "cron.json")
	jobs, err := readCron(path)
	if err != nil {
		return err
	}
	id = strings.TrimSpace(id)
	if id == "" {
		return fmt.Errorf("empty id")
	}
	var panelID uint
	allDigits := true
	for _, r := range id {
		if r < '0' || r > '9' {
			allDigits = false
			break
		}
	}
	if allDigits {
		_, _ = fmt.Sscanf(id, "%d", &panelID)
	}
	var next []cronJob
	removed := false
	for _, j := range jobs {
		if j.ID == id {
			removed = true
			continue
		}
		next = append(next, j)
	}
	if !removed && panelID != 0 {
		next = nil
		for _, j := range jobs {
			if j.PanelJobID == panelID {
				removed = true
				continue
			}
			next = append(next, j)
		}
	}
	if !removed {
		return fmt.Errorf("cron job not found: %s", id)
	}
	return writeCron(path, next)
}

// FTPAccounts domain başına.
func FTPAccounts(cfg *config.Config, domain string) ([]gin.H, error) {
	d := safeDomain(domain)
	if d == "" {
		return nil, fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "ftp", d+".json")
	rows, err := readSlice(path)
	if err != nil {
		return nil, err
	}
	out := make([]gin.H, 0, len(rows))
	for _, r := range rows {
		if _, ok := r["password"]; ok {
			r = cloneMap(r)
			r["password"] = "[stored]"
		}
		out = append(out, gin.H(r))
	}
	return out, nil
}

func cloneMap(m map[string]interface{}) map[string]interface{} {
	o := make(map[string]interface{}, len(m))
	for k, v := range m {
		o[k] = v
	}
	return o
}

func FTPAdd(cfg *config.Config, domain string, body map[string]interface{}) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "ftp", d+".json")
	rows, err := readSlice(path)
	if err != nil {
		return err
	}
	rows = append(rows, body)
	return writeSlice(path, rows)
}

func FTPDelete(cfg *config.Config, domain, username string) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "ftp", d+".json")
	rows, err := readSlice(path)
	if err != nil {
		return err
	}
	var next []map[string]interface{}
	for _, r := range rows {
		if fmt.Sprint(r["username"]) == username {
			continue
		}
		next = append(next, r)
	}
	return writeSlice(path, next)
}

type mailState struct {
	MailEnabled bool                     `json:"mail_enabled"`
	Mailboxes   []map[string]interface{} `json:"mailboxes"`
	SPF         string                   `json:"spf"`
	DMARC       string                   `json:"dmarc"`
	DKIM        gin.H                    `json:"dkim"`
}

func readMail(path string) (*mailState, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return &mailState{
				MailEnabled: true,
				Mailboxes:   nil,
				DKIM:        gin.H{"enabled": false},
			}, nil
		}
		return nil, err
	}
	var s mailState
	if err := json.Unmarshal(b, &s); err != nil {
		return nil, err
	}
	if s.DKIM == nil {
		s.DKIM = gin.H{"enabled": false}
	}
	return &s, nil
}

func writeMail(path string, s *mailState) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(s, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0o640)
}

func MailOverview(cfg *config.Config, domain string) (gin.H, error) {
	d := safeDomain(domain)
	if d == "" {
		return nil, fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	s, err := readMail(path)
	if err != nil {
		return nil, err
	}
	boxes := make([]gin.H, 0, len(s.Mailboxes))
	for _, m := range s.Mailboxes {
		c := cloneMap(m)
		if _, ok := c["password"]; ok {
			c["password"] = "[stored]"
		}
		boxes = append(boxes, gin.H(c))
	}
	return gin.H{
		"mail_enabled": s.MailEnabled,
		"mailboxes":    boxes,
		"dkim":         s.DKIM,
		"spf":          s.SPF,
		"dmarc":        s.DMARC,
	}, nil
}

func MailAddMailbox(cfg *config.Config, domain string, entry map[string]interface{}) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	s, err := readMail(path)
	if err != nil {
		return err
	}
	s.Mailboxes = append(s.Mailboxes, entry)
	return writeMail(path, s)
}

func MailDeleteMailbox(cfg *config.Config, domain, email string) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	s, err := readMail(path)
	if err != nil {
		return err
	}
	var next []map[string]interface{}
	for _, m := range s.Mailboxes {
		if fmt.Sprint(m["email"]) == email {
			continue
		}
		next = append(next, m)
	}
	s.Mailboxes = next
	return writeMail(path, s)
}

// FirewallRule son kurallar (özet).
func AppendFirewallRule(cfg *config.Config, rule gin.H) error {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "firewall-rules.json")
	rows, err := readSlice(path)
	if err != nil {
		return err
	}
	rule["applied_at"] = time.Now().UTC().Format(time.RFC3339)
	rows = append(rows, map[string]interface{}(rule))
	return writeSlice(path, rows)
}

// FirewallRulesList son eklenen güvenlik duvarı kuralı özetleri.
func FirewallRulesList(cfg *config.Config) ([]gin.H, error) {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "firewall-rules.json")
	rows, err := readSlice(path)
	if err != nil {
		return nil, err
	}
	out := make([]gin.H, 0, len(rows))
	for _, r := range rows {
		out = append(out, gin.H(r))
	}
	return out, nil
}
