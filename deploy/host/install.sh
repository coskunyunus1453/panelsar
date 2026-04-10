#!/usr/bin/env bash
#
# Hostvim — müşteri sunucusunda çalışır (root).
#
# SİZ (Kodsar): Bu dosyayı HTTPS ile yayınlayın, aşağıdaki varsayılan repo URL’ini kendi Git adresinizle değiştirin.
# Örnek konum: https://kodsar.com/panel/install.sh
#
# Markdown listesinden kopyalarken satır başındaki "* " veya "• " İŞARETİNİ SİLİN;
# aksi halde kabuk * ile mevcut dizindeki dosya adlarını genişletir (ör. go, hostvim-admin-login.txt)
# ve komut "go hostvim-admin-login.txt …" gibi patlar. Güvenli: cd /tmp && curl … | bash
#
# İki giriş noktası (önerilen):
#   • Community / freemium:  deploy/host/install-community.sh
#   • Pro (lisanslı):          deploy/host/install-pro.sh  (+ HOSTVIM_LICENSE_KEY=... isteğe bağlı)
# Bu dosya (install.sh) ortak motor; doğrudan çağrılırsa APP_PROFILE varsayılanı customer’dır.
#
# Müşteri komutu (Linux VPS — SSL doğrulaması AÇIK):
#   • Root SSH: ssh root@SUNUCU_IP → curl -fsSL "…/install-community.sh" | bash
#   • Pro: HOSTVIM_LICENSE_KEY="..." curl -fsSL "…/install-pro.sh" | bash
#   • Eski adlar (yönlendirme): install-customer.sh → community, install-vendor.sh → pro
#   • İlk admin: /root/hostvim-admin-login.txt
#   macOS/Windows’ta çalıştırmayın; boş Debian/Ubuntu sunucuda çalışır.
#
# Ortam ile (ör. özel branch):
#   sudo HOSTVIM_BRANCH=release HOSTVIM_REPO_URL=https://github.com/kodsar/hostvim.git bash -s <<< "$(curl -fsSL https://kodsar.com/panel/install.sh)"
#   (Eski: PANELSAR_BRANCH / PANELSAR_REPO_URL hâlâ okunur.)
#
# Plesk / cPanel benzeri izolasyon (varsayılan):
#   Aynı komutu tekrar çalıştırmak = kod güncelle + migrate; panel DB ve data/www korunur.
# Tam sıfırlama (migrate:fresh + hosting temizliği) ancak bilinçli seçilirse:
#   HOSTVIM_FRESH_INSTALL=1 curl -fsSL "URL" | bash
#   veya RESET_PANEL_DB=1 curl -fsSL "URL" | bash
#
# Diğer varsayılanlar:
#   HOSTVIM_SEED_DEMO_USERS=0 — demo kullanıcı seed etme (eski: PANELSAR_SEED_DEMO_USERS)
#   İlk kurulumda kullanıcı yoksa db:seed admin üretir; HOSTVIM_ADMIN_PASSWORD verilmezse rastgele şifre
#   Üretim önerisi: HOSTVIM_ADMIN_EMAIL=yonetici@alanadin.com ve/veya HOSTVIM_APP_URL=https://panel.alanadin.com
#   (verilmezse sırayla LETS_ENCRYPT_EMAIL, APP_URL ana makinesi, son çare admin@sunucu-FQDN kullanılır)
#   Her çalıştırmada yeni admin şifresi üretilir ( /root/hostvim-admin-login.txt ). Şifreyi elle sabitlemek: HOSTVIM_ADMIN_PASSWORD=...
#   Sadece kod güncellemesi (şifre dokunulmasın): HOSTVIM_PRESERVE_ADMIN_PASSWORD=1
#
# Zorunlu proxy/kırık sertifika (ÖNERİLMEZ): yalnızca geçici tanı veya iç ağda:
#   HOSTVIM_INSECURE_DOWNLOAD=1 curl -fsSL ...  → betik içinde curl -k kullanılır (eski: PANELSAR_INSECURE_DOWNLOAD)
#   Müşteri dosyayı önce indirip: curl -k -O ... && sudo bash install.sh
#
set -euo pipefail

