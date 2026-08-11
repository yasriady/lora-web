#!/usr/bin/env bash
# Buat Python venv untuk gateway LoRa di Raspberry Pi.
# spidev & RPi.GPIO biasanya dari apt → pakai --system-site-packages.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

VENV_DIR="${VENV_DIR:-$ROOT/.venv}"
PYTHON_BIN="${PYTHON_BIN:-python3}"

echo "==> Memeriksa paket sistem (disarankan via apt)..."
missing=()
dpkg -s python3-venv >/dev/null 2>&1 || missing+=(python3-venv)
dpkg -s python3-spidev >/dev/null 2>&1 || missing+=(python3-spidev)
dpkg -s python3-rpi.gpio >/dev/null 2>&1 || missing+=(python3-rpi.gpio)

if ((${#missing[@]})); then
  echo "Paket belum terpasang: ${missing[*]}"
  echo "Jalankan:"
  echo "  sudo apt update"
  echo "  sudo apt install -y ${missing[*]}"
  exit 1
fi

if [[ ! -d "$VENV_DIR" ]]; then
  echo "==> Membuat venv di $VENV_DIR (system-site-packages)..."
  "$PYTHON_BIN" -m venv --system-site-packages "$VENV_DIR"
else
  echo "==> Venv sudah ada: $VENV_DIR"
fi

# shellcheck disable=SC1091
source "$VENV_DIR/bin/activate"

echo "==> Upgrade pip..."
python -m pip install --upgrade pip

echo "==> Install dependencies dari requirements.txt..."
python -m pip install -r "$ROOT/requirements.txt"

echo
echo "Selesai."
echo "Aktifkan venv:"
echo "  source $VENV_DIR/bin/activate"
echo "Atau jalankan service:"
echo "  ./run.sh"
echo
echo "Jangan lupa set token gateway:"
echo "  export LORA_GATEWAY_ID=GW001"
echo "  export LORA_API_TOKEN=your_token"
