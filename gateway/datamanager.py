"""Persistent FIFO delivery queue for LoRa gateway payloads."""

from __future__ import annotations

import json
import logging
import sqlite3
import threading
from collections.abc import Mapping
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import requests


logger = logging.getLogger(__name__)


class DataManager:
    """Persist gateway packets and deliver them to an HTTP endpoint in FIFO order.

    Args:
        database_path: Path to the SQLite database used as the persistent queue.
        server_url: HTTP endpoint that accepts packet JSON via POST.
        retry_interval: Seconds to wait before checking the queue after each
            delivery attempt or while the queue is empty.
        request_timeout: HTTP request timeout in seconds.
        headers: Optional HTTP headers sent with every delivery request.
    """

    def __init__(
        self,
        database_path: str | Path,
        server_url: str,
        retry_interval: float = 5.0,
        request_timeout: float = 10.0,
        headers: Mapping[str, str] | None = None,
    ) -> None:
        """Initialize the persistent queue without starting the sender thread.

        Args:
            database_path: Path to the SQLite database file.
            server_url: HTTP endpoint for packet delivery.
            retry_interval: Delay between sender-loop iterations in seconds.
            request_timeout: HTTP POST timeout in seconds.
            headers: Optional HTTP headers sent with every delivery request.

        Raises:
            ValueError: If an interval or timeout is not positive.
            sqlite3.Error: If the SQLite database cannot be initialized.
        """
        if retry_interval <= 0:
            raise ValueError("retry_interval must be greater than zero")
        if request_timeout <= 0:
            raise ValueError("request_timeout must be greater than zero")

        self._database_path = Path(database_path)
        self._server_url = server_url
        self._retry_interval = retry_interval
        self._request_timeout = request_timeout
        self._headers = dict(headers) if headers is not None else {}
        self._lock = threading.Lock()
        self._running = threading.Event()
        self._wake_sender = threading.Event()
        self._thread: threading.Thread | None = None

        try:
            self._database_path.parent.mkdir(parents=True, exist_ok=True)
            self._connection = sqlite3.connect(
                self._database_path,
                check_same_thread=False,
            )
            self._connection.execute(
                """
                CREATE TABLE IF NOT EXISTS buffer (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at TEXT NOT NULL,
                    payload TEXT NOT NULL
                )
                """
            )
            self._connection.commit()
        except sqlite3.Error:
            logger.exception("Failed to initialize SQLite buffer")
            raise

    def start(self) -> None:
        """Start the background sender thread if it is not already running."""
        if self._running.is_set():
            return

        self._running.set()
        self._wake_sender.clear()
        self._thread = threading.Thread(
            target=self._sender_loop,
            name="data-manager-sender",
            daemon=False,
        )
        self._thread.start()
        logger.info("Sender started")

    def stop(self) -> None:
        """Request sender shutdown and wait until its thread has exited."""
        if not self._running.is_set():
            return

        self._running.clear()
        self._wake_sender.set()

        thread = self._thread
        if thread is not None and thread is not threading.current_thread():
            thread.join()

        logger.info("Sender stopped")

    def enqueue(self, packet: dict[str, Any]) -> None:
        """Serialize and persist a packet for later FIFO delivery.

        Args:
            packet: JSON-serializable gateway packet.

        Raises:
            TypeError: If packet is not a dictionary or is not JSON serializable.
            sqlite3.Error: If the packet cannot be saved to SQLite.
        """
        if not isinstance(packet, dict):
            raise TypeError("packet must be a dictionary")

        try:
            payload = json.dumps(packet, separators=(",", ":"), ensure_ascii=False)
        except (TypeError, ValueError):
            logger.exception("Failed to serialize packet for buffering")
            raise

        self._insert(payload)
        logger.info("Data buffered")
        self._wake_sender.set()

    def _insert(self, payload: str) -> None:
        """Insert a serialized payload into the persistent queue.

        Args:
            payload: JSON string to persist.

        Raises:
            sqlite3.Error: If the insert operation fails.
        """
        created_at = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")
        try:
            with self._lock:
                self._connection.execute(
                    "INSERT INTO buffer (created_at, payload) VALUES (?, ?)",
                    (created_at, payload),
                )
                self._connection.commit()
        except sqlite3.Error:
            logger.exception("Failed to buffer data")
            raise

    def _get_next(self) -> tuple[int, str] | None:
        """Return the oldest queued record, or None if the queue is empty.

        Returns:
            A tuple containing the record ID and serialized payload, or None.
        """
        try:
            with self._lock:
                cursor = self._connection.execute(
                    "SELECT id, payload FROM buffer ORDER BY id ASC LIMIT 1"
                )
                row = cursor.fetchone()
        except sqlite3.Error:
            logger.exception("Failed to read buffered data")
            return None

        if row is None:
            return None
        return int(row[0]), str(row[1])

    def _delete(self, record_id: int) -> bool:
        """Delete a successfully delivered record.

        Args:
            record_id: SQLite ID of the record to remove.

        Returns:
            True when the record was deleted; otherwise False.
        """
        try:
            with self._lock:
                cursor = self._connection.execute(
                    "DELETE FROM buffer WHERE id = ?",
                    (record_id,),
                )
                self._connection.commit()
            return cursor.rowcount == 1
        except sqlite3.Error:
            logger.exception("Failed to delete delivered record %s", record_id)
            return False

    def _send(self, record_id: int, payload: str) -> bool:
        """Send one serialized payload to the configured HTTP endpoint.

        Args:
            record_id: SQLite ID used only for logging.
            payload: JSON string stored in the persistent queue.

        Returns:
            True only when the server returns HTTP 200.
        """
        try:
            data = json.loads(payload)
            logger.info("Sending record %s", record_id)
            response = requests.post(
                self._server_url,
                json=data,
                headers=self._headers,
                timeout=self._request_timeout,
            )
        except (json.JSONDecodeError, requests.RequestException):
            logger.warning("Delivery failed for record %s", record_id, exc_info=True)
            return False

        if response.status_code != 200:
            logger.warning(
                "Delivery failed for record %s: HTTP %s",
                record_id,
                response.status_code,
            )
            return False
        return True

    def _sender_loop(self) -> None:
        """Continuously deliver the oldest queued record until stopped."""
        while self._running.is_set():
            record = self._get_next()
            if record is None:
                self._wake_sender.wait(self._retry_interval)
                self._wake_sender.clear()
                continue

            record_id, payload = record
            if self._send(record_id, payload) and self._delete(record_id):
                logger.info("Record delivered: %s", record_id)
            else:
                logger.info("Retry later: record %s", record_id)

            self._wake_sender.wait(self._retry_interval)
            self._wake_sender.clear()
