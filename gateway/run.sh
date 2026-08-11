#!/usr/bin/env bash
# Jalankan gateway.py memakai venv lokal (.venv).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VENV_DIR="${VENV_DIR:-$ROOT/.venv}"

if [[ ! -x "$VENV_DIR/bin/python" ]]; then
  echo "Venv belum ada. Jalankan dulu: ./setup_venv.sh" >&2
  exit 1
fi

if [[ -f "$ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT/.env"
  set +a
fi

exec "$VENV_DIR/bin/python" "$ROOT/gateway.py" "$@"
