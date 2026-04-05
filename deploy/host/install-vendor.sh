#!/usr/bin/env bash
#
# ÖNEMLİ — Markdown / dokümandan kopyalarken satır başına "* " EKLEMEYİN.
# "* curl ... | bash" yazarsanız kabukta * tüm dosya adlarını genişletir; ~/ içinde
# "go", "hostvim-admin-login.txt" vb. varsa komut "go hostvim-admin-login.txt …" gibi
# bozulur (Go: unknown command). Doğru örnek — yalnızca bu satır, başında yıldız yok:
#   cd /tmp && curl -fsSL "https://raw.githubusercontent.com/…/install-vendor.sh" | bash
#
set -euo pipefail

export APP_PROFILE=vendor
export VENDOR_ENABLED=true
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-true}"
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL:-${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/panelsar/main/deploy/host/install.sh}}"
export PANELSAR_INSTALL_SCRIPT_URL="$HOSTVIM_INSTALL_SCRIPT_URL"

# Ana install.sh stdin'den çalıştırılır; böylece betik içindeki " karakterleri
# bash -c "$(curl …)" gibi bir sarmalayıcıda dış tırnakları kırıp komutu bozmaz.
# Yukarıdaki export'lar bu bash sürecine devralınır.
curl -fsSL "$HOSTVIM_INSTALL_SCRIPT_URL" | bash
