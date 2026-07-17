#!/usr/bin/env bash
set -euo pipefail

REPO_URL="${MKAUTH_SICREDI_REPO_URL:-https://github.com/brsxdlols/mkauth-sicredi.git}"
BRANCH="${MKAUTH_SICREDI_BRANCH:-main}"
WORKDIR="$(mktemp -d /tmp/mkauth-sicredi.XXXXXX)"

cleanup() {
  rm -rf "$WORKDIR"
}
trap cleanup EXIT

echo "== Baixando instalador MK Auth Sicredi =="

if command -v git >/dev/null 2>&1; then
  git clone --depth=1 --branch "$BRANCH" "$REPO_URL" "$WORKDIR" >/dev/null 2>&1
else
  if ! command -v curl >/dev/null 2>&1; then
    echo "curl nao encontrado no servidor." >&2
    exit 1
  fi
  if ! command -v tar >/dev/null 2>&1; then
    echo "tar nao encontrado no servidor." >&2
    exit 1
  fi
  curl -fsSL "https://github.com/brsxdlols/mkauth-sicredi/archive/refs/heads/${BRANCH}.tar.gz" | tar -xz -C "$WORKDIR" --strip-components=1
fi

bash "$WORKDIR/install.sh"
