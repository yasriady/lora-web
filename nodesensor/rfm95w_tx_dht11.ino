/**
 * NodeMCU + RFM95W + DHT11 — LoRa TX sensor node
 *
 * Aliran data: Nodesensor > Gateway > Database/Web (Laravel)
 *
 * Paket LoRa (teks JSON, flat) yang dikirim node:
 *   {"node_id":"NODE01","temperature":29.5,"humidity":73.0,"battery":3.70}
 *
 * Gateway harus:
 *   - baca paket + RSSI/SNR
 *   - tambah gateway_id + timestamp
 *   - POST ke API /api/v1/telemetry (Bearer token gateway)
 *
 * Legacy flat temperature/humidity diterima aplikasi dan dinormalisasi ke metrics.
 * Di web, daftarkan node dengan preset "Environment (Temp/Humidity)" / env_basic.
 *
 * Library:
 *   - LoRa (sandeepmistry)
 *   - DHT sensor library (Adafruit) + Adafruit Unified Sensor
 */

#include <SPI.h>
#include <LoRa.h>
#include <DHT.h>

// —— identitas node (harus sama dengan yang didaftarkan di aplikasi) ——
#define NODE_ID "NODE01"

// —— pin LoRa (sama seperti rfm95w_tx.ino) ——
#define LORA_SCK   D5
#define LORA_MISO  D6
#define LORA_MOSI  D7
#define LORA_CS    D8
#define LORA_RST   D0
#define LORA_DIO0  D1
#define LORA_FREQ  923E6   // AS923 / Indonesia

// —— pin DHT11 (D2 = GPIO4; hindari D4 = LED_BUILTIN) ——
#define DHT_PIN  D2
#define DHT_TYPE DHT11

// —— interval kirim (ms) ——
#define TX_INTERVAL_MS 10000UL

// —— baterai opsional via A0 (voltage divider); set false jika tidak dipakai ——
#define ENABLE_BATTERY_ADC false
#define ADC_VREF           3.3f
#define ADC_MAX            1023.0f
#define BATTERY_DIVIDER    2.0f   // mis. 2x resistor sama → kalikan 2

DHT dht(DHT_PIN, DHT_TYPE);

void setup()
{
  Serial.begin(115200);
  delay(200);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH); // mati (active LOW)

  dht.begin();

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

  Serial.println(F("LoRa TX DHT11 Ready"));
  Serial.print(F("node_id="));
  Serial.println(NODE_ID);
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

bool buildPayload(char *buf, size_t buflen, float temperature, float humidity, float battery)
{
  // Flat JSON → cocok dengan StoreTelemetryRequest (legacy fields → metrics)
  if (isnan(battery)) {
    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"temperature\":%.1f,\"humidity\":%.1f}",
             NODE_ID,
             temperature,
             humidity) < (int) buflen;
  }

  return snprintf(
           buf,
           buflen,
           "{\"node_id\":\"%s\",\"temperature\":%.1f,\"humidity\":%.1f,\"battery\":%.2f}",
           NODE_ID,
           temperature,
           humidity,
           battery) < (int) buflen;
}

void loop()
{
  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature(); // °C

  if (isnan(humidity) || isnan(temperature)) {
    Serial.println(F("DHT11 baca gagal, skip TX"));
    delay(2000);
    return;
  }

  // DHT11 resolusi ~1°C / 1% RH — clamp ke rentang validasi API
  if (humidity < 0.0f) {
    humidity = 0.0f;
  }
  if (humidity > 100.0f) {
    humidity = 100.0f;
  }

  float battery = readBatteryVolts();

  char payload[128];
  if (!buildPayload(payload, sizeof(payload), temperature, humidity, battery)) {
    Serial.println(F("Payload terlalu panjang"));
    delay(TX_INTERVAL_MS);
    return;
  }

  Serial.print(F("TX: "));
  Serial.println(payload);

  digitalWrite(LED_BUILTIN, LOW);
  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();
  digitalWrite(LED_BUILTIN, HIGH);

  delay(TX_INTERVAL_MS);
}
