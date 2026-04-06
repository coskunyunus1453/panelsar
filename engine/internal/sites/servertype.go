package sites

import "strings"

// NormalizeServerType panel / engine genelinde tek değer: nginx | apache | openlitespeed.
func NormalizeServerType(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	switch s {
	case "apache":
		return "apache"
	case "openlitespeed", "ols", "litespeed":
		return "openlitespeed"
	default:
		return "nginx"
	}
}
