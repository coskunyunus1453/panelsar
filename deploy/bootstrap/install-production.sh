#!/usr/bin/env bash
#
# Panelsar — tek sunucuda üretim kurulumu (Debian 12 / Ubuntu 22.04+)
#
# Hedef: güvenlik (engine yalnızca loopback), hız (gzip, static cache), kolaylık (tek komut iskeleti)
#
# Kullanım (root) — sıfır sunucu:
#   git clone <repo> /var/www/panelsar && cd /var/www/panelsar
#   sudo bash deploy/bootstrap/install-production.sh
#
# Sadece kod/config güncellemesi (paket kurulumu atlanır):
#   cd /var/www/panelsar && git pull --ff-only
#   SKIP_APT=1 sudo -E bash deploy/bootstrap/install-production.sh
#
# Ortam değişkenleri (isteğe bağlı):
#   PANELSAR_HOME=/var/www/panelsar
#   SERVER_NAME=_          # sadece IP ile erişim için default_server (nginx şablonunda _)
#   LETS_ENCRYPT_EMAIL=admin@ornek.com
#   SKIP_APT=1             # paket kurulumunu atla (yeniden çalıştırma)
#   SKIP_UFW=1             # UFW kurma
#   WITH_MARIADB=1         # MariaDB kur ve panel veritabanını oluştur (önerilir)
#   WITH_POSTGRES=1        # Engine için PostgreSQL (isteğe bağlı)
#   WITH_NODE_REPO=1       # NodeSource 20.x ekle (frontend build için önerilir)
#   PANELSAR_GO_VERSION=1.22.3  # engine/go.mod ile uyumlu (varsayılan; go.dev'den kurulur)
#   PANELSAR_PHP_VERSION=8.4    # panel/composer.lock (Ondrej/Sury); Symfony 8 için 8.4 önerilir
#   PANELSAR_EXTRA_PHP_FPM_VERSIONS="8.3 8.2"  # ek FPM (boş = yalnız ana sürüm)
#   WITH_PHPMYADMIN=1           # apt phpMyAdmin + Nginx /phpmyadmin + PHPMYADMIN_URL
#   WITH_CERTBOT=1              # certbot + python3-certbot-nginx (Let's Encrypt)
#   WITH_APACHE=1               # apache2; Nginx 80 ile çakışmaz — Apache :8080 + engine apache_http_port: 8080
#   WITH_LOCAL_POSTFIX=1        # Postfix + mailutils (panel giden posta: sendmail; Admin → Giden posta’dan SMTP’ye geçilebilir)
#   SKIP_DB_SEED=1              # migrate sonrası db:seed atla
#   RESET_PANEL_DB=1            # DİKKAT: Panel veritabanını her çalıştırmada sıfırlar (kullanıcı/domain vb. silinir)
#   PANELSAR_SEED_DEMO_USERS=1  # Demo reseller/user hesaplarını da seed et (varsayılan: 0)
#   (engine systemd drop-in) PANELSAR_TERMINAL_NO_ROOT=1  # web terminali www-data kabuğunda (varsayılan: root sudo)
#   PANELSAR_ADMIN_EMAIL=...    # ilk admin e-posta (varsayılan: admin@<sunucu-hostname>)
#   PANELSAR_ADMIN_PASSWORD=... # sabit şifre; verilmezse kurulumda rastgele üretilir
#
set -euo pipefail

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
PANELSAR_HOME="${PANELSAR_HOME:-/var/www/panelsar}"
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
  echo "Hata: panel/ veya engine/ bulunamadı. Bu betiği repo kökünden çalıştırın (PANELSAR_HOME=$PANELSAR_HOME)." >&2
  exit 1
fi

if [[ "$PANELSAR_HOME" != "$REPO_ROOT" ]]; then
  echo "Uyarı: PANELSAR_HOME ($PANELSAR_HOME) ile repo ($REPO_ROOT) farklı. Aynı yapın önerilir." >&2
fi

export DEBIAN_FRONTEND=noninteractive

