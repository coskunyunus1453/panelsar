package models

import "time"

type Site struct {
	ID           uint      `json:"id"`
	Domain       string    `json:"domain"`
	UserID       uint      `json:"user_id"`
	DocumentRoot string    `json:"document_root"`
	PHPVersion   string    `json:"php_version"`
	ServerType   string    `json:"server_type"`
	SSLEnabled   bool      `json:"ssl_enabled"`
	Status       string    `json:"status"`
	CreatedAt    time.Time `json:"created_at"`
}

type SiteCreateRequest struct {
	Domain     string `json:"domain" binding:"required"`
	UserID     uint   `json:"user_id" binding:"required"`
	PHPVersion string `json:"php_version"`
	ServerType string `json:"server_type"`
}

type SSLRequest struct {
	Domain string `json:"domain" binding:"required"`
}

type NginxVhost struct {
	Domain       string
	DocumentRoot string
	PHPVersion   string
	SSLEnabled   bool
	SSLCertPath  string
	SSLKeyPath   string
	AccessLog    string
	ErrorLog     string
}

func DefaultNginxVhost(domain, phpVersion string) NginxVhost {
	return NginxVhost{
		Domain:       domain,
		DocumentRoot: "/var/www/" + domain + "/public_html",
		PHPVersion:   phpVersion,
		SSLEnabled:   false,
		AccessLog:    "/var/log/nginx/" + domain + ".access.log",
		ErrorLog:     "/var/log/nginx/" + domain + ".error.log",
	}
}
