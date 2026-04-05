#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENGINE_DIR="$ROOT/engine"
RUN_DIR="$ROOT/.run"
LOG_DIR="$ROOT/storage/logs"
PID_FILE="$RUN_DIR/hostvim-engine.pid"
LOCAL_LOG="$LOG_DIR/engine-local.log"
SERVICE_NAME="hostvim-engine"

ACTION="${1:-status}"

ensure_dirs() {
  mkdir -p "$RUN_DIR" "$LOG_DIR"
}

is_linux_systemd() {
  [[ "$(uname -s)" == "Linux" ]] && command -v systemctl >/dev/null 2>&1
}

service_exists() {
  systemctl list-unit-files | rg -n "^${SERVICE_NAME}\\.service\\b" >/dev/null 2>&1
}

local_is_running() {
  [[ -f "$PID_FILE" ]] || return 1
  local pid
  pid="$(<"$PID_FILE")"
  [[ -n "$pid" ]] || return 1
  kill -0 "$pid" >/dev/null 2>&1
}

local_start() {
  ensure_dirs
  if lsof -ti :9090 >/dev/null 2>&1; then
    echo "9090 portu kullanimda. Engine zaten calisiyor olabilir."
    return 0
  fi
  if local_is_running; then
    echo "Engine zaten calisiyor (pid: $(<"$PID_FILE"))."
    return 0
  fi

  (
    cd "$ENGINE_DIR"
    export HOSTVIM_HOME="$ROOT"
    export HOSTVIM_CONFIG_DIR="$ENGINE_DIR/configs"
    export PANELSAR_HOME="$ROOT"
    export PANELSAR_CONFIG_DIR="$ENGINE_DIR/configs"
    nohup go run ./cmd/hostvim-engine >>"$LOCAL_LOG" 2>&1 &
    echo $! >"$PID_FILE"
  )
  sleep 1
  echo "Engine local baslatildi. Log: $LOCAL_LOG"
}

local_stop() {
  if ! local_is_running; then
    rm -f "$PID_FILE"
    echo "Engine local zaten calismiyor."
    return 0
  fi
  local pid
  pid="$(<"$PID_FILE")"
  kill "$pid" >/dev/null 2>&1 || true
  rm -f "$PID_FILE"
  echo "Engine local durduruldu (pid: $pid)."
}

local_status() {
  if local_is_running; then
    echo "Engine local: RUNNING (pid: $(<"$PID_FILE"))."
  elif lsof -ti :9090 >/dev/null 2>&1; then
    echo "Engine local: RUNNING (9090 dinliyor, pid dosyasi yok)."
  else
    echo "Engine local: STOPPED."
  fi
}

local_logs() {
  ensure_dirs
  if [[ ! -f "$LOCAL_LOG" ]]; then
    echo "Log bulunamadi: $LOCAL_LOG"
    return 0
  fi
  tail -n 80 "$LOCAL_LOG"
}

linux_start() {
  if service_exists; then
    sudo systemctl enable --now "$SERVICE_NAME"
    sudo systemctl restart "$SERVICE_NAME"
    sudo systemctl --no-pager --full status "$SERVICE_NAME" | sed -n '1,20p'
  else
    echo "Systemd servisi bulunamadi. Local moda geciliyor..."
    local_start
  fi
}

linux_stop() {
  if service_exists; then
    sudo systemctl stop "$SERVICE_NAME"
    echo "Engine systemd servisi durduruldu."
  else
    local_stop
  fi
}

linux_status() {
  if service_exists; then
    sudo systemctl --no-pager --full status "$SERVICE_NAME" | sed -n '1,20p'
  else
    local_status
  fi
}

linux_logs() {
  if service_exists; then
    sudo journalctl -u "$SERVICE_NAME" -n 80 --no-pager
  else
    local_logs
  fi
}

run_healthcheck() {
  if curl -fsS -m 2 "http://127.0.0.1:9090/health" >/dev/null 2>&1; then
    echo "Healthcheck: OK (127.0.0.1:9090)"
  else
    echo "Healthcheck: FAIL (127.0.0.1:9090)"
    return 1
  fi
}

case "$ACTION" in
  start)
    if is_linux_systemd; then linux_start; else local_start; fi
    run_healthcheck
    ;;
  stop)
    if is_linux_systemd; then linux_stop; else local_stop; fi
    ;;
  restart)
    if is_linux_systemd; then linux_stop; linux_start; else local_stop; local_start; fi
    run_healthcheck
    ;;
  status)
    if is_linux_systemd; then linux_status; else local_status; fi
    ;;
  logs)
    if is_linux_systemd; then linux_logs; else local_logs; fi
    ;;
  health)
    run_healthcheck
    ;;
  *)
    echo "Kullanim: $0 {start|stop|restart|status|logs|health}"
    exit 1
    ;;
esac
