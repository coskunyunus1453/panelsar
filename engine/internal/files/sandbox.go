package files

import (
	"archive/zip"
	"errors"
	"fmt"
	"io"
	"io/fs"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"syscall"
)

type ListEntry struct {
	Name  string `json:"name"`
	IsDir bool   `json:"is_dir"`
	Size  int64  `json:"size"`
	Mode  string `json:"mode"`
	ModTime int64 `json:"mtime"`
}

func ResolveUnderRoot(root, rel string) (string, error) {
	cleanRoot := filepath.Clean(root)
	rel = strings.TrimPrefix(rel, "/")
	joined := filepath.Join(cleanRoot, rel)
	absRoot, err := filepath.Abs(cleanRoot)
	if err != nil {
		return "", err
	}
	absJoined, err := filepath.Abs(joined)
	if err != nil {
		return "", err
	}
	if absJoined != absRoot && !strings.HasPrefix(absJoined, absRoot+string(os.PathSeparator)) {
		return "", fmt.Errorf("path escapes root")
	}

	// Symlink-safe doğrulama:
	// - Sadece abs path prefix'i kontrolü root dışına kaçışı engellemez (ör. root içindeki symlink -> /etc).
	// - Bu yüzden mümkünse EvalSymlinks ile gerçek (real) hedefi bulup root prefix'i ile tekrar doğrularız.
	realRoot := absRoot
	if rr, err := filepath.EvalSymlinks(absRoot); err == nil && rr != "" {
		realRoot = rr
	}

	target := absJoined
	if rj, err := filepath.EvalSymlinks(absJoined); err == nil && rj != "" {
		target = rj
	} else {
		// Hedef dosya yeni oluşturulacaksa (var olmayan path), EvalSymlinks başarısız olur.
		// Bu durumda mevcut olan parent dizinini resolve edip hedefi onun altında varsayarız.
		parent := filepath.Dir(absJoined)
		if rp, err := filepath.EvalSymlinks(parent); err == nil && rp != "" {
			target = filepath.Join(rp, filepath.Base(absJoined))
		}
	}

	// root prefix check (real path üzerinden)
	sep := string(os.PathSeparator)
	if target != realRoot && !strings.HasPrefix(target, realRoot+sep) {
		return "", fmt.Errorf("path escapes root (realpath)")
	}

	// Sistem dizinleri (liste genişletilebilir) - domain root içinde bile olsa erişimi reddet.
	restrictedPrefixes := []string{
		"/etc", "/bin", "/sbin", "/usr/bin", "/usr/sbin",
		"/lib", "/lib64",
		"/proc", "/sys",
	}
	for _, p := range restrictedPrefixes {
		if target == p || strings.HasPrefix(target, p+sep) {
			return "", fmt.Errorf("editing system paths is not allowed")
		}
	}

	return absJoined, nil
}

func List(root, rel string) ([]ListEntry, error) {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return nil, err
	}
	entries, err := os.ReadDir(base)
	if err != nil {
		return nil, err
	}
	out := make([]ListEntry, 0, len(entries))
	for _, e := range entries {
		info, err := e.Info()
		if err != nil {
			continue
		}
		out = append(out, ListEntry{
			Name:  e.Name(),
			IsDir: e.IsDir(),
			Size:  info.Size(),
			Mode:  info.Mode().String(),
			ModTime: info.ModTime().Unix(),
		})
	}
	sort.Slice(out, func(i, j int) bool {
		if out[i].IsDir != out[j].IsDir {
			return out[i].IsDir
		}
		return strings.ToLower(out[i].Name) < strings.ToLower(out[j].Name)
	})
	return out, nil
}

// ListPaged returns paginated entries along with total count.
// Sorting is stable and directories are always listed first.
func ListPaged(root, rel string, offset, limit int, sortKey, order string) ([]ListEntry, int, error) {
	all, err := List(root, rel)
	if err != nil {
		return nil, 0, err
	}

	total := len(all)
	if offset < 0 {
		offset = 0
	}
	if limit <= 0 {
		limit = total
	}

	// Resort with requested sortKey/order while keeping directories first.
	// We can’t easily reuse List() comparator because it always sorts by name.
	// The list size is still directory-size dependent.
	sort.SliceStable(all, func(i, j int) bool {
		a := all[i]
		b := all[j]
		// Directories first
		if a.IsDir != b.IsDir {
			return a.IsDir
		}
		asc := strings.ToLower(order) != "desc"

		var aLessB bool
		var bLessA bool
		switch strings.ToLower(sortKey) {
		case "size":
			aLessB = a.Size < b.Size
			bLessA = b.Size < a.Size
		case "mtime":
			aLessB = a.ModTime < b.ModTime
			bLessA = b.ModTime < a.ModTime
		case "name":
			aLessB = strings.ToLower(a.Name) < strings.ToLower(b.Name)
			bLessA = strings.ToLower(b.Name) < strings.ToLower(a.Name)
		default:
			aLessB = strings.ToLower(a.Name) < strings.ToLower(b.Name)
			bLessA = strings.ToLower(b.Name) < strings.ToLower(a.Name)
		}

		if asc {
			return aLessB
		}
		// Desc: swap the less-than comparison
		return bLessA
	})

	if offset > total {
		return []ListEntry{}, total, nil
	}
	end := offset + limit
	if end > total {
		end = total
	}
	return all[offset:end], total, nil
}

