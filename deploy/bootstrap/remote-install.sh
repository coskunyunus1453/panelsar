#!/usr/bin/env bash
#
# Müşteri sunucusunda çalıştırılır (root). Repoyu sizin barındırdığınız adresten çeker ve üretim kurulumunu başlatır.
#
# Örnek (sizin dağıtım URL’nize göre değiştirin):
#   curl -fsSL https://install.panelsar.com/remote-install.sh | sudo bash
#
# Veya ortam değişkenleriyle:
#   curl -fsSL https://install.panelsar.com/remote-install.sh | sudo -E bash -s
#
#   PANELSAR_REPO_URL=https://github.com/sirket/panelsar.git \
#   PANELSAR_BRANCH=main \
#   PANELSAR_HOME=/var/www/panelsar \
#   bash -s <<< "$(curl -fsSL https://install.panelsar.com/remote-install.sh)"
#
set -euo pipefail

[[ "$(id -u)" -eq 0 ]] || { echo "Root gerekli: sudo bash veya curl ... | sudo bash" >&2; exit 1; }

PANELSAR_REPO_URL="${PANELSAR_REPO_URL:-https://github.com/panelsar/panelsar.git}"
PANELSAR_BRANCH="${PANELSAR_BRANCH:-main}"
PANELSAR_HOME="${PANELSAR_HOME:-/var/www/panelsar}"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git ca-certificates curl
# Engine derlemesi için (yoksa install-production uyarı verir)
command -v go >/dev/null 2>&1 || apt-get install -y -qq golang-go || true

PARENT="$(dirname "$PANELSAR_HOME")"
mkdir -p "$PARENT"

if [[ -d "$PANELSAR_HOME/.git" ]]; then
  echo "==> Mevcut repo güncelleniyor: $PANELSAR_HOME"
  cd "$PANELSAR_HOME"
  git remote set-url origin "$PANELSAR_REPO_URL" 2>/dev/null || true
  git fetch origin "$PANELSAR_BRANCH" --depth 1
  git checkout "$PANELSAR_BRANCH"
  git reset --hard "origin/$PANELSAR_BRANCH"
else
  echo "==> Repo klonlanıyor: $PANELSAR_REPO_URL ($PANELSAR_BRANCH)"
  rm -rf "$PANELSAR_HOME"
  git clone --depth 1 --branch "$PANELSAR_BRANCH" "$PANELSAR_REPO_URL" "$PANELSAR_HOME"
fi

cd "$PANELSAR_HOME"
if [[ ! -f deploy/bootstrap/install-production.sh ]]; then
  echo "Hata: deploy/bootstrap/install-production.sh bulunamadı. Repo URL/branch doğru mu?" >&2
  exit 1
fi

echo "==> Yerel kurulum betiği çalıştırılıyor..."
exec bash deploy/bootstrap/install-production.sh
