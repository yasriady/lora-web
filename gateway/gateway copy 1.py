#!/usr/bin/env python3
"""
gateway.py
==============================================================================
SX1276 Single Channel LoRa Gateway - Raspberry Pi 2B / RFM95W
==============================================================================

A monolithic, from-datasheet implementation of a single-channel LoRa
receive gateway built directly against the Semtech SX1276/77/78/79
datasheet (Rev. 7). No third-party LoRa library is used anywhere in this
file -- all register framing, SPI transactions, and LoRa modem
configuration are implemented manually.

Target platform:
    - Raspberry Pi 2 Model B
    - Raspberry Pi OS Bookworm
    - Python 3.13

Allowed dependencies:
    - spidev        (raw SPI transactions)
    - RPi.GPIO      (RESET output, DIO0 interrupt input)
    - Python standard library (logging, json, time, dataclasses, ...)

Explicitly NOT used: adafruit_rfm9x, CircuitPython/Blinka, busio,
digitalio, RadioHead, pySX127x, pyLoRa, LoRaRF, or any "LoRa" package.

------------------------------------------------------------------------------
WIRING (Raspberry Pi -> RFM95W), SPI0 / CE0
------------------------------------------------------------------------------
    3.3V    -> VCC
    GND     -> GND
    GPIO10  -> MOSI
    GPIO9   <- MISO
    GPIO11  -> SCK
    GPIO8   -> NSS  (CS, SPI0 CE0)
    GPIO25  -> RESET
    GPIO4   <- DIO0  (RxDone interrupt)

------------------------------------------------------------------------------
DEFAULT RADIO CONFIGURATION
------------------------------------------------------------------------------
    Frequency        : 923,000,000 Hz
    Bandwidth        : 125,000 Hz
    Spreading Factor  : SF7
    Coding Rate       : 4/5
    CRC               : ON
    Sync Word         : 0x12
    Preamble          : 8 symbols
    Header            : Explicit
    IQ                : Normal
    LNA               : AGC enabled

------------------------------------------------------------------------------
USAGE
------------------------------------------------------------------------------
    python3 gateway.py

Runs the single-channel gateway in RX CONTINUOUS mode, using DIO0 as a
hardware interrupt (GPIO edge detection) rather than register polling.
Received packets are expected to carry a UTF-8 JSON payload and are
pretty-printed to the console along with RSSI/SNR/timestamp metadata.

==============================================================================
"""

from __future__ import annotations

import argparse
import json
import logging
import signal
import sys
import time
from dataclasses import dataclass
from datetime import datetime
from types import FrameType
from typing import Optional

try:
    import spidev  # type: ignore
except ImportError:
    spidev = None

try:
    import RPi.GPIO as GPIO  # type: ignore
except ImportError:
    GPIO = None


# ==============================================================================
# SX1276 REGISTER MAP (Semtech SX1276/77/78/79 Datasheet Rev.7)
# ==============================================================================

REG_FIFO: int = 0x00
REG_OP_MODE: int = 0x01
REG_FRF_MSB: int = 0x06
REG_FRF_MID: int = 0x07
REG_FRF_LSB: int = 0x08
REG_PA_CONFIG: int = 0x09
REG_PA_RAMP: int = 0x0A
REG_OCP: int = 0x0B
REG_LNA: int = 0x0C
REG_FIFO_ADDR_PTR: int = 0x0D
REG_FIFO_TX_BASE_ADDR: int = 0x0E
REG_FIFO_RX_BASE_ADDR: int = 0x0F
REG_FIFO_RX_CURRENT_ADDR: int = 0x10
REG_IRQ_FLAGS_MASK: int = 0x11
REG_IRQ_FLAGS: int = 0x12
REG_RX_NB_BYTES: int = 0x13
REG_RX_HEADER_CNT_VALUE_MSB: int = 0x14
REG_RX_HEADER_CNT_VALUE_LSB: int = 0x15
REG_RX_PACKET_CNT_VALUE_MSB: int = 0x16
REG_RX_PACKET_CNT_VALUE_LSB: int = 0x17
REG_MODEM_STAT: int = 0x18
REG_PKT_SNR_VALUE: int = 0x19
REG_PKT_RSSI_VALUE: int = 0x1A
REG_RSSI_VALUE: int = 0x1B
REG_HOP_CHANNEL: int = 0x1C
REG_MODEM_CONFIG1: int = 0x1D
REG_MODEM_CONFIG2: int = 0x1E
REG_SYMB_TIMEOUT_LSB: int = 0x1F
REG_PREAMBLE_MSB: int = 0x20
REG_PREAMBLE_LSB: int = 0x21
REG_PAYLOAD_LENGTH: int = 0x22
REG_MAX_PAYLOAD_LENGTH: int = 0x23
REG_HOP_PERIOD: int = 0x24
REG_FIFO_RX_BYTE_ADDR: int = 0x25
REG_MODEM_CONFIG3: int = 0x26
REG_PPM_CORRECTION: int = 0x27
REG_FEI_MSB: int = 0x28
REG_FEI_MID: int = 0x29
REG_FEI_LSB: int = 0x2A
REG_RSSI_WIDEBAND: int = 0x2C
REG_DETECT_OPTIMIZE: int = 0x31
REG_INVERT_IQ: int = 0x33
REG_DETECTION_THRESHOLD: int = 0x37
REG_SYNC_WORD: int = 0x39
REG_INVERT_IQ2: int = 0x3B
REG_DIO_MAPPING1: int = 0x40
REG_DIO_MAPPING2: int = 0x41
REG_VERSION: int = 0x42
REG_TCXO: int = 0x4B
REG_PA_DAC: int = 0x4D

