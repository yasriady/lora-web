#!/usr/bin/env bash
# Hapus systemd unit lora-gateway.
set -euo pipefail

UNIT_NAME="lora-gateway.service"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Jalankan dengan sudo: sudo ./uninstall_service.sh" >&2
  exit 1
fi

systemctl disable --now "$UNIT_NAME" 2>/dev/null || true
rm -f "/etc/systemd/system/${UNIT_NAME}"
systemctl daemon-reload
echo "Service $UNIT_NAME dihapus."
