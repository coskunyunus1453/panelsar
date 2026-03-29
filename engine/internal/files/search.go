package files

import (
	"bufio"
	"bytes"
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"strings"
	"unicode/utf8"
)

// SearchHit — metin araması sonucu (alan adı köküne göre göreli yol).
type SearchHit struct {
	Path    string `json:"path"`
	Line    int    `json:"line"`
	Preview string `json:"preview"`
}

func isSkippableDir(name string) bool {
	switch name {
	case "node_modules", ".git", "vendor", "__pycache__", ".svn", ".hg", ".idea", "dist", "build":
		return true
	default:
		return false
	}
}

func isProbablyBinary(b []byte) bool {
	n := len(b)
	if n > 8192 {
		n = 8192
	}
	return bytes.IndexByte(b[:n], 0) >= 0
}

// SearchText root = alan adı kökü (…/data/www/example.com), relDir = arama başlangıç alt yolu.
func SearchText(root, relDir, query string, maxHits, maxDepth int, maxFileBytes int64) ([]SearchHit, error) {
	if maxHits <= 0 {
		maxHits = 200
	}
	if maxDepth <= 0 {
		maxDepth = 14
	}
	if maxFileBytes <= 0 {
		maxFileBytes = 2 << 20
	}
	query = strings.TrimSpace(query)
	if query == "" {
		return nil, fmt.Errorf("empty query")
	}
	qLower := strings.ToLower(query)

	base, err := ResolveUnderRoot(root, relDir)
	if err != nil {
		return nil, err
	}
	st, err := os.Stat(base)
	if err != nil {
		return nil, err
	}
	if !st.IsDir() {
		return nil, fmt.Errorf("search path is not a directory")
	}

	var hits []SearchHit
	_ = filepath.WalkDir(base, func(path string, d fs.DirEntry, werr error) error {
		if werr != nil {
			return nil
		}
		if len(hits) >= maxHits {
			return fs.SkipAll
		}
		if d.IsDir() {
			if isSkippableDir(d.Name()) {
				return fs.SkipDir
			}
			relFromBase, _ := filepath.Rel(base, path)
			if relFromBase != "." {
				depth := strings.Count(relFromBase, string(filepath.Separator)) + 1
				if depth > maxDepth {
					return fs.SkipDir
				}
			}
			return nil
		}

		info, err := d.Info()
		if err != nil || info.IsDir() {
			return nil
		}
		if info.Size() > maxFileBytes {
			return nil
		}

		data, err := os.ReadFile(path)
		if err != nil {
			return nil
		}
		if isProbablyBinary(data) || !utf8.Valid(data) {
			return nil
		}
		relToDomain, err := filepath.Rel(root, path)
		if err != nil {
			return nil
		}
		relSlash := filepath.ToSlash(relToDomain)

		low := strings.ToLower(string(data))
		if !strings.Contains(low, qLower) {
			return nil
		}

		s := bufio.NewScanner(bytes.NewReader(data))
		lineNum := 0
		for s.Scan() {
			lineNum++
			if len(hits) >= maxHits {
				return fs.SkipAll
			}
			line := s.Text()
			if strings.Contains(strings.ToLower(line), qLower) {
				prev := line
				if len(prev) > 280 {
					prev = prev[:280] + "…"
				}
				hits = append(hits, SearchHit{
					Path:    relSlash,
					Line:    lineNum,
					Preview: prev,
				})
			}
		}
		return nil
	})

	return hits, nil
}
