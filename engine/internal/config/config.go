package config

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"github.com/spf13/viper"
)

type Config struct {
	Server    ServerConfig    `mapstructure:"server"`
	Database  DatabaseConfig  `mapstructure:"database"`
	Docker    DockerConfig    `mapstructure:"docker"`
	Services  ServicesConfig  `mapstructure:"services"`
	Security  SecurityConfig  `mapstructure:"security"`
	Paths     PathsConfig     `mapstructure:"paths"`
	Hosting   HostingConfig   `mapstructure:"hosting"`
}

type ServerConfig struct {
	Port               int    `mapstructure:"port"`
	Host               string `mapstructure:"host"`
	SecretKey          string `mapstructure:"secret_key"`
	Debug              bool   `mapstructure:"debug"`
	PrometheusEnabled  bool   `mapstructure:"prometheus_enabled"`
	PrometheusPath     string `mapstructure:"prometheus_path"`
	// HTTP sunucu zaman aşımları (saniye). Dosya yükleme / yedek gibi büyük gövdeler için düşük değer 502 üretebilir.
	ReadTimeoutSeconds  int `mapstructure:"read_timeout_seconds"`
	WriteTimeoutSeconds int `mapstructure:"write_timeout_seconds"`
	IdleTimeoutSeconds  int `mapstructure:"idle_timeout_seconds"`
}

type DatabaseConfig struct {
	Host     string `mapstructure:"host"`
	Port     int    `mapstructure:"port"`
	Name     string `mapstructure:"name"`
	User     string `mapstructure:"user"`
	Password string `mapstructure:"password"`
}

type DockerConfig struct {
	Enabled    bool   `mapstructure:"enabled"`
	SocketPath string `mapstructure:"socket_path"`
	Network    string `mapstructure:"network"`
}

type ServicesConfig struct {
	Nginx    ServiceConfig `mapstructure:"nginx"`
	Apache   ServiceConfig `mapstructure:"apache"`
	PHPFPM   ServiceConfig `mapstructure:"php_fpm"`
	MySQL    ServiceConfig `mapstructure:"mysql"`
	Postfix  ServiceConfig `mapstructure:"postfix"`
	Dovecot  ServiceConfig `mapstructure:"dovecot"`
	Redis    ServiceConfig `mapstructure:"redis"`
}

type ServiceConfig struct {
	Enabled    bool   `mapstructure:"enabled"`
	ConfigPath string `mapstructure:"config_path"`
	BinaryPath string `mapstructure:"binary_path"`
}

type SecurityConfig struct {
	JWTSecret        string `mapstructure:"jwt_secret"`
	InternalAPIKey   string `mapstructure:"internal_api_key"`
	AllowedOrigins   string `mapstructure:"allowed_origins"`
	TokenExpiry      int    `mapstructure:"token_expiry"`
	MaxLoginAttempts int    `mapstructure:"max_login_attempts"`
	Fail2banEnabled  bool   `mapstructure:"fail2ban_enabled"`
}

type PathsConfig struct {
	WebRoot   string `mapstructure:"web_root"`
	VhostsDir string `mapstructure:"vhosts_dir"`
	SSLDir    string `mapstructure:"ssl_dir"`
	BackupDir string `mapstructure:"backup_dir"`
	LogDir    string `mapstructure:"log_dir"`
	TempDir   string `mapstructure:"temp_dir"`
}

