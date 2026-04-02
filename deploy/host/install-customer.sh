#!/usr/bin/env bash
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-false}"
export PANELSAR_INSTALL_SCRIPT_URL="${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/panelsar/main/deploy/host/install.sh}"

# Ana install.sh stdin'den çalıştırılır; böylece betik içindeki " karakterleri
# bash -c "$(curl …)" gibi bir sarmalayıcıda dış tırnakları kırıp komutu bozmaz.
# Yukarıdaki export'lar bu bash sürecine devralınır.
curl -fsSL "$PANELSAR_INSTALL_SCRIPT_URL" | bash
