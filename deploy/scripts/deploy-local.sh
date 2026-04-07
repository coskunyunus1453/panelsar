#!/usr/bin/env bash
set -euo pipefail

# Yerel geliştirme deploy:
# - Frontend'i doğru base path ile build eder
# - dist çıktısını panel/public altına senkronlar
# - Laravel optimize/cache temizliğini dener (izin yoksa uyarı verir)
#
# Kullanım:
#   bash deploy/scripts/deploy-local.sh
#
# Opsiyonel:
#   VITE_BASE_URL=/hostvim/panel/public/ bash deploy/scripts/deploy-local.sh
#   PANEL_ROOT=/custom/path/panel FRONTEND_ROOT=/custom/path/frontend bash deploy/scripts/deploy-local.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

PANEL_ROOT="${PANEL_ROOT:-$REPO_ROOT/panel}"
FRONTEND_ROOT="${FRONTEND_ROOT:-$REPO_ROOT/frontend}"
VITE_BASE_URL="${VITE_BASE_URL:-/hostvim/panel/public/}"

echo "==> Repo: $REPO_ROOT"
echo "==> Frontend: $FRONTEND_ROOT"
echo "==> Panel: $PANEL_ROOT"
echo "==> VITE_BASE_URL: $VITE_BASE_URL"

if [[ ! -f "$FRONTEND_ROOT/package.json" ]]; then
  echo "Hata: $FRONTEND_ROOT/package.json bulunamadı." >&2
  exit 1
fi

if [[ ! -d "$PANEL_ROOT/public" ]]; then
  echo "Hata: $PANEL_ROOT/public dizini bulunamadı." >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "Hata: npm bulunamadı." >&2
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "Hata: rsync bulunamadı." >&2
  exit 1
fi

echo "==> Frontend build"
(cd "$FRONTEND_ROOT" && VITE_BASE_URL="$VITE_BASE_URL" npm run build)

echo "==> dist -> panel/public senkron"
if ! rsync -a --delete \
  --exclude index.php \
  --exclude .htaccess \
  "$FRONTEND_ROOT/dist/" "$PANEL_ROOT/public/"; then
  echo "Uyari: rsync izin hatasi verdi, sudo ile tekrar deneniyor..."
  sudo rsync -a --delete \
    --exclude index.php \
    --exclude .htaccess \
    "$FRONTEND_ROOT/dist/" "$PANEL_ROOT/public/"
fi

# Önce storage/bootstrap izinleri — aksi halde artisan cache:clear (file driver) Permission denied verir.
if [[ -f "$SCRIPT_DIR/fix-panel-permissions.sh" ]] && [[ -f "$PANEL_ROOT/artisan" ]]; then
  echo "==> storage/bootstrap/public/admin izinleri (Laravel öncesi; sudo gerekebilir)"
  if [[ "$(uname -s)" == "Darwin" ]] && [[ -d "/Applications/XAMPP/xamppfiles" ]]; then
    export RUN_USER="${RUN_USER:-daemon}"
    export RUN_GROUP="${RUN_GROUP:-daemon}"
  fi
  bash "$SCRIPT_DIR/fix-panel-permissions.sh" "$PANEL_ROOT" || {
    echo "Uyari: chown/chmod atlandi; gerekirse: sudo bash $SCRIPT_DIR/fix-panel-permissions.sh $PANEL_ROOT" >&2
  }
fi

if [[ -f "$PANEL_ROOT/artisan" ]]; then
  echo "==> hostvim:fix-permissions (dizinler + chmod)"
  (cd "$PANEL_ROOT" && php artisan hostvim:fix-permissions) || {
    echo "Uyari: hostvim:fix-permissions kismen basarisiz olabilir." >&2
  }

  echo "==> Laravel optimize:clear"
  (cd "$PANEL_ROOT" && php artisan optimize:clear) || {
    echo "Uyari: optimize:clear calisamadi (izin/ortam kontrol edin)." >&2
  }

  echo "==> Laravel cache:clear (opsiyonel)"
  (cd "$PANEL_ROOT" && php artisan cache:clear) || {
    echo "Uyari: cache:clear calisamadi. Calistirin: sudo bash $SCRIPT_DIR/fix-panel-permissions.sh $PANEL_ROOT" >&2
  }
fi

echo "Tamam. Simdi tarayicida hard refresh yapin (Cmd+Shift+R)."
