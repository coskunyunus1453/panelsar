#!/usr/bin/env bash
#
# Panelsar — müşteri sunucusunda çalışır (root).
#
# SİZ (Kodsar): Bu dosyayı HTTPS ile yayınlayın, aşağıdaki varsayılan repo URL’ini kendi Git adresinizle değiştirin.
# Örnek konum: https://kodsar.com/panel/install.sh
#
# Müşteri komutu (Linux VPS — SSL doğrulaması AÇIK):
#   • Root SSH ile (aaPanel gibi ekstra şifre yok): ssh root@SUNUCU_IP → curl -fsSL "URL" | bash
#   • sudo kullanıcı: aynı komut; betik bir kez sudo parolası sorup kendini root ile yeniden çalıştırır.
#   • İlk admin URL/e-posta/şifre: /root/panelsar-admin-login.txt (kurulum özeti çıktısında da yazılır)
#   • İkinci seçenek (klasik): curl -fsSL "URL" | sudo bash
#   macOS/Windows’ta çalıştırmayın; boş Debian/Ubuntu sunucuda çalışır.
#
# Ortam ile (ör. özel branch):
#   sudo PANELSAR_BRANCH=release PANELSAR_REPO_URL=https://github.com/kodsar/panelsar.git bash -s <<< "$(curl -fsSL https://kodsar.com/panel/install.sh)"
#
# Varsayılan davranış (tek komut kurulum):
#   RESET_PANEL_DB=1 ve PANELSAR_SEED_DEMO_USERS=0 ile çalışır.
#   Yani panel veritabanı sıfır kurulur, demo kullanıcı seed edilmez,
#   PANELSAR_ADMIN_PASSWORD verilmediyse admin şifresi rastgele üretilir.
#
# Zorunlu proxy/kırık sertifika (ÖNERİLMEZ): yalnızca geçici tanı veya iç ağda:
#   PANELSAR_INSECURE_DOWNLOAD=1 curl -fsSL ...  → betik içinde curl -k kullanılır (sadece bu dosyayı indirirken anlamsız; pipe ile çalışmaz)
#   Müşteri dosyayı önce indirip: curl -k -O ... && sudo bash install.sh
#
set -euo pipefail

# ─── Dağıtımcı: repo URL + bu betiğin ham (raw) HTTPS adresi aynı depoyu göstermeli (sudo yeniden çalıştırma için) ───
: "${PANELSAR_INSTALL_SCRIPT_URL:=https://raw.githubusercontent.com/coskunyunus1453/panelsar/main/deploy/host/install.sh}"
: "${PANELSAR_REPO_URL:=https://github.com/coskunyunus1453/panelsar.git}"
: "${PANELSAR_BRANCH:=main}"
: "${PANELSAR_HOME:=/var/www/panelsar}"
# Tek komut kurulum varsayılanı:
# - Panel veritabanını sıfırdan kur
# - Demo kullanıcıları seed etme
# - Admin şifresini script rastgele üretsin (PANELSAR_ADMIN_PASSWORD verilmezse)
: "${RESET_PANEL_DB:=1}"
: "${PANELSAR_SEED_DEMO_USERS:=0}"
export RESET_PANEL_DB
export PANELSAR_SEED_DEMO_USERS

if [[ "$(uname -s)" != "Linux" ]]; then
  echo "Panelsar kurulumu yalnızca Linux (Debian/Ubuntu) sunucu içindir." >&2
  echo "macOS veya yerel bilgisayarınızda değil; boş VPS'e SSH ile bağlanıp orada çalıştırın." >&2
  echo "Örnek: ssh root@SUNUCU_IP  ardından: curl -fsSL \"$PANELSAR_INSTALL_SCRIPT_URL\" | bash" >&2
  exit 1
fi

if [[ "$(id -u)" -ne 0 ]]; then
  if command -v sudo >/dev/null 2>&1; then
    TMP="$(mktemp)"
    trap 'rm -f "$TMP"' EXIT
    if command -v curl >/dev/null 2>&1; then
      if [[ "${PANELSAR_INSECURE_DOWNLOAD:-0}" == "1" ]]; then
        curl -fsSLk "$PANELSAR_INSTALL_SCRIPT_URL" -o "$TMP"
      else
        curl -fsSL "$PANELSAR_INSTALL_SCRIPT_URL" -o "$TMP"
      fi
    elif command -v wget >/dev/null 2>&1; then
      if [[ "${PANELSAR_INSECURE_DOWNLOAD:-0}" == "1" ]]; then
        wget -qO "$TMP" "$PANELSAR_INSTALL_SCRIPT_URL" --no-check-certificate
      else
        wget -qO "$TMP" "$PANELSAR_INSTALL_SCRIPT_URL"
      fi
    else
      echo "Root gerekli veya curl/wget ile betik indirilemiyor. Örnek: curl -fsSL ... | sudo bash" >&2
      exit 1
    fi
    echo "Yönetici yetkisi gerekli; sudo bir kez parola sorabilir (root SSH kullanırsanız sorulmaz)." >&2
    exec sudo -E bash "$TMP"
  fi
  echo "Root veya sudo ile çalıştırın. Örnek: curl -fsSL \"$PANELSAR_INSTALL_SCRIPT_URL\" | sudo bash" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git ca-certificates curl
# Go: deploy/bootstrap/install-production.sh içinde engine/go.mod ile uyumlu sürüm (go.dev) kurulur; apt golang-go kullanılmaz.

PARENT="$(dirname "$PANELSAR_HOME")"
mkdir -p "$PARENT"

if [[ -d "$PANELSAR_HOME/.git" ]]; then
  echo "==> Güncelleniyor: $PANELSAR_HOME"
  cd "$PANELSAR_HOME"
  git remote set-url origin "$PANELSAR_REPO_URL" 2>/dev/null || true
  git fetch origin "$PANELSAR_BRANCH" --depth 1
  git checkout "$PANELSAR_BRANCH"
  git reset --hard "origin/$PANELSAR_BRANCH"
  git clean -fd
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

# Panel/engine özellik güncellemeleri için bu dosyayı değiştirmeniz gerekmez: aynı komut repo’yu çeker;
# install-production.sh PHP, ön yüz, Go engine derlemesi ve systemd yeniden başlatmayı yapar.
echo "==> Kurulum (install-production.sh) başlıyor..."
exec bash deploy/bootstrap/install-production.sh
