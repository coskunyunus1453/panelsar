package tools

import (
	"context"
	"fmt"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/sites"
)

// Run belge kökünde izin verilen composer / npm komutlarını çalıştırır.
func Run(cfg *config.Config, domain, tool, action string) (string, error) {
	if !cfg.Hosting.ManageSiteTools {
		return "", fmt.Errorf("manage_site_tools devre dışı")
	}
	if domain == "" || strings.Contains(domain, "..") {
		return "", fmt.Errorf("invalid domain")
	}
	meta, err := sites.ReadSiteMeta(cfg.Paths.WebRoot, domain)
	if err != nil || meta == nil {
		return "", fmt.Errorf("site not found")
	}
	docRoot := filepath.Clean(meta.DocumentRoot)
	if !strings.HasPrefix(docRoot, filepath.Clean(cfg.Paths.WebRoot)) {
		return "", fmt.Errorf("invalid document root")
	}

	tool = strings.ToLower(strings.TrimSpace(tool))
	action = strings.ToLower(strings.TrimSpace(action))

	maxSec := cfg.Hosting.ToolsMaxSeconds
	if maxSec <= 0 {
		maxSec = 300
	}
	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(maxSec)*time.Second)
	defer cancel()

	switch tool {
	case "composer":
		return runComposer(ctx, cfg, docRoot, action)
	case "npm":
		return runNpm(ctx, cfg, docRoot, action)
	default:
		return "", fmt.Errorf("desteklenmeyen tool: %q", tool)
	}
}

func runComposer(ctx context.Context, cfg *config.Config, dir, action string) (string, error) {
	bin := strings.TrimSpace(cfg.Hosting.ComposerPath)
	if bin == "" {
		bin = "composer"
	}
	var args []string
	switch action {
	case "install":
		args = []string{"install", "--no-interaction", "--no-ansi", "--no-progress"}
	case "update":
		args = []string{"update", "--no-interaction", "--no-ansi", "--no-progress"}
	case "dump-autoload":
		args = []string{"dump-autoload", "--no-interaction", "--no-ansi"}
	default:
		return "", fmt.Errorf("desteklenmeyen composer action: %q (install, update, dump-autoload)", action)
	}
	cmd := exec.CommandContext(ctx, bin, args...)
	cmd.Dir = dir
	out, err := cmd.CombinedOutput()
	s := strings.TrimSpace(string(out))
	if err != nil {
		if s != "" {
			return s, fmt.Errorf("%w — %s", err, s)
		}
		return s, err
	}
	return s, nil
}

func runNpm(ctx context.Context, cfg *config.Config, dir, action string) (string, error) {
	bin := strings.TrimSpace(cfg.Hosting.NpmPath)
	if bin == "" {
		bin = "npm"
	}
	var args []string
	switch action {
	case "install":
		args = []string{"install", "--no-audit", "--no-fund"}
	case "ci":
		args = []string{"ci", "--no-audit", "--no-fund"}
	default:
		return "", fmt.Errorf("desteklenmeyen npm action: %q (install, ci)", action)
	}
	cmd := exec.CommandContext(ctx, bin, args...)
	cmd.Dir = dir
	out, err := cmd.CombinedOutput()
	s := strings.TrimSpace(string(out))
	if err != nil {
		if s != "" {
			return s, fmt.Errorf("%w — %s", err, s)
		}
		return s, err
	}
	return s, nil
}
