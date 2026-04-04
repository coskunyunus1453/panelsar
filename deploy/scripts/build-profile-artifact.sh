#!/usr/bin/env bash
set -euo pipefail

PROFILE="${1:-}"
OUT_DIR="${2:-}"

if [[ -z "$PROFILE" ]]; then
  echo "Kullanim: $0 <customer|vendor> [output-dir]" >&2
  exit 1
fi

if [[ "$PROFILE" != "customer" && "$PROFILE" != "vendor" ]]; then
  echo "Gecersiz profil: $PROFILE (customer|vendor olmali)" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMMON_EXCLUDES="$REPO_ROOT/deploy/profiles/common.exclude"
PROFILE_EXCLUDES="$REPO_ROOT/deploy/profiles/${PROFILE}.exclude"

if [[ ! -f "$COMMON_EXCLUDES" ]] || [[ ! -f "$PROFILE_EXCLUDES" ]]; then
  echo "Exclude dosyalari eksik: $COMMON_EXCLUDES veya $PROFILE_EXCLUDES" >&2
  exit 1
fi

if [[ -z "$OUT_DIR" ]]; then
  OUT_DIR="$REPO_ROOT/dist-artifacts"
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
COMMIT="$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo "unknown")"
PKG_ROOT="$OUT_DIR/work-${PROFILE}-${STAMP}"
PKG_NAME="hostvim-${PROFILE}-${STAMP}-${COMMIT}.tar.gz"
PKG_PATH="$OUT_DIR/$PKG_NAME"

mkdir -p "$OUT_DIR"
rm -rf "$PKG_ROOT"
mkdir -p "$PKG_ROOT"

echo "==> Profil artifact hazirlaniyor: $PROFILE"
echo "==> Kopyalama: $REPO_ROOT -> $PKG_ROOT"

rsync -a \
  --exclude-from="$COMMON_EXCLUDES" \
  --exclude-from="$PROFILE_EXCLUDES" \
  "$REPO_ROOT/" "$PKG_ROOT/"

mkdir -p "$PKG_ROOT/deploy/meta"
{
  echo "profile=$PROFILE"
  echo "commit=$COMMIT"
  echo "built_at=$STAMP"
} > "$PKG_ROOT/deploy/meta/profile-artifact.env"

# Varsayilan profili artifact'e sabitle (deploy script tarafinda override edilebilir).
if [[ -f "$PKG_ROOT/panel/.env.production.example" ]]; then
  if grep -q "^APP_PROFILE=" "$PKG_ROOT/panel/.env.production.example"; then
    sed -i.bak "s/^APP_PROFILE=.*/APP_PROFILE=$PROFILE/" "$PKG_ROOT/panel/.env.production.example"
    rm -f "$PKG_ROOT/panel/.env.production.example.bak"
  else
    printf "\nAPP_PROFILE=%s\n" "$PROFILE" >> "$PKG_ROOT/panel/.env.production.example"
  fi
fi

tar -czf "$PKG_PATH" -C "$OUT_DIR" "$(basename "$PKG_ROOT")"
rm -rf "$PKG_ROOT"

echo "==> Tamamlandi: $PKG_PATH"
