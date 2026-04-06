package hosting

import (
	"os"
	"path/filepath"
)

// DirSizeBytes site dizinindeki dosyaların toplam boyutu (alt dizinler dahil).
func DirSizeBytes(root string) (int64, error) {
	root = filepath.Clean(root)
	var total int64
	err := filepath.Walk(root, func(path string, info os.FileInfo, err error) error {
		if err != nil {
			if os.IsNotExist(err) {
				return nil
			}
			return err
		}
		if info.IsDir() {
			return nil
		}
		total += info.Size()
		return nil
	})
	if err != nil {
		return 0, err
	}
	return total, nil
}
