package backup

import (
	"bytes"
	"context"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"hostvim/engine/internal/config"
)

// ArchiveDomain creates a gzip tar of paths.WebRoot/domain into destPath.
// destPath must lie under allowedDestPrefix (engine-state/backup-files).
func ArchiveDomain(ctx context.Context, cfg *config.Config, domain, destPath, allowedDestPrefix string) error {
	if cfg == nil {
		return fmt.Errorf("nil config")
	}
	webRoot := filepath.Clean(cfg.Paths.WebRoot)
	if webRoot == "" || webRoot == "." {
		return fmt.Errorf("paths.web_root is not set")
	}
	rel, err := filepath.Rel(webRoot, filepath.Join(webRoot, domain))
	if err != nil || rel == ".." || strings.HasPrefix(rel, ".."+string(filepath.Separator)) {
		return fmt.Errorf("invalid domain path")
	}
	siteDir := filepath.Join(webRoot, domain)
	fi, err := os.Stat(siteDir)
	if err != nil {
		return fmt.Errorf("site directory: %w", err)
	}
	if !fi.IsDir() {
		return fmt.Errorf("site path is not a directory")
	}

	absDest, err := filepath.Abs(filepath.Clean(destPath))
	if err != nil {
		return fmt.Errorf("dest path: %w", err)
	}
	absPrefix, err := filepath.Abs(filepath.Clean(allowedDestPrefix))
	if err != nil {
		return fmt.Errorf("backup dir: %w", err)
	}
	if !strings.HasPrefix(absDest, absPrefix+string(filepath.Separator)) && absDest != absPrefix {
		return fmt.Errorf("refusing to write backup outside backup directory")
	}
	if err := os.MkdirAll(filepath.Dir(absDest), 0o750); err != nil {
		return fmt.Errorf("mkdir backup parent: %w", err)
	}

	tarBin := strings.TrimSpace(cfg.Hosting.BackupTarPath)
	if tarBin == "" {
		tarBin = "tar"
	}

	// GNU/BSD tar: archive top-level folder "domain" under webRoot.
	var stderr bytes.Buffer
	cmd := exec.CommandContext(ctx, tarBin, "-czf", absDest, "-C", webRoot, domain)
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		_ = os.Remove(absDest)
		msg := strings.TrimSpace(stderr.String())
		if msg != "" {
			return fmt.Errorf("tar: %w (%s)", err, msg)
		}
		return fmt.Errorf("tar: %w", err)
	}
	return nil
}

// RestoreDomain extracts a tar.gz created by ArchiveDomain into paths.WebRoot (overwrites site files).
func RestoreDomain(ctx context.Context, cfg *config.Config, archivePath, allowedArchivePrefix string) error {
	if cfg == nil {
		return fmt.Errorf("nil config")
	}
	webRoot := filepath.Clean(cfg.Paths.WebRoot)
	if webRoot == "" {
		return fmt.Errorf("paths.web_root is not set")
	}
	absArc, err := filepath.Abs(filepath.Clean(archivePath))
	if err != nil {
		return fmt.Errorf("archive path: %w", err)
	}
	absArcPrefix, err := filepath.Abs(filepath.Clean(allowedArchivePrefix))
	if err != nil {
		return fmt.Errorf("archive prefix: %w", err)
	}
	if !strings.HasPrefix(absArc, absArcPrefix+string(filepath.Separator)) && absArc != absArcPrefix {
		return fmt.Errorf("refusing to read archive outside backup directory")
	}
	if _, err := os.Stat(absArc); err != nil {
		return fmt.Errorf("archive: %w", err)
	}
	absWeb, err := filepath.Abs(webRoot)
	if err != nil {
		return fmt.Errorf("web root: %w", err)
	}
	if fi, err := os.Stat(absWeb); err != nil || !fi.IsDir() {
		if err != nil {
			return fmt.Errorf("web root: %w", err)
		}
		return fmt.Errorf("web root is not a directory")
	}

	tarBin := strings.TrimSpace(cfg.Hosting.BackupTarPath)
	if tarBin == "" {
		tarBin = "tar"
	}
	var stderr bytes.Buffer
	cmd := exec.CommandContext(ctx, tarBin, "-xzf", absArc, "-C", absWeb)
	cmd.Stderr = &stderr
	if err := cmd.Run(); err != nil {
		msg := strings.TrimSpace(stderr.String())
		if msg != "" {
			return fmt.Errorf("tar extract: %w (%s)", err, msg)
		}
		return fmt.Errorf("tar extract: %w", err)
	}
	return nil
}
