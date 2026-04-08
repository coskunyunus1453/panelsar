#!/usr/bin/env bash
# Hostvim — tam posta yığını: Postfix (25/587/465) + Dovecot (IMAP) + OpenDKIM + Nginx + Roundcube (SQLite)
# Debian 12 / Ubuntu 22.04+ (Ubuntu: universe etkin olmalı — Roundcube için).
# Üretimde TLS için Let's Encrypt önerilir; ilk kurulum ssl-cert snakeoil kullanır.
#
# Not: Sanal posta kutuları engine ile senkronize edilir; bu betik MTA/IMAP/webmail altyapısını açar.
# Panel nginx yapılandırmasına dokunmaz (default site silinmez).
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

if [[ "$(id -u)" -ne 0 ]]; then
  echo "hostvim-mail-stack-setup: root ile çalıştırılmalı" >&2
  exit 1
fi

HOST_FQDN="$(hostname -f 2>/dev/null || hostname)"
SNAKE_CERT="/etc/ssl/certs/ssl-cert-snakeoil.pem"
SNAKE_KEY="/etc/ssl/private/ssl-cert-snakeoil.key"
OPENDKIM_MILTER="inet:127.0.0.1:8891"

run_apt() {
  apt-get update -qq
  apt-get install -y -qq "$@"
}

echo "==> Paketler (Postfix, Dovecot, OpenDKIM, Nginx, PHP-FPM, Roundcube)..."
run_apt \
  ca-certificates openssl ssl-cert \
  postfix \
  dovecot-core dovecot-imapd dovecot-lmtpd \
  opendkim opendkim-tools \
  nginx \
  php-fpm php-cli php-mbstring php-xml php-intl php-sqlite3 php-curl php-zip \
  sqlite3

if apt-cache show roundcube-core &>/dev/null; then
  run_apt roundcube-core roundcube-sqlite3
else
  echo "Hata: roundcube-core paketi bulunamadı. Ubuntu'da: sudo add-apt-repository universe && apt update" >&2
  exit 1
fi

PHP_SOCK="$(ls -1 /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
if [[ -z "${PHP_SOCK}" ]]; then
  echo "Hata: php-fpm unix socket bulunamadı (/run/php/php*-fpm.sock)" >&2
  exit 1
fi

echo "==> Postfix (TLS, SASL → Dovecot, OpenDKIM milter ${OPENDKIM_MILTER})..."
postconf -e "compatibility_level=3.6"
postconf -e "smtpd_banner=\$myhostname ESMTP Hostvim"
postconf -e "biff=no"
postconf -e "append_dot_mydomain=no"
postconf -e "readme_directory=no"
postconf -e "smtpd_tls_security_level=may"
postconf -e "smtp_tls_security_level=may"
postconf -e "smtpd_tls_auth_only=yes"
postconf -e "smtpd_tls_cert_file=${SNAKE_CERT}"
postconf -e "smtpd_tls_key_file=${SNAKE_KEY}"
postconf -e "smtp_tls_CApath=/etc/ssl/certs"
postconf -e "smtpd_sasl_type=dovecot"
postconf -e "smtpd_sasl_path=private/auth"
postconf -e "smtpd_sasl_auth_enable=yes"
postconf -e "smtpd_sasl_security_options=noanonymous"
postconf -e "smtpd_recipient_restrictions=permit_sasl_authenticated,permit_mynetworks,reject_unauth_destination"
postconf -e "mynetworks=127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128"
postconf -e "inet_interfaces=all"
postconf -e "myhostname=${HOST_FQDN}"
postconf -e "milter_default_action=accept"
postconf -e "milter_protocol=6"
postconf -e "smtpd_milters=${OPENDKIM_MILTER}"
postconf -e "non_smtpd_milters=${OPENDKIM_MILTER}"

MASTER_CF="/etc/postfix/master.cf"
if grep -qE '^#submission' "$MASTER_CF" 2>/dev/null; then
  sed -i 's/^#submission/submission/' "$MASTER_CF" || true
fi
if ! grep -qE '^submission[[:space:]]+inet' "$MASTER_CF" 2>/dev/null; then
  cat >>"$MASTER_CF" <<EOF

# hostvim-mail-stack
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject_unauth_destination
EOF
fi

if ! grep -qE '^smtps[[:space:]]+inet' "$MASTER_CF" 2>/dev/null; then
  cat >>"$MASTER_CF" <<EOF

smtps     inet  n       -       y       -       -       smtpd
  -o syslog_name=postfix/smtps
  -o smtpd_tls_wrappermode=yes
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject_unauth_destination
EOF
fi

