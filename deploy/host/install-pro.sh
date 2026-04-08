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
export HOSTVIM_REPO_URL="${HOSTVIM_REPO_URL:-https://github.com/coskunyunus1453/hostvim.git}"
export HOSTVIM_BRANCH="${HOSTVIM_BRANCH:-main}"
export HOSTVIM_AUTO_SYNC_GIT=1
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/hostvim/main/deploy/host/install.sh}"
HOSTVIM_INSTALL_SCRIPT_URL="${HOSTVIM_INSTALL_SCRIPT_URL}?ts=$(date +%s)"
export PANELSAR_INSTALL_SCRIPT_URL="$HOSTVIM_INSTALL_SCRIPT_URL"
export PANELSAR_REPO_URL="$HOSTVIM_REPO_URL"
export PANELSAR_BRANCH="$HOSTVIM_BRANCH"

curl -fsSL "$HOSTVIM_INSTALL_SCRIPT_URL" | bash
