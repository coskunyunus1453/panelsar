package api

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/panelmirror"
)

type securityIntelPolicy struct {
	Mode           string   `json:"mode"`
	CountriesAllow []string `json:"countries_allow"`
	CountriesDeny  []string `json:"countries_deny"`
	ASNAllow       []int    `json:"asn_allow"`
	ASNDeny        []int    `json:"asn_deny"`
	MinRiskScore   int      `json:"min_risk_score"`
	PanelAllowlist []string `json:"panel_allowlist"`
	UpdatedAt      string   `json:"updated_at"`
}

type securityIntelStatus struct {
	DBVersion          string `json:"db_version"`
	LastUpdate         string `json:"last_update"`
	TotalMatches       int    `json:"total_matches"`
	ObserveOnly        bool   `json:"observe_only"`
	EnforcementReady   bool   `json:"enforcement_ready"`
	PrivateIPGeoBypass bool   `json:"private_ip_geo_bypass"`
}

func defaultSecurityIntelPolicy() securityIntelPolicy {
	return securityIntelPolicy{
		Mode:           "observe",
		CountriesAllow: []string{},
		CountriesDeny:  []string{},
		ASNAllow:       []int{},
		ASNDeny:        []int{},
		MinRiskScore:   70,
		PanelAllowlist: []string{},
		UpdatedAt:      time.Now().UTC().Format(time.RFC3339),
	}
}

func engineStateDir(cfg *config.Config) string {
	return panelmirror.EngineStateDir(cfg)
}

func securityIntelPolicyPath(cfg *config.Config) string {
	return filepath.Join(engineStateDir(cfg), "security-intel-policy.json")
}

func securityIntelStatusPath(cfg *config.Config) string {
	return filepath.Join(engineStateDir(cfg), "security-intel-status.json")
}

func loadSecurityIntelPolicy(cfg *config.Config) (securityIntelPolicy, error) {
	p := securityIntelPolicyPath(cfg)
	b, err := os.ReadFile(p)
	if err != nil {
		if os.IsNotExist(err) {
			return defaultSecurityIntelPolicy(), nil
		}
		return securityIntelPolicy{}, err
	}
	var policy securityIntelPolicy
	if err := json.Unmarshal(b, &policy); err != nil {
		return securityIntelPolicy{}, err
	}
	return normalizeSecurityIntelPolicy(policy)
}

func saveSecurityIntelPolicy(cfg *config.Config, policy securityIntelPolicy) error {
	norm, err := normalizeSecurityIntelPolicy(policy)
	if err != nil {
		return err
	}
	norm.UpdatedAt = time.Now().UTC().Format(time.RFC3339)
	if err := os.MkdirAll(engineStateDir(cfg), 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(norm, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(securityIntelPolicyPath(cfg), b, 0o640)
}

func loadSecurityIntelStatus(cfg *config.Config) (securityIntelStatus, error) {
	policy, err := loadSecurityIntelPolicy(cfg)
	if err != nil {
		return securityIntelStatus{}, err
	}
	p := securityIntelStatusPath(cfg)
	b, err := os.ReadFile(p)
	if err != nil {
		if os.IsNotExist(err) {
			return securityIntelStatus{
				DBVersion:          "local-demo-v1",
				LastUpdate:         policy.UpdatedAt,
				TotalMatches:       0,
				ObserveOnly:        strings.EqualFold(policy.Mode, "observe"),
				EnforcementReady:   true,
				PrivateIPGeoBypass: true,
			}, nil
		}
		return securityIntelStatus{}, err
	}
	var status securityIntelStatus
	if err := json.Unmarshal(b, &status); err != nil {
		return securityIntelStatus{}, err
	}
	if strings.TrimSpace(status.DBVersion) == "" {
		status.DBVersion = "local-demo-v1"
	}
	status.ObserveOnly = strings.EqualFold(policy.Mode, "observe")
	status.EnforcementReady = true
	status.PrivateIPGeoBypass = true
	return status, nil
}

func saveSecurityIntelStatus(cfg *config.Config, status securityIntelStatus) error {
	if err := os.MkdirAll(engineStateDir(cfg), 0o750); err != nil {
		return err
	}
	b, err := json.MarshalIndent(status, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(securityIntelStatusPath(cfg), b, 0o640)
}

func normalizeCountryCodes(values []string) []string {
	out := make([]string, 0, len(values))
	seen := make(map[string]struct{}, len(values))
	for _, v := range values {
		code := strings.ToUpper(strings.TrimSpace(v))
		if len(code) != 2 {
			continue
		}
		if code[0] < 'A' || code[0] > 'Z' || code[1] < 'A' || code[1] > 'Z' {
			continue
		}
		if _, ok := seen[code]; ok {
			continue
		}
		seen[code] = struct{}{}
		out = append(out, code)
	}
	return out
}

func normalizeASNList(values []int) []int {
	out := make([]int, 0, len(values))
	seen := make(map[int]struct{}, len(values))
	for _, v := range values {
		if v <= 0 || v > 429496729 {
			continue
		}
		if _, ok := seen[v]; ok {
			continue
		}
		seen[v] = struct{}{}
		out = append(out, v)
	}
	return out
}

func normalizePanelAllowlist(values []string) []string {
	out := make([]string, 0, len(values))
	seen := make(map[string]struct{}, len(values))
	for _, v := range values {
		trimmed := strings.TrimSpace(v)
		if trimmed == "" {
			continue
		}
		if _, ok := seen[trimmed]; ok {
			continue
		}
		seen[trimmed] = struct{}{}
		out = append(out, trimmed)
	}
	return out
}

func normalizeSecurityIntelPolicy(policy securityIntelPolicy) (securityIntelPolicy, error) {
	mode := strings.ToLower(strings.TrimSpace(policy.Mode))
	if mode == "" {
		mode = "observe"
	}
	if mode != "observe" && mode != "enforce" {
		return securityIntelPolicy{}, fmt.Errorf("invalid mode")
	}
	policy.Mode = mode
	policy.CountriesAllow = normalizeCountryCodes(policy.CountriesAllow)
	policy.CountriesDeny = normalizeCountryCodes(policy.CountriesDeny)
	policy.ASNAllow = normalizeASNList(policy.ASNAllow)
	policy.ASNDeny = normalizeASNList(policy.ASNDeny)
	policy.PanelAllowlist = normalizePanelAllowlist(policy.PanelAllowlist)
	if policy.MinRiskScore < 0 {
		policy.MinRiskScore = 0
	}
	if policy.MinRiskScore > 100 {
		policy.MinRiskScore = 100
	}
	return policy, nil
}
