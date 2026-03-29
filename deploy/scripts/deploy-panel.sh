#!/usr/bin/env bash
# Panelsar panel — tek sunucu deploy sırası (örnek).
# Kullanım: PANEL_ROOT=/var/www/panelsar/panel bash deploy/scripts/deploy-panel.sh
set -euo pipefail

PANEL_ROOT="${PANEL_ROOT:?PANEL_ROOT tanımlayın (örn. /var/www/panelsar/panel)}"
FRONTEND_ROOT="${FRONTEND_ROOT:-$(dirname "$PANEL_ROOT")/frontend}"
RUN_USER="${RUN_USER:-www-data}"

echo "==> Panel: $PANEL_ROOT"

if [[ ! -f "$PANEL_ROOT/.env" ]]; then
  echo "Hata: $PANEL_ROOT/.env yok. Önce sunucuya .env yerleştirin (Git dışı)." >&2
  exit 1
fi

cd "$PANEL_ROOT"

if command -v git >/dev/null 2>&1 && [[ -d .git ]]; then
  echo "==> git pull"
  git pull --ff-only
fi

echo "==> composer install"
if [[ "$(id -un)" == "$RUN_USER" ]]; then
  composer install --no-dev --optimize-autoloader --no-interaction
else
  sudo -u "$RUN_USER" composer install --no-dev --optimize-autoloader --no-interaction
fi

echo "==> migrate"
sudo -u "$RUN_USER" php artisan migrate --force

echo "==> optimize"
sudo -u "$RUN_USER" php artisan config:cache
sudo -u "$RUN_USER" php artisan route:cache
sudo -u "$RUN_USER" php artisan view:cache

if [[ -d "$FRONTEND_ROOT" ]] && [[ -f "$FRONTEND_ROOT/package.json" ]]; then
  if ! command -v npm >/dev/null 2>&1; then
    echo "Hata: npm yok; frontend derlenemiyor." >&2
    exit 1
  fi
  echo "==> frontend build ($FRONTEND_ROOT)"
  if [[ -f "$FRONTEND_ROOT/package-lock.json" ]]; then
    (cd "$FRONTEND_ROOT" && npm ci && npm run build)
  else
    (cd "$FRONTEND_ROOT" && npm install && npm run build)
  fi
  echo "==> rsync frontend dist -> panel/public (index.php korunur)"
  rsync -a --delete \
    --exclude index.php \
    --exclude .htaccess \
    "$FRONTEND_ROOT/dist/" "$PANEL_ROOT/public/"
fi

echo "==> panelsar:install-check"
sudo -u "$RUN_USER" php artisan panelsar:install-check || true

echo "Tamam."