echo "==> Dovecot (Postfix SASL soketi; TLS snakeoil)..."
cat >/etc/dovecot/conf.d/99-hostvim-mail-stack.conf <<EOF
ssl_cert = <${SNAKE_CERT}
ssl_key = <${SNAKE_KEY}

service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
}
EOF

echo "==> OpenDKIM (inet:8891 — Postfix ile uyumlu)..."
install -d -m 0750 /etc/opendkim/keys/default
if [[ ! -f /etc/opendkim/keys/default/default.private ]]; then
  opendkim-genkey -b 2048 -d "${HOST_FQDN}" -D /etc/opendkim/keys/default -s default -v
  chown -R opendkim:opendkim /etc/opendkim/keys
fi

cat >/etc/opendkim.conf <<EOF
Syslog                  yes
Canonicalization        relaxed/simple
Mode                    sv
SubDomains              no
AutoRestart             yes
Background              yes
SignatureAlgorithm      rsa-sha256

KeyTable                refile:/etc/opendkim/key.table
SigningTable            refile:/etc/opendkim/signing.table
ExternalIgnoreList      /etc/opendkim/trusted.hosts
InternalHosts           /etc/opendkim/trusted.hosts

Socket                  inet:8891@127.0.0.1
PidFile                 /run/opendkim/opendkim.pid
UMask                   007
UserID                  opendkim:opendkim
EOF

cat >/etc/opendkim/trusted.hosts <<EOF
127.0.0.1
localhost
${HOST_FQDN}
EOF

cat >/etc/opendkim/key.table <<EOF
default._domainkey.${HOST_FQDN} ${HOST_FQDN}:default:/etc/opendkim/keys/default/default.private
EOF

cat >/etc/opendkim/signing.table <<EOF
*@${HOST_FQDN} default._domainkey.${HOST_FQDN}
EOF

echo "==> Roundcube (localhost IMAPS/SMTP submission)..."
install -d -m 0755 /etc/roundcube
cat >/etc/roundcube/config.local.inc.php <<'PHP'
<?php
$config['product_name'] = 'Hostvim Webmail';
$config['default_host'] = 'ssl://127.0.0.1';
$config['default_port'] = 993;
$config['imap_conn_options'] = [
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
  ],
];
$config['smtp_server'] = 'tls://127.0.0.1';
$config['smtp_port'] = 587;
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['smtp_conn_options'] = [
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
  ],
];
PHP

echo "==> Nginx (webmail.* — panel varsayılanına dokunulmadı)..."
cat >/etc/nginx/snippets/hostvim-roundcube-php.conf <<'NGX'
location ~ ^/(bin|SQL|config|temp|logs)/ {
  deny all;
}
location ~ \.php$ {
  include snippets/fastcgi-php.conf;
  fastcgi_pass unix:PHP_SOCK_PLACEHOLDER;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  include fastcgi_params;
}
NGX
sed -i "s|PHP_SOCK_PLACEHOLDER|${PHP_SOCK}|g" /etc/nginx/snippets/hostvim-roundcube-php.conf

cat >/etc/nginx/sites-available/hostvim-roundcube <<'NGX'
server {
  listen 80;
  listen [::]:80;
  server_name ~^webmail\.(?<dom>[a-z0-9.-]+)$;
  root /usr/share/roundcube;
  index index.php;
  client_max_body_size 25M;
  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }
  include snippets/hostvim-roundcube-php.conf;
}
NGX

ln -sf /etc/nginx/sites-available/hostvim-roundcube /etc/nginx/sites-enabled/50-hostvim-roundcube.conf

echo "==> Servisler..."
systemctl enable postfix dovecot opendkim nginx
systemctl enable "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm"
systemctl restart postfix
systemctl restart dovecot
systemctl restart opendkim
systemctl restart nginx
systemctl restart "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm"
nginx -t

echo ""
echo "=== Hostvim mail stack tamam (mail-stack-webmail) ==="
echo "FQDN: ${HOST_FQDN}"
echo "Güvenlik duvarı önerisi: ufw allow 25,80,443,143,465,587,993/tcp"
echo ""
echo "DKIM DNS (default._domainkey.${HOST_FQDN} TXT):"
[[ -f /etc/opendkim/keys/default/default.txt ]] && cat /etc/opendkim/keys/default/default.txt
echo ""
echo "Teslim edilebilirlik: SPF, DKIM, DMARC, gönderici IP için PTR kayıtlarını tamamlayın."
echo "HTTPS: certbot --nginx -d webmail.ornekalan.com (veya uygun san)."
echo "OK mail-stack-webmail"
