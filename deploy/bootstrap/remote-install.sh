#!/usr/bin/env bash
#
# Müşteri sunucusunda çalıştırılır (root). Repoyu sizin barındırdığınız adresten çeker ve üretim kurulumunu başlatır.
#
# Örnek (sizin dağıtım URL’nize göre değiştirin):
#   curl -fsSL https://install.hostvim.com/remote-install.sh | sudo bash
#
# Veya ortam değişkenleriyle:
#   curl -fsSL https://install.hostvim.com/remote-install.sh | sudo -E bash -s
#
#   HOSTVIM_REPO_URL=https://github.com/sirket/hostvim.git \
#   HOSTVIM_BRANCH=main \
#   HOSTVIM_HOME=/var/www/hostvim \
#   bash -s <<< "$(curl -fsSL https://install.hostvim.com/remote-install.sh)"
#
# Eski otomasyon: PANELSAR_REPO_URL / PANELSAR_BRANCH / PANELSAR_HOME hâlâ okunur.
#
set -euo pipefail

[[ "$(id -u)" -eq 0 ]] || { echo "Root gerekli: sudo bash veya curl ... | sudo bash" >&2; exit 1; }

HOSTVIM_REPO_URL="${HOSTVIM_REPO_URL:-${PANELSAR_REPO_URL:-https://github.com/coskunyunus1453/panelsar.git}}"
HOSTVIM_BRANCH="${HOSTVIM_BRANCH:-${PANELSAR_BRANCH:-main}}"
HOSTVIM_HOME="${HOSTVIM_HOME:-${PANELSAR_HOME:-/var/www/hostvim}}"
export PANELSAR_HOME="$HOSTVIM_HOME"
export PANELSAR_REPO_URL="$HOSTVIM_REPO_URL"
export PANELSAR_BRANCH="$HOSTVIM_BRANCH"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git ca-certificates curl
# Engine derlemesi için (yoksa install-production uyarı verir)
command -v go >/dev/null 2>&1 || apt-get install -y -qq golang-go || true

PARENT="$(dirname "$HOSTVIM_HOME")"
mkdir -p "$PARENT"

if [[ -d "$HOSTVIM_HOME/.git" ]]; then
  echo "==> Mevcut repo güncelleniyor: $HOSTVIM_HOME"
  cd "$HOSTVIM_HOME"
  git remote set-url origin "$HOSTVIM_REPO_URL" 2>/dev/null || true
  git fetch origin "$HOSTVIM_BRANCH" --depth 1
  git checkout "$HOSTVIM_BRANCH"
  git reset --hard "origin/$HOSTVIM_BRANCH"
else
  echo "==> Repo klonlanıyor: $HOSTVIM_REPO_URL ($HOSTVIM_BRANCH)"
  rm -rf "$HOSTVIM_HOME"
  git clone --depth 1 --branch "$HOSTVIM_BRANCH" "$HOSTVIM_REPO_URL" "$HOSTVIM_HOME"
fi

cd "$HOSTVIM_HOME"
if [[ ! -f deploy/bootstrap/install-production.sh ]]; then
  echo "Hata: deploy/bootstrap/install-production.sh bulunamadı. Repo URL/branch doğru mu?" >&2
  exit 1
fi

echo "==> Yerel kurulum betiği çalıştırılıyor..."
exec bash deploy/bootstrap/install-production.sh
