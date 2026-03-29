// Package stack — panelden tetiklenen beyaz listeli sunucu paketleri (apt).
package stack

import (
	"errors"
	"os/exec"
	"strings"
)

const installScript = "/usr/local/sbin/panelsar-stack-install"

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

// InstallBundle sudo ile panelsar-stack-install çalıştırır (engine www-data).
func InstallBundle(id string) (string, error) {
	id = strings.TrimSpace(id)
	if !ValidBundle(id) {
		return "", ErrUnknownBundle
	}
	cmd := exec.Command("sudo", installScript, id)
	out, err := cmd.CombinedOutput()
	return strings.TrimSpace(string(out)), err
}