// HostingConfig — Faz 1: gerçek web sunucusu entegrasyonu (varsayılanlar güvenli: kapalı).
type HostingConfig struct {
	NginxManageVhosts     bool   `mapstructure:"nginx_manage_vhosts"`
	NginxSitesEnabled     string `mapstructure:"nginx_sites_enabled"`
	PHPFPMsocket          string `mapstructure:"php_fpm_socket"`
	NginxReloadAfterVhost bool   `mapstructure:"nginx_reload_after_vhost"`
	PHPFPMmanagePools     bool   `mapstructure:"php_fpm_manage_pools"`
	PHPFPMlistenDir       string `mapstructure:"php_fpm_listen_dir"`
	PHPFPMpoolDirTemplate string `mapstructure:"php_fpm_pool_dir_template"`
	PHPFPMreloadAfterPool bool   `mapstructure:"php_fpm_reload_after_pool"`
	PHPFPMpoolUser        string `mapstructure:"php_fpm_pool_user"`
	PHPFPMpoolGroup       string `mapstructure:"php_fpm_pool_group"`

	ApacheManageVhosts     bool   `mapstructure:"apache_manage_vhosts"`
	ApacheSitesAvailable   string `mapstructure:"apache_sites_available"`
	ApacheSitesEnabled     string `mapstructure:"apache_sites_enabled"`
	ApacheReloadAfterVhost bool   `mapstructure:"apache_reload_after_vhost"`
	// ApacheHTTPPort — Nginx panel 80 kullanırken 8080 (çakışmasız). 0 veya yoksa 80.
	ApacheHTTPPort int `mapstructure:"apache_http_port"`

	// OpenLiteSpeed (server_type: openlitespeed) — conf.d parçaları + listener map dosyaları.
	OLSManageVhosts       bool   `mapstructure:"openlitespeed_manage_vhosts"`
	OLSConfRoot           string `mapstructure:"openlitespeed_conf_root"`
	OLSReloadAfterVhost   bool   `mapstructure:"openlitespeed_reload_after_vhost"`
	OLSCtrlPath           string `mapstructure:"openlitespeed_ctrl_path"`

	ManageSSL               bool   `mapstructure:"manage_ssl"`
	CertbotPath             string `mapstructure:"certbot_path"`
	LetsEncryptEmail        string `mapstructure:"lets_encrypt_email"`
	LetsEncryptStaging      bool   `mapstructure:"lets_encrypt_staging"`
	LetsEncryptIncludeWww bool `mapstructure:"lets_encrypt_include_www"`

	ManageSiteTools bool   `mapstructure:"manage_site_tools"`
	ComposerPath    string `mapstructure:"composer_path"`
	NpmPath         string `mapstructure:"npm_path"`
	ToolsMaxSeconds int    `mapstructure:"tools_max_seconds"`
	WordPressZipURL  string `mapstructure:"wordpress_zip_url"`
	OpenCartZipURL   string `mapstructure:"opencart_zip_url"`

	// NginxVhostHelper — sudo ile çağrılan betik (varsayılan: /usr/local/sbin/hostvim-nginx-vhost).
	NginxVhostHelper string `mapstructure:"nginx_vhost_helper"`
	// StackInstallScript — panel stack demetleri için sudo betiği (varsayılan: /usr/local/sbin/hostvim-stack-install).
	StackInstallScript string `mapstructure:"stack_install_script"`

	// Faz 6 — gerçek dosya yedeği (Linux prod: execute_backups true; XAMPP’te false bırakın)
	ExecuteBackups       bool   `mapstructure:"execute_backups"`
	BackupTarPath        string `mapstructure:"backup_tar_path"`
	BackupMaxSeconds     int    `mapstructure:"backup_max_seconds"`
	ExecuteBackupRestore bool   `mapstructure:"execute_backup_restore"`
}

func Load() (*Config, error) {
	viper.SetConfigName("engine")
	viper.SetConfigType("yaml")

	configDir := strings.TrimSpace(os.Getenv("HOSTVIM_CONFIG_DIR"))
	if configDir == "" {
		configDir = strings.TrimSpace(os.Getenv("PANELSAR_CONFIG_DIR")) // eski kurulumlar
	}
	if configDir == "" {
		configDir = "/etc/hostvim"
	}
	viper.AddConfigPath(configDir)
	viper.AddConfigPath("/etc/hostvim")
	viper.AddConfigPath("/etc/panelsar")
	viper.AddConfigPath(filepath.Join(".", "configs"))
	viper.AddConfigPath(".")

	setDefaults()

	viper.AutomaticEnv()
	viper.SetEnvPrefix("HOSTVIM")
	viper.SetEnvKeyReplacer(strings.NewReplacer(".", "_"))

	if err := viper.ReadInConfig(); err != nil {
		if _, ok := err.(viper.ConfigFileNotFoundError); !ok {
			return nil, fmt.Errorf("error reading config: %w", err)
		}
	}

	var cfg Config
	if err := viper.Unmarshal(&cfg); err != nil {
		return nil, fmt.Errorf("error unmarshaling config: %w", err)
	}

	cfg.resolvePaths()

	return &cfg, nil
}

func (c *Config) resolvePaths() {
	if c.Paths.WebRoot != "" {
		return
	}
	home := strings.TrimSpace(os.Getenv("HOSTVIM_HOME"))
	if home == "" {
		home = strings.TrimSpace(os.Getenv("PANELSAR_HOME")) // eski kurulumlar
	}
	if home == "" {
		if wd, err := os.Getwd(); err == nil {
			if filepath.Base(wd) == "engine" {
				home = filepath.Clean(filepath.Join(wd, ".."))
			} else {
				home = wd
			}
		} else {
			home = "."
		}
	}
	if abs, err := filepath.Abs(home); err == nil {
		home = abs
	}
	c.Paths.WebRoot = filepath.Join(home, "data", "www")
	if c.Paths.TempDir == "" || c.Paths.TempDir == "/tmp/panelsar" || c.Paths.TempDir == "/tmp/hostvim" {
		c.Paths.TempDir = filepath.Join(home, "data", "tmp")
	}
	if c.Paths.SSLDir == "" || c.Paths.SSLDir == "/etc/panelsar/ssl" || c.Paths.SSLDir == "/etc/hostvim/ssl" {
		c.Paths.SSLDir = filepath.Join(home, "data", "ssl")
	}
}

