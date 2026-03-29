package files

import (
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"sort"
	"strings"
)

type ListEntry struct {
	Name  string `json:"name"`
	IsDir bool   `json:"is_dir"`
	Size  int64  `json:"size"`
	Mode  string `json:"mode"`
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

func Mkdir(root, rel string) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	return os.MkdirAll(base, 0o755)
}

func Remove(root, rel string) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	return os.RemoveAll(base)
}

func WriteFile(root, rel string, data []byte, perm fs.FileMode) error {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(base), 0o755); err != nil {
		return err
	}
	return os.WriteFile(base, data, perm)
}

func ReadFile(root, rel string) ([]byte, error) {
	base, err := ResolveUnderRoot(root, rel)
	if err != nil {
		return nil, err
	}
	return os.ReadFile(base)
}

// MaxEditorFileSize panel düzenleyicisi için üst boyut.
const MaxEditorFileSize int64 = 6 << 20

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
