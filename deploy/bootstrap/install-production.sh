#!/usr/bin/env bash
#
# Hostvim — tek sunucuda üretim kurulumu (Debian 12 / Ubuntu 22.04+)
#
# Hedef: güvenlik (engine yalnızca loopback), hız (gzip, static cache), kolaylık (tek komut iskeleti)
#
# Kullanım (root) — sıfır sunucu:
#   git clone <repo> /var/www/hostvim && cd /var/www/hostvim
#   sudo bash deploy/bootstrap/install-production.sh
#
# Sadece kod/config güncellemesi (paket kurulumu atlanır):
#   cd /var/www/hostvim && git pull --ff-only
#   SKIP_APT=1 sudo -E bash deploy/bootstrap/install-production.sh
#
# Ortam değişkenleri (isteğe bağlı):
#   HOSTVIM_HOME=/var/www/hostvim   (verilmezse repo kökü = betiğin bulunduğu proje; eski: PANELSAR_HOME yedeği)
#   SERVER_NAME=_          # sadece IP ile erişim için default_server (nginx şablonunda _)
#   LETS_ENCRYPT_EMAIL=admin@ornek.com
#   SKIP_APT=1             # paket kurulumunu atla (yeniden çalıştırma)
#   SKIP_UFW=1             # UFW kurma
#   WITH_MARIADB=1         # MariaDB kur ve panel veritabanını oluştur (önerilir)
#   WITH_POSTGRES=1        # Engine için PostgreSQL (isteğe bağlı)
#   WITH_NODE_REPO=1       # NodeSource 20.x ekle (frontend build için önerilir)
#   HOSTVIM_GO_VERSION=1.22.3  # engine/go.mod ile uyumlu (varsayılan; go.dev'den kurulur)
#   HOSTVIM_PHP_VERSION=8.4    # panel/composer.lock (Ondrej/Sury); Symfony 8 için 8.4 önerilir
#   HOSTVIM_EXTRA_PHP_FPM_VERSIONS="8.3 8.2"  # ek FPM (boş = yalnız ana sürüm)
#   WITH_PHPMYADMIN=1           # apt phpMyAdmin + Nginx /phpmyadmin + PHPMYADMIN_URL
#   WITH_CERTBOT=1              # certbot + python3-certbot-nginx (Let's Encrypt)
#   WITH_APACHE=1               # apache2; Nginx 80 ile çakışmaz — Apache :8080 + engine apache_http_port: 8080
#   WITH_LOCAL_POSTFIX=1        # Postfix + mailutils (panel giden posta: sendmail; Admin → Giden posta’dan SMTP’ye geçilebilir)
#   SKIP_DB_SEED=1              # migrate sonrası db:seed atla
#   RESET_PANEL_DB=1            # DİKKAT: migrate:fresh + (varsayılan) data/www vb. temizlik — üretimde yalnızca gerektiğinde
#   HOSTVIM_FRESH_INSTALL=1     # RESET_PANEL_DB=1 ile aynı (fabrika / boş lab sunucusu; müşteri “onarım”unda kullanmayın)
#   HOSTVIM_SEED_DEMO_USERS=1  # Demo reseller/user hesaplarını da seed et (varsayılan: 0)
#   (engine systemd drop-in) HOSTVIM_TERMINAL_NO_ROOT=1  # web terminali www-data kabuğunda (varsayılan: root sudo)
#   HOSTVIM_ADMIN_EMAIL=...       # ilk admin e-posta (verilirse her şeyi geçer; önerilir)
#   HOSTVIM_ADMIN_EMAIL_DOMAIN=…  # örn. ornek.com → admin@ornek.com (açık e-posta yoksa)
#   HOSTVIM_APP_URL=…             # örn. https://panel.ornek.com — .env APP_URL + e-posta türetimi için
#   LETS_ENCRYPT_EMAIL=…          # ACME; HOSTVIM_ADMIN_EMAIL yoksa ilk admin e-postası olarak da kullanılabilir
#   HOSTVIM_ADMIN_PASSWORD=...    # sabit şifre; verilmezse kurulumda rastgele üretilir
#
set -euo pipefail

# Fabrika sıfırlama (install.sh ile aynı anahtar; doğrudan bu betik çalıştırılıyorsa da geçerli)
if [[ "${HOSTVIM_FRESH_INSTALL:-0}" == "1" ]] || [[ "${HOSTVIM_FRESH_INSTALL:-0}" == "yes" ]]; then
  export RESET_PANEL_DB=1
fi

# Kolay kurulum: varsayılan olarak MariaDB + Node 20 kaynağı
WITH_MARIADB="${WITH_MARIADB:-1}"
WITH_NODE_REPO="${WITH_NODE_REPO:-1}"

