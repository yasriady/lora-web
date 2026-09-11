/**
 * NodeMCU + RFM95W + DHT22 — LoRa TX sensor node + deep sleep
 *
 * Berdasarkan rfm95w_tx_ds18b20_deepsleep.ino, sensor diganti DS18B20 → DHT22
 * (temperature + humidity). ESP8266 deep sleep setelah setiap siklus baca + kirim.
 *
 * Payload disamakan dengan lora_dht22.ino:
 *   {"node_id":"NODE04","seq":1,"temperature":29.50,"humidity":75.20}
 *   {"node_id":"NODE04","seq":1,"temperature":29.50,"humidity":75.20,"battery":3.70}
 *
 * Field seq: nomor urut paket (mulai 1) untuk hitung PDR di gateway.
 * Bertahan antar deep sleep via RTC memory; reset ke 1 setelah power-cycle.
 *
 * Aliran data: Nodesensor > Gateway > Database/Web (Laravel)
 *
 * Gateway harus:
 *   - baca paket + RSSI/SNR
 *   - tambah gateway_id + timestamp
 *   - POST ke API /api/v1/telemetry (Bearer token gateway)
 *
 * Legacy flat temperature/humidity diterima aplikasi dan dinormalisasi ke metrics.
 * Di web, daftarkan node dengan preset Environment (temperature + humidity).
 *
 * Wiring DHT22:
 *   VDD  → 3.3V
 *   GND  → GND
 *   DATA → D2 (GPIO4) + resistor pull-up 4.7kΩ–10kΩ ke 3.3V
 *
 * Wiring LoRa RST (sama seperti sketch DS18B20 deep sleep):
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
 *   - DHT sensor library (Adafruit)
 *   - Adafruit Unified Sensor (dependency DHT)
 */

#include <SPI.h>
#include <LoRa.h>
#include <DHT.h>
#include <ESP8266WiFi.h>

// —— identitas node (harus sama dengan yang didaftarkan di aplikasi) ——
#define NODE_ID "Esp32"

// —— pin LoRa (RST di D3; D0 cadangan untuk wake deep sleep) ——
#define LORA_SCK   D5
#define LORA_MISO  D6
#define LORA_MOSI  D7
#define LORA_CS    D8
#define LORA_RST   D3
#define LORA_DIO0  D1
#define LORA_FREQ  923E6   // AS923 / Indonesia

// —— pin DHT22 (D2 = GPIO4; hindari D4 = LED_BUILTIN) ——
#define DHT_PIN  D2
#define DHT_TYPE DHT22

// —— interval deep sleep (detik). Ubah nilai ini sesuai kebutuhan. ——
// Default 30 agar setara lora_dht22.ino. Maks ~71 menit.
// Matriks eksperimen: 30 / 60 / 300 / 600
// Catatan: DHT22 butuh ~2 s settle setelah power-up tiap wake.
const uint32_t SLEEP_INTERVAL_SEC = 30;

// —— baterai via A0 (voltage divider); set false jika tidak dipakai ——
#define ENABLE_BATTERY_ADC true
#define ADC_VREF           3.3f
#define ADC_MAX            1023.0f
#define BATTERY_DIVIDER    2.0f   // mis. 2x resistor sama → kalikan 2

// —— seq di RTC memory (RAM hilang saat ESP.deepSleep) ——
#define RTC_SEQ_MAGIC 0xD4220001UL

struct RtcSeqState {
  uint32_t magic;
  uint32_t seq;
};

static RtcSeqState rtcSeq;

DHT dht(DHT_PIN, DHT_TYPE);

void loadSeqFromRtc()
{
  ESP.rtcUserMemoryRead(0, (uint32_t *)&rtcSeq, sizeof(rtcSeq));
  if (rtcSeq.magic != RTC_SEQ_MAGIC) {
    rtcSeq.magic = RTC_SEQ_MAGIC;
    rtcSeq.seq = 0;
  }
}

void saveSeqToRtc()
{
  ESP.rtcUserMemoryWrite(0, (uint32_t *)&rtcSeq, sizeof(rtcSeq));
}

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

  loadSeqFromRtc();

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH); // mati (active LOW)

  dht.begin();
  // DHT22: baca pertama tidak andal tanpa settle setelah cold boot / wake
  delay(2000);

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

  Serial.println(F("LoRa TX DHT22 Deep Sleep Ready"));
  Serial.print(F("node_id="));
  Serial.println(NODE_ID);
  Serial.print(F("sleep_sec="));
  Serial.println(SLEEP_INTERVAL_SEC);
  Serial.print(F("last_seq="));
  Serial.println(rtcSeq.seq);
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

bool buildPayload(char *buf, size_t buflen, uint32_t seq, float temperature, float humidity, float battery)
{
  // Flat JSON → cocok dengan StoreTelemetryRequest + sama dengan lora_dht22.ino
  if (isnan(battery)) {
    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"seq\":%lu,\"temperature\":%.2f,\"humidity\":%.2f}",
             NODE_ID,
             (unsigned long)seq,
             temperature,
             humidity) < (int) buflen;
  }

  return snprintf(
           buf,
           buflen,
           "{\"node_id\":\"%s\",\"seq\":%lu,\"temperature\":%.2f,\"humidity\":%.2f,\"battery\":%.2f}",
           NODE_ID,
           (unsigned long)seq,
           temperature,
           humidity,
           battery) < (int) buflen;
}

void loop()
{
  float temperature = dht.readTemperature(); // °C
  float humidity = dht.readHumidity();       // %RH

  if (isnan(temperature) || isnan(humidity)) {
    Serial.println(F("DHT22 baca gagal, skip TX"));
    goToDeepSleep();
    return;
  }

  // Clamp ke rentang validasi API
  if (temperature < -100.0f) {
    temperature = -100.0f;
  }
  if (temperature > 200.0f) {
    temperature = 200.0f;
  }
  if (humidity < 0.0f) {
    humidity = 0.0f;
  }
  if (humidity > 100.0f) {
    humidity = 100.0f;
  }

  float battery = readBatteryVolts();

  // seq hanya naik jika payload berhasil dibangun (akan dikirim)
  uint32_t thisSeq = rtcSeq.seq + 1;

  char payload[160];
  if (!buildPayload(payload, sizeof(payload), thisSeq, temperature, humidity, battery)) {
    Serial.println(F("Payload terlalu panjang"));
    goToDeepSleep();
    return;
  }

  rtcSeq.seq = thisSeq;
  saveSeqToRtc();

  Serial.print(F("TX: "));
  Serial.println(payload);

  digitalWrite(LED_BUILTIN, LOW);
  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();
  digitalWrite(LED_BUILTIN, HIGH);

  goToDeepSleep();
}