# ─── Dağıtımcı: repo URL + bu betiğin ham (raw) HTTPS adresi aynı depoyu göstermeli (sudo yeniden çalıştırma için) ───
# Varsayılan repo adı hostvim; GitHub’da hâlâ panelsar ise HOSTVIM_REPO_URL ile ezin.
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL:-${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/hostvim/main/deploy/host/install.sh}}"
HOSTVIM_REPO_URL="${HOSTVIM_REPO_URL:-${PANELSAR_REPO_URL:-https://github.com/coskunyunus1453/hostvim.git}}"
HOSTVIM_BRANCH="${HOSTVIM_BRANCH:-${PANELSAR_BRANCH:-main}}"
HOSTVIM_HOME="${HOSTVIM_HOME:-${PANELSAR_HOME:-/var/www/hostvim}}"
HOSTVIM_SEED_DEMO_USERS="${HOSTVIM_SEED_DEMO_USERS:-${PANELSAR_SEED_DEMO_USERS:-0}}"
HOSTVIM_INSECURE_DOWNLOAD="${HOSTVIM_INSECURE_DOWNLOAD:-${PANELSAR_INSECURE_DOWNLOAD:-0}}"
export PANELSAR_HOME="$HOSTVIM_HOME"
export PANELSAR_REPO_URL="$HOSTVIM_REPO_URL"
export PANELSAR_BRANCH="$HOSTVIM_BRANCH"
export PANELSAR_INSTALL_SCRIPT_URL="$HOSTVIM_INSTALL_SCRIPT_URL"
export PANELSAR_SEED_DEMO_USERS="$HOSTVIM_SEED_DEMO_USERS"
export HOSTVIM_SEED_DEMO_USERS="$HOSTVIM_SEED_DEMO_USERS"
# Varsayılan RESET_PANEL_DB=0: yeniden kurulum / güncellemede müşteri verisi silinmez.
: "${RESET_PANEL_DB:=0}"
if [[ "${HOSTVIM_FRESH_INSTALL:-0}" == "1" ]] || [[ "${HOSTVIM_FRESH_INSTALL:-0}" == "yes" ]]; then
  RESET_PANEL_DB=1
fi
export RESET_PANEL_DB
export HOSTVIM_SEED_DEMO_USERS

if [[ "$(uname -s)" != "Linux" ]]; then
  echo "Hostvim kurulumu yalnızca Linux (Debian/Ubuntu) sunucu içindir." >&2
  echo "macOS veya yerel bilgisayarınızda değil; boş VPS'e SSH ile bağlanıp orada çalıştırın." >&2
  echo "Örnek: ssh root@SUNUCU_IP  ardından: curl -fsSL \"$HOSTVIM_INSTALL_SCRIPT_URL\" | bash" >&2
  exit 1
fi

if [[ "$(id -u)" -ne 0 ]]; then
  if command -v sudo >/dev/null 2>&1; then
    TMP="$(mktemp)"
    trap 'rm -f "$TMP"' EXIT
    if command -v curl >/dev/null 2>&1; then
      if [[ "$HOSTVIM_INSECURE_DOWNLOAD" == "1" ]]; then
        curl -fsSLk "$HOSTVIM_INSTALL_SCRIPT_URL" -o "$TMP"
      else
        curl -fsSL "$HOSTVIM_INSTALL_SCRIPT_URL" -o "$TMP"
      fi
    elif command -v wget >/dev/null 2>&1; then
      if [[ "$HOSTVIM_INSECURE_DOWNLOAD" == "1" ]]; then
        wget -qO "$TMP" "$HOSTVIM_INSTALL_SCRIPT_URL" --no-check-certificate
      else
        wget -qO "$TMP" "$HOSTVIM_INSTALL_SCRIPT_URL"
      fi
    else
      echo "Root gerekli veya curl/wget ile betik indirilemiyor. Örnek: curl -fsSL ... | sudo bash" >&2
      exit 1
    fi
    echo "Yönetici yetkisi gerekli; sudo bir kez parola sorabilir (root SSH kullanırsanız sorulmaz)." >&2
    exec sudo -E bash "$TMP"
  fi
  echo "Root veya sudo ile çalıştırın. Örnek: curl -fsSL \"$HOSTVIM_INSTALL_SCRIPT_URL\" | sudo bash" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git ca-certificates curl
