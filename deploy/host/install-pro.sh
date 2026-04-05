#!/usr/bin/env bash
#
# Hostvim — Pro kurulum (lisanslı: tam özellik seti). Lisans ve panel müşterileri merkezi (ör. hostvim.com) üzerinden yönetilir.
#
# Satın alma sonrası verilen anahtarı kurulumdan önce veya satırda verin:
#   HOSTVIM_LICENSE_KEY="hv_...." curl -fsSL "…/install-pro.sh" | bash
#
# Markdown'dan kopyalarken satır başına "* " eklemeyin. Güvenli: cd /tmp && curl … | bash
#
# Örnek:
#   curl -fsSL "https://raw.githubusercontent.com/coskunyunus1453/hostvim/main/deploy/host/install-pro.sh" | bash
#
# Community (ücretsiz / freemium): deploy/host/install-community.sh
#
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-false}"
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL:-${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/hostvim/main/deploy/host/install.sh}}"
export PANELSAR_INSTALL_SCRIPT_URL="$HOSTVIM_INSTALL_SCRIPT_URL"

curl -fsSL "$HOSTVIM_INSTALL_SCRIPT_URL" | bash
