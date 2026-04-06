package panelmirror

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	"hostvim/engine/internal/backup"
	"hostvim/engine/internal/config"
	"hostvim/engine/internal/sites"
)

var mu sync.Mutex

func dir(cfg *config.Config) string {
	base := filepath.Clean(cfg.Paths.WebRoot)
	return filepath.Join(filepath.Dir(base), "engine-state")
}

// EngineStateDir web kökünün bir üstündeki engine-state dizini (yedek, DNS aynası vb.).
func EngineStateDir(cfg *config.Config) string {
	return dir(cfg)
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

func readKV(path string) (map[string]string, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		if os.IsNotExist(err) {
			return map[string]string{}, nil
		}
		return nil, err
	}
	var out map[string]string
	if len(strings.TrimSpace(string(b))) == 0 {
		return map[string]string{}, nil
	}
	if err := json.Unmarshal(b, &out); err != nil {
		return nil, err
	}
	if out == nil {
		return map[string]string{}, nil
	}
	return out, nil
}

func writeKV(path string, v map[string]string) error {
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

func SecurityGetValue(cfg *config.Config, key string) (string, error) {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "security.json")
	kv, err := readKV(path)
	if err != nil {
		return "", err
	}
	return strings.TrimSpace(kv[key]), nil
}

func SecuritySetValue(cfg *config.Config, key, value string) error {
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "security.json")
	kv, err := readKV(path)
	if err != nil {
		return err
	}
	kv[key] = value
	return writeKV(path, kv)
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
	backupDir := filepath.Join(dir(cfg), "backup-files")
	if err := os.MkdirAll(backupDir, 0o750); err != nil {
		return nil, err
	}
	id := fmt.Sprintf("bk_%d", time.Now().UnixNano())
	outPath := filepath.Join(backupDir, id+".tar.gz")
	now := time.Now().UTC().Format(time.RFC3339)

	path := filepath.Join(dir(cfg), "backups.json")
	mu.Lock()
	rows, err := readSlice(path)
	if err != nil {
		mu.Unlock()
		return nil, err
	}
	entry := map[string]interface{}{
		"id":              id,
		"domain":          d,
		"type":            typ,
		"panel_backup_id": panelID,
		"queued_at":       now,
		"path":            outPath,
	}

	if !cfg.Hosting.ExecuteBackups {
		entry["status"] = "completed"
		entry["completed_at"] = now
		entry["size_bytes"] = float64(0)
		rows = append(rows, entry)
		err = writeSlice(path, rows)
		mu.Unlock()
		if err != nil {
			return nil, err
		}
		return gin.H{
			"message": "backup recorded (execute_backups=false; no archive on disk)",
			"id":      id,
			"path":    outPath,
			"status":  "completed",
		}, nil
	}

	entry["status"] = "running"
	rows = append(rows, entry)
	if err := writeSlice(path, rows); err != nil {
		mu.Unlock()
		return nil, err
	}
	mu.Unlock()

	maxSec := cfg.Hosting.BackupMaxSeconds
	if maxSec <= 0 {
		maxSec = 3600
	}
	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(maxSec)*time.Second)
	defer cancel()

	runErr := backup.ArchiveDomain(ctx, cfg, d, outPath, backupDir)

	mu.Lock()
	defer mu.Unlock()
	rows, err = readSlice(path)
	if err != nil {
		return nil, err
	}
	completedAt := time.Now().UTC().Format(time.RFC3339)
	var sizeBytes int64
	if runErr == nil {
		if st, e := os.Stat(outPath); e == nil {
			sizeBytes = st.Size()
		}
	}
	for i := range rows {
		if idStr(rows[i]["id"]) != id {
			continue
		}
		if runErr != nil {
			rows[i]["status"] = "failed"
			rows[i]["error"] = runErr.Error()
			_ = os.Remove(outPath)
		} else {
			rows[i]["status"] = "completed"
			rows[i]["size_bytes"] = float64(sizeBytes)
		}
		rows[i]["completed_at"] = completedAt
		break
	}
	if err := writeSlice(path, rows); err != nil {
		return nil, err
	}

	if runErr != nil {
		return gin.H{
			"id":     id,
			"path":   outPath,
			"status": "failed",
			"error":  runErr.Error(),
		}, nil
	}
	return gin.H{
		"message":    "backup completed",
		"id":         id,
		"path":       outPath,
		"status":     "completed",
		"size_bytes": sizeBytes,
	}, nil
}

