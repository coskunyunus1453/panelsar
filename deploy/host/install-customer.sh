#!/usr/bin/env bash
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-false}"
export PANELSAR_INSTALL_SCRIPT_URL="${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/panelsar/main/deploy/host/install.sh}"

# Wrapper her zaman ana install.sh'i uzaktan çalıştırır (curl|bash güvenli uyum).
exec bash -c "$(curl -fsSL "$PANELSAR_INSTALL_SCRIPT_URL")"
