// Package stack — panelden tetiklenen beyaz listeli sunucu paketleri (apt).
// Tam apt/dpkg geri alması bu katmanda yok; betik hata verirse çıktı API’ye döner.
// Betik yolu engine.yaml → hosting.stack_install_script ile değiştirilebilir.
package stack

import (
	"errors"
	"os/exec"
	"strings"

	"hostvim/engine/internal/config"
)

const defaultInstallScript = "/usr/local/sbin/hostvim-stack-install"

func stackInstallScriptPath(cfg *config.Config) string {
	if cfg == nil {
		return defaultInstallScript
	}
	s := strings.TrimSpace(cfg.Hosting.StackInstallScript)
	if s != "" {
		return s
	}
	return defaultInstallScript
}

// Module — admin arayüzünde listelenen demet.
type Module struct {
	ID          string `json:"id"`
	Category    string `json:"category"`
	Title       string `json:"title"`
	Description string `json:"description"`
	CheckPkg    string `json:"check_package"`
	Installed   bool   `json:"installed"`
}

// Catalog sabit liste (Go ile senkron; betikteki case ile aynı ID’ler).
func Catalog() []Module {
	return []Module{
		{
			ID: "php-8-3-fpm-extra", Category: "php",
			Title: "PHP 8.3 FPM + uzantılar", Description: "FPM, MySQL, mbstring, xml, zip, curl, intl, bcmath, sqlite",
			CheckPkg: "php8.3-fpm",
		},
		{
			ID: "php-8-2-fpm-extra", Category: "php",
			Title: "PHP 8.2 FPM + uzantılar", Description: "FPM, MySQL, mbstring, xml, zip, curl, intl, bcmath, sqlite",
			CheckPkg: "php8.2-fpm",
		},
		{
			ID: "mail-postfix-relay", Category: "mail",
			Title: "Postfix (SMTP gönderim)", Description: "İnternet sitesi tipi; panel/Laravel .env ile SMTP kullanımına uygun temel MTA",
			CheckPkg: "postfix",
		},
		{
			ID: "mail-dovecot-imap", Category: "mail",
			Title: "Dovecot (IMAP)", Description: "Gelen posta kutusu sunucusu — DNS/MX ve kullanıcı yapılandırması ayrıca gerekir",
			CheckPkg: "dovecot-core",
		},
		{
			ID: "mail-opendkim", Category: "mail",
			Title: "OpenDKIM", Description: "DKIM imzalama (DNS TXT kayıtları ve postfix entegrasyonu elle tamamlanır)",
			CheckPkg: "opendkim",
		},
		{
			ID: "mail-stack-webmail", Category: "mail",
			Title: "Tam posta + Roundcube webmail", Description: "Postfix (25/587/465 TLS) + Dovecot (IMAP) + OpenDKIM + Nginx + Roundcube (SQLite); müşteri webmail.* üzerinden",
			CheckPkg: "roundcube-core",
		},
	}
}

func dpkgInstalled(pkg string) bool {
	if strings.TrimSpace(pkg) == "" {
		return false
	}
	out, err := exec.Command("dpkg-query", "-W", "-f=${Status}", pkg).CombinedOutput()
	if err != nil {
		return false
	}
	s := strings.TrimSpace(string(out))
	return strings.HasPrefix(s, "install ok")
}

// ModulesWithStatus catalog + kurulu mu bilgisi.
func ModulesWithStatus() []Module {
	list := Catalog()
	for i := range list {
		list[i].Installed = dpkgInstalled(list[i].CheckPkg)
	}
	return list
}

// ValidBundle ID beyaz listede mi.
func ValidBundle(id string) bool {
	id = strings.TrimSpace(id)
	for _, m := range Catalog() {
		if m.ID == id {
			return true
		}
	}
	return false
}

// ErrUnknownBundle — ID beyaz listede değil.
var ErrUnknownBundle = errors.New("bilinmeyen paket demeti")

// InstallBundle sudo ile yapılandırılmış stack betiğini çalıştırır (engine www-data).
func InstallBundle(cfg *config.Config, id string) (string, error) {
	id = strings.TrimSpace(id)
	if !ValidBundle(id) {
		return "", ErrUnknownBundle
	}
	script := stackInstallScriptPath(cfg)
	cmd := exec.Command("sudo", script, id)
	out, err := cmd.CombinedOutput()
	return strings.TrimSpace(string(out)), err
}