REGISTER_NAMES: dict[int, str] = {
    REG_FIFO: "Fifo",
    REG_OP_MODE: "OpMode",
    REG_FRF_MSB: "FrfMsb",
    REG_FRF_MID: "FrfMid",
    REG_FRF_LSB: "FrfLsb",
    REG_PA_CONFIG: "PaConfig",
    REG_PA_RAMP: "PaRamp",
    REG_OCP: "Ocp",
    REG_LNA: "Lna",
    REG_FIFO_ADDR_PTR: "FifoAddrPtr",
    REG_FIFO_TX_BASE_ADDR: "FifoTxBaseAddr",
    REG_FIFO_RX_BASE_ADDR: "FifoRxBaseAddr",
    REG_FIFO_RX_CURRENT_ADDR: "FifoRxCurrentAddr",
    REG_IRQ_FLAGS_MASK: "IrqFlagsMask",
    REG_IRQ_FLAGS: "IrqFlags",
    REG_RX_NB_BYTES: "RxNbBytes",
    REG_RX_HEADER_CNT_VALUE_MSB: "RxHeaderCntMsb",
    REG_RX_HEADER_CNT_VALUE_LSB: "RxHeaderCntLsb",
    REG_RX_PACKET_CNT_VALUE_MSB: "RxPacketCntMsb",
    REG_RX_PACKET_CNT_VALUE_LSB: "RxPacketCntLsb",
    REG_MODEM_STAT: "ModemStat",
    REG_PKT_SNR_VALUE: "PktSnrValue",
    REG_PKT_RSSI_VALUE: "PktRssiValue",
    REG_RSSI_VALUE: "RssiValue",
    REG_HOP_CHANNEL: "HopChannel",
    REG_MODEM_CONFIG1: "ModemConfig1",
    REG_MODEM_CONFIG2: "ModemConfig2",
    REG_SYMB_TIMEOUT_LSB: "SymbTimeoutLsb",
    REG_PREAMBLE_MSB: "PreambleMsb",
    REG_PREAMBLE_LSB: "PreambleLsb",
    REG_PAYLOAD_LENGTH: "PayloadLength",
    REG_MAX_PAYLOAD_LENGTH: "MaxPayloadLength",
    REG_HOP_PERIOD: "HopPeriod",
    REG_FIFO_RX_BYTE_ADDR: "FifoRxByteAddr",
    REG_MODEM_CONFIG3: "ModemConfig3",
    REG_PPM_CORRECTION: "PpmCorrection",
    REG_FEI_MSB: "FeiMsb",
    REG_FEI_MID: "FeiMid",
    REG_FEI_LSB: "FeiLsb",
    REG_RSSI_WIDEBAND: "RssiWideband",
    REG_DETECT_OPTIMIZE: "DetectOptimize",
    REG_INVERT_IQ: "InvertIQ",
    REG_DETECTION_THRESHOLD: "DetectionThreshold",
    REG_SYNC_WORD: "SyncWord",
    REG_INVERT_IQ2: "InvertIQ2",
    REG_DIO_MAPPING1: "DioMapping1",
    REG_DIO_MAPPING2: "DioMapping2",
    REG_VERSION: "Version",
    REG_TCXO: "Tcxo",
    REG_PA_DAC: "PaDac",
}

# ------------------------------------------------------------------------------
# OpMode (REG_OP_MODE) bit fields
# ------------------------------------------------------------------------------
OP_MODE_LONG_RANGE_MODE: int = 0x80  # bit7 = 1 -> LoRa mode

MODE_SLEEP: int = 0x00
MODE_STDBY: int = 0x01
MODE_FSTX: int = 0x02
MODE_TX: int = 0x03
MODE_FSRX: int = 0x04
MODE_RXCONTINUOUS: int = 0x05
MODE_RXSINGLE: int = 0x06
MODE_CAD: int = 0x07

# ------------------------------------------------------------------------------
# IRQ_FLAGS (REG_IRQ_FLAGS) bit masks, LoRa mode
# ------------------------------------------------------------------------------
IRQ_RX_TIMEOUT: int = 0x80
IRQ_RX_DONE: int = 0x40
IRQ_PAYLOAD_CRC_ERROR: int = 0x20
IRQ_VALID_HEADER: int = 0x10
IRQ_TX_DONE: int = 0x08
IRQ_CAD_DONE: int = 0x04
IRQ_FHSS_CHANGE_CHANNEL: int = 0x02
IRQ_CAD_DETECTED: int = 0x01
IRQ_ALL_MASK: int = 0xFF

# ------------------------------------------------------------------------------
# LoRa bandwidth table -> REG_MODEM_CONFIG1[7:4]
# ------------------------------------------------------------------------------
BANDWIDTH_TABLE: dict[int, int] = {
    7_800: 0x0,
    10_400: 0x1,
    15_600: 0x2,
    20_800: 0x3,
    31_250: 0x4,
    41_700: 0x5,
    62_500: 0x6,
    125_000: 0x7,
    250_000: 0x8,
    500_000: 0x9,
}

# Coding rate denominator (4/5..4/8) -> REG_MODEM_CONFIG1[3:1]
CODING_RATE_TABLE: dict[int, int] = {
    5: 0x1,
    6: 0x2,
    7: 0x3,
    8: 0x4,
}

SX1276_EXPECTED_VERSION: int = 0x12
FXOSC_HZ: float = 32_000_000.0
FSTEP_HZ: float = FXOSC_HZ / 524_288.0  # 2^19, per datasheet section 4.1

# ------------------------------------------------------------------------------
# Default hardware / SPI configuration
# ------------------------------------------------------------------------------
DEFAULT_SPI_BUS: int = 0
DEFAULT_SPI_DEVICE: int = 0
DEFAULT_SPI_SPEED_HZ: int = 1_000_000
DEFAULT_SPI_MODE: int = 0

DEFAULT_RESET_PIN: int = 25     # RESET -> GPIO22 (BCM), wiringPi pin_rst=3
DEFAULT_DIO0_PIN: int = 22      # DIO0  -> GPIO25 (BCM), wiringPi pin_dio0=6
DEFAULT_NSS_PIN: int = 8        # NSS   -> GPIO2  (BCM), wiringPi pin_nss=8
                                 # NOTE: NSS is NOT wired to hardware CE0/CE1,
                                 # so it must be toggled manually via GPIO
                                 # around every SPI transaction (see
                                 # SX1276._cs_select()/_cs_deselect() below).
                                 # Hardware SPI is still used for MOSI/MISO/SCK.

RESET_LOW_DURATION_S: float = 0.0001
RESET_RECOVERY_DELAY_S: float = 0.005

SPI_WRITE_BIT: int = 0x80

