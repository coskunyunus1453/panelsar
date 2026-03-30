package phpini

import (
	"bytes"
	"errors"
	"fmt"
	"io/fs"
	"os"
	"os/exec"
	"strings"
	"syscall"
)

const helper = "/usr/local/sbin/panelsar-php-ini"

func isPermissionDenied(err error) bool {
	if err == nil {
		return false
	}
	if errors.Is(err, fs.ErrPermission) {
		return true
	}
	var pe *fs.PathError
	if errors.As(err, &pe) {
		if errors.Is(pe.Err, syscall.EACCES) || errors.Is(pe.Err, syscall.EPERM) {
			return true
		}
	}
	return strings.Contains(strings.ToLower(err.Error()), "permission denied")
}

// Read tries os.ReadFile; on permission denied uses sudo helper (production: www-data).
func Read(path string) ([]byte, error) {
	b, err := os.ReadFile(path)
	if err == nil {
		return b, nil
	}
	if !isPermissionDenied(err) {
		return nil, err
	}
	return readElevated(path)
}

func readElevated(path string) ([]byte, error) {
	if _, statErr := os.Stat(helper); statErr != nil {
		return nil, fmt.Errorf("php.ini okunamıyor (izin yok); %s kurulu değil: %w", helper, statErr)
	}
	cmd := exec.Command("sudo", "-n", helper, "read", path)
	out, err := cmd.Output()
	if err != nil {
		var ee *exec.ExitError
		if errors.As(err, &ee) {
			return nil, fmt.Errorf("php-ini helper read: %w — %s", err, strings.TrimSpace(string(ee.Stderr)))
		}
		return nil, fmt.Errorf("php-ini helper read: %w", err)
	}
	return out, nil
}

// Write tries os.WriteFile; on permission denied uses sudo helper.
func Write(path string, content []byte, perm fs.FileMode) error {
	if err := os.WriteFile(path, content, perm); err == nil {
		return nil
	} else if !isPermissionDenied(err) {
		return err
	}
	return writeElevated(path, content)
}

func writeElevated(path string, content []byte) error {
	if _, statErr := os.Stat(helper); statErr != nil {
		return fmt.Errorf("permission denied — panelsar-php-ini kurulu değil veya sudoers eksik: %v", statErr)
	}
	cmd := exec.Command("sudo", "-n", helper, "write", path)
	cmd.Stdin = bytes.NewReader(content)
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("php-ini helper write: %w — %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}
