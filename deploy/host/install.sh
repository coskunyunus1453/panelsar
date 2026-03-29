#!/usr/bin/env bash
#
# Panelsar — müşteri sunucusunda çalışır (root).
#
# SİZ (Kodsar): Bu dosyayı HTTPS ile yayınlayın, aşağıdaki varsayılan repo URL’ini kendi Git adresinizle değiştirin.
# Örnek konum: https://kodsar.com/panel/install.sh
#
# Müşteri komutu (aaPanel tarzı — SSL doğrulaması AÇIK, -k yok):
#   URL="https://kodsar.com/panel/install.sh" && if command -v curl >/dev/null 2>&1; then curl -fsSL "$URL" | sudo bash; else wget -qO- "$URL" | sudo bash; fi
#
# Ortam ile (ör. özel branch):
#   sudo PANELSAR_BRANCH=release PANELSAR_REPO_URL=https://github.com/kodsar/panelsar.git bash -s <<< "$(curl -fsSL https://kodsar.com/panel/install.sh)"
#
# Zorunlu proxy/kırık sertifika (ÖNERİLMEZ): yalnızca geçici tanı veya iç ağda:
#   PANELSAR_INSECURE_DOWNLOAD=1 curl -fsSL ...  → betik içinde curl -k kullanılır (sadece bu dosyayı indirirken anlamsız; pipe ile çalışmaz)
#   Müşteri dosyayı önce indirip: curl -k -O ... && sudo bash install.sh
#
set -euo pipefail

# ─── Dağıtımcı: burayı kendi Git reponuzla değiştirin ───
: "${PANELSAR_REPO_URL:=https://github.com/coskunyunus1453/panelsar.git}"
: "${PANELSAR_BRANCH:=main}"
: "${PANELSAR_HOME:=/var/www/panelsar}"

[[ "$(id -u)" -eq 0 ]] || { echo "Root gerekli. Örnek: curl ... | sudo bash" >&2; exit 1; }

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git ca-certificates curl
command -v go >/dev/null 2>&1 || apt-get install -y -qq golang-go || true

PARENT="$(dirname "$PANELSAR_HOME")"
mkdir -p "$PARENT"

if [[ -d "$PANELSAR_HOME/.git" ]]; then
  echo "==> Güncelleniyor: $PANELSAR_HOME"
  cd "$PANELSAR_HOME"
  git remote set-url origin "$PANELSAR_REPO_URL" 2>/dev/null || true
  git fetch origin "$PANELSAR_BRANCH" --depth 1
  git checkout "$PANELSAR_BRANCH"
  git reset --hard "origin/$PANELSAR_BRANCH"
else
  echo "==> Klonlanıyor: $PANELSAR_REPO_URL ($PANELSAR_BRANCH)"
  rm -rf "$PANELSAR_HOME"
  git clone --depth 1 --branch "$PANELSAR_BRANCH" "$PANELSAR_REPO_URL" "$PANELSAR_HOME"
fi

cd "$PANELSAR_HOME"
if [[ ! -f deploy/bootstrap/install-production.sh ]]; then
  echo "Hata: deploy/bootstrap/install-production.sh yok. Repo/branch kontrol edin." >&2
  exit 1
fi

echo "==> Kurulum (install-production.sh) başlıyor..."
exec bash deploy/bootstrap/install-production.sh
