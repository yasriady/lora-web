/**
 * NodeMCU + RFM95W + DS18B20 — LoRa TX sensor node + deep sleep
 *
 * Berdasarkan rfm95w_tx_ds18b20.ino, dengan ESP8266 deep sleep
 * setelah setiap siklus baca + kirim.
 *
 * Aliran data: Nodesensor > Gateway > Database/Web (Laravel)
 *
 * Paket LoRa (teks JSON, flat) yang dikirim node:
 *   {"node_id":"NODE01","temperature":29.5,"battery":3.70}
 *
 * Gateway harus:
 *   - baca paket + RSSI/SNR
 *   - tambah gateway_id + timestamp
 *   - POST ke API /api/v1/telemetry (Bearer token gateway)
 *
 * Legacy flat temperature diterima aplikasi dan dinormalisasi ke metrics.
 * Di web, daftarkan node dengan preset Custom (schema temperature saja)
 * atau Environment — field humidity boleh kosong.
 *
 * Wiring DS18B20 (parasite power TIDAK dipakai di sketch ini):
 *   VDD → 3.3V
 *   GND → GND
 *   DQ  → D2 (GPIO4) + resistor pull-up 4.7kΩ ke 3.3V
 *
 * Wiring LoRa RST (BERBEDA dari sketch tanpa deep sleep):
 *   RFM95W RST → D3 (GPIO0)   // D0 dipakai untuk wake deep sleep
 *   RFM95W CS  → D8
 *   RFM95W DIO0→ D1
 *   SPI: SCK D5, MISO D6, MOSI D7
 *
 * Wiring deep sleep (wajib agar bangun otomatis):
 *   D0 (GPIO16) → RST NodeMCU
 *
 * Library:
 *   - LoRa (sandeepmistry)
 *   - OneWire
 *   - DallasTemperature
 */

#include <SPI.h>
#include <LoRa.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ESP8266WiFi.h>

// —— identitas node (harus sama dengan yang didaftarkan di aplikasi) ——
#define NODE_ID "NODE04"

// —— pin LoRa (RST di D3; D0 cadangan untuk wake deep sleep) ——
#define LORA_SCK   D5
#define LORA_MISO  D6
#define LORA_MOSI  D7
#define LORA_CS    D8
#define LORA_RST   D3
#define LORA_DIO0  D1
#define LORA_FREQ  923E6   // AS923 / Indonesia

// —— pin DS18B20 (D2 = GPIO4; hindari D4 = LED_BUILTIN) ——
#define ONE_WIRE_BUS D2

// —— interval deep sleep (detik). Ubah nilai ini sesuai kebutuhan. ——
// Default 10 agar setara TX_INTERVAL_MS sketch asli. Maks ~71 menit.
const uint32_t SLEEP_INTERVAL_SEC = 10;

// —— baterai opsional via A0 (voltage divider); set false jika tidak dipakai ——
#define ENABLE_BATTERY_ADC false
#define ADC_VREF           3.3f
#define ADC_MAX            1023.0f
#define BATTERY_DIVIDER    2.0f   // mis. 2x resistor sama → kalikan 2

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

void goToDeepSleep()
{
  digitalWrite(LED_BUILTIN, HIGH); // mati (active LOW)
  LoRa.sleep();
  Serial.print(F("Deep sleep "));
  Serial.print(SLEEP_INTERVAL_SEC);
  Serial.println(F(" s"));
  Serial.flush();
  ESP.deepSleep((uint64_t)SLEEP_INTERVAL_SEC * 1000000ULL);
}

void setup()
{
  WiFi.mode(WIFI_OFF);
  WiFi.forceSleepBegin();
  delay(1);

  Serial.begin(115200);
  delay(200);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH); // mati (active LOW)

  sensors.begin();
  sensors.setResolution(12); // ~0.0625°C, konversi ~750 ms

  LoRa.setPins(LORA_CS, LORA_RST, LORA_DIO0);
  if (!LoRa.begin(LORA_FREQ)) {
    Serial.println(F("LoRa gagal init!"));
    while (1) {
      digitalWrite(LED_BUILTIN, LOW);
      delay(200);
      digitalWrite(LED_BUILTIN, HIGH);
      delay(200);
    }
  }

  Serial.println(F("LoRa TX DS18B20 Deep Sleep Ready"));
  Serial.print(F("node_id="));
  Serial.println(NODE_ID);
  Serial.print(F("devices="));
  Serial.println(sensors.getDeviceCount());
  Serial.print(F("sleep_sec="));
  Serial.println(SLEEP_INTERVAL_SEC);
}

float readBatteryVolts()
{
#if ENABLE_BATTERY_ADC
  int raw = analogRead(A0);
  return (raw / ADC_MAX) * ADC_VREF * BATTERY_DIVIDER;
#else
  return NAN;
#endif
}

bool buildPayload(char *buf, size_t buflen, float temperature, float battery)
{
  // Flat JSON → cocok dengan StoreTelemetryRequest (legacy fields → metrics)
  if (isnan(battery)) {
    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"temperature\":%.2f}",
             NODE_ID,
             temperature) < (int) buflen;
  }

  return snprintf(
           buf,
           buflen,
           "{\"node_id\":\"%s\",\"temperature\":%.2f,\"battery\":%.2f}",
           NODE_ID,
           temperature,
           battery) < (int) buflen;
}

void loop()
{
  sensors.requestTemperatures();
  float temperature = sensors.getTempCByIndex(0);

  // DallasTemperature: DEVICE_DISCONNECTED_C == -127.0
  if (temperature == DEVICE_DISCONNECTED_C || isnan(temperature)) {
    Serial.println(F("DS18B20 baca gagal, skip TX"));
    goToDeepSleep();
    return;
  }

  // Clamp ke rentang validasi API (temperature between -100..200)
  if (temperature < -100.0f) {
    temperature = -100.0f;
  }
  if (temperature > 200.0f) {
    temperature = 200.0f;
  }

  float battery = readBatteryVolts();

  char payload[128];
  if (!buildPayload(payload, sizeof(payload), temperature, battery)) {
    Serial.println(F("Payload terlalu panjang"));
    goToDeepSleep();
    return;
  }

  Serial.print(F("TX: "));
  Serial.println(payload);

  digitalWrite(LED_BUILTIN, LOW);
  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();
  digitalWrite(LED_BUILTIN, HIGH);

  goToDeepSleep();
}
