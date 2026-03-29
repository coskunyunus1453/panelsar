#!/usr/bin/env bash
# Panelsar: engine + Laravel API + Vite (macOS / Homebrew PHP & Go)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PANELSAR_HOME="$ROOT"
export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

echo "Panelsar geliştirme ortamı başlatılıyor…"
echo "Kök: $ROOT"

lsof -ti :9090 | xargs kill -9 2>/dev/null || true
lsof -ti :8000 | xargs kill -9 2>/dev/null || true
lsof -ti :3000 | xargs kill -9 2>/dev/null || true
sleep 1

if [[ ! -f "$ROOT/engine/bin/panelsar-engine" ]]; then
  (cd "$ROOT/engine" && go mod tidy && go build -o bin/panelsar-engine ./cmd/panelsar-engine/)
fi

PANELSAR_CONFIG_DIR="$ROOT/engine/configs" "$ROOT/engine/bin/panelsar-engine" &
echo "Engine → http://127.0.0.1:9090"

(cd "$ROOT/panel" && php artisan serve --host=127.0.0.1 --port=8000) &
echo "Panel API → http://127.0.0.1:8000"

(cd "$ROOT/frontend" && npm run dev -- --host 127.0.0.1 --port 3000) &
echo "Arayüz → http://127.0.0.1:3000"

sleep 3
open "http://127.0.0.1:3000/login" 2>/dev/null || true

echo ""
echo "Giriş: admin@panelsar.com / password"
echo "Durdurmak için: kill \$(lsof -ti :9090) \$(lsof -ti :8000) \$(lsof -ti :3000)"
wait
