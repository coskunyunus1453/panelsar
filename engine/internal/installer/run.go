package installer

import (
	"fmt"
	"strings"

	"hostvim/engine/internal/config"
)

// DBConfig WordPress vb. kurulumlar için MySQL bağlantısı.
type DBConfig struct {
	Host        string
	Port        int
	Name        string
	User        string
	Password    string
	TablePrefix string
}

// Run idempotent uygulama kurulumu (wordpress, opencart).
func Run(cfg *config.Config, app, domain string, db *DBConfig, opts *Options) error {
	app = strings.ToLower(strings.TrimSpace(app))
	switch app {
	case "wordpress":
		if db == nil || strings.TrimSpace(db.Name) == "" || strings.TrimSpace(db.User) == "" {
			return fmt.Errorf("wordpress için db_name ve db_user gerekli")
		}
		return installWordPress(cfg, domain, db, opts)
	case "opencart":
		return installOpenCart(cfg, domain, db)
	default:
		return fmt.Errorf("desteklenmeyen uygulama: %q — wordpress veya opencart kullanın", app)
	}
}
