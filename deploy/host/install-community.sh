#!/usr/bin/env bash
#
# Hostvim — Community / Freemium kurulum (tek sunucu, barındırma paneli).
#
# Markdown'dan kopyalarken satır başına "* " EKLEMEYİN; kabuk * ile dosya adı genişletmesi komutu bozar.
# Güvenli: cd /tmp && curl -fsSL "…/install-community.sh" | bash
#
# Örnek:
#   curl -fsSL "https://raw.githubusercontent.com/coskunyunus1453/hostvim/main/deploy/host/install-community.sh" | bash
#
# Pro (lisanslı tam özellik): deploy/host/install-pro.sh (+ HOSTVIM_LICENSE_KEY)
#
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-false}"
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL:-${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/hostvim/main/deploy/host/install.sh}}"
export PANELSAR_INSTALL_SCRIPT_URL="$HOSTVIM_INSTALL_SCRIPT_URL"

curl -fsSL "$HOSTVIM_INSTALL_SCRIPT_URL" | bash
