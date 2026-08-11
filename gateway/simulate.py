"""Simple simulation for testing DataManager without LoRa hardware."""

from __future__ import annotations

import logging
import time
from datetime import datetime, timezone

from datamanager import DataManager

        # packet = {
        #     "gateway_id": "GW002",
        #     "node_id": "NODE04",
        #     "temperature": 29.5,
        #     "humidity": 73,
        #     "battery": 3.92,
        #     "rssi": -82,
        #     "snr": 8.5,
        #     "timestamp": datetime.now(timezone.utc)
        #     .isoformat()
        #     .replace("+00:00", "Z"),
        # }

SERVER_URL = "https://lora-dev.apache.web.id/api/v1/telemetry"
DATABASE_PATH = "simulation_buffer.db"
HEADERS = {
    "Authorization": (
        "Bearer 675933b07ccf8740a074bea920fd574c87c743521bab144607f9ea3099964eb8"
    )
}


def main() -> None:
    """Create sample packets and send them through DataManager."""
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )

    data_manager = DataManager(
        database_path=DATABASE_PATH,
        server_url=SERVER_URL,
        retry_interval=2.0,
        headers=HEADERS,
    )
    data_manager.start()

    try:
        packet = {
            "gateway_id": "GW002",
            "node_id": "NODE04",
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
        data_manager.enqueue(packet)

        time.sleep(10)
    except KeyboardInterrupt:
        logging.info("Simulation interrupted")
    finally:
        data_manager.stop()


if __name__ == "__main__":
    main()