// BackupRestore extracts a completed backup archive into web root (dangerous; hosting.execute_backup_restore).
func BackupRestore(cfg *config.Config, backupID string) (gin.H, error) {
	backupID = strings.TrimSpace(backupID)
	if backupID == "" {
		return nil, fmt.Errorf("invalid backup id")
	}
	if !cfg.Hosting.ExecuteBackupRestore {
		return gin.H{"message": "restore not executed (hosting.execute_backup_restore=false)"}, nil
	}
	path := filepath.Join(dir(cfg), "backups.json")
	mu.Lock()
	rows, err := readSlice(path)
	if err != nil {
		mu.Unlock()
		return nil, err
	}
	var arcPath string
	for _, r := range rows {
		if idStr(r["id"]) == backupID {
			if strings.ToLower(strings.TrimSpace(fmt.Sprint(r["status"]))) != "completed" {
				mu.Unlock()
				return nil, fmt.Errorf("backup is not completed")
			}
			arcPath = strings.TrimSpace(fmt.Sprint(r["path"]))
			break
		}
	}
	mu.Unlock()
	if arcPath == "" {
		return nil, fmt.Errorf("backup not found")
	}
	backupDir := filepath.Join(dir(cfg), "backup-files")
	maxSec := cfg.Hosting.BackupMaxSeconds
	if maxSec <= 0 {
		maxSec = 3600
	}
	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(maxSec)*time.Second)
	defer cancel()
	if err := backup.RestoreDomain(ctx, cfg, arcPath, backupDir); err != nil {
		return nil, err
	}
	return gin.H{"message": "restore completed", "id": backupID}, nil
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

// CronUpdate engine-state/cron.json içindeki kaydı günceller (id veya panel job id ile).
func CronUpdate(cfg *config.Config, id, schedule, command, desc string) error {
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
	updated := false
	for i := range jobs {
		match := jobs[i].ID == id || (panelID != 0 && jobs[i].PanelJobID == panelID)
		if !match {
			continue
		}
		jobs[i].Schedule = schedule
		jobs[i].Command = command
		jobs[i].Description = desc
		updated = true
		break
	}
	if !updated {
		return fmt.Errorf("cron job not found: %s", id)
	}
	return writeCron(path, jobs)
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
	Forwarders  []map[string]interface{} `json:"forwarders"`
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
				Forwarders:  nil,
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
		"forwarders": func() []gin.H {
			out := make([]gin.H, 0, len(s.Forwarders))
			for _, f := range s.Forwarders {
				out = append(out, gin.H(cloneMap(f)))
			}
			return out
		}(),
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

// MailDeleteDomain domain'e ait mail state dosyasini tamamen temizler (idempotent).
func MailDeleteDomain(cfg *config.Config, domain string) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	if err := os.Remove(path); err != nil && !os.IsNotExist(err) {
		return err
	}
	return nil
}