detect_php_fpm_sock() {
  local pv="${PANELSAR_PHP_VERSION:-8.4}"
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

panelsar_git_safe_directory() {
  local d="$1"
  [[ -d "$d/.git" ]] || return 0
  if ! git config --system --get-all safe.directory 2>/dev/null | grep -qxF "$d"; then
    git config --system --add safe.directory "$d"
  fi
}

# Nginx panel 80/443 kullanır; Apache yalnızca HTTP 8080 (engine hosting.apache_http_port ile uyumlu)
panelsar_apache_bind_8080() {
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
    panelsar_apache_bind_8080
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

mkdir -p "$PANELSAR_HOME/data"/{www,tmp,ssl,backups,logs,vhosts}
mkdir -p /etc/panelsar
chown -R www-data:www-data "$PANELSAR_HOME/data"

# Kimlik anahtarları:
# - İlk kurulumda güvenli rastgele üretilir.
# - Sonraki kurulum/güncellemelerde mevcut /etc/panelsar/engine.yaml içinden okunup korunur.
#   Böylece panel↔engine auth kopmaz.
ENGINE_DST="/etc/panelsar/engine.yaml"
FORCE_ROTATE_ENGINE_KEYS="${FORCE_ROTATE_ENGINE_KEYS:-0}"

if [[ -f "$ENGINE_DST" ]] && [[ "$FORCE_ROTATE_ENGINE_KEYS" != "1" ]]; then
  EXISTING_INTERNAL_KEY="$(yaml_value_from_block "$ENGINE_DST" "security" "internal_api_key" || true)"
  EXISTING_ENGINE_JWT="$(yaml_value_from_block "$ENGINE_DST" "security" "jwt_secret" || true)"
  EXISTING_ENGINE_SECRET="$(yaml_value_from_block "$ENGINE_DST" "server" "secret_key" || true)"
else
  EXISTING_INTERNAL_KEY=""
  EXISTING_ENGINE_JWT=""
  EXISTING_ENGINE_SECRET=""
fi

INTERNAL_KEY="${EXISTING_INTERNAL_KEY:-$(openssl rand -hex 32)}"
ENGINE_SECRET="${EXISTING_ENGINE_SECRET:-$(openssl rand -hex 32)}"
ENGINE_JWT="${EXISTING_ENGINE_JWT:-$(openssl rand -hex 32)}"
ENGINE_DB_PASS="$(openssl rand -hex 24)"

# Engine yaml
ENGINE_TMPL="$REPO_ROOT/deploy/configs/engine.production.yaml"
sed \
  -e "s|__INTERNAL_KEY__|$INTERNAL_KEY|g" \
  -e "s|__ENGINE_SECRET_KEY__|$ENGINE_SECRET|g" \
  -e "s|__ENGINE_JWT_SECRET__|$ENGINE_JWT|g" \
  -e "s|__ENGINE_DB_PASSWORD__|$ENGINE_DB_PASS|g" \
  -e "s|__PANELSAR_HOME__|$PANELSAR_HOME|g" \
  -e "s|__LETS_ENCRYPT_EMAIL__|$LETS_ENCRYPT_EMAIL|g" \
  -e "s|__PHP_FPM_SOCKET__|$PHP_FPM_SOCK|g" \
  "$ENGINE_TMPL" > "$ENGINE_DST"
chmod 640 "$ENGINE_DST"
chown root:www-data "$ENGINE_DST"

# PostgreSQL engine kullanıcısı (isteğe bağlı)
if [[ "${WITH_POSTGRES:-}" == "1" ]] || [[ "${WITH_POSTGRES:-}" == "yes" ]]; then
  sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='panelsar'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER panelsar WITH PASSWORD '$ENGINE_DB_PASS';"
  sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='panelsar'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE panelsar OWNER panelsar;"
fi

# Go engine derle (apt'teki golang-go genelde esiktir; ensure-go-toolchain.sh go.dev sürümünü kurar)
ensure_go_toolchain
(cd "$REPO_ROOT/engine" && go build -buildvcs=false -o /usr/local/bin/panelsar-engine ./cmd/panelsar-engine)
chmod 755 /usr/local/bin/panelsar-engine

# systemd
sed \
  -e "s|__PANELSAR_HOME__|$PANELSAR_HOME|g" \
  -e "s|__ENGINE_BINARY__|/usr/local/bin/panelsar-engine|g" \
  "$REPO_ROOT/deploy/systemd/panelsar-engine.service" > /etc/systemd/system/panelsar-engine.service
systemctl daemon-reload
if [[ -x /usr/local/bin/panelsar-engine ]]; then
  systemctl enable panelsar-engine
  systemctl restart panelsar-engine || true
fi

# Engine www-data iken nginx sites-enabled'a yazamaz; sudo ile izinli betikler
if [[ -f "$REPO_ROOT/deploy/host/panelsar-nginx-vhost" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/panelsar-nginx-vhost" /usr/local/sbin/panelsar-nginx-vhost
fi
if [[ -f "$REPO_ROOT/deploy/host/panelsar-stack-install" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/panelsar-stack-install" /usr/local/sbin/panelsar-stack-install
fi
if [[ -f "$REPO_ROOT/deploy/host/panelsar-terminal-root" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/panelsar-terminal-root" /usr/local/sbin/panelsar-terminal-root
fi
if [[ -f "$REPO_ROOT/deploy/host/panelsar-php-ini" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/panelsar-php-ini" /usr/local/sbin/panelsar-php-ini
fi
if [[ -f "$REPO_ROOT/deploy/host/panelsar-security" ]]; then
  install -m 755 "$REPO_ROOT/deploy/host/panelsar-security" /usr/local/sbin/panelsar-security
fi
cat > /etc/sudoers.d/panelsar-engine <<'SUDOERS'
www-data ALL=(root) NOPASSWD: /usr/local/sbin/panelsar-nginx-vhost
www-data ALL=(root) NOPASSWD: /usr/local/sbin/panelsar-stack-install
www-data ALL=(root) NOPASSWD: /usr/local/sbin/panelsar-terminal-root
www-data ALL=(root) NOPASSWD: /usr/local/sbin/panelsar-php-ini
www-data ALL=(root) NOPASSWD: /usr/local/sbin/panelsar-security
SUDOERS
chmod 440 /etc/sudoers.d/panelsar-engine
visudo -cf /etc/sudoers.d/panelsar-engine

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

php "$PANEL_ROOT/artisan" key:generate --force 2>/dev/null || true

# .env üretim ayarları (sed ile idempotent değil; basit grep ile atla)
update_env() {
  local key="$1" val="$2"
  if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" "$ENV_FILE"
  else
    echo "${key}=${val}" >> "$ENV_FILE"
  fi
}

update_env "APP_ENV" "production"
update_env "APP_DEBUG" "false"
update_env "APP_PROFILE" "$APP_PROFILE"
update_env "ENFORCE_ADMIN_2FA" "$ENFORCE_ADMIN_2FA"
update_env "APP_URL" "http://$(hostname -I 2>/dev/null | awk '{print $1}' || echo localhost)"
update_env "ENGINE_API_URL" "http://127.0.0.1:9090"
update_env "ENGINE_INTERNAL_KEY" "$INTERNAL_KEY"
update_env "ENGINE_API_SECRET" "$ENGINE_JWT"
update_env "LOG_LEVEL" "error"

# Yerel Postfix: Laravel sendmail ile gönderir; SMTP’yi panelden (Admin → Giden posta) tanımlarsınız
if [[ "${WITH_LOCAL_POSTFIX:-1}" == "1" ]] || [[ "${WITH_LOCAL_POSTFIX:-1}" == "yes" ]]; then
  _MAIL_FROM="noreply@$(hostname -f 2>/dev/null || hostname)"
  update_env "MAIL_MAILER" "sendmail"
  update_env "MAIL_FROM_ADDRESS" "\"${_MAIL_FROM}\""
  update_env "MAIL_FROM_NAME" "\"Panelsar\""
fi

# phpMyAdmin (kuruluysa panelde otomatik link)
if [[ -d /usr/share/phpmyadmin ]]; then
  _APP_URL_VAL="$(grep '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r' | tr -d ' ')"
  [[ -n "$_APP_URL_VAL" ]] && update_env "PHPMYADMIN_URL" "${_APP_URL_VAL%/}/phpmyadmin"
fi

# MariaDB panel DB
if [[ "${WITH_MARIADB}" == "1" ]] || [[ "${WITH_MARIADB}" == "yes" ]]; then
  # Yeniden kurulumda her seferinde yeni şifre üretmek, CREATE USER IF NOT EXISTS ile uyumsuzluk (1045) yaratır
  if [[ -s /root/panelsar-panel-mysql.secret ]]; then
    PANEL_DB_PASS="$(cat /root/panelsar-panel-mysql.secret)"
  else
    PANEL_DB_PASS="$(openssl rand -hex 16)"
  fi
  MARIADB_CMD=(mariadb)
  command -v mariadb >/dev/null 2>&1 || MARIADB_CMD=(mysql)
  "${MARIADB_CMD[@]}" -e "CREATE DATABASE IF NOT EXISTS panelsar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true
  "${MARIADB_CMD[@]}" -e "CREATE USER IF NOT EXISTS 'panelsar'@'localhost' IDENTIFIED BY '$PANEL_DB_PASS';" || true
  "${MARIADB_CMD[@]}" -e "ALTER USER 'panelsar'@'localhost' IDENTIFIED BY '$PANEL_DB_PASS';" || true
  "${MARIADB_CMD[@]}" -e "GRANT ALL PRIVILEGES ON panelsar.* TO 'panelsar'@'localhost'; FLUSH PRIVILEGES;" || true
  update_env "DB_CONNECTION" "mysql"
  update_env "DB_HOST" "127.0.0.1"
  update_env "DB_PORT" "3306"
  update_env "DB_DATABASE" "panelsar"
  update_env "DB_USERNAME" "panelsar"
  update_env "DB_PASSWORD" "$PANEL_DB_PASS"

  # Hosting panelinden DB oluşturma için root yerine ayrı bir servis kullanıcısı.
  if [[ -s /root/panelsar-mysql-provision.secret ]]; then
    MYSQL_PROVISION_PASS="$(cat /root/panelsar-mysql-provision.secret)"
  else
    MYSQL_PROVISION_PASS="$(openssl rand -hex 18)"
  fi
  "${MARIADB_CMD[@]}" -e "CREATE USER IF NOT EXISTS 'panelsar_provision'@'localhost' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "CREATE USER IF NOT EXISTS 'panelsar_provision'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "ALTER USER 'panelsar_provision'@'localhost' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "ALTER USER 'panelsar_provision'@'127.0.0.1' IDENTIFIED BY '$MYSQL_PROVISION_PASS';" || true
  "${MARIADB_CMD[@]}" -e "GRANT ALL PRIVILEGES ON *.* TO 'panelsar_provision'@'localhost' WITH GRANT OPTION;" || true
  "${MARIADB_CMD[@]}" -e "GRANT ALL PRIVILEGES ON *.* TO 'panelsar_provision'@'127.0.0.1' WITH GRANT OPTION;" || true
  "${MARIADB_CMD[@]}" -e "FLUSH PRIVILEGES;" || true
  update_env "MYSQL_PROVISION_ENABLED" "true"
  update_env "MYSQL_PROVISION_HOST" "localhost"
  update_env "MYSQL_PROVISION_PORT" "3306"
  update_env "MYSQL_PROVISION_USERNAME" "panelsar_provision"
  update_env "MYSQL_PROVISION_PASSWORD" "$MYSQL_PROVISION_PASS"
  echo "$MYSQL_PROVISION_PASS" > /root/panelsar-mysql-provision.secret
  chmod 600 /root/panelsar-mysql-provision.secret
  echo "MySQL provision şifresi: /root/panelsar-mysql-provision.secret"

  echo "$PANEL_DB_PASS" > /root/panelsar-panel-mysql.secret
  chmod 600 /root/panelsar-panel-mysql.secret
  echo "Panel MySQL şifresi: /root/panelsar-panel-mysql.secret"
fi

# Composer www-data ile çalışır; panel/ yalnızca storage/cache www-data ise vendor/ oluşturulamaz
mkdir -p "$PANEL_ROOT/vendor"
chown -R www-data:www-data "$PANEL_ROOT"
chmod -R ug+rwx "$PANEL_ROOT/storage" "$PANEL_ROOT/bootstrap/cache"

panelsar_git_safe_directory "$REPO_ROOT"

sudo -u www-data composer --working-dir="$PANEL_ROOT" install --no-dev --optimize-autoloader --no-interaction

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
    (cd "$FRONTEND_ROOT" && npm ci && VITE_APP_PROFILE="$APP_PROFILE" npm run build)
  else
    (cd "$FRONTEND_ROOT" && npm install && VITE_APP_PROFILE="$APP_PROFILE" npm run build)
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
  sudo -u www-data php "$PANEL_ROOT/artisan" migrate --force
fi
sudo -u www-data php "$PANEL_ROOT/artisan" panelsar:init-outbound-mail --no-interaction 2>/dev/null || true

if [[ "${SKIP_DB_SEED:-}" != "1" ]]; then
  RESET_DB_MODE=0
  if [[ "${RESET_PANEL_DB:-0}" == "1" ]] || [[ "${RESET_PANEL_DB:-0}" == "yes" ]]; then
    RESET_DB_MODE=1
  fi

  HOST_FQDN="$(hostname -f 2>/dev/null || hostname || echo panelsar.local)"
  HOST_FQDN="${HOST_FQDN// /}"
  ADMIN_EMAIL="${PANELSAR_ADMIN_EMAIL:-admin@${HOST_FQDN}}"
  SEED_DEMO_USERS="${PANELSAR_SEED_DEMO_USERS:-0}"
  USER_COUNT=""
  if [[ "$RESET_DB_MODE" == "0" ]] && { [[ "${WITH_MARIADB}" == "1" ]] || [[ "${WITH_MARIADB}" == "yes" ]]; }; then
    DB_PW=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2- | tr -d '\r')
    MARIADB_CMD=(mariadb)
    command -v mariadb >/dev/null 2>&1 || MARIADB_CMD=(mysql)
    if [[ -n "$DB_PW" ]]; then
      USER_COUNT=$(MYSQL_PWD="$DB_PW" "${MARIADB_CMD[@]}" -u panelsar -h 127.0.0.1 panelsar -Nse "SELECT COUNT(*)" 2>/dev/null || echo "")
    fi
  elif [[ "$RESET_DB_MODE" == "0" ]] && grep -q '^DB_CONNECTION=sqlite' "$ENV_FILE" 2>/dev/null && [[ -f "$PANEL_ROOT/database/database.sqlite" ]]; then
    USER_COUNT=$(sqlite3 "$PANEL_ROOT/database/database.sqlite" "SELECT COUNT(*) FROM users;" 2>/dev/null || echo "")
  fi
  [[ -n "$USER_COUNT" ]] || USER_COUNT=0

  WRITE_LOGIN=0
  ADMIN_PASSWORD=""
  if [[ "$RESET_DB_MODE" == "1" ]]; then
    # Fresh kurulum modunda her zaman yeni admin kimliği üret.
    ADMIN_PASSWORD="${PANELSAR_ADMIN_PASSWORD:-$(openssl rand -hex 12)}"
    WRITE_LOGIN=1
  elif [[ -n "${PANELSAR_ADMIN_PASSWORD:-}" ]]; then
    ADMIN_PASSWORD="$PANELSAR_ADMIN_PASSWORD"
    WRITE_LOGIN=1
  elif [[ "$USER_COUNT" == "0" ]]; then
    ADMIN_PASSWORD="$(openssl rand -hex 12)"
    WRITE_LOGIN=1
  fi

  LOGIN_FILE="/root/panelsar-admin-login.txt"
  PANEL_URL_HINT="$(grep -E '^APP_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
  [[ -n "$PANEL_URL_HINT" ]] || PANEL_URL_HINT="http://$(hostname -I 2>/dev/null | awk '{print $1}' || echo localhost)"

  if [[ "$WRITE_LOGIN" == "1" ]]; then
    {
      echo "Panelsar — ilk admin girişi"
      echo "Panel URL: ${PANEL_URL_HINT}"
      echo "E-posta:   ${ADMIN_EMAIL}"
      echo "Şifre:     ${ADMIN_PASSWORD}"
      echo "İlk girişten sonra şifreyi değiştirin."
    } > "$LOGIN_FILE"
    chmod 600 "$LOGIN_FILE"
  fi

  if [[ -n "$ADMIN_PASSWORD" ]]; then
    sudo -u www-data env \
      PANELSAR_ADMIN_EMAIL="$ADMIN_EMAIL" \
      PANELSAR_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
      PANELSAR_SEED_DEMO_USERS="$SEED_DEMO_USERS" \
      php "$PANEL_ROOT/artisan" db:seed --force
  else
    sudo -u www-data env \
      PANELSAR_ADMIN_EMAIL="$ADMIN_EMAIL" \
      PANELSAR_SEED_DEMO_USERS="$SEED_DEMO_USERS" \
      php "$PANEL_ROOT/artisan" db:seed --force
  fi
fi

sudo -u www-data php "$PANEL_ROOT/artisan" config:cache
sudo -u www-data php "$PANEL_ROOT/artisan" route:cache
sudo -u www-data php "$PANEL_ROOT/artisan" view:cache
sudo -u www-data php "$PANEL_ROOT/artisan" panelsar:ensure-system-cron || true

# OS-level scheduler: Laravel schedule:run her dakika tetiklensin.
cat > /etc/cron.d/panelsar-panel-scheduler <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd "$PANEL_ROOT" && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/panelsar-panel-scheduler
systemctl enable --now cron 2>/dev/null || systemctl enable --now crond 2>/dev/null || true

# Queue worker: uzun süren işleri request dışına alır (installer/deploy/stack vb.).
cat > /etc/systemd/system/panelsar-panel-queue.service <<EOF
[Unit]
Description=Panelsar Laravel Queue Worker
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
systemctl enable --now panelsar-panel-queue.service

# Nginx
NGX_DST="/etc/nginx/sites-available/panelsar.conf"
sed \
  -e "s|__SERVER_NAME__|$SERVER_NAME|g" \
  -e "s|__PANEL_PUBLIC__|$PANEL_ROOT/public|g" \
  -e "s|__PHP_FPM_SOCK__|$PHP_FPM_SOCK|g" \
  "$REPO_ROOT/deploy/nginx/panelsar.conf" > "$NGX_DST"

if [[ "$SERVER_NAME" == "_" ]]; then
  sed -i 's/listen 80;/listen 80 default_server;/' "$NGX_DST" || true
  sed -i 's/listen \[::\]:80;/listen [::]:80 default_server;/' "$NGX_DST" || true
fi

ln -sf "$NGX_DST" /etc/nginx/sites-enabled/panelsar.conf
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
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
echo "=== Panelsar kurulum özeti ==="
echo "  Panel kökü:     $PANELSAR_HOME"
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
  if [[ -n "${ADMIN_PASSWORD:-}" ]] && [[ "${WRITE_LOGIN:-0}" == "1" ]]; then
    echo "#  Adres:      ${PANEL_URL_HINT}"
    echo "#  E-posta:    ${ADMIN_EMAIL}"
    echo "#  Şifre:      ${ADMIN_PASSWORD}"
    echo "################################################################"
    echo "#  (Kopya: /root/panelsar-admin-login.txt)"
  elif [[ -f /root/panelsar-admin-login.txt ]]; then
    echo "#  (Önceki kurulumdan kayıtlı giriş bilgisi:)"
    while IFS= read -r line || [[ -n "$line" ]]; do
      echo "#  $line"
    done < /root/panelsar-admin-login.txt
    echo "################################################################"
  else
    echo "#  Bu çalıştırmada yeni şifre üretilmedi (kullanıcılar zaten vardı)."
    echo "#  Bilinen admin ile girin veya şifre sıfırlayın."
    echo "################################################################"
  fi
  echo ""
fi
echo "Sonraki adımlar:"
echo "  1) DNS ile alan adını bu sunucuya yönlendirin; ücretsiz SSL: sudo certbot --nginx -d ornek.com --email $LETS_ENCRYPT_EMAIL --agree-tos --non-interactive"
echo "  2) APP_URL ve PHPMYADMIN_URL değerlerini .env içinde gerçek HTTPS URL ile güncelleyin: nano $ENV_FILE"
echo "  3) sudo -u www-data php $PANEL_ROOT/artisan config:cache && php artisan panelsar:install-check"
echo ""
