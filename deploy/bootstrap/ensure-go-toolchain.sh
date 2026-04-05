#!/usr/bin/env bash
# Hostvim: engine/go.mod ile uyumlu Go toolchain (resmi go.dev paketi).
# Kaynak: https://go.dev/dl/ — müşteri sunucusunda apt'teki eski golang-go yerine kullanılır.
#
# Ortam (isteğe bağlı):
#   HOSTVIM_GO_VERSION=1.22.3   # engine/go.mod ile uyumlu (eski: PANELSAR_GO_VERSION)

ensure_go_toolchain() {
  local want="${HOSTVIM_GO_VERSION:-${PANELSAR_GO_VERSION:-1.22.3}}"
  local arch tarball url tmp
  case "$(uname -m)" in
    x86_64) arch=amd64 ;;
    aarch64) arch=arm64 ;;
    *)
      echo "Hata: Desteklenmeyen CPU mimarisi: $(uname -m) (amd64/arm64 gerekir)." >&2
      return 1
      ;;
  esac

  local have=""
  if command -v go >/dev/null 2>&1; then
    have="$(go version 2>/dev/null | awk '{print $3}' | sed 's/^go//')"
    if [[ -n "$have" ]] && [[ "$(printf '%s\n' "$want" "$have" | sort -V | head -n1)" == "$want" ]]; then
      export PATH="/usr/local/go/bin:${PATH}"
      return 0
    fi
  fi

  if ! command -v curl >/dev/null 2>&1 && ! command -v wget >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq curl ca-certificates
  fi

  tarball="go${want}.linux-${arch}.tar.gz"
  url="https://go.dev/dl/${tarball}"
  tmp="$(mktemp)"

  echo "==> Go ${want} kuruluyor (engine derlemesi için; mevcut: ${have:-yok})..."
  if command -v curl >/dev/null 2>&1; then
    if [[ "${HOSTVIM_INSECURE_DOWNLOAD:-${PANELSAR_INSECURE_DOWNLOAD:-0}}" == "1" ]]; then
      curl -fsSLk "$url" -o "$tmp"
    else
      curl -fsSL "$url" -o "$tmp"
    fi
  else
    if [[ "${HOSTVIM_INSECURE_DOWNLOAD:-${PANELSAR_INSECURE_DOWNLOAD:-0}}" == "1" ]]; then
      wget -qO "$tmp" "$url" --no-check-certificate
    else
      wget -qO "$tmp" "$url"
    fi
  fi

  rm -rf /usr/local/go
  tar -C /usr/local -xzf "$tmp"
  rm -f "$tmp"
  ln -sf /usr/local/go/bin/go /usr/bin/go
  ln -sf /usr/local/go/bin/gofmt /usr/bin/gofmt
  export PATH="/usr/local/go/bin:${PATH}"

  have="$(go version | awk '{print $3}' | sed 's/^go//')"
  if [[ -z "$have" ]] || [[ "$(printf '%s\n' "$want" "$have" | sort -V | head -n1)" != "$want" ]]; then
    echo "Hata: Go kuruldu ama sürüm beklentiyle uyuşmuyor (istenen: ${want}, olan: ${have:-?})." >&2
    return 1
  fi
  echo "==> Go hazır: $(go version)"
  return 0
}
