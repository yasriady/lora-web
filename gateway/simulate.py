"""Simple simulation for testing DataManager without LoRa hardware.

Reads delivery settings from gateway/.env (same keys as gateway.py / run.sh):
  LORA_GATEWAY_ID, LORA_API_TOKEN, LORA_SERVER_URL,
  LORA_BUFFER_DB, LORA_RETRY_INTERVAL, LORA_REQUEST_TIMEOUT
"""

from __future__ import annotations

import logging
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

_GATEWAY_DIR = Path(__file__).resolve().parent
_ENV_PATH = _GATEWAY_DIR / ".env"

if str(_GATEWAY_DIR) not in sys.path:
    sys.path.insert(0, str(_GATEWAY_DIR))

from datamanager import DataManager

# Sample node used only for simulation payload content.
DEFAULT_SIM_NODE_ID = "NODE04"


def load_dotenv(path: Path = _ENV_PATH) -> None:
    """Load KEY=VALUE pairs from .env into os.environ (does not override existing)."""
    if not path.is_file():
        return

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip("'").strip('"')
        if key and key not in os.environ:
            os.environ[key] = value


def env_str(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def env_float(name: str, default: float) -> float:
    raw = os.environ.get(name)
    if raw is None or not str(raw).strip():
        return default
    return float(raw)


def build_config() -> dict:
    """Collect DataManager settings from environment / .env."""
    load_dotenv()

    gateway_id = env_str("LORA_GATEWAY_ID", "GW001")
    api_token = env_str("LORA_API_TOKEN")
    server_url = env_str(
        "LORA_SERVER_URL",
        "https://lora-dev.apache.web.id/api/v1/telemetry",
    )
    database_path = env_str(
        "LORA_BUFFER_DB",
        str(_GATEWAY_DIR / "simulation_buffer.db"),
    )
    retry_interval = env_float("LORA_RETRY_INTERVAL", 5.0)
    request_timeout = env_float("LORA_REQUEST_TIMEOUT", 10.0)
    node_id = env_str("LORA_SIM_NODE_ID", DEFAULT_SIM_NODE_ID)

    if not api_token:
        raise SystemExit(
            "LORA_API_TOKEN kosong. Isi gateway/.env atau export LORA_API_TOKEN."
        )

    headers = {"Authorization": f"Bearer {api_token}"}

    return {
        "gateway_id": gateway_id,
        "node_id": node_id,
        "server_url": server_url,
        "database_path": database_path,
        "retry_interval": retry_interval,
        "request_timeout": request_timeout,
        "headers": headers,
    }


def sample_packet(gateway_id: str, node_id: str) -> dict:
    """Build one sample telemetry packet matching the Laravel API shape."""
    return {
        "gateway_id": gateway_id,
        "node_id": node_id,
        "timestamp": datetime.now(timezone.utc)
        .isoformat()
        .replace("+00:00", "Z"),
        "battery": 3.92,
        "rssi": -82,
        "snr": 8.5,
        "metrics": {
            "temperature": 33.2,
            "humidity": 65,
            "pressure": 1012.4,
            "soil_moisture": 41.2,
        },
    }


def main() -> None:
    """Create a sample packet and send it through DataManager."""
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )

    cfg = build_config()
    logging.info(
        "Simulate using gateway_id=%s node_id=%s url=%s db=%s",
        cfg["gateway_id"],
        cfg["node_id"],
        cfg["server_url"],
        cfg["database_path"],
    )

    data_manager = DataManager(
        database_path=cfg["database_path"],
        server_url=cfg["server_url"],
        retry_interval=cfg["retry_interval"],
        request_timeout=cfg["request_timeout"],
        headers=cfg["headers"],
    )
    data_manager.start()

    try:
        packet = sample_packet(cfg["gateway_id"], cfg["node_id"])
        data_manager.enqueue(packet)
        # Give the sender thread time to flush before exit.
        wait_s = max(cfg["retry_interval"] * 2, 5.0)
        time.sleep(wait_s)
    except KeyboardInterrupt:
        logging.info("Simulation interrupted")
    finally:
        data_manager.stop()


if __name__ == "__main__":
    main()