func setDefaults() {
	viper.SetDefault("server.port", 9090)
	viper.SetDefault("server.host", "0.0.0.0")
	viper.SetDefault("server.debug", false)
	viper.SetDefault("server.prometheus_enabled", false)
	viper.SetDefault("server.prometheus_path", "/metrics")
	viper.SetDefault("server.read_timeout_seconds", 600)
	viper.SetDefault("server.write_timeout_seconds", 600)
	viper.SetDefault("server.idle_timeout_seconds", 120)

	viper.SetDefault("database.host", "localhost")
	viper.SetDefault("database.port", 5432)
	viper.SetDefault("database.name", "hostvim")
	viper.SetDefault("database.user", "hostvim")

	viper.SetDefault("docker.enabled", true)
	viper.SetDefault("docker.socket_path", "/var/run/docker.sock")
	viper.SetDefault("docker.network", "hostvim_net")

	viper.SetDefault("security.token_expiry", 3600)
	viper.SetDefault("security.max_login_attempts", 5)
	viper.SetDefault("security.fail2ban_enabled", true)
	viper.SetDefault("security.allowed_origins", "http://localhost,http://127.0.0.1")

	viper.SetDefault("paths.web_root", "")
	viper.SetDefault("paths.vhosts_dir", "/etc/nginx/sites-available")
	viper.SetDefault("paths.ssl_dir", "/etc/hostvim/ssl")
	viper.SetDefault("paths.backup_dir", "/var/backups/hostvim")
	viper.SetDefault("paths.log_dir", "/var/log/hostvim")
	viper.SetDefault("paths.temp_dir", "/tmp/hostvim")

	viper.SetDefault("hosting.nginx_manage_vhosts", false)
	viper.SetDefault("hosting.nginx_sites_enabled", "/etc/nginx/sites-enabled")
	viper.SetDefault("hosting.php_fpm_socket", "")
	viper.SetDefault("hosting.nginx_reload_after_vhost", false)

	viper.SetDefault("hosting.php_fpm_manage_pools", false)
	viper.SetDefault("hosting.php_fpm_listen_dir", "/run/php")
	viper.SetDefault("hosting.php_fpm_pool_dir_template", "/etc/php/{{version}}/fpm/pool.d")
	viper.SetDefault("hosting.php_fpm_reload_after_pool", false)
	viper.SetDefault("hosting.php_fpm_pool_user", "www-data")
	viper.SetDefault("hosting.php_fpm_pool_group", "www-data")

	viper.SetDefault("hosting.apache_manage_vhosts", false)
	viper.SetDefault("hosting.apache_sites_available", "/etc/apache2/sites-available")
	viper.SetDefault("hosting.apache_sites_enabled", "/etc/apache2/sites-enabled")
	viper.SetDefault("hosting.apache_reload_after_vhost", false)
	viper.SetDefault("hosting.apache_http_port", 80)

	viper.SetDefault("hosting.openlitespeed_manage_vhosts", false)
	viper.SetDefault("hosting.openlitespeed_conf_root", "/usr/local/lsws")
	viper.SetDefault("hosting.openlitespeed_reload_after_vhost", false)
	viper.SetDefault("hosting.openlitespeed_ctrl_path", "")

	viper.SetDefault("hosting.manage_ssl", false)
	viper.SetDefault("hosting.certbot_path", "")
	viper.SetDefault("hosting.lets_encrypt_email", "")
	viper.SetDefault("hosting.lets_encrypt_staging", false)
	viper.SetDefault("hosting.lets_encrypt_include_www", true)

	viper.SetDefault("hosting.manage_site_tools", false)
	viper.SetDefault("hosting.composer_path", "composer")
	viper.SetDefault("hosting.npm_path", "npm")
	viper.SetDefault("hosting.tools_max_seconds", 300)
	viper.SetDefault("hosting.wordpress_zip_url", "https://wordpress.org/latest.zip")
	viper.SetDefault("hosting.nginx_vhost_helper", "/usr/local/sbin/hostvim-nginx-vhost")
	viper.SetDefault("hosting.stack_install_script", "/usr/local/sbin/hostvim-stack-install")

	viper.SetDefault("hosting.execute_backups", false)
	viper.SetDefault("hosting.backup_tar_path", "tar")
	viper.SetDefault("hosting.backup_max_seconds", 3600)
	viper.SetDefault("hosting.execute_backup_restore", false)
}