// MailReconcile panelde olmayan domain'lere ait engine-state/mail/*.json kalintilarini raporlar/siler.
func MailReconcile(cfg *config.Config, dryRun bool) (gin.H, error) {
	mu.Lock()
	defer mu.Unlock()

	active, err := sites.ListDomains(cfg.Paths.WebRoot)
	if err != nil {
		return nil, err
	}
	activeSet := map[string]struct{}{}
	for _, d := range active {
		activeSet[strings.ToLower(strings.TrimSpace(d))] = struct{}{}
	}

	mailDir := filepath.Join(dir(cfg), "mail")
	entries, err := os.ReadDir(mailDir)
	if err != nil {
		if os.IsNotExist(err) {
			return gin.H{
				"dry_run":        dryRun,
				"mail_state_dir": mailDir,
				"active_domains": len(activeSet),
				"scanned":        0,
				"orphans":        []string{},
				"removed":        []string{},
			}, nil
		}
		return nil, err
	}

	orphans := make([]string, 0)
	removed := make([]string, 0)
	scanned := 0
	for _, e := range entries {
		if e.IsDir() {
			continue
		}
		name := e.Name()
		if !strings.HasSuffix(name, ".json") {
			continue
		}
		scanned++
		domain := strings.TrimSuffix(strings.ToLower(strings.TrimSpace(name)), ".json")
		if domain == "" {
			continue
		}
		if _, ok := activeSet[domain]; ok {
			continue
		}
		orphans = append(orphans, domain)
		if dryRun {
			continue
		}
		if err := os.Remove(filepath.Join(mailDir, name)); err == nil || os.IsNotExist(err) {
			removed = append(removed, domain)
		}
	}

	return gin.H{
		"dry_run":        dryRun,
		"mail_state_dir": mailDir,
		"active_domains": len(activeSet),
		"scanned":        scanned,
		"orphans":        orphans,
		"removed":        removed,
	}, nil
}

// MailPatchMailbox updates fields (password, quota_mb, etc.) for one mailbox by email address.
func MailPatchMailbox(cfg *config.Config, domain, mailboxEmail string, patch map[string]interface{}) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	if strings.TrimSpace(mailboxEmail) == "" {
		return fmt.Errorf("email required")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	s, err := readMail(path)
	if err != nil {
		return err
	}
	found := false
	for i, m := range s.Mailboxes {
		if fmt.Sprint(m["email"]) == mailboxEmail {
			found = true
			for k, v := range patch {
				if k == "email" {
					continue
				}
				m[k] = v
			}
			s.Mailboxes[i] = m
			break
		}
	}
	if !found {
		return fmt.Errorf("mailbox not found")
	}
	return writeMail(path, s)
}

func MailAddForwarder(cfg *config.Config, domain string, entry map[string]interface{}) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	source := strings.ToLower(strings.TrimSpace(fmt.Sprint(entry["source"])))
	dest := strings.ToLower(strings.TrimSpace(fmt.Sprint(entry["destination"])))
	if source == "" || dest == "" {
		return fmt.Errorf("source and destination required")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	s, err := readMail(path)
	if err != nil {
		return err
	}
	for _, f := range s.Forwarders {
		if strings.EqualFold(fmt.Sprint(f["source"]), source) && strings.EqualFold(fmt.Sprint(f["destination"]), dest) {
			return nil
		}
	}
	entry["source"] = source
	entry["destination"] = dest
	s.Forwarders = append(s.Forwarders, entry)
	return writeMail(path, s)
}

func MailDeleteForwarder(cfg *config.Config, domain, source, destination string) error {
	d := safeDomain(domain)
	if d == "" {
		return fmt.Errorf("invalid domain")
	}
	source = strings.ToLower(strings.TrimSpace(source))
	destination = strings.ToLower(strings.TrimSpace(destination))
	if source == "" || destination == "" {
		return fmt.Errorf("source and destination required")
	}
	mu.Lock()
	defer mu.Unlock()
	path := filepath.Join(dir(cfg), "mail", d+".json")
	s, err := readMail(path)
	if err != nil {
		return err
	}
	next := make([]map[string]interface{}, 0, len(s.Forwarders))
	for _, f := range s.Forwarders {
		if strings.EqualFold(fmt.Sprint(f["source"]), source) && strings.EqualFold(fmt.Sprint(f["destination"]), destination) {
			continue
		}
		next = append(next, f)
	}
	s.Forwarders = next
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
