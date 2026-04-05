#!/usr/bin/env bash
#
# ÖNEMLİ — Satır başına "* " (madde işareti) yazmayın; kabuk * ile dizindeki dosya
# adlarını genişletir ve komutu bozar. Doğru: curl -fsSL "…/install-customer.sh" | bash
# İsterseniz: cd /tmp && curl … | bash
#
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-false}"
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL:-${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/panelsar/main/deploy/host/install.sh}}"
export PANELSAR_INSTALL_SCRIPT_URL="$HOSTVIM_INSTALL_SCRIPT_URL"

# Ana install.sh stdin'den çalıştırılır; böylece betik içindeki " karakterleri
# bash -c "$(curl …)" gibi bir sarmalayıcıda dış tırnakları kırıp komutu bozmaz.
# Yukarıdaki export'lar bu bash sürecine devralınır.
curl -fsSL "$HOSTVIM_INSTALL_SCRIPT_URL" | bash
