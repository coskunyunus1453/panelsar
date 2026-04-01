#!/usr/bin/env bash
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-true}"
export PANELSAR_INSTALL_SCRIPT_URL="${PANELSAR_INSTALL_SCRIPT_URL:-https://raw.githubusercontent.com/coskunyunus1453/panelsar/main/deploy/host/install.sh}"

if [[ -n "${BASH_SOURCE[0]:-}" ]] && [[ -f "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/install.sh" ]]; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  exec bash "$SCRIPT_DIR/install.sh"
fi

# curl|bash ile çalıştırıldığında yerel dosya yolu yoktur; ana install.sh'i uzaktan çalıştır.
exec bash -c "$(curl -fsSL "$PANELSAR_INSTALL_SCRIPT_URL")"
