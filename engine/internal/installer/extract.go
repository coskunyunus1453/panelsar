package installer

import (
	"archive/zip"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
)

// unzipWithPrefix extracts only entries under prefix (e.g. "wordpress/" or "upload/").
func unzipWithPrefix(srcZip, destDir, prefix string) error {
	prefix = filepath.ToSlash(prefix)
	if prefix == "" || strings.Contains(prefix, "..") || strings.HasPrefix(prefix, "/") {
		return fmt.Errorf("zip: geçersiz önek")
	}
	r, err := zip.OpenReader(srcZip)
	if err != nil {
		return fmt.Errorf("zip: %w", err)
	}
	defer r.Close()

	absDest, err := filepath.Abs(destDir)
	if err != nil {
		return err
	}

	for _, f := range r.File {
		name := filepath.ToSlash(f.Name)
		if !strings.HasPrefix(name, prefix) {
			continue
		}
		rel := strings.TrimPrefix(name, prefix)
		if rel == "" || strings.HasSuffix(rel, "/") {
			continue
		}
		rel = filepath.FromSlash(rel)
		if rel == "" || rel == "." {
			continue
		}
		if filepath.IsAbs(rel) {
			continue
		}
		if strings.Contains(rel, "..") {
			continue
		}
		target := filepath.Join(destDir, rel)
		absTarget, err := filepath.Abs(target)
		if err != nil {
			return err
		}
		if absTarget != absDest && !strings.HasPrefix(absTarget, absDest+string(os.PathSeparator)) {
			continue
		}
		if f.FileInfo().IsDir() {
			if err := os.MkdirAll(target, 0o755); err != nil {
				return err
			}
			continue
		}
		if err := os.MkdirAll(filepath.Dir(target), 0o755); err != nil {
			return err
		}
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.OpenFile(target, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, f.Mode())
		if err != nil {
			rc.Close()
			return err
		}
		_, err = io.Copy(out, rc)
		rc.Close()
		out.Close()
		if err != nil {
			return err
		}
	}
	return nil
}

// UnzipOpenCartUpload finds */upload/ in the archive (release zip veya kaynak zip) ve içeriği destDir'e açar.
func UnzipOpenCartUpload(srcZip, destDir string) error {
	r, err := zip.OpenReader(srcZip)
	if err != nil {
		return fmt.Errorf("zip: %w", err)
	}
	defer r.Close()

	var prefix string
	for _, f := range r.File {
		fn := filepath.ToSlash(f.Name)
		if strings.Contains(fn, "..") {
			continue
		}
		if strings.HasSuffix(fn, "/upload/index.php") {
			prefix = strings.TrimSuffix(fn, "index.php")
			break
		}
	}
	if prefix == "" {
		return fmt.Errorf("opencart: zip içinde upload/index.php bulunamadı")
	}
	prefix = filepath.ToSlash(prefix)
	if strings.Contains(prefix, "..") || strings.HasPrefix(prefix, "/") {
		return fmt.Errorf("opencart: zip içinde geçersiz upload yolu")
	}
	return unzipWithPrefix(srcZip, destDir, prefix)
}
