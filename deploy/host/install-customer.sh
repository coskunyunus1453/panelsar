#!/usr/bin/env bash
set -euo pipefail

export APP_PROFILE=customer
export VENDOR_ENABLED=false
export ENFORCE_ADMIN_2FA="${ENFORCE_ADMIN_2FA:-true}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec bash "$SCRIPT_DIR/install.sh"
