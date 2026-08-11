#!/usr/bin/env bash
# Install / update systemd unit untuk gateway LoRa di Raspberry Pi.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="$ROOT/lora-gateway.service.in"
UNIT_NAME="lora-gateway.service"
UNIT_PATH="/etc/systemd/system/${UNIT_NAME}"

SERVICE_USER="${SERVICE_USER:-${SUDO_USER:-$USER}}"
SERVICE_GROUP="${SERVICE_GROUP:-$SERVICE_USER}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Jalankan dengan sudo:" >&2
  echo "  sudo ./install_service.sh" >&2
  exit 1
fi

if [[ ! -f "$TEMPLATE" ]]; then
  echo "Template tidak ditemukan: $TEMPLATE" >&2
  exit 1
fi

if [[ ! -x "$ROOT/.venv/bin/python" ]]; then
  echo "Venv belum siap di $ROOT/.venv" >&2
  echo "Jalankan dulu sebagai user biasa: ./setup_venv.sh" >&2
  exit 1
fi

if [[ ! -f "$ROOT/.env" ]]; then
  echo "File .env belum ada di $ROOT" >&2
  echo "Salin dulu: cp .env.example .env && nano .env" >&2
  exit 1
fi

if ! id "$SERVICE_USER" >/dev/null 2>&1; then
  echo "User tidak ditemukan: $SERVICE_USER" >&2
  exit 1
fi

# Pastikan user bisa akses SPI/GPIO (abaikan jika group belum ada).
for grp in gpio spi; do
  if getent group "$grp" >/dev/null 2>&1; then
    usermod -aG "$grp" "$SERVICE_USER" || true
  fi
done

tmp_unit="$(mktemp)"
sed \
  -e "s|__SERVICE_USER__|${SERVICE_USER}|g" \
  -e "s|__SERVICE_GROUP__|${SERVICE_GROUP}|g" \
  -e "s|__GATEWAY_DIR__|${ROOT}|g" \
  "$TEMPLATE" >"$tmp_unit"

install -m 644 "$tmp_unit" "$UNIT_PATH"
rm -f "$tmp_unit"

systemctl daemon-reload
systemctl enable "$UNIT_NAME"
systemctl restart "$UNIT_NAME"

echo
echo "Service terpasang: $UNIT_PATH"
echo "  User            : $SERVICE_USER"
echo "  WorkingDirectory: $ROOT"
echo
echo "Perintah berguna:"
echo "  sudo systemctl status lora-gateway"
echo "  sudo journalctl -u lora-gateway -f"
echo "  sudo systemctl restart lora-gateway"
echo "  sudo systemctl stop lora-gateway"
echo "  sudo systemctl disable --now lora-gateway"
echo
echo "Catatan:"
echo "  - Path relatif di .env (mis. LORA_BUFFER_DB=./gateway_buffer.db)"
echo "    aman karena WorkingDirectory = folder gateway."
echo "  - Jika DIO0 interrupt gagal, coba jalankan sebagai root:"
echo "      sudo SERVICE_USER=root SERVICE_GROUP=root ./install_service.sh"
echo "  - Setelah usermod group gpio/spi, logout/login sekali agar group aktif"
echo "    (atau reboot) bila service dijalankan sebagai user non-root."