# ------------------------------------------------------------------------------
# Default LoRa radio/gateway configuration (per project specification)
# ------------------------------------------------------------------------------
DEFAULT_FREQUENCY_HZ: int = 923_000_000  # matched to Arduino TX: LoRa.begin(923.2E6)
DEFAULT_BANDWIDTH_HZ: int = 125_000
DEFAULT_SPREADING_FACTOR: int = 7
DEFAULT_CODING_RATE_DENOM: int = 5  # 4/5
DEFAULT_CRC_ENABLED: bool = True
DEFAULT_SYNC_WORD: int = 0x12
DEFAULT_PREAMBLE_LENGTH: int = 8
DEFAULT_TX_POWER_DBM: int = 17

FIFO_RX_BASE_ADDR: int = 0x00
FIFO_TX_BASE_ADDR: int = 0x80
MAX_PAYLOAD_LENGTH: int = 255


# ==============================================================================
# LOGGING
# ==============================================================================

logger: logging.Logger = logging.getLogger("gateway")


def configure_logging(level: int = logging.INFO) -> None:
    """Configure module-level logging with a simple timestamped format."""
    handler = logging.StreamHandler(stream=sys.stdout)
    formatter = logging.Formatter(
        fmt="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )
    handler.setFormatter(formatter)
    logger.setLevel(level)
    logger.addHandler(handler)
    logger.propagate = False


# ==============================================================================
# DATA STRUCTURES
# ==============================================================================

@dataclass
class RadioConfig:
    """Holds the LoRa modem configuration for the gateway session."""
    frequency_hz: int = DEFAULT_FREQUENCY_HZ
    bandwidth_hz: int = DEFAULT_BANDWIDTH_HZ
    spreading_factor: int = DEFAULT_SPREADING_FACTOR
    coding_rate_denom: int = DEFAULT_CODING_RATE_DENOM
    crc_enabled: bool = DEFAULT_CRC_ENABLED
    sync_word: int = DEFAULT_SYNC_WORD
    preamble_length: int = DEFAULT_PREAMBLE_LENGTH
    tx_power_dbm: int = DEFAULT_TX_POWER_DBM


@dataclass
class LoRaPacket:
    """Represents a single received LoRa packet with radio metadata."""
    timestamp: datetime
    payload: bytes
    rssi_dbm: float
    snr_db: float
    length: int


# ==============================================================================
# EXCEPTIONS
# ==============================================================================

class RadioInitError(RuntimeError):
    """Raised when the radio fails to initialize (SPI, reset, or chip ID)."""


# ==============================================================================
# class SX1276 - LOW-LEVEL DATASHEET-ACCURATE DRIVER
# ==============================================================================

class SX1276:
    """
    Low-level SX1276 LoRa modem driver.

    Implements manual SPI register framing exactly as specified in the
    Semtech SX1276 datasheet (Rev.7). No abstraction library or
    third-party LoRa driver is used -- every register write here maps
    directly to a documented datasheet field.
    """

    def __init__(
        self,
        spi_bus: int = DEFAULT_SPI_BUS,
        spi_device: int = DEFAULT_SPI_DEVICE,
        spi_speed_hz: int = DEFAULT_SPI_SPEED_HZ,
        reset_pin: int = DEFAULT_RESET_PIN,
        dio0_pin: int = DEFAULT_DIO0_PIN,
        nss_pin: int = DEFAULT_NSS_PIN,
    ) -> None:
        self.spi_bus: int = spi_bus
        self.spi_device: int = spi_device
        self.spi_speed_hz: int = spi_speed_hz
        self.reset_pin: int = reset_pin
        self.dio0_pin: int = dio0_pin
        self.nss_pin: int = nss_pin

        self.spi: Optional["spidev.SpiDev"] = None
        self._gpio_ready: bool = False

    # --------------------------------------------------------------------
    # SPI LIFECYCLE
    # --------------------------------------------------------------------

    def open(self) -> None:
        """Open and configure the SPI bus, and initialize GPIO lines."""
        if spidev is None:
            raise RadioInitError(
                "spidev module not available. Install with: "
                "sudo apt-get install python3-spidev"
            )
        if GPIO is None:
            raise RadioInitError(
                "RPi.GPIO module not available. Install with: "
                "sudo apt-get install python3-rpi.gpio"
            )
        try:
            GPIO.setwarnings(False)
            GPIO.setmode(GPIO.BCM)
            GPIO.setup(self.reset_pin, GPIO.OUT, initial=GPIO.HIGH)
            GPIO.setup(self.dio0_pin, GPIO.IN, pull_up_down=GPIO.PUD_DOWN)
            # NSS is a plain GPIO here (not hardware CE0/CE1) and is toggled
            # manually around every SPI transaction. Idle state is HIGH
            # (deselected) per SX1276 datasheet chip-select polarity.
            GPIO.setup(self.nss_pin, GPIO.OUT, initial=GPIO.HIGH)
            self._gpio_ready = True
        except Exception as exc:  # noqa: BLE001
            raise RadioInitError(f"GPIO setup failed: {exc}") from exc

        try:
            self.spi = spidev.SpiDev()
            self.spi.open(self.spi_bus, self.spi_device)
            self.spi.max_speed_hz = self.spi_speed_hz
            self.spi.mode = DEFAULT_SPI_MODE
            self.spi.bits_per_word = 8
            # Hardware CE0/CE1 is not wired to NSS on this board -- disable
            # the kernel's automatic chip-select toggling so it doesn't
            # glitch an unrelated/unconnected pin during each transfer.
            # Not all spidev builds expose this attribute, so this is
            # best-effort and non-fatal if unsupported.
            try:
                self.spi.no_cs = True
            except (AttributeError, OSError, IOError):
                logger.warning(
                    "spidev does not support no_cs on this system; hardware "
                    "CE0/CE1 will still toggle but is unused/unconnected."
                )
        except Exception as exc:  # noqa: BLE001
            raise RadioInitError(f"SPI open failed: {exc}") from exc

    def close(self) -> None:
        """Close the SPI bus and release GPIO resources."""
        if self.spi is not None:
            try:
                self.spi.close()
            finally:
                self.spi = None
        if GPIO is not None and self._gpio_ready:
            GPIO.cleanup()
            self._gpio_ready = False

    # --------------------------------------------------------------------
    # LOW-LEVEL SPI REGISTER ACCESS
    # --------------------------------------------------------------------

    def _cs_select(self) -> None:
        """Manually drive NSS low to select the chip before a transfer."""
        GPIO.output(self.nss_pin, GPIO.LOW)

    def _cs_deselect(self) -> None:
        """Manually drive NSS high to deselect the chip after a transfer."""
        GPIO.output(self.nss_pin, GPIO.HIGH)

    def read_reg(self, address: int) -> int:
        """Read a single register: [addr & 0x7F, 0x00] -> value in byte 1."""
        if self.spi is None:
            raise RadioInitError("SPI not open. Call open() first.")
        self._cs_select()
        try:
            response = self.spi.xfer2([address & 0x7F, 0x00])
        finally:
            self._cs_deselect()
        return response[1] & 0xFF

    def write_reg(self, address: int, value: int) -> None:
        """Write a single register: [addr | 0x80, value]."""
        if self.spi is None:
            raise RadioInitError("SPI not open. Call open() first.")
        self._cs_select()
        try:
            self.spi.xfer2([(address & 0x7F) | SPI_WRITE_BIT, value & 0xFF])
        finally:
            self._cs_deselect()

    def burst_read(self, address: int, length: int) -> list[int]:
        """Burst-read 'length' bytes starting at 'address' (e.g. FIFO)."""
        if self.spi is None:
            raise RadioInitError("SPI not open. Call open() first.")
        tx_buffer = [address & 0x7F] + [0x00] * length
        self._cs_select()
        try:
            response = self.spi.xfer2(tx_buffer)
        finally:
            self._cs_deselect()
        return [b & 0xFF for b in response[1:]]

    def burst_write(self, address: int, data: list[int]) -> None:
        """Burst-write a list of bytes starting at 'address' (e.g. FIFO)."""
        if self.spi is None:
            raise RadioInitError("SPI not open. Call open() first.")
        tx_buffer = [(address & 0x7F) | SPI_WRITE_BIT] + [b & 0xFF for b in data]
        self._cs_select()
        try:
            self.spi.xfer2(tx_buffer)
        finally:
            self._cs_deselect()


    # --------------------------------------------------------------------
    # RESET
    # --------------------------------------------------------------------

    def hardware_reset(self) -> None:
        """
        Perform a hardware reset per datasheet section 7.2.2: hold RESET
        low for >=100us, release, then wait ~5ms for the chip to become
        ready before issuing further SPI commands.
        """
        if GPIO is None or not self._gpio_ready:
            raise RadioInitError("GPIO not initialized. Call open() first.")
        try:
            GPIO.output(self.reset_pin, GPIO.LOW)
            time.sleep(RESET_LOW_DURATION_S)
            GPIO.output(self.reset_pin, GPIO.HIGH)
            time.sleep(RESET_RECOVERY_DELAY_S)
        except Exception as exc:  # noqa: BLE001
            raise RadioInitError(f"Hardware reset failed: {exc}") from exc

    def read_version(self) -> int:
        """Read REG_VERSION (silicon identification)."""
        return self.read_reg(REG_VERSION)

    # --------------------------------------------------------------------
    # RADIO STATE CONTROL
    # --------------------------------------------------------------------

    def _set_mode(self, mode: int) -> None:
        """Set OpMode bits while preserving LongRangeMode (LoRa) bit."""
        self.write_reg(REG_OP_MODE, OP_MODE_LONG_RANGE_MODE | (mode & 0x07))

    def sleep(self) -> None:
        """Enter SLEEP mode (lowest power, FIFO not accessible)."""
        self._set_mode(MODE_SLEEP)

    def standby(self) -> None:
        """Enter STANDBY mode (oscillator and modem circuits active)."""
        self._set_mode(MODE_STDBY)

    def idle(self) -> None:
        """Alias for standby() -- the SX1276 idle state is STANDBY."""
        self.standby()

    def rx_continuous(self) -> None:
        """Enter RX CONTINUOUS mode: modem stays in receive indefinitely."""
        self._set_mode(MODE_RXCONTINUOUS)

    def rx_single(self) -> None:
        """Enter RX SINGLE mode: modem returns to STANDBY after one packet."""
        self._set_mode(MODE_RXSINGLE)

    # --------------------------------------------------------------------
    # RADIO CONFIGURATION
    # --------------------------------------------------------------------

    def enable_lora_mode(self) -> None:
        """
        Switch the chip into LoRa mode. Per datasheet, LongRangeMode can
        only be changed while in SLEEP mode.
        """
        self.write_reg(REG_OP_MODE, MODE_SLEEP)  # FSK sleep first
        time.sleep(0.01)
        self.write_reg(REG_OP_MODE, OP_MODE_LONG_RANGE_MODE | MODE_SLEEP)
        time.sleep(0.01)

    def set_frequency(self, frequency_hz: int) -> None:
        """
        Set the carrier frequency. Frf = frequency_hz / FSTEP, written as
        a 24-bit value across REG_FRF_MSB/MID/LSB (datasheet section 4.1).
        """
        frf = int(round(frequency_hz / FSTEP_HZ))
        self.write_reg(REG_FRF_MSB, (frf >> 16) & 0xFF)
        self.write_reg(REG_FRF_MID, (frf >> 8) & 0xFF)
        self.write_reg(REG_FRF_LSB, frf & 0xFF)

    def set_bandwidth(self, bandwidth_hz: int) -> None:
        """Set the LoRa signal bandwidth via REG_MODEM_CONFIG1[7:4]."""
        if bandwidth_hz not in BANDWIDTH_TABLE:
            raise ValueError(
                f"Unsupported bandwidth {bandwidth_hz} Hz. "
                f"Valid values: {sorted(BANDWIDTH_TABLE)}"
            )
        bw_bits = BANDWIDTH_TABLE[bandwidth_hz]
        current = self.read_reg(REG_MODEM_CONFIG1)
        updated = (current & 0x0F) | (bw_bits << 4)
        self.write_reg(REG_MODEM_CONFIG1, updated)

    def set_spreading_factor(self, spreading_factor: int) -> None:
        """
        Set the spreading factor (SF6..SF12) via REG_MODEM_CONFIG2[7:4].
        SF6 requires implicit header mode and special detection settings
        per datasheet errata; this driver supports SF7-SF12 explicitly.
        """
        if not 6 <= spreading_factor <= 12:
            raise ValueError("Spreading factor must be between 6 and 12.")
        current = self.read_reg(REG_MODEM_CONFIG2)
        updated = (current & 0x0F) | ((spreading_factor & 0x0F) << 4)
        self.write_reg(REG_MODEM_CONFIG2, updated)

        # Detection optimize / threshold per datasheet Table 34 (SF6 vs SF7-12)
        if spreading_factor == 6:
            self.write_reg(REG_DETECT_OPTIMIZE, 0xC5)
            self.write_reg(REG_DETECTION_THRESHOLD, 0x0C)
        else:
            self.write_reg(REG_DETECT_OPTIMIZE, 0xC3)
            self.write_reg(REG_DETECTION_THRESHOLD, 0x0A)

    def set_coding_rate(self, denominator: int) -> None:
        """Set the coding rate (4/5..4/8) via REG_MODEM_CONFIG1[3:1]."""
        if denominator not in CODING_RATE_TABLE:
            raise ValueError(
                f"Unsupported coding rate denominator {denominator}. "
                f"Valid values: {sorted(CODING_RATE_TABLE)}"
            )
        cr_bits = CODING_RATE_TABLE[denominator]
        current = self.read_reg(REG_MODEM_CONFIG1)
        updated = (current & 0xF1) | (cr_bits << 1)
        self.write_reg(REG_MODEM_CONFIG1, updated)

    def set_crc(self, enabled: bool) -> None:
        """Enable/disable payload CRC via REG_MODEM_CONFIG2 bit2."""
        current = self.read_reg(REG_MODEM_CONFIG2)
        updated = (current | 0x04) if enabled else (current & ~0x04)
        self.write_reg(REG_MODEM_CONFIG2, updated & 0xFF)

    def set_sync_word(self, sync_word: int) -> None:
        """Set the LoRa sync word (REG_SYNC_WORD, 0x12 = private network)."""
        self.write_reg(REG_SYNC_WORD, sync_word & 0xFF)

    def set_preamble(self, length: int) -> None:
        """Set preamble length (symbols) across REG_PREAMBLE_MSB/LSB."""
        self.write_reg(REG_PREAMBLE_MSB, (length >> 8) & 0xFF)
        self.write_reg(REG_PREAMBLE_LSB, length & 0xFF)

    def set_lna(self, boost_hf: bool = True, agc_auto: bool = True) -> None:
        """
        Configure the LNA. LnaGain is left at its max-gain reset value
        (000 selects the internal AGC-controlled gain); LnaBoostHf is set
        for improved sensitivity at high frequency, and AgcAutoOn in
        REG_MODEM_CONFIG3 lets the AGC control gain automatically.
        """
        lna = self.read_reg(REG_LNA)
        if boost_hf:
            lna = (lna & 0xFC) | 0x03  # LnaBoostHf = 11 (recommended)
        else:
            lna = lna & 0xFC
        self.write_reg(REG_LNA, lna)

        modem_config3 = self.read_reg(REG_MODEM_CONFIG3)
        if agc_auto:
            modem_config3 |= 0x04  # AgcAutoOn
        else:
            modem_config3 &= ~0x04
        self.write_reg(REG_MODEM_CONFIG3, modem_config3 & 0xFF)

    def set_explicit_header(self, explicit: bool = True) -> None:
        """Select explicit (0) or implicit (1) header mode, ModemConfig1 bit0."""
        current = self.read_reg(REG_MODEM_CONFIG1)
        updated = (current & 0xFE) if explicit else (current | 0x01)
        self.write_reg(REG_MODEM_CONFIG1, updated)

    def set_iq_normal(self) -> None:
        """Set normal (non-inverted) IQ per datasheet recommended values."""
        self.write_reg(REG_INVERT_IQ, 0x27)
        self.write_reg(REG_INVERT_IQ2, 0x1D)

    def set_tx_power(self, power_dbm: int) -> None:
        """
        Set TX output power via REG_PA_CONFIG. Uses the PA_BOOST pin
        (bit7=1) supported on RFM95W modules, valid range +2..+17 dBm
        (up to +20 dBm with PA_DAC high-power mode, not enabled here).
        """
        power_dbm = max(2, min(17, power_dbm))
        pa_config = 0x80 | (power_dbm - 2)  # PaSelect=1 (PA_BOOST), MaxPower left at reset
        self.write_reg(REG_PA_CONFIG, pa_config)

    def set_dio0_mapping_rxdone(self) -> None:
        """Map DIO0 to RxDone in LoRa mode (REG_DIO_MAPPING1 bits7:6 = 00)."""
        current = self.read_reg(REG_DIO_MAPPING1)
        updated = current & 0x3F
        self.write_reg(REG_DIO_MAPPING1, updated)

    def set_fifo_base_addresses(
        self,
        rx_base: int = FIFO_RX_BASE_ADDR,
        tx_base: int = FIFO_TX_BASE_ADDR,
    ) -> None:
        """Configure the FIFO RX/TX base addresses (shared 256-byte FIFO)."""
        self.write_reg(REG_FIFO_RX_BASE_ADDR, rx_base & 0xFF)
        self.write_reg(REG_FIFO_TX_BASE_ADDR, tx_base & 0xFF)

    def set_max_payload_length(self, length: int = MAX_PAYLOAD_LENGTH) -> None:
        """Set the maximum accepted payload length (REG_MAX_PAYLOAD_LENGTH)."""
        self.write_reg(REG_MAX_PAYLOAD_LENGTH, length & 0xFF)

    # --------------------------------------------------------------------
    # FIFO ACCESS
    # --------------------------------------------------------------------

    def fifo_read(self, length: int) -> list[int]:
        """Read 'length' bytes from the FIFO at the current FifoAddrPtr."""
        return self.burst_read(REG_FIFO, length)

    def fifo_write(self, data: list[int]) -> None:
        """Write bytes into the FIFO at the current FifoAddrPtr."""
        self.burst_write(REG_FIFO, data)

    # --------------------------------------------------------------------
    # IRQ HANDLING
    # --------------------------------------------------------------------

    def clear_irq(self, mask: int = IRQ_ALL_MASK) -> None:
        """Clear IRQ flags by writing 1s to the corresponding bits."""
        self.write_reg(REG_IRQ_FLAGS, mask & 0xFF)

    def read_irq(self) -> int:
        """Read the current IRQ_FLAGS register value."""
        return self.read_reg(REG_IRQ_FLAGS)

    def wait_irq(self, mask: int, timeout_s: Optional[float] = None) -> int:
        """
        Poll IRQ_FLAGS until any bit in 'mask' is set, or until timeout.
        Returns the IRQ_FLAGS value observed when the condition was met,
        or 0x00 if the timeout elapsed without a match. This is used as
        a fallback path; the gateway itself relies on the DIO0 GPIO
        interrupt rather than this polling loop for normal operation.
        """
        start_time = time.monotonic()
        while True:
            flags = self.read_irq()
            if flags & mask:
                return flags
            if timeout_s is not None and (time.monotonic() - start_time) > timeout_s:
                return 0x00
            time.sleep(0.001)

    # --------------------------------------------------------------------
    # SIGNAL QUALITY
    # --------------------------------------------------------------------

    def read_rssi(self) -> int:
        """Read the raw (instantaneous) RSSI register value."""
        return self.read_reg(REG_RSSI_VALUE)

    def read_snr(self) -> float:
        """
        Read the packet SNR in dB. REG_PKT_SNR_VALUE is a signed value
        in two's complement, scaled by 4 (datasheet section 6.4).
        """
        raw = self.read_reg(REG_PKT_SNR_VALUE)
        if raw & 0x80:
            # Negative value: two's complement conversion.
            snr = (raw - 256) / 4.0
        else:
            snr = raw / 4.0
        return snr

    def _read_packet_rssi(self, snr_db: float) -> float:
        """
        Compute calibrated packet RSSI in dBm from REG_PKT_RSSI_VALUE,
        applying the standard SX1276 HF-port correction (datasheet
        section 5.5.5, RssiOffset = -157 for frequencies >= 779 MHz).
        """
        packet_rssi_raw = self.read_reg(REG_PKT_RSSI_VALUE)
        rssi_offset = -157.0  # HF port (>=779MHz); use -164 for LF port
        if snr_db < 0:
            rssi_dbm = rssi_offset + packet_rssi_raw + snr_db * 0.25
        else:
            rssi_dbm = rssi_offset + packet_rssi_raw * (16.0 / 15.0)
        return rssi_dbm

    # --------------------------------------------------------------------
    # PAYLOAD RECEPTION
    # --------------------------------------------------------------------

    def receive_packet(self) -> Optional[LoRaPacket]:
        """
        Handle a single RxDone event: validate CRC, read the payload from
        the FIFO at the current RX address, and gather RSSI/SNR metadata.

        Returns None if the IRQ indicates a CRC error or no valid header
        (caller should clear IRQ and keep waiting -- FIFO-empty / no-packet
        conditions are not treated as fatal errors).
        """
        irq_flags = self.read_irq()

        if irq_flags & IRQ_PAYLOAD_CRC_ERROR:
            logger.warning("Payload CRC error - packet discarded.")
            self.clear_irq()
            return None

        if not (irq_flags & IRQ_RX_DONE):
            # Not actually a completed reception; nothing to do.
            return None

        length = self.read_reg(REG_RX_NB_BYTES)
        current_addr = self.read_reg(REG_FIFO_RX_CURRENT_ADDR)
        self.write_reg(REG_FIFO_ADDR_PTR, current_addr)

        raw_bytes = self.fifo_read(length)
        snr_db = self.read_snr()
        rssi_dbm = self._read_packet_rssi(snr_db)

        self.clear_irq()

        return LoRaPacket(
            timestamp=datetime.now(),
            payload=bytes(raw_bytes),
            rssi_dbm=rssi_dbm,
            snr_db=snr_db,
            length=length,
        )

    # --------------------------------------------------------------------
    # REGISTER DUMP
    # --------------------------------------------------------------------

    def dump_register(self) -> dict[int, int]:
        """Read and return every named register as an address->value map."""
        return {addr: self.read_reg(addr) for addr in sorted(REGISTER_NAMES)}

    def print_register_dump(self) -> None:
        """Pretty-print the full register dump."""
        values = self.dump_register()
        print("=" * 60)
        print("REGISTER DUMP")
        print("=" * 60)
        for addr, value in values.items():
            name = REGISTER_NAMES.get(addr, "Unknown")
            print(f"0x{addr:02X}  {name:<20}0x{value:02X}")
        print("=" * 60)


# ==============================================================================
# class Gateway - SINGLE CHANNEL LORA GATEWAY ORCHESTRATION
# ==============================================================================

class Gateway:
    """
    Single-channel LoRa gateway built on top of the SX1276 driver.

    Responsibilities:
      - Initialize and configure the radio per the target RadioConfig
      - Put the modem into RX CONTINUOUS mode
      - Use DIO0 as a GPIO interrupt to detect RxDone events (not polling)
      - Decode/validate the received JSON payload and print a report
    """

    def __init__(self, radio: SX1276, config: RadioConfig) -> None:
        self.radio: SX1276 = radio
        self.config: RadioConfig = config
        self._packet_pending: bool = False
        self._running: bool = False
        # True once GPIO.add_event_detect() succeeds on DIO0. If interrupt
        # registration fails (e.g. insufficient permissions, or a stale
        # sysfs GPIO export left behind by a previous unclean shutdown),
        # the gateway falls back to polling REG_IRQ_FLAGS instead of
        # crashing outright.
        self._interrupt_mode: bool = False

    # --------------------------------------------------------------------
    # INITIALIZATION
    # --------------------------------------------------------------------

    def initialize_radio(self) -> None:
        """
        Bring the radio up: open SPI/GPIO, hardware reset, verify chip
        identity, and apply the full LoRa modem configuration. Any
        failure here is fatal, per project error-handling requirements.
        """
        try:
            self.radio.open()
        except RadioInitError as exc:
            logger.error("SPI/GPIO initialization failed: %s", exc)
            raise SystemExit(1) from exc

        try:
            self.radio.hardware_reset()
        except RadioInitError as exc:
            logger.error("Hardware reset failed: %s", exc)
            raise SystemExit(1) from exc

        version = self.radio.read_version()
        if version != SX1276_EXPECTED_VERSION:
            self.radio.hardware_reset() # Ddy: 20260803, reset 
            version = self.radio.read_version()
            if version != SX1276_EXPECTED_VERSION:
                logger.error(
                    "Unexpected REG_VERSION: 0x%02X (expected 0x%02X). Halting.",
                    version,
                    SX1276_EXPECTED_VERSION,
                )
                raise SystemExit(1)
        logger.info("Radio Version: 0x%02X", version)

        self.radio.enable_lora_mode()
        self.radio.standby()

        self.radio.set_frequency(self.config.frequency_hz)
        self.radio.set_bandwidth(self.config.bandwidth_hz)
        self.radio.set_spreading_factor(self.config.spreading_factor)
        self.radio.set_coding_rate(self.config.coding_rate_denom)
        self.radio.set_crc(self.config.crc_enabled)
        self.radio.set_sync_word(self.config.sync_word)
        self.radio.set_preamble(self.config.preamble_length)
        self.radio.set_explicit_header(explicit=True)
        self.radio.set_iq_normal()
        self.radio.set_lna(boost_hf=True, agc_auto=True)
        self.radio.set_tx_power(self.config.tx_power_dbm)
        self.radio.set_fifo_base_addresses()
        self.radio.set_max_payload_length()
        self.radio.set_dio0_mapping_rxdone()

        self.radio.write_reg(REG_FIFO_ADDR_PTR, FIFO_RX_BASE_ADDR)
        self.radio.clear_irq()

        logger.info("Radio configuration applied successfully.")

    # --------------------------------------------------------------------
    # DIO0 INTERRUPT HANDLING
    # --------------------------------------------------------------------

    def _on_dio0_rising_edge(self, channel: int) -> None:
        """
        GPIO edge-detect callback (runs in RPi.GPIO's internal thread).
        Kept minimal: just flags that a packet is pending so the main
        loop can safely perform SPI I/O outside the interrupt context.
        """
        self._packet_pending = True

    def _arm_dio0_interrupt(self) -> None:
        """
        Register a rising-edge interrupt handler on the DIO0 GPIO pin.

        If registration fails, this is NOT treated as fatal: edge
        detection on Raspberry Pi OS commonly fails when the process
        isn't running as root, or when a previous unclean shutdown left
        a stale GPIO export in sysfs. In that case the gateway logs a
        warning and falls back to polling REG_IRQ_FLAGS in the main
        loop instead of using a hardware interrupt.
        """
        if GPIO is None:
            logger.warning("RPi.GPIO not available - falling back to polling mode.")
            self._interrupt_mode = False
            return
        try:
            GPIO.add_event_detect(
                self.radio.dio0_pin,
                GPIO.RISING,
                callback=self._on_dio0_rising_edge,
            )
            self._interrupt_mode = True
            logger.info("DIO0 interrupt armed on GPIO%d.", self.radio.dio0_pin)
        except RuntimeError as exc:
            logger.warning(
                "Failed to arm DIO0 interrupt (%s). Falling back to polling "
                "REG_IRQ_FLAGS instead. If this persists, try running with "
                "sudo, or reboot to clear a stale GPIO export.",
                exc,
            )
            self._interrupt_mode = False

    def _disarm_dio0_interrupt(self) -> None:
        """Remove the DIO0 interrupt handler, if one was armed."""
        if GPIO is not None and self._interrupt_mode:
            try:
                GPIO.remove_event_detect(self.radio.dio0_pin)
            except Exception:  # noqa: BLE001 - best-effort cleanup
                pass
            self._interrupt_mode = False

    # --------------------------------------------------------------------
    # PACKET PROCESSING
    # --------------------------------------------------------------------

    @staticmethod
    def _decode_json_payload(payload: bytes) -> Optional[dict]:
        """
        Attempt to decode and validate the payload as UTF-8 JSON.
        Returns the parsed dict on success, or None if decoding/validation
        fails (the raw payload is still reported to the operator either way).
        """
        try:
            text = payload.decode("utf-8")
        except UnicodeDecodeError:
            logger.warning("Payload is not valid UTF-8.")
            return None

        try:
            parsed = json.loads(text)
        except json.JSONDecodeError as exc:
            logger.warning("Payload is not valid JSON: %s", exc)
            return None

        if not isinstance(parsed, dict):
            logger.warning("Payload JSON is not an object.")
            return None

        return parsed

    def _print_packet_report(self, packet: LoRaPacket) -> None:
        """Print the formatted packet reception report."""
        payload_hex = " ".join(f"{b:02X}" for b in packet.payload)
        try:
            payload_utf8 = packet.payload.decode("utf-8")
        except UnicodeDecodeError:
            payload_utf8 = "<non-UTF8 payload>"

        print("-" * 60)
        print("Packet Received")
        print(f"Timestamp   : {packet.timestamp.strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"Length      : {packet.length} Bytes")
        print(f"RSSI        : {packet.rssi_dbm:.0f} dBm")
        print(f"SNR         : {packet.snr_db:.2f} dB")
        print(f"Payload HEX : {payload_hex}")
        print(f"Payload UTF8: {payload_utf8}")
        print("-" * 60)

        parsed_json = self._decode_json_payload(packet.payload)
        if parsed_json is not None:
            print("Payload JSON (pretty):")
            print(json.dumps(parsed_json, indent=2, ensure_ascii=False))
            print("-" * 60)

    def _handle_pending_packet(self) -> None:
        """Process a pending RxDone event flagged by the DIO0 interrupt."""
        self._packet_pending = False
        try:
            packet = self.radio.receive_packet()
        except Exception as exc:  # noqa: BLE001
            logger.error("Error while reading received packet: %s", exc)
            self.radio.clear_irq()
            return

        if packet is None:
            # CRC error or spurious IRQ: FIFO effectively empty for us,
            # keep waiting for the next valid packet (not a fatal error).
            logger.warning("No valid packet available - continuing to wait.")
            return

        self._print_packet_report(packet)

    # --------------------------------------------------------------------
    # MAIN LOOP
    # --------------------------------------------------------------------

    def print_banner(self) -> None:
        """Print the gateway startup banner."""
        print("=" * 60)
        print("SX1276 Single Channel Gateway")
        print("=" * 60)
        print(f"Radio Version     : 0x{SX1276_EXPECTED_VERSION:02X}")
        print(f"Frequency         : {self.config.frequency_hz / 1_000_000:.3f} MHz")
        print(f"Bandwidth         : {self.config.bandwidth_hz // 1000} kHz")
        print(f"Spreading Factor  : SF{self.config.spreading_factor}")
        print(f"Coding Rate       : 4/{self.config.coding_rate_denom}")
        print(f"CRC               : {'ON' if self.config.crc_enabled else 'OFF'}")
        print("=" * 60)
        print("Waiting Packet...")
        print("=" * 60)

    def run(self) -> None:
        """
        Start RX CONTINUOUS mode and enter the main event loop, waiting
        on the DIO0 interrupt for incoming packets until stopped.
        """
        self.initialize_radio()
        self._arm_dio0_interrupt()
        self.radio.rx_continuous()
        self.print_banner()

        self._running = True
        try:
            while self._running:
                if self._interrupt_mode:
                    # DIO0 hardware interrupt path: callback sets the flag.
                    if self._packet_pending:
                        self._handle_pending_packet()
                    else:
                        time.sleep(0.01)
                else:
                    # Fallback path: poll IRQ_FLAGS directly (no interrupt
                    # available). Still event-driven from the radio's
                    # perspective -- RxDone is only set once a packet has
                    # actually arrived in RX CONTINUOUS mode.
                    if self.radio.read_irq() & IRQ_RX_DONE:
                        self._handle_pending_packet()
                    else:
                        time.sleep(0.01)
        finally:
            self._disarm_dio0_interrupt()

    def stop(self) -> None:
        """Signal the main loop to exit gracefully."""
        self._running = False


# ==============================================================================
# ENTRY POINT
# ==============================================================================

def _install_signal_handlers(gateway: Gateway) -> None:
    """Install SIGINT/SIGTERM handlers for graceful shutdown."""

    def _handler(signum: int, frame: Optional[FrameType]) -> None:
        logger.info("Signal %s received - shutting down gateway.", signum)
        gateway.stop()

    signal.signal(signal.SIGINT, _handler)
    signal.signal(signal.SIGTERM, _handler)


def build_arg_parser() -> argparse.ArgumentParser:
    """Construct the CLI argument parser for pin/SPI/radio overrides."""
    parser = argparse.ArgumentParser(
        prog="gateway.py",
        description="SX1276 single channel LoRa gateway (Raspberry Pi).",
    )
    parser.add_argument(
        "--spi-bus", type=int, default=DEFAULT_SPI_BUS,
        help=f"SPI bus number (default: {DEFAULT_SPI_BUS}).",
    )
    parser.add_argument(
        "--spi-device", type=int, default=DEFAULT_SPI_DEVICE,
        help=f"SPI device/CE number (default: {DEFAULT_SPI_DEVICE}).",
    )
    parser.add_argument(
        "--spi-speed", type=int, default=DEFAULT_SPI_SPEED_HZ,
        help=f"SPI clock speed in Hz (default: {DEFAULT_SPI_SPEED_HZ}).",
    )
    parser.add_argument(
        "--reset-pin", type=int, default=DEFAULT_RESET_PIN,
        help=f"BCM GPIO number for RESET (default: {DEFAULT_RESET_PIN}).",
    )
    parser.add_argument(
        "--dio0-pin", type=int, default=DEFAULT_DIO0_PIN,
        help=f"BCM GPIO number for DIO0 (default: {DEFAULT_DIO0_PIN}).",
    )
    parser.add_argument(
        "--nss-pin", type=int, default=DEFAULT_NSS_PIN,
        help=(
            "BCM GPIO number for manual NSS/chip-select "
            f"(default: {DEFAULT_NSS_PIN}). Only used when NSS is NOT "
            "wired to hardware CE0/CE1."
        ),
    )
    parser.add_argument(
        "--frequency", type=int, default=DEFAULT_FREQUENCY_HZ,
        help=f"Carrier frequency in Hz (default: {DEFAULT_FREQUENCY_HZ}).",
    )
    return parser


def main() -> int:
    """Program entry point. Returns process exit code."""
    configure_logging(logging.INFO)

    parser = build_arg_parser()
    args = parser.parse_args()

    config = RadioConfig(frequency_hz=args.frequency)
    radio = SX1276(
        spi_bus=args.spi_bus,
        spi_device=args.spi_device,
        spi_speed_hz=args.spi_speed,
        reset_pin=args.reset_pin,
        dio0_pin=args.dio0_pin,
        nss_pin=args.nss_pin,
    )
    gateway = Gateway(radio, config)
    _install_signal_handlers(gateway)

    try:
        gateway.run()
    except SystemExit as exc:
        return int(exc.code) if isinstance(exc.code, int) else 1
    except Exception as exc:  # noqa: BLE001 - top-level safety net
        logger.error("Fatal gateway error: %s", exc)
        return 2
    finally:
        radio.close()

    return 0

# # Ddy: 20260803 Added the following to allow running as a script
# def hardware_reset():
#     logger.warning("Performing SX1276 hardware reset...")
# 
#     GPIO.output(DEFAULT_RESET_PIN, GPIO.LOW)
#     time.sleep(0.2)
# 
#     GPIO.output(DEFAULT_RESET_PIN, GPIO.HIGH)
#     time.sleep(0.2)

# Ddy: 20260803 Added the following to allow running as a script
def detect_radio():
    version = read_register(REG_VERSION)

    if version == 0x12:
        logger.info("SX1276 detected (REG_VERSION=0x12)")
        return True

    logger.error(
        "Unexpected REG_VERSION: 0x%02X (expected 0x12)",
        version
    )

    return False

if __name__ == "__main__":
    sys.exit(main())
