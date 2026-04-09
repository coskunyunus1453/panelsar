package files

import (
	"archive/zip"
	"crypto/rand"
	"encoding/hex"
	"errors"
	"fmt"
	"io"
	"io/fs"
	"os"
	"os/user"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"syscall"
	"time"
)

const (
	// ZIP bomb koruması: aşırı entry ve aşırı açılmış boyut sınırı.
	maxUnzipEntries = 20000
	maxUnzipBytes   = int64(5 << 30) // 5 GiB
)

type ListEntry struct {
	Name    string `json:"name"`
	IsDir   bool   `json:"is_dir"`
	Size    int64  `json:"size"`
	Mode    string `json:"mode"`
	Owner   string `json:"owner"`
	Group   string `json:"group"`
	ModTime int64  `json:"mtime"`
}

func resolveOwnerGroup(info fs.FileInfo) (string, string) {
	st, ok := info.Sys().(*syscall.Stat_t)
	if !ok || st == nil {
		return "", ""
	}

	uid := strconv.FormatUint(uint64(st.Uid), 10)
	gid := strconv.FormatUint(uint64(st.Gid), 10)

	owner := uid
	group := gid

	if u, err := user.LookupId(uid); err == nil && strings.TrimSpace(u.Username) != "" {
		owner = u.Username
	}
	if g, err := user.LookupGroupId(gid); err == nil && strings.TrimSpace(g.Name) != "" {
		group = g.Name
	}

	return owner, group
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
		owner, group := resolveOwnerGroup(info)
		out = append(out, ListEntry{
			Name:    e.Name(),
			IsDir:   e.IsDir(),
			Size:    info.Size(),
			Mode:    info.Mode().String(),
			Owner:   owner,
			Group:   group,
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

	parent := filepath.Dir(toAbs)
	if err := os.MkdirAll(parent, 0o755); err != nil {
		return err
	}
	id := randomTempID()
	base := filepath.Base(toAbs)
	tmpPath := filepath.Join(parent, ".tmp_copy_"+id+"_"+base)

	if st.IsDir() {
		if err := os.Mkdir(tmpPath, 0o755); err != nil {
			return err
		}
		committed := false
		defer func() {
			if !committed {
				_ = os.RemoveAll(tmpPath)
			}
		}()
		if err := copyDir(fromAbs, tmpPath); err != nil {
			return err
		}
		if err := os.Rename(tmpPath, toAbs); err != nil {
			return err
		}
		committed = true
		return nil
	}

	committed := false
	defer func() {
		if !committed {
			_ = os.RemoveAll(tmpPath)
		}
	}()
	if err := copyFile(fromAbs, tmpPath, st.Mode().Perm()); err != nil {
		return err
	}
	if err := os.Rename(tmpPath, toAbs); err != nil {
		return err
	}
	committed = true
	return nil
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

// unzipIntoDirectory zip girdilerini rootDir altına yazar (zip slip kontrolü rootDir’e göre).
func unzipIntoDirectory(rootDir string, r *zip.Reader) error {
	sep := string(os.PathSeparator)
	if len(r.File) > maxUnzipEntries {
		return fmt.Errorf("archive has too many entries (%d > %d)", len(r.File), maxUnzipEntries)
	}
	var totalUncompressed int64
	for _, f := range r.File {
		name := filepath.Clean(filepath.FromSlash(strings.ReplaceAll(f.Name, "\\", "/")))
		if name == "." || name == ".." || filepath.IsAbs(name) || strings.HasPrefix(name, ".."+sep) {
			return fmt.Errorf("archive entry escapes target")
		}
		if strings.Contains(name, "\x00") {
			return fmt.Errorf("archive entry contains NUL byte")
		}
		dst := filepath.Join(rootDir, name)
		if dst != rootDir && !strings.HasPrefix(dst, rootDir+sep) {
			return fmt.Errorf("archive entry escapes target")
		}
		if err := os.MkdirAll(filepath.Dir(dst), 0o755); err != nil {
			return err
		}
		if f.FileInfo().IsDir() {
			if err := os.MkdirAll(dst, 0o755); err != nil {
				return err
			}
			continue
		}
		if f.UncompressedSize64 > uint64(maxUnzipBytes) {
			return fmt.Errorf("archive entry too large: %s", filepath.ToSlash(name))
		}
		if totalUncompressed > maxUnzipBytes-int64(f.UncompressedSize64) {
			return fmt.Errorf("archive uncompressed size exceeds limit (%d bytes)", maxUnzipBytes)
		}
		totalUncompressed += int64(f.UncompressedSize64)
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.OpenFile(dst, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, f.Mode().Perm())
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

func normalizeUnzipIfExists(s string) string {
	switch strings.ToLower(strings.TrimSpace(s)) {
	case "overwrite":
		return "overwrite"
	case "skip":
		return "skip"
	default:
		return "fail"
	}
}

func collectUnzipConflicts(srcDir, dstDir string) ([]string, error) {
	conflicts := make([]string, 0, 8)
	err := filepath.Walk(srcDir, func(path string, info fs.FileInfo, err error) error {
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(srcDir, path)
		if err != nil {
			return err
		}
		if rel == "." || info.IsDir() {
			return nil
		}
		dst := filepath.Join(dstDir, rel)
		if _, err := os.Stat(dst); err == nil {
			conflicts = append(conflicts, filepath.ToSlash(rel))
		} else if !errors.Is(err, os.ErrNotExist) {
			return err
		}
		return nil
	})
	return conflicts, err
}

func mergeUnzippedDirectory(srcDir, dstDir, ifExists string) error {
	return filepath.Walk(srcDir, func(path string, info fs.FileInfo, err error) error {
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(srcDir, path)
		if err != nil {
			return err
		}
		if rel == "." {
			return nil
		}
		dst := filepath.Join(dstDir, rel)
		if info.IsDir() {
			return os.MkdirAll(dst, 0o755)
		}
		if st, err := os.Stat(dst); err == nil {
			if st.IsDir() {
				return fmt.Errorf("cannot overwrite directory with file: %s", filepath.ToSlash(rel))
			}
			switch ifExists {
			case "skip":
				return nil
			case "overwrite":
				if err := os.Remove(dst); err != nil {
					return err
				}
			default:
				return fmt.Errorf("target already exists: %s", filepath.ToSlash(rel))
			}
		} else if !errors.Is(err, os.ErrNotExist) {
			return err
		}
		return copyFile(path, dst, info.Mode())
	})
}

func UnzipPath(root, archiveRel, targetDirRel, ifExists string) error {
	arc, err := ResolveUnderRoot(root, archiveRel)
	if err != nil {
		return err
	}
	finalDir, err := ResolveUnderRoot(root, targetDirRel)
	if err != nil {
		return err
	}
	parent := filepath.Dir(finalDir)
	if err := os.MkdirAll(parent, 0o755); err != nil {
		return err
	}

	id := randomTempID()
	tmpDir := filepath.Join(parent, ".tmp_unzip_"+id)
	if _, err := os.Stat(tmpDir); err == nil {
		return fmt.Errorf("temporary unzip path already exists")
	}
	if err := os.Mkdir(tmpDir, 0o755); err != nil {
		return err
	}

	committed := false
	defer func() {
		if !committed {
			_ = os.RemoveAll(tmpDir)
		}
	}()

	zr, err := zip.OpenReader(arc)
	if err != nil {
		return err
	}
	defer zr.Close()

	if err := unzipIntoDirectory(tmpDir, &zr.Reader); err != nil {
		return err
	}

	ifExists = normalizeUnzipIfExists(ifExists)

	fi, err := os.Stat(finalDir)
	if errors.Is(err, os.ErrNotExist) {
		if err := os.MkdirAll(finalDir, 0o755); err != nil {
			return err
		}
	}
	if err != nil {
		return err
	}
	if fi != nil && !fi.IsDir() {
		return fmt.Errorf("unzip target exists and is not a directory")
	}

	if ifExists == "fail" {
		conflicts, err := collectUnzipConflicts(tmpDir, finalDir)
		if err != nil {
			return err
		}
		if len(conflicts) > 0 {
			sample := conflicts
			if len(sample) > 5 {
				sample = sample[:5]
			}
			return fmt.Errorf("conflicts detected (%d): %s", len(conflicts), strings.Join(sample, ", "))
		}
	}
	if err := mergeUnzippedDirectory(tmpDir, finalDir, ifExists); err != nil {
		return err
	}

	committed = true
	return nil
}

// randomTempID üretir; geçici dizin/dosya adları için kullanılır.
func randomTempID() string {
	var b [8]byte
	if _, err := rand.Read(b[:]); err != nil {
		return strconv.FormatInt(int64(os.Getpid()), 36) + strconv.FormatInt(time.Now().UnixNano(), 36)
	}
	return hex.EncodeToString(b[:])
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
