package sites

import (
	"fmt"
	"strings"

	"github.com/panelsar/engine/internal/nginx"
)

// AppendAlias ana site meta’sına alias ekler (FQDN, küçük harf).
func AppendAlias(webRoot, primary, alias string) error {
	primary = strings.ToLower(strings.TrimSpace(primary))
	alias = strings.ToLower(strings.TrimSpace(alias))
	if primary == "" || strings.Contains(primary, "..") {
		return fmt.Errorf("invalid primary domain")
	}
	if alias == "" {
		return fmt.Errorf("invalid alias")
	}
	if !nginx.DomainSafe(primary) || !nginx.DomainSafe(alias) {
		return fmt.Errorf("invalid hostname format")
	}
	if alias == primary {
		return fmt.Errorf("alias equals primary")
	}
	m, err := ReadSiteMeta(webRoot, primary)
	if err != nil {
		return err
	}
	if m == nil {
		return fmt.Errorf("site not found")
	}
	seen := map[string]struct{}{primary: {}, "www." + primary: {}}
	for _, a := range m.Aliases {
		seen[strings.ToLower(strings.TrimSpace(a))] = struct{}{}
	}
	if _, ok := seen[alias]; ok {
		return fmt.Errorf("alias already present")
	}
	m.Aliases = append(m.Aliases, alias)
	return WriteSiteMeta(webRoot, primary, m)
}

// RemoveAlias ana siteden alias çıkarır.
func RemoveAlias(webRoot, primary, alias string) error {
	primary = strings.ToLower(strings.TrimSpace(primary))
	alias = strings.ToLower(strings.TrimSpace(alias))
	if primary == "" {
		return fmt.Errorf("invalid primary domain")
	}
	m, err := ReadSiteMeta(webRoot, primary)
	if err != nil {
		return err
	}
	if m == nil {
		return fmt.Errorf("site not found")
	}
	var next []string
	for _, a := range m.Aliases {
		if strings.ToLower(strings.TrimSpace(a)) != alias {
			next = append(next, a)
		}
	}
	m.Aliases = next
	return WriteSiteMeta(webRoot, primary, m)
}
