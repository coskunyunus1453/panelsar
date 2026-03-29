#!/usr/bin/env bash
# Panelsar: panel/composer.lock ile uyumlu PHP-FPM (Ondrej / Sury).
# Ubuntu varsayılan php-fpm çoğu zaman 8.1 — Laravel 11 + kilit dosyası 8.2+ (çoğu paket 8.3/8.4) ister.
#
# Ortam:
#   PANELSAR_PHP_VERSION=8.4   # panel + varsayılan FPM (composer.lock / Symfony 8)
#   PANELSAR_EXTRA_PHP_FPM_VERSIONS="8.3 8.2"   # ek FPM havuzları (boş string = yalnız ana sürüm)

ensure_php_fpm_packages() {
  [[ "${SKIP_APT:-}" == "1" ]] && return 0

  local pv="${PANELSAR_PHP_VERSION:-8.4}"
  local extra="${PANELSAR_EXTRA_PHP_FPM_VERSIONS:-8.3 8.2}"
  if [[ ! -r /etc/os-release ]]; then
    echo "Hata: /etc/os-release bulunamadı." >&2
    exit 1
  fi
  # shellcheck source=/dev/null
  . /etc/os-release

  case "${ID:-}" in
    ubuntu)
      if ! grep -rq "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
        apt-get install -y -qq software-properties-common
        add-apt-repository -y ppa:ondrej/php
      fi
      ;;
    debian)
      apt-get install -y -qq lsb-release ca-certificates apt-transport-https curl gnupg
      if [[ ! -f /etc/apt/sources.list.d/php-sury.list ]]; then
        curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/php-sury.gpg
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php-sury.list
      fi
      ;;
    *)
      echo "Hata: Desteklenmeyen dağıtım: ${ID:-?} (ubuntu veya debian gerekir)." >&2
      exit 1
      ;;
  esac

  apt-get update -qq
  apt-get install -y -qq \
    "php${pv}-fpm" \
    "php${pv}-cli" \
    "php${pv}-common" \
    "php${pv}-curl" \
    "php${pv}-mbstring" \
    "php${pv}-xml" \
    "php${pv}-bcmath" \
    "php${pv}-zip" \
    "php${pv}-intl" \
    "php${pv}-sqlite3" \
    "php${pv}-mysql" \
    "php${pv}-pgsql"

  # Ek PHP-FPM sürümleri (alan adı PHP sürüm seçimi için)
  local ev ver
  for ev in $extra; do
    ver="${ev//[^0-9.]/}"
    [[ -z "$ver" ]] && continue
    [[ "$ver" == "$pv" ]] && continue
    apt-get install -y -qq \
      "php${ver}-fpm" \
      "php${ver}-cli" \
      "php${ver}-common" \
      "php${ver}-curl" \
      "php${ver}-mbstring" \
      "php${ver}-xml" \
      "php${ver}-zip" \
      "php${ver}-mysql" \
      "php${ver}-sqlite3" || true
    if systemctl list-unit-files "php${ver}-fpm.service" &>/dev/null || [[ -f "/lib/systemd/system/php${ver}-fpm.service" ]]; then
      systemctl enable "php${ver}-fpm" 2>/dev/null || true
      systemctl restart "php${ver}-fpm" 2>/dev/null || true
    fi
  done

  if [[ -x "/usr/bin/php${pv}" ]]; then
    update-alternatives --install /usr/bin/php php "/usr/bin/php${pv}" 100 || true
    update-alternatives --set php "/usr/bin/php${pv}" || true
  fi

  systemctl enable "php${pv}-fpm"
  systemctl restart "php${pv}-fpm"

  # Tutulmayan eski FPM'leri durdur (panel + extra listesi dışı)
  local keep=" $pv "
  for ev in $extra; do
    ver="${ev//[^0-9.]/}"
    [[ -n "$ver" ]] && keep+=" ${ver} "
  done
  for old in 7.4 8.0 8.1 8.2 8.3 8.4; do
    if [[ "$keep" == *" $old "* ]]; then
      continue
    fi
    if systemctl is-active --quiet "php${old}-fpm" 2>/dev/null; then
      systemctl stop "php${old}-fpm" 2>/dev/null || true
    fi
  done

  echo "==> PHP hazır: $(php -v | head -n1)"
}

require_php_for_composer() {
  # SKIP_APT=1 ile elle kurulumda minimum kontrol (composer.lock / Symfony 8 → 8.4)
  if ! command -v php >/dev/null 2>&1; then
    echo "Hata: php yok. Kurun veya SKIP_APT=0 ile tekrar çalıştırın." >&2
    exit 1
  fi
  local major minor
  major=$(php -r 'echo PHP_MAJOR_VERSION;')
  minor=$(php -r 'echo PHP_MINOR_VERSION;')
  if [[ "$major" -lt 8 ]] || { [[ "$major" -eq 8 ]] && [[ "$minor" -lt 4 ]]; }; then
    echo "Hata: PHP 8.4+ gerekli (composer.lock); şu an: ${major}.${minor}. Ondrej/Sury ile php8.4 kurun veya SKIP_APT=0 ile betiği çalıştırın." >&2
    exit 1
  fi
}
