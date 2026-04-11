package hosting

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"hostvim/engine/internal/config"
	"hostvim/engine/internal/nginx"
	"hostvim/engine/internal/panelmirror"
	"hostvim/engine/internal/phpfpm"
	"hostvim/engine/internal/sites"
	"hostvim/engine/internal/ssl"
)

// ReplaceHostRootSuffix eski kök FQDN (ör. eski.com) ile biten ana makine adını yenisine çevirir.
func ReplaceHostRootSuffix(host, oldRoot, newRoot string) string {
	h := strings.ToLower(strings.TrimSpace(host))
	oldRoot = strings.ToLower(strings.TrimSpace(oldRoot))
	newRoot = strings.ToLower(strings.TrimSpace(newRoot))
	if h == "" || oldRoot == "" || newRoot == "" {
		return host
	}
	if h == oldRoot {
		return newRoot
	}
	suf := "." + oldRoot
	if strings.HasSuffix(h, suf) {
		return strings.TrimSuffix(h, suf) + "." + newRoot
	}
	return h
}

func listSubdomainPathSegments(webRoot, parent string) ([]string, error) {
	subDir := filepath.Join(webRoot, parent, ".hostvim", "subdomains")
	entries, err := os.ReadDir(subDir)
	if err != nil {
		if os.IsNotExist(err) {
			subDir = filepath.Join(webRoot, parent, ".panelsar", "subdomains")
			entries, err = os.ReadDir(subDir)
		}
		if err != nil {
			if os.IsNotExist(err) {
				return nil, nil
			}
			return nil, err
		}
	}
	var segs []string
	for _, e := range entries {
		if e.IsDir() || !strings.HasSuffix(e.Name(), ".json") {
			continue
		}
		seg := strings.TrimSuffix(e.Name(), ".json")
		if seg != "" {
			segs = append(segs, seg)
		}
	}
	return segs, nil
}

// RenamePrimarySite web dizinini, engine-state aynalarını ve vhost’ları from → to taşır.
func RenamePrimarySite(cfg *config.Config, from, to string) error {
	from = strings.ToLower(strings.TrimSpace(from))
	to = strings.ToLower(strings.TrimSpace(to))
	if from == "" || to == "" || from == to || strings.Contains(from, "..") || strings.Contains(to, "..") {
		return fmt.Errorf("invalid rename request")
	}
	if !sites.IsValidDomain(from) || !sites.IsValidDomain(to) {
		return fmt.Errorf("invalid domain name")
	}
	if !nginx.DomainSafe(from) || !nginx.DomainSafe(to) {
		return fmt.Errorf("domain not safe for web server")
	}
	webRoot := filepath.Clean(cfg.Paths.WebRoot)
	if webRoot == "" || webRoot == "." {
		return fmt.Errorf("web_root not configured")
	}
	fromDir := filepath.Join(webRoot, from)
	toDir := filepath.Join(webRoot, to)
	fi, err := os.Stat(fromDir)
	if err != nil || !fi.IsDir() {
		return fmt.Errorf("source site directory not found")
	}
	if _, err := os.Stat(toDir); err == nil {
		return fmt.Errorf("target domain directory already exists")
	} else if !os.IsNotExist(err) {
		return err
	}

	meta, err := sites.ReadSiteMeta(webRoot, from)
	if err != nil {
		return err
	}
	if meta == nil {
		return fmt.Errorf("site meta not found for %s", from)
	}

	segments, err := listSubdomainPathSegments(webRoot, from)
	if err != nil {
		return err
	}
	subMetas := make(map[string]*sites.SiteMeta)
	for _, seg := range segments {
		sm, rerr := sites.ReadSubdomainMeta(webRoot, from, seg)
		if rerr != nil {
			return rerr
		}
		if sm == nil {
			continue
		}
		subMetas[seg] = sm
		h := strings.ToLower(strings.TrimSpace(sm.Hostname))
		if h != "" {
			if err := RemoveSubdomainVhost(cfg, h, sm); err != nil {
				return fmt.Errorf("remove subdomain vhost %s: %w", sm.Hostname, err)
			}
		}
	}

	if meta.SSLEnabled {
		_ = ssl.Delete(cfg, from)
		meta.SSLEnabled = false
	}
	RemoveWebServerForSiteDeletion(cfg, from)

	if err := panelmirror.RenameDomainStateFiles(cfg, from, to); err != nil {
		return fmt.Errorf("rename engine-state: %w", err)
	}

	if rel, err := filepath.Rel(fromDir, meta.DocumentRoot); err == nil {
		meta.DocumentRoot = filepath.Join(toDir, rel)
	} else {
		meta.DocumentRoot = filepath.Join(toDir, "public_html")
	}
	for i := range meta.Aliases {
		meta.Aliases[i] = ReplaceHostRootSuffix(meta.Aliases[i], from, to)
	}

	for seg, sm := range subMetas {
		sm.Hostname = ReplaceHostRootSuffix(sm.Hostname, from, to)
		subBaseOld := filepath.Join(fromDir, seg)
		if rel, err := filepath.Rel(subBaseOld, sm.DocumentRoot); err == nil {
			sm.DocumentRoot = filepath.Join(toDir, seg, rel)
		} else {
			sm.DocumentRoot = filepath.Join(toDir, seg, "public_html")
		}
		_ = seg
	}

	if err := os.Rename(fromDir, toDir); err != nil {
		return fmt.Errorf("rename site directory: %w", err)
	}

	if err := sites.WriteSiteMeta(webRoot, to, meta); err != nil {
		return err
	}
	for seg, sm := range subMetas {
		if err := sites.WriteSubdomainMeta(webRoot, to, seg, sm); err != nil {
			return err
		}
	}

	ps := poolSettings(cfg)
	phpV := meta.PHPVersion
	if strings.TrimSpace(phpV) == "" {
		phpV = "8.2"
	}
	phpV = phpfpm.NormalizeVersion(phpV)
	phpSocket := nginx.EffectivePHPSocket(phpV, cfg.Hosting.PHPFPMsocket)
	if cfg.Hosting.PHPFPMmanagePools {
		removed := phpfpm.RemovePoolBestEffortAllVersions(ps, from)
		sock, _, _, perr := phpfpm.WritePool(ps, to, phpV, meta.DocumentRoot)
		if perr != nil {
			return fmt.Errorf("php-fpm pool for new domain: %w", perr)
		}
		phpSocket = sock
		if cfg.Hosting.PHPFPMreloadAfterPool {
			_ = phpfpm.Reload(phpV)
			for _, rv := range removed {
				if rv != "" && rv != phpV {
					_ = phpfpm.Reload(rv)
				}
			}
		}
	} else if cfg.Hosting.PHPFPMreloadAfterPool {
		_ = phpfpm.Reload(phpV)
	}

	if err := ApplyWebServer(cfg, to, meta.DocumentRoot, meta, phpSocket); err != nil {
		return fmt.Errorf("apply main vhost: %w", err)
	}

	for seg, sm := range subMetas {
		if err := ApplySubdomainVhost(cfg, to, sm.Hostname, sm.DocumentRoot, sm); err != nil {
			return fmt.Errorf("apply subdomain vhost %s: %w", sm.Hostname, err)
		}
		_ = seg
	}

	return nil
}