func Mkdir(root, rel string) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	return os.MkdirAll(base, 0o755)
}

func Rename(root, fromRel, toRel string) error {
	fromAbs, err := ResolveUnderRoot(root, fromRel)
	if err != nil {
		return err
	}
	toAbs, err := ResolveUnderRoot(root, toRel)
	if err != nil {
		return err
	}

	// Safety: do not overwrite.
	if _, err := os.Stat(toAbs); err == nil {
		return fmt.Errorf("target already exists")
	} else if err != nil && !errors.Is(err, os.ErrNotExist) {
		return err
	}

	if err := os.Rename(fromAbs, toAbs); err != nil {
		// Cross-device move: fallback for regular files only.
		if errors.Is(err, syscall.EXDEV) {
			st, stErr := os.Stat(fromAbs)
			if stErr != nil {
				return stErr
			}
			if st.IsDir() {
				return fmt.Errorf("cross-device directory move is not supported")
			}
			if err := copyFile(fromAbs, toAbs, st.Mode().Perm()); err != nil {
				return err
			}
			return os.Remove(fromAbs)
		}
		return err
	}

	return nil
}

func copyFile(src, dst string, perm fs.FileMode) error {
	if err := os.MkdirAll(filepath.Dir(dst), 0o755); err != nil {
		return err
	}
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()

	out, err := os.OpenFile(dst, os.O_CREATE|os.O_EXCL|os.O_WRONLY, perm)
	if err != nil {
		return err
	}
	defer out.Close()

	if _, err := io.Copy(out, in); err != nil {
		return err
	}
	return out.Sync()
}

func Remove(root, rel string) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	return os.RemoveAll(base)
}

func Copy(root, fromRel, toRel string) error {
	fromAbs, err := ResolveUnderRoot(root, fromRel)
	if err != nil {
		return err
	}
	toAbs, err := ResolveUnderRoot(root, toRel)
	if err != nil {
		return err
	}
	if _, err := os.Stat(toAbs); err == nil {
		return fmt.Errorf("target already exists")
	} else if !errors.Is(err, os.ErrNotExist) {
		return err
	}
	st, err := os.Stat(fromAbs)
	if err != nil {
		return err
	}
	if st.IsDir() {
		return copyDir(fromAbs, toAbs)
	}
	return copyFile(fromAbs, toAbs, st.Mode().Perm())
}

func copyDir(src, dst string) error {
	return filepath.WalkDir(src, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(src, path)
		if err != nil {
			return err
		}
		target := filepath.Join(dst, rel)
		if d.IsDir() {
			return os.MkdirAll(target, 0o755)
		}
		info, err := d.Info()
		if err != nil {
			return err
		}
		return copyFile(path, target, info.Mode().Perm())
	})
}

func Chmod(root, rel string, mode fs.FileMode) error {
	abs, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	return os.Chmod(abs, mode)
}

func ZipPath(root, sourceRel, targetRel string) error {
	src, err := ResolveUnderRoot(root, sourceRel)
	if err != nil {
		return err
	}
	dst, err := ResolveUnderRoot(root, targetRel)
	if err != nil {
		return err
	}
	if _, err := os.Stat(dst); err == nil {
		return fmt.Errorf("target already exists")
	} else if !errors.Is(err, os.ErrNotExist) {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(dst), 0o755); err != nil {
		return err
	}
	out, err := os.OpenFile(dst, os.O_CREATE|os.O_EXCL|os.O_WRONLY, 0o644)
	if err != nil {
		return err
	}
	defer out.Close()
	zw := zip.NewWriter(out)
	defer zw.Close()

	srcInfo, err := os.Stat(src)
	if err != nil {
		return err
	}
	baseName := filepath.Base(src)
	if srcInfo.IsDir() {
		return filepath.Walk(src, func(path string, info fs.FileInfo, err error) error {
			if err != nil {
				return err
			}
			rel, err := filepath.Rel(src, path)
			if err != nil {
				return err
			}
			zipName := filepath.ToSlash(filepath.Join(baseName, rel))
			if info.IsDir() {
				if rel == "." {
					return nil
				}
				_, err = zw.Create(zipName + "/")
				return err
			}
			return writeZipFile(zw, path, zipName, info)
		})
	}
	return writeZipFile(zw, src, baseName, srcInfo)
}