# Go: deploy/bootstrap/install-production.sh içinde engine/go.mod ile uyumlu sürüm (go.dev) kurulur; apt golang-go kullanılmaz.

PARENT="$(dirname "$HOSTVIM_HOME")"
mkdir -p "$PARENT"

if [[ -d "$HOSTVIM_HOME/.git" ]]; then
  echo "==> Güncelleniyor: $HOSTVIM_HOME"
  cd "$HOSTVIM_HOME"
  git remote set-url origin "$HOSTVIM_REPO_URL" 2>/dev/null || true
  git fetch origin "$HOSTVIM_BRANCH" --depth 1
  git checkout "$HOSTVIM_BRANCH"
  git reset --hard "origin/$HOSTVIM_BRANCH"
  git clean -fd
else
  echo "==> Klonlanıyor: $HOSTVIM_REPO_URL ($HOSTVIM_BRANCH)"
  rm -rf "$HOSTVIM_HOME"
  git clone --depth 1 --branch "$HOSTVIM_BRANCH" "$HOSTVIM_REPO_URL" "$HOSTVIM_HOME"
fi

cd "$HOSTVIM_HOME"
if [[ ! -f deploy/bootstrap/install-production.sh ]]; then
  echo "Hata: deploy/bootstrap/install-production.sh yok. Repo/branch kontrol edin." >&2
  exit 1
fi

# Kritik helper dosyasını kurulumdan önce senkronla (install-production yine doğrulayacak).
if [[ -f deploy/host/hostvim-security ]]; then
  install -m 755 deploy/host/hostvim-security /usr/local/sbin/hostvim-security
  ln -sfn /usr/local/sbin/hostvim-security /usr/local/sbin/panelsar-security
fi
if [[ -f deploy/host/hostvim-nginx-vhost ]]; then
  install -m 755 deploy/host/hostvim-nginx-vhost /usr/local/sbin/hostvim-nginx-vhost
  ln -sfn /usr/local/sbin/hostvim-nginx-vhost /usr/local/sbin/panelsar-nginx-vhost
fi
if [[ -f deploy/host/hostvim-stack-install ]]; then
  install -m 755 deploy/host/hostvim-stack-install /usr/local/sbin/hostvim-stack-install
  ln -sfn /usr/local/sbin/hostvim-stack-install /usr/local/sbin/panelsar-stack-install
fi
if [[ -f deploy/host/hostvim-terminal-root ]]; then
  install -m 755 deploy/host/hostvim-terminal-root /usr/local/sbin/hostvim-terminal-root
  ln -sfn /usr/local/sbin/hostvim-terminal-root /usr/local/sbin/panelsar-terminal-root
fi
if [[ -f deploy/host/hostvim-php-ini ]]; then
  install -m 755 deploy/host/hostvim-php-ini /usr/local/sbin/hostvim-php-ini
  ln -sfn /usr/local/sbin/hostvim-php-ini /usr/local/sbin/panelsar-php-ini
fi
if [[ -f deploy/host/hostvim-cleaner ]]; then
  install -m 755 deploy/host/hostvim-cleaner /usr/local/sbin/hostvim-cleaner
  ln -sfn /usr/local/sbin/hostvim-cleaner /usr/local/sbin/panelsar-cleaner
fi
if [[ -f deploy/host/hostvim-mail-stack-setup.sh ]]; then
  install -m 755 deploy/host/hostvim-mail-stack-setup.sh /usr/local/sbin/hostvim-mail-stack-setup.sh
  ln -sfn /usr/local/sbin/hostvim-mail-stack-setup.sh /usr/local/sbin/panelsar-mail-stack-setup.sh
fi

# Panel/engine özellik güncellemeleri için bu dosyayı değiştirmeniz gerekmez: aynı komut repo’yu çeker;
# install-production.sh PHP, ön yüz, Go engine derlemesi ve systemd yeniden başlatmayı yapar.
echo "==> Kurulum (install-production.sh) başlıyor..."
exec bash deploy/bootstrap/install-production.sh