[[ "$(id -u)" -eq 0 ]] || { echo "Root ile çalıştırın: sudo bash $0" >&2; exit 1; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=ensure-go-toolchain.sh
source "$SCRIPT_DIR/ensure-go-toolchain.sh"
# shellcheck source=ensure-php-packages.sh
source "$SCRIPT_DIR/ensure-php-packages.sh"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
# Varsayılan: klon/kök dizin = repo (HOSTVIM_HOME uyarısı olmaması için). Üretimde isterseniz /var/www/hostvim verin.
HOSTVIM_HOME="${HOSTVIM_HOME:-${PANELSAR_HOME:-$REPO_ROOT}}"
SERVER_NAME="${SERVER_NAME:-_}"
LETS_ENCRYPT_EMAIL="${LETS_ENCRYPT_EMAIL:-admin@localhost}"
APP_PROFILE="${APP_PROFILE:-customer}"
if [[ "${ENFORCE_ADMIN_2FA:-}" == "" ]]; then
  if [[ "$APP_PROFILE" == "vendor" ]]; then
    ENFORCE_ADMIN_2FA=true
  else
    ENFORCE_ADMIN_2FA=false
  fi
fi

if [[ ! -d "$REPO_ROOT/panel" ]] || [[ ! -d "$REPO_ROOT/engine" ]]; then
  echo "Hata: panel/ veya engine/ bulunamadı. Bu betiği repo kökünden çalıştırın (HOSTVIM_HOME=$HOSTVIM_HOME)." >&2
  exit 1
fi

if [[ "$HOSTVIM_HOME" != "$REPO_ROOT" ]]; then
  echo "Uyarı: HOSTVIM_HOME ($HOSTVIM_HOME) ile repo ($REPO_ROOT) farklı. Aynı yapın önerilir." >&2
fi

export DEBIAN_FRONTEND=noninteractive

detect_php_fpm_sock() {
  local pv="${HOSTVIM_PHP_VERSION:-${PANELSAR_PHP_VERSION:-8.4}}"
  local s
  for s in "/run/php/php${pv}-fpm.sock" /run/php/php8.4-fpm.sock /run/php/php8.3-fpm.sock /run/php/php8.2-fpm.sock /run/php/php-fpm.sock; do
    if [[ -S "$s" ]]; then
      echo "$s"
      return 0
    fi
  done
  echo "/run/php/php${pv}-fpm.sock"
}

yaml_value_from_block() {
  local file="$1" block="$2" key="$3"
  [[ -f "$file" ]] || return 1
  awk -v block="$block" -v key="$key" '
    function ltrim(s){ sub(/^[[:space:]]+/, "", s); return s }
    function rtrim(s){ sub(/[[:space:]]+$/, "", s); return s }
    function trim(s){ return rtrim(ltrim(s)) }
    BEGIN { inblock=0 }
    {
      line=$0
      if (match(line, "^[[:space:]]*" block ":[[:space:]]*$")) {
        inblock=1
        next
      }
      if (inblock && match(line, "^[[:space:]]*[A-Za-z0-9_]+:[[:space:]]*$")) {
        inblock=0
      }
      if (!inblock) next
      if (match(line, "^[[:space:]]*" key ":[[:space:]]*")) {
        sub("^[[:space:]]*" key ":[[:space:]]*", "", line)
        gsub(/^"|"$/, "", line)
        gsub(/^'\''|'\''$/, "", line)
        print trim(line)
        exit
      }
    }
  ' "$file"
}

hostvim_git_safe_directory() {
  local d="$1"
  [[ -d "$d/.git" ]] || return 0
  if ! git config --system --get-all safe.directory 2>/dev/null | grep -qxF "$d"; then
    git config --system --add safe.directory "$d"
  fi
}

# Nginx panel 80/443 kullanır; Apache yalnızca HTTP 8080 (engine hosting.apache_http_port ile uyumlu)
hostvim_apache_bind_8080() {
  local pc=/etc/apache2/ports.conf
  [[ -f "$pc" ]] || return 1
  sed -i \
    -e 's/^Listen 80$/Listen 8080/' \
    -e 's/^Listen \[::\]:80$/Listen [::]:8080/' \
    "$pc"
  # Varsayılan SSL sitesi + 443 dinleyicisi Nginx ile çakışır
  sed -i \
    -e 's/^Listen 443$/#Listen 443/' \
    -e 's/^Listen \[::\]:443$/#Listen [::]:443/' \
    "$pc" 2>/dev/null || true
  a2dissite default-ssl 2>/dev/null || true
  a2dissite default-ssl.conf 2>/dev/null || true
  local f
  for f in /etc/apache2/sites-available/*.conf; do
    [[ -f "$f" ]] || continue
    sed -i \
      -e 's/<VirtualHost \*:80>/<VirtualHost *:8080>/g' \
      -e 's/<VirtualHost \*:80 >/<VirtualHost *:8080>/g' \
      "$f"
  done
  apache2ctl configtest
}

# apt kurulumu
if [[ "${SKIP_APT:-}" != "1" ]]; then
  apt-get update -qq
  apt-get install -y -qq \
    nginx \
    curl \
    ca-certificates \
    sudo \
    git \
    rsync \
    unzip \
    sqlite3 \
    acl \
    software-properties-common \
    lsb-release \
    gnupg

  ensure_php_fpm_packages

  if [[ "${WITH_NODE_REPO}" == "1" ]] || [[ "${WITH_NODE_REPO}" == "yes" ]]; then
    if [[ ! -f /etc/apt/sources.list.d/nodesource.list ]]; then
      curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    fi
    apt-get install -y -qq nodejs
  else
    apt-get install -y -qq nodejs npm || true
  fi
  if ! command -v npm >/dev/null 2>&1; then
    echo "Hata: npm bulunamadı (frontend derlemesi zorunlu). WITH_NODE_REPO=1 ile NodeSource kurun veya nodejs/npm kurun." >&2
    exit 1
  fi

  if ! command -v composer >/dev/null 2>&1; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi

  if [[ "${WITH_MARIADB}" == "1" ]] || [[ "${WITH_MARIADB}" == "yes" ]]; then
    apt-get install -y -qq mariadb-server mariadb-client
    systemctl enable --now mariadb
  fi

  if [[ "${WITH_CERTBOT:-1}" == "1" ]] || [[ "${WITH_CERTBOT:-1}" == "yes" ]]; then
    apt-get install -y -qq certbot python3-certbot-nginx
  fi

  if [[ "${WITH_APACHE:-1}" == "1" ]] || [[ "${WITH_APACHE:-1}" == "yes" ]]; then
    apt-get install -y -qq apache2
    hostvim_apache_bind_8080
    systemctl enable apache2
    systemctl restart apache2
    echo "==> Apache etkin: HTTP :8080 (Nginx panel :80). Engine apache_http_port=8080 ile uyumlu."
  fi

  if [[ "${WITH_PHPMYADMIN:-1}" == "1" ]] || [[ "${WITH_PHPMYADMIN:-1}" == "yes" ]]; then
    echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect none" | debconf-set-selections
    echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | debconf-set-selections
    apt-get install -y -qq phpmyadmin
  fi

  if [[ "${WITH_LOCAL_POSTFIX:-1}" == "1" ]] || [[ "${WITH_LOCAL_POSTFIX:-1}" == "yes" ]]; then
    echo "postfix postfix/main_mailer_type select Internet Site" | debconf-set-selections
    echo "postfix postfix/mailname string $(hostname -f 2>/dev/null || hostname)" | debconf-set-selections
    apt-get install -y -qq postfix mailutils
    systemctl enable postfix
    systemctl restart postfix
  fi

  if [[ "${WITH_POSTGRES:-}" == "1" ]] || [[ "${WITH_POSTGRES:-}" == "yes" ]]; then
    apt-get install -y -qq postgresql postgresql-client
    systemctl enable --now postgresql
  fi
else
  require_php_for_composer
fi

# PHP-FPM soketi (apt sonrası)
PHP_FPM_SOCK="$(detect_php_fpm_sock)"

mkdir -p "$HOSTVIM_HOME/data"/{www,tmp,ssl,backups,logs,vhosts}
mkdir -p /etc/hostvim
chown -R www-data:www-data "$HOSTVIM_HOME/data"

# RESET modunda eski hosting kalıntılarını da temizle (plesk benzeri "silince anında düşsün" davranışı).
if [[ "${RESET_PANEL_DB:-0}" == "1" ]] || [[ "${RESET_PANEL_DB:-0}" == "yes" ]]; then
  CLEAN_HOSTING_STATE_ON_RESET="${CLEAN_HOSTING_STATE_ON_RESET:-1}"
  if [[ "$CLEAN_HOSTING_STATE_ON_RESET" == "1" ]] || [[ "$CLEAN_HOSTING_STATE_ON_RESET" == "yes" ]]; then
    echo "==> RESET_PANEL_DB=1: eski hosting state temizleniyor (webroot/vhost/ssl/backup)."
    rm -rf "$HOSTVIM_HOME/data/www/"* 2>/dev/null || true
    rm -rf "$HOSTVIM_HOME/data/ssl/"* 2>/dev/null || true
    rm -rf "$HOSTVIM_HOME/data/backups/"* 2>/dev/null || true
    rm -rf /var/backups/hostvim/* /var/backups/panelsar/* 2>/dev/null || true
    rm -f /etc/nginx/sites-enabled/hostvim-*.conf /etc/nginx/sites-enabled/panelsar-*.conf 2>/dev/null || true
    rm -f /etc/apache2/sites-enabled/hostvim-*.conf /etc/apache2/sites-enabled/panelsar-*.conf 2>/dev/null || true
    rm -f /etc/apache2/sites-available/hostvim-*.conf /etc/apache2/sites-available/panelsar-*.conf 2>/dev/null || true
    nginx -t >/dev/null 2>&1 && systemctl reload nginx || true
    if command -v apache2ctl >/dev/null 2>&1; then
      apache2ctl configtest >/dev/null 2>&1 && systemctl reload apache2 || true
    fi
  fi
fi

# Kimlik anahtarları:
# - İlk kurulumda güvenli rastgele üretilir.
# - Sonraki kurulum/güncellemelerde mevcut /etc/hostvim/engine.yaml (veya eski /etc/panelsar/engine.yaml) içinden okunup korunur.
#   Böylece panel↔engine auth kopmaz.
ENGINE_DST="/etc/hostvim/engine.yaml"
ENGINE_LEGACY_DST="/etc/panelsar/engine.yaml"
FORCE_ROTATE_ENGINE_KEYS="${FORCE_ROTATE_ENGINE_KEYS:-0}"

ENGINE_KEY_SRC=""
if [[ -f "$ENGINE_DST" ]] && [[ "$FORCE_ROTATE_ENGINE_KEYS" != "1" ]]; then
  ENGINE_KEY_SRC="$ENGINE_DST"
elif [[ -f "$ENGINE_LEGACY_DST" ]] && [[ "$FORCE_ROTATE_ENGINE_KEYS" != "1" ]]; then
  ENGINE_KEY_SRC="$ENGINE_LEGACY_DST"
fi
if [[ -n "$ENGINE_KEY_SRC" ]]; then
  EXISTING_INTERNAL_KEY="$(yaml_value_from_block "$ENGINE_KEY_SRC" "security" "internal_api_key" || true)"
  EXISTING_ENGINE_JWT="$(yaml_value_from_block "$ENGINE_KEY_SRC" "security" "jwt_secret" || true)"
  EXISTING_ENGINE_SECRET="$(yaml_value_from_block "$ENGINE_KEY_SRC" "server" "secret_key" || true)"
else
  EXISTING_INTERNAL_KEY=""
  EXISTING_ENGINE_JWT=""
  EXISTING_ENGINE_SECRET=""
fi

INTERNAL_KEY="${EXISTING_INTERNAL_KEY:-$(openssl rand -hex 32)}"
ENGINE_SECRET="${EXISTING_ENGINE_SECRET:-$(openssl rand -hex 32)}"
ENGINE_JWT="${EXISTING_ENGINE_JWT:-$(openssl rand -hex 32)}"
# Boş string atanmışsa :- genişlemesi yeni değer üretmez; engine panel auth kırılır.
[[ -n "$INTERNAL_KEY" ]] || INTERNAL_KEY="$(openssl rand -hex 32)"
[[ -n "$ENGINE_SECRET" ]] || ENGINE_SECRET="$(openssl rand -hex 32)"
[[ -n "$ENGINE_JWT" ]] || ENGINE_JWT="$(openssl rand -hex 32)"
ENGINE_DB_PASS="$(openssl rand -hex 24)"
PANEL_ORIGINS="${PANEL_ORIGINS:-http://localhost,http://127.0.0.1}"
if [[ "$SERVER_NAME" != "_" ]]; then
  PANEL_ORIGINS="$PANEL_ORIGINS,http://$SERVER_NAME,https://$SERVER_NAME"
fi

# Önceki kurulumlardan kalan zayıf/placeholder anahtarlar yayın güvenliği için döndürülür.
if [[ "$INTERNAL_KEY" == "hostvim-engine-internal-dev" ]] || [[ "$INTERNAL_KEY" == "panelsar-engine-internal-dev" ]] || [[ "$INTERNAL_KEY" == *"change"* ]]; then
  INTERNAL_KEY="$(openssl rand -hex 32)"
fi
if [[ "$ENGINE_SECRET" == *"change"* ]]; then
  ENGINE_SECRET="$(openssl rand -hex 32)"
fi
if [[ "$ENGINE_JWT" == *"change"* ]] || [[ "$ENGINE_JWT" == *"dev"* ]]; then
  ENGINE_JWT="$(openssl rand -hex 32)"
fi

# Engine yaml
ENGINE_TMPL="$REPO_ROOT/deploy/configs/engine.production.yaml"
sed \
  -e "s|__INTERNAL_KEY__|$INTERNAL_KEY|g" \
  -e "s|__ENGINE_SECRET_KEY__|$ENGINE_SECRET|g" \
  -e "s|__ENGINE_JWT_SECRET__|$ENGINE_JWT|g" \
  -e "s|__ENGINE_DB_PASSWORD__|$ENGINE_DB_PASS|g" \
  -e "s|__HOSTVIM_HOME__|$HOSTVIM_HOME|g" \
  -e "s|__LETS_ENCRYPT_EMAIL__|$LETS_ENCRYPT_EMAIL|g" \
  -e "s|__PHP_FPM_SOCKET__|$PHP_FPM_SOCK|g" \
  -e "s|__PANEL_ORIGINS__|$PANEL_ORIGINS|g" \
  "$ENGINE_TMPL" > "$ENGINE_DST"
chmod 640 "$ENGINE_DST"
chown root:www-data "$ENGINE_DST"

# PostgreSQL engine kullanıcısı (isteğe bağlı)
if [[ "${WITH_POSTGRES:-}" == "1" ]] || [[ "${WITH_POSTGRES:-}" == "yes" ]]; then
  sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='hostvim'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER hostvim WITH PASSWORD '$ENGINE_DB_PASS';"
  sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='hostvim'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE hostvim OWNER hostvim;"
fi

# Go engine derle (apt'teki golang-go genelde esiktir; ensure-go-toolchain.sh go.dev sürümünü kurar)
ensure_go_toolchain
(cd "$REPO_ROOT/engine" && go build -buildvcs=false -o /usr/local/bin/hostvim-engine ./cmd/hostvim-engine)
chmod 755 /usr/local/bin/hostvim-engine

# systemd
sed \
  -e "s|__HOSTVIM_HOME__|$HOSTVIM_HOME|g" \
  -e "s|__ENGINE_BINARY__|/usr/local/bin/hostvim-engine|g" \
  "$REPO_ROOT/deploy/systemd/hostvim-engine.service" > /etc/systemd/system/hostvim-engine.service
systemctl daemon-reload
if [[ -x /usr/local/bin/hostvim-engine ]]; then
  systemctl enable hostvim-engine
  systemctl restart hostvim-engine || true
fi

# Engine www-data iken nginx sites-enabled'a yazamaz; sudo ile izinli betikler
if [[ -f "$REPO_ROOT/deploy/host/hostvim-nginx-vhost" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/hostvim-nginx-vhost" /usr/local/sbin/hostvim-nginx-vhost
fi
if [[ -f "$REPO_ROOT/deploy/host/hostvim-stack-install" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/hostvim-stack-install" /usr/local/sbin/hostvim-stack-install
fi
if [[ -f "$REPO_ROOT/deploy/host/hostvim-terminal-root" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/hostvim-terminal-root" /usr/local/sbin/hostvim-terminal-root
fi
if [[ -f "$REPO_ROOT/deploy/host/hostvim-php-ini" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/hostvim-php-ini" /usr/local/sbin/hostvim-php-ini
fi
if [[ -f "$REPO_ROOT/deploy/host/hostvim-security" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/hostvim-security" /usr/local/sbin/hostvim-security
fi
if [[ -f "$REPO_ROOT/deploy/host/hostvim-cleaner" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/hostvim-cleaner" /usr/local/sbin/hostvim-cleaner
fi
cat > /etc/sudoers.d/hostvim-engine <<'SUDOERS'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/hostvim-nginx-vhost
www-data ALL=(root) NOPASSWD: /usr/local/sbin/hostvim-stack-install
www-data ALL=(root) NOPASSWD: /usr/local/sbin/hostvim-terminal-root
www-data ALL=(root) NOPASSWD: /usr/local/sbin/hostvim-php-ini
www-data ALL=(root) NOPASSWD: /usr/local/sbin/hostvim-security
SUDOERS
chmod 440 /etc/sudoers.d/hostvim-engine
visudo -cf /etc/sudoers.d/hostvim-engine

# Panel .env
PANEL_ROOT="$REPO_ROOT/panel"
ENV_EXAMPLE="$PANEL_ROOT/.env.production.example"
ENV_FILE="$PANEL_ROOT/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  if [[ -f "$ENV_EXAMPLE" ]]; then
    cp "$ENV_EXAMPLE" "$ENV_FILE"
  else
    cp "$PANEL_ROOT/.env.example" "$ENV_FILE"
  fi
fi

# APP_KEY: composer install + vendor/autoload sonrası üretilir (aşağıda). Erken key:generate
# vendor yokken sessizce başarısız olup APP_KEY boş kalıyordu → db:seed "No application encryption key".

# .env üretim ayarları (sed ile idempotent değil; basit grep ile atla)
update_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

# İlk yönetici e-postası (Plesk benzeri: mümkünse gerçek alan / iletişim adresi).
# Sıra: HOSTVIM_ADMIN_EMAIL > PANELSAR_… > HOSTVIM_ADMIN_EMAIL_DOMAIN > LETS_ENCRYPT_EMAIL >
#       APP_URL ana makinesi (IP/localhost değilse → admin@host) > admin@<hostname -f>
hostvim_resolve_admin_email() {
  local explicit domain le app_url host fqdn
  explicit="${HOSTVIM_ADMIN_EMAIL:-${PANELSAR_ADMIN_EMAIL:-}}"
  explicit="${explicit//[[:space:]]/}"
  if [[ -n "$explicit" ]]; then
    echo "$explicit"
    return 0
  fi
  domain="${HOSTVIM_ADMIN_EMAIL_DOMAIN:-${PANELSAR_ADMIN_EMAIL_DOMAIN:-}}"
  domain="${domain//[[:space:]]/}"
  if [[ -n "$domain" && "$domain" == *.* && "$domain" != *"@"* ]]; then
    echo "admin@${domain}"
    return 0
  fi
  le="${LETS_ENCRYPT_EMAIL:-}"
  le="${le//[[:space:]]/}"
  if [[ -n "$le" && "$le" == *"@"* ]]; then
    echo "$le"
    return 0
  fi
  app_url="$(grep -E '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r')"
  app_url="${app_url#\"}"
  app_url="${app_url%\"}"
  app_url="${app_url//[[:space:]]/}"
  host="${app_url#*://}"
  host="${host%%/*}"
  host="${host%%\?*}"
  if [[ "$host" == \[*\]* ]]; then
    host=""
  elif [[ "$host" =~ ^([^:]+):[0-9]+$ ]]; then
    host="${BASH_REMATCH[1]}"
  fi
  if [[ -n "$host" && "$host" != "localhost" && "$host" != "127.0.0.1" ]]; then
    if [[ "$host" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
      :
    elif [[ "$host" == *:* ]]; then
      :
    else
      echo "admin@${host}"
      return 0
    fi
  fi
  fqdn="$(hostname -f 2>/dev/null || hostname || echo hostvim.local)"
  fqdn="${fqdn// /}"
  echo "admin@${fqdn}"
}

update_env "APP_ENV" "production"
update_env "APP_DEBUG" "false"
update_env "APP_PROFILE" "$APP_PROFILE"
update_env "ENFORCE_ADMIN_2FA" "$ENFORCE_ADMIN_2FA"
_PANEL_APP_URL="${HOSTVIM_APP_URL:-${PANEL_APP_URL:-}}"
if [[ -z "$_PANEL_APP_URL" ]]; then
  _PANEL_APP_URL="http://$(hostname -I 2>/dev/null | awk '{print $1}' || echo localhost)"
fi
update_env "APP_URL" "$_PANEL_APP_URL"
update_env "ENGINE_API_URL" "http://127.0.0.1:9090"
update_env "ENGINE_INTERNAL_KEY" "$INTERNAL_KEY"
update_env "ENGINE_API_SECRET" "$ENGINE_JWT"
update_env "LOG_LEVEL" "error"

# Yerel Postfix: Laravel sendmail ile gönderir; SMTP’yi panelden (Admin → Giden posta) tanımlarsınız
if [[ "${WITH_LOCAL_POSTFIX:-1}" == "1" ]] || [[ "${WITH_LOCAL_POSTFIX:-1}" == "yes" ]]; then
  _MAIL_FROM="noreply@$(hostname -f 2>/dev/null || hostname)"
  update_env "MAIL_MAILER" "sendmail"
  update_env "MAIL_FROM_ADDRESS" "\"${_MAIL_FROM}\""
  update_env "MAIL_FROM_NAME" "\"Hostvim\""
fi

# phpMyAdmin (kuruluysa panelde otomatik link)
if [[ -d /usr/share/phpmyadmin ]]; then
  _APP_URL_VAL="$(grep '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r' | tr -d ' ')"
  [[ -n "$_APP_URL_VAL" ]] && update_env "PHPMYADMIN_URL" "${_APP_URL_VAL%/}/phpmyadmin"
fi

# MariaDB panel DB
if [[ "${WITH_MARIADB}" == "1" ]] || [[ "${WITH_MARIADB}" == "yes" ]]; then
  # Yeniden kurulumda her seferinde yeni şifre üretmek, CREATE USER IF NOT EXISTS ile uyumsuzluk (1045) yaratır
  if [[ -s /root/hostvim-panel-mysql.secret ]]; then
    PANEL_DB_PASS="$(cat /root/hostvim-panel-mysql.secret)"
  elif [[ -s /root/panelsar-panel-mysql.secret ]]; then
    PANEL_DB_PASS="$(cat /root/panelsar-panel-mysql.secret)"
  else
    PANEL_DB_PASS="$(openssl rand -hex 16)"
  fi
  MARIADB_CMD=(mariadb)
  command -v mariadb >/dev/null 2>&1 || MARIADB_CMD=(mysql)
  "${MARIADB_CMD[@]}" -e "CREATE DATABASE IF NOT EXISTS hostvim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true
  "${MARIADB_CMD[@]}" -e "CREATE USER IF NOT EXISTS 'hostvim'@'localhost' IDENTIFIED BY '$PANEL_DB_PASS';" || true
  "${MARIADB_CMD[@]}" -e "ALTER USER 'hostvim'@'localhost' IDENTIFIED BY '$PANEL_DB_PASS';" || true
  "${MARIADB_CMD[@]}" -e "GRANT ALL PRIVILEGES ON hostvim.* TO 'hostvim'@'localhost'; FLUSH PRIVILEGES;" || true
  update_env "DB_CONNECTION" "mysql"
  update_env "DB_HOST" "127.0.0.1"
  update_env "DB_PORT" "3306"
  update_env "DB_DATABASE" "hostvim"
  update_env "DB_USERNAME" "hostvim"
  update_env "DB_PASSWORD" "$PANEL_DB_PASS"

  # Hosting panelinden DB oluşturma için root yerine ayrı bir servis kullanıcısı.
  if [[ -s /root/hostvim-mysql-provision.secret ]]; then
    MYSQL_PROVISION_PASS="$(cat /root/hostvim-mysql-provision.secret)"
  elif [[ -s /root/panelsar-mysql-provision.secret ]]; then
    MYSQL_PROVISION_PASS="$(cat /root/panelsar-mysql-provision.secret)"
  else
    MYSQL_PROVISION_PASS="$(openssl rand -hex 18)"
  fi
  "${MARIADB_CMD[@]}" -e "CREATE USER IF NOT EXISTS 'hostvim_provision'@'localhost' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "CREATE USER IF NOT EXISTS 'hostvim_provision'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "ALTER USER 'hostvim_provision'@'localhost' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "ALTER USER 'hostvim_provision'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "GRANT ALL PRIVILEGES ON *.* TO 'hostvim_provision'@'localhost' WITH GRANT OPTION;" || true
  "${MARIADB_CMD[@]}" -e "GRANT ALL PRIVILEGES ON *.* TO 'hostvim_provision'@'127.0.0.1' WITH GRANT OPTION;" || true
  "${MARIADB_CMD[@]}" -e "FLUSH PRIVILEGES;" || true
  update_env "MYSQL_PROVISION_ENABLED" "true"
  update_env "MYSQL_PROVISION_HOST" "localhost"
  update_env "MYSQL_PROVISION_PORT" "3306"
  update_env "MYSQL_PROVISION_USERNAME" "hostvim_provision"
  update_env "MYSQL_PROVISION_PASSWORD" "$MYSQL_PROVISION_PASS"
  echo "$MYSQL_PROVISION_PASS" > /root/hostvim-mysql-provision.secret
  chmod 600 /root/hostvim-mysql-provision.secret
  echo "MySQL provision şifresi: /root/hostvim-mysql-provision.secret"

  echo "$PANEL_DB_PASS" > /root/hostvim-panel-mysql.secret
  chmod 600 /root/hostvim-panel-mysql.secret
  echo "Panel MySQL şifresi: /root/hostvim-panel-mysql.secret"
fi

# Composer www-data ile çalışır; panel/ yalnızca storage/cache www-data ise vendor/ oluşturulamaz
mkdir -p "$PANEL_ROOT/vendor"
chown -R www-data:www-data "$PANEL_ROOT"
chmod -R ug+rwx "$PANEL_ROOT/storage" "$PANEL_ROOT/bootstrap/cache"

hostvim_git_safe_directory "$REPO_ROOT"

sudo -u www-data composer --working-dir="$PANEL_ROOT" install --no-dev --optimize-autoloader --no-interaction

if ! grep -qE '^APP_KEY=base64:.+' "$ENV_FILE" 2>/dev/null; then
  echo "==> Laravel APP_KEY üretiliyor (.env)…"
  sudo -u www-data php "$PANEL_ROOT/artisan" key:generate --force --no-interaction || {
    echo "Hata: php artisan key:generate başarısız; .env veya composer kurulumunu kontrol edin." >&2
    exit 1
  }
fi

if grep -q '^DB_CONNECTION=sqlite' "$ENV_FILE" 2>/dev/null; then
  install -d -o www-data -g www-data -m 775 "$PANEL_ROOT/database"
  if [[ ! -f "$PANEL_ROOT/database/database.sqlite" ]]; then
    sudo -u www-data touch "$PANEL_ROOT/database/database.sqlite"
  fi
fi

# Frontend → public/ (sessiz atlama yok: panel arayüzü dist olmadan çalışmaz)
FRONTEND_ROOT="$REPO_ROOT/frontend"
if [[ -f "$FRONTEND_ROOT/package.json" ]]; then
  if ! command -v npm >/dev/null 2>&1; then
    echo "Hata: frontend/ için npm gerekli. SKIP_APT=1 kullandıysanız önce Node.js + npm kurun." >&2
    exit 1
  fi
  if [[ -f "$FRONTEND_ROOT/package-lock.json" ]]; then
    (cd "$FRONTEND_ROOT" && npm ci && VITE_BASE_URL="${VITE_BASE_URL:-}" VITE_APP_PROFILE="$APP_PROFILE" npm run build)
  else
    (cd "$FRONTEND_ROOT" && npm install && VITE_BASE_URL="${VITE_BASE_URL:-}" VITE_APP_PROFILE="$APP_PROFILE" npm run build)
  fi
  rsync -a --delete \
    --exclude index.php \
    --exclude .htaccess \
    "$FRONTEND_ROOT/dist/" "$PANEL_ROOT/public/"
fi

if [[ "${RESET_PANEL_DB:-0}" == "1" ]] || [[ "${RESET_PANEL_DB:-0}" == "yes" ]]; then
  echo "==> RESET_PANEL_DB=1: Panel veritabanı sıfırlanıyor (migrate:fresh)."
  sudo -u www-data php "$PANEL_ROOT/artisan" migrate:fresh --force
else
  echo "==> Panel veritabanı korunuyor: migrate --force (yeniden kurulum / güncelleme; kullanıcı ve site kayıtları silinmez)."
  sudo -u www-data php "$PANEL_ROOT/artisan" migrate --force
fi
sudo -u www-data php "$PANEL_ROOT/artisan" hostvim:init-outbound-mail --no-interaction 2>/dev/null || true

if [[ "${SKIP_DB_SEED:-}" != "1" ]]; then
  RESET_DB_MODE=0
  if [[ "${RESET_PANEL_DB:-0}" == "1" ]] || [[ "${RESET_PANEL_DB:-0}" == "yes" ]]; then
    RESET_DB_MODE=1
  fi

  HOST_FQDN="$(hostname -f 2>/dev/null || hostname || echo hostvim.local)"
  HOST_FQDN="${HOST_FQDN// /}"
  ADMIN_EMAIL="$(hostvim_resolve_admin_email)"
  SEED_DEMO_USERS="${HOSTVIM_SEED_DEMO_USERS:-${PANELSAR_SEED_DEMO_USERS:-0}}"
  USER_COUNT=""
  if [[ "$RESET_DB_MODE" == "0" ]] && { [[ "${WITH_MARIADB}" == "1" ]] || [[ "${WITH_MARIADB}" == "yes" ]]; }; then
    DB_PW=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2- | tr -d '\r')
    MARIADB_CMD=(mariadb)
    command -v mariadb >/dev/null 2>&1 || MARIADB_CMD=(mysql)
    if [[ -n "$DB_PW" ]]; then
      DB_USER_Q="$(grep '^DB_USERNAME=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r' | tr -d ' ')"
      DB_NAME_Q="$(grep '^DB_DATABASE=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r' | tr -d ' ')"
      [[ -n "$DB_USER_Q" ]] || DB_USER_Q="hostvim"
      [[ -n "$DB_NAME_Q" ]] || DB_NAME_Q="hostvim"
      USER_COUNT=$(MYSQL_PWD="$DB_PW" "${MARIADB_CMD[@]}" -u "$DB_USER_Q" -h 127.0.0.1 "$DB_NAME_Q" -Nse "SELECT COUNT(*)" 2>/dev/null || echo "")
    fi
  elif [[ "$RESET_DB_MODE" == "0" ]] && grep -q '^DB_CONNECTION=sqlite' "$ENV_FILE" 2>/dev/null && [[ -f "$PANEL_ROOT/database/database.sqlite" ]]; then
    USER_COUNT=$(sqlite3 "$PANEL_ROOT/database/database.sqlite" "SELECT COUNT(*) FROM users;" 2>/dev/null || echo "")
  fi
  [[ -n "$USER_COUNT" ]] || USER_COUNT=0

  WRITE_LOGIN=0
  ADMIN_PASSWORD=""
  if [[ "$RESET_DB_MODE" == "1" ]]; then
    # Fresh kurulum modunda her zaman yeni admin kimliği üret.
    ADMIN_PASSWORD="${HOSTVIM_ADMIN_PASSWORD:-$(openssl rand -hex 12)}"
    WRITE_LOGIN=1
  elif [[ -n "${HOSTVIM_ADMIN_PASSWORD:-}" ]]; then
    ADMIN_PASSWORD="$HOSTVIM_ADMIN_PASSWORD"
    WRITE_LOGIN=1
  elif [[ "$USER_COUNT" == "0" ]]; then
    ADMIN_PASSWORD="$(openssl rand -hex 12)"
    WRITE_LOGIN=1
  fi

  LOGIN_FILE="/root/hostvim-admin-login.txt"
  PANEL_URL_HINT="$(grep -E '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
  [[ -n "$PANEL_URL_HINT" ]] || PANEL_URL_HINT="http://$(hostname -I 2>/dev/null | awk '{print $1}' || echo localhost)"

  # Her çalıştırmada yaz: güncellemede WRITE_LOGIN=0 olunca eski panelsar-admin-login.txt yanlış gösterilmesin.
  {
    echo "Hostvim — panel giriş bilgisi ($(date -u +%Y-%m-%dT%H:%MZ 2>/dev/null || date))"
    echo "Panel URL: ${PANEL_URL_HINT}"
    echo "E-posta:   ${ADMIN_EMAIL}"
    if [[ "$WRITE_LOGIN" == "1" ]]; then
      echo "Şifre:     ${ADMIN_PASSWORD}"
      echo "İlk girişten sonra şifreyi değiştirin."
    else
      echo "Şifre:     (bu çalıştırmada üretilmedi — veritabanında zaten kullanıcı vardı; önceki şifre geçerli)"
      echo "Not: E-postayı burada değiştirmek DB’deki kullanıcıyı otomatik yenilemez; panelden düzenleyin veya HOSTVIM_ADMIN_EMAIL ile sıfırdan seed (RESET) kullanın."
    fi
  } > "$LOGIN_FILE"
  chmod 600 "$LOGIN_FILE"

  if [[ -n "$ADMIN_PASSWORD" ]]; then
    sudo -u www-data env \
      HOSTVIM_ADMIN_EMAIL="$ADMIN_EMAIL" \
      HOSTVIM_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
      HOSTVIM_SEED_DEMO_USERS="$SEED_DEMO_USERS" \
      php "$PANEL_ROOT/artisan" db:seed --force
  else
    sudo -u www-data env \
      HOSTVIM_ADMIN_EMAIL="$ADMIN_EMAIL" \
      HOSTVIM_SEED_DEMO_USERS="$SEED_DEMO_USERS" \
      php "$PANEL_ROOT/artisan" db:seed --force
  fi
fi

sudo -u www-data php "$PANEL_ROOT/artisan" config:cache
sudo -u www-data php "$PANEL_ROOT/artisan" route:cache
sudo -u www-data php "$PANEL_ROOT/artisan" view:cache
sudo -u www-data php "$PANEL_ROOT/artisan" hostvim:ensure-system-cron || true

# OS-level scheduler: Laravel schedule:run her dakika tetiklensin.
rm -f /etc/cron.d/panelsar-panel-scheduler 2>/dev/null || true
cat > /etc/cron.d/hostvim-panel-scheduler <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd "$PANEL_ROOT" && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/hostvim-panel-scheduler
systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

# Geçici .tmp_* dizinleri (yarım unzip/copy): günlük temizlik
rm -f /etc/cron.d/panelsar-cleaner 2>/dev/null || true
if [[ -x /usr/local/sbin/hostvim-cleaner ]]; then
  HOSTVIM_CLEANER_WEB_ROOT="${HOSTVIM_HOSTING_WEB_ROOT:-${HOSTVIM_HOME}/data/www}"
  cat > /etc/cron.d/hostvim-cleaner <<CRON
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
# Her gün 04:17 — 2 saatten eski .tmp_* ( /tmp + web_root )
17 4 * * * root HOSTVIM_HOSTING_WEB_ROOT=${HOSTVIM_CLEANER_WEB_ROOT} /usr/local/sbin/hostvim-cleaner 2>&1 | logger -t hostvim-cleaner
CRON
  chmod 644 /etc/cron.d/hostvim-cleaner
fi

# Queue worker: uzun süren işleri request dışına alır (installer/deploy/stack vb.).
systemctl disable --now panelsar-panel-queue.service 2>/dev/null || true
cat > /etc/systemd/system/hostvim-panel-queue.service <<EOF
[Unit]
Description=Hostvim Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$PANEL_ROOT
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=120
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now hostvim-panel-queue.service

# Nginx — eski Panelsar site dosyası default_server ile çakışmasın (duplicate default server hatası)
rm -f /etc/nginx/sites-enabled/panelsar.conf /etc/nginx/sites-enabled/panelsar 2>/dev/null || true
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

# Nginx
NGX_DST="/etc/nginx/sites-available/hostvim.conf"
sed \
  -e "s|__SERVER_NAME__|$SERVER_NAME|g" \
  -e "s|__PANEL_PUBLIC__|$PANEL_ROOT/public|g" \
  -e "s|__PHP_FPM_SOCK__|$PHP_FPM_SOCK|g" \
  "$REPO_ROOT/deploy/nginx/hostvim.conf" > "$NGX_DST"

if [[ "$SERVER_NAME" == "_" ]]; then
  sed -i 's/listen 80;/listen 80 default_server;/' "$NGX_DST" || true
  sed -i 's/listen \[::\]:80;/listen [::]:80 default_server;/' "$NGX_DST" || true
fi

ln -sf "$NGX_DST" /etc/nginx/sites-enabled/hostvim.conf
nginx -t
systemctl reload nginx
systemctl enable nginx

# UFW
if [[ "${SKIP_UFW:-}" != "1" ]] && command -v ufw >/dev/null 2>&1; then
  ufw allow OpenSSH >/dev/null 2>&1 || ufw allow 22/tcp
  ufw allow 'Nginx Full' >/dev/null 2>&1 || { ufw allow 80/tcp; ufw allow 443/tcp; }
  if [[ "${WITH_APACHE:-1}" == "1" ]] || [[ "${WITH_APACHE:-1}" == "yes" ]]; then
    ufw allow 8080/tcp >/dev/null 2>&1 || true
  fi
  ufw --force enable || true
fi

echo ""
echo "=== Hostvim kurulum özeti ==="
echo "  Panel kökü:     $HOSTVIM_HOME"
if [[ "${SKIP_DB_SEED:-}" != "1" ]] && [[ -n "${ADMIN_EMAIL:-}" ]]; then
  case "$ADMIN_EMAIL" in
    admin@*contaboserver*|admin@vmi*)
      echo "  İpucu: İlk admin e-postası sunucu FQDN. Üretimde: HOSTVIM_ADMIN_EMAIL=... veya HOSTVIM_APP_URL=https://panel.alanadin.com"
      ;;
  esac
fi
echo "  Engine API:     http://127.0.0.1:9090 (yalnızca sunucu içi — dışarıya açmayın)"
echo "  ENGINE_INTERNAL_KEY panel .env ile eşleşiyor."
echo "  Nginx site:     $NGX_DST"
if [[ "${RESET_PANEL_DB:-0}" == "1" ]] || [[ "${RESET_PANEL_DB:-0}" == "yes" ]]; then
  echo "  Fresh mode:     ON (RESET_PANEL_DB=1)"
fi
if [[ "${WITH_APACHE:-1}" == "1" ]] || [[ "${WITH_APACHE:-1}" == "yes" ]]; then
  echo "  Apache HTTP:    :8080 (alan adı Apache seçiliyse http://alan:8080 — SSL için Nginx veya ayrı plan)"
fi
echo ""
if [[ "${SKIP_DB_SEED:-}" != "1" ]]; then
  echo "################################################################"
  echo "#  PANEL GİRİŞİ — Tarayıcıda panele böyle girin"
  echo "################################################################"
  if [[ -f /root/hostvim-admin-login.txt ]]; then
    echo "#  (Güncel: /root/hostvim-admin-login.txt)"
    while IFS= read -r line || [[ -n "$line" ]]; do
      echo "#  $line"
    done < /root/hostvim-admin-login.txt
    echo "################################################################"
  elif [[ -f /root/panelsar-admin-login.txt ]]; then
    echo "#  UYARI: Yalnızca eski panelsar-admin-login.txt bulundu; güvenilir olmayabilir."
    while IFS= read -r line || [[ -n "$line" ]]; do
      echo "#  $line"
    done < /root/panelsar-admin-login.txt
    echo "################################################################"
  else
    echo "#  Giriş dosyası yok. Bilinen admin ile girin veya şifre sıfırlayın."
    echo "################################################################"
  fi
  echo ""
fi
echo "Sonraki adımlar:"
echo "  1) DNS ile alan adını bu sunucuya yönlendirin; ücretsiz SSL: sudo certbot --nginx -d ornek.com --email $LETS_ENCRYPT_EMAIL --agree-tos --non-interactive"
echo "  2) APP_URL ve PHPMYADMIN_URL değerlerini .env içinde gerçek HTTPS URL ile güncelleyin: nano $ENV_FILE"
echo "  3) sudo -u www-data php $PANEL_ROOT/artisan config:cache && php artisan hostvim:install-check"
echo ""