func writeZipFile(zw *zip.Writer, srcPath, zipName string, info fs.FileInfo) error {
	h, err := zip.FileInfoHeader(info)
	if err != nil {
		return err
	}
	h.Name = zipName
	h.Method = zip.Deflate
	w, err := zw.CreateHeader(h)
	if err != nil {
		return err
	}
	in, err := os.Open(srcPath)
	if err != nil {
		return err
	}
	defer in.Close()
	_, err = io.Copy(w, in)
	return err
}

func UnzipPath(root, archiveRel, targetDirRel string) error {
	arc, err := ResolveUnderRoot(root, archiveRel)
	if err != nil {
		return err
	}
	targetDir, err := ResolveUnderRoot(root, targetDirRel)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(targetDir, 0o755); err != nil {
		return err
	}
	r, err := zip.OpenReader(arc)
	if err != nil {
		return err
	}
	defer r.Close()
	for _, f := range r.File {
		name := strings.ReplaceAll(f.Name, "\\", "/")
		if strings.Contains(name, "..") {
			return fmt.Errorf("archive entry escapes target")
		}
		dst := filepath.Join(targetDir, filepath.FromSlash(name))
		if err := os.MkdirAll(filepath.Dir(dst), 0o755); err != nil {
			return err
		}
		if f.FileInfo().IsDir() {
			if err := os.MkdirAll(dst, 0o755); err != nil {
				return err
			}
			continue
		}
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.OpenFile(dst, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, f.Mode())
		if err != nil {
			rc.Close()
			return err
		}
		if _, err := io.Copy(out, rc); err != nil {
			out.Close()
			rc.Close()
			return err
		}
		out.Close()
		rc.Close()
	}
	return nil
}

// IsExecutionRiskFile returns true for file extensions that could be executed by the server.
// The editor/file-manager module blocks editing/saving these types.
func IsExecutionRiskFile(rel string) bool {
	ext := strings.ToLower(filepath.Ext(rel))
	switch ext {
	case ".sh", ".bash", ".cgi", ".pl", ".py", ".exe", ".bin":
		return true
	default:
		return false
	}
}

func WriteFile(root, rel string, data []byte, perm fs.FileMode) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	if IsExecutionRiskFile(rel) {
		return fmt.Errorf("editing this file type is blocked")
	}
	if err := os.MkdirAll(filepath.Dir(base), 0o755); err != nil {
		return err
	}
	return os.WriteFile(base, data, perm)
}

// CreateFile only creates a new file; it will fail if target already exists.
func CreateFile(root, rel string, data []byte, perm fs.FileMode) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	if IsExecutionRiskFile(rel) {
		return fmt.Errorf("editing this file type is blocked")
	}
	if err := os.MkdirAll(filepath.Dir(base), 0o755); err != nil {
		return err
	}
	f, err := os.OpenFile(base, os.O_CREATE|os.O_EXCL|os.O_WRONLY, perm)
	if err != nil {
		if os.IsExist(err) {
			return fmt.Errorf("target already exists")
		}
		return err
	}
	defer f.Close()
	if _, err := f.Write(data); err != nil {
		return err
	}
	return f.Sync()
}

func ReadFile(root, rel string) ([]byte, error) {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return nil, err
	}
	return os.ReadFile(base)
}

// MaxEditorFileSize panel düzenleyicisi için üst boyut.
const MaxEditorFileSize int64 = 3 << 20

// ReadFileForEditor büyük dosyaları reddeder.
func ReadFileForEditor(root, rel string) ([]byte, error) {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return nil, err
	}
	st, err := os.Stat(base)
	if err != nil {
		return nil, err
	}
	if st.IsDir() {
		return nil, fmt.Errorf("is a directory")
	}
	if st.Size() > MaxEditorFileSize {
		return nil, fmt.Errorf("file too large for editor (max %d MiB)", MaxEditorFileSize>>20)
	}
	return os.ReadFile(base)
}

// MaxDownloadFileSize download için üst limit.
const MaxDownloadFileSize int64 = 20 << 20

func ReadFileForDownload(root, rel string) ([]byte, error) {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return nil, err
	}
	st, err := os.Stat(base)
	if err != nil {
		return nil, err
	}
	if st.IsDir() {
		return nil, fmt.Errorf("is a directory")
	}
	if st.Size() > MaxDownloadFileSize {
		return nil, fmt.Errorf("file too large for download (max %d MiB)", MaxDownloadFileSize>>20)
	}
	return os.ReadFile(base)
}
