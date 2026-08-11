/**
 * NodeMCU + RFM95W + DHT11 — LoRa TX sensor node
 *
 * Jika DHT11 gagal dibaca:
 *   - temperature dan humidity menggunakan nilai simulasi random
 *   - payload diberi flag "simulated":true
 *
 * Paket normal:
 *   {"node_id":"NODE01","temperature":29.5,"humidity":73.0}
 *
 * Paket ketika DHT11 gagal:
 *   {"node_id":"NODE01","temperature":31.4,"humidity":67.0,"simulated":true}
 *
 * Library:
 *   - LoRa (sandeepmistry)
 *   - DHT sensor library (Adafruit) + Adafruit Unified Sensor
 */

#include <SPI.h>
#include <LoRa.h>
#include <DHT.h>

// —— identitas node ——
#define NODE_ID "NODE03"

// —— pin LoRa ——
#define LORA_SCK   D5
#define LORA_MISO  D6
#define LORA_MOSI  D7
#define LORA_CS    D8
#define LORA_RST   D0
#define LORA_DIO0  D1
#define LORA_FREQ  923E6   // AS923 / Indonesia

// —— pin DHT11 ——
#define DHT_PIN  D2
#define DHT_TYPE DHT11

// —— interval kirim ——
#define TX_INTERVAL_MS 10000UL

// —— baterai opsional via A0 ——
#define ENABLE_BATTERY_ADC false
#define ADC_VREF           3.3f
#define ADC_MAX            1023.0f
#define BATTERY_DIVIDER    2.0f

// —— rentang nilai simulasi ——
#define SIM_TEMP_MIN       25.0f
#define SIM_TEMP_MAX       35.0f

#define SIM_HUMIDITY_MIN   50.0f
#define SIM_HUMIDITY_MAX   90.0f

DHT dht(DHT_PIN, DHT_TYPE);

void setup()
{
  Serial.begin(115200);
  delay(200);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH); // mati (active LOW)

  dht.begin();

  // Seed random menggunakan noise ADC
  randomSeed(analogRead(A0));

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

// Generate temperatur simulasi dengan 1 angka desimal
float generateSimulatedTemperature()
{
  long value = random(
    (long)(SIM_TEMP_MIN * 10),
    (long)(SIM_TEMP_MAX * 10) + 1
  );

  return value / 10.0f;
}

// Generate humidity simulasi dengan 1 angka desimal
float generateSimulatedHumidity()
{
  long value = random(
    (long)(SIM_HUMIDITY_MIN * 10),
    (long)(SIM_HUMIDITY_MAX * 10) + 1
  );

  return value / 10.0f;
}

bool buildPayload(
  char *buf,
  size_t buflen,
  float temperature,
  float humidity,
  float battery,
  bool simulated
)
{
  // Tanpa battery
  if (isnan(battery)) {

    if (simulated) {
      return snprintf(
               buf,
               buflen,
               "{\"node_id\":\"%s\",\"temperature\":%.1f,\"humidity\":%.1f,\"simulated\":true}",
               NODE_ID,
               temperature,
               humidity
             ) < (int)buflen;
    }

    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"temperature\":%.1f,\"humidity\":%.1f}",
             NODE_ID,
             temperature,
             humidity
           ) < (int)buflen;
  }

  // Dengan battery
  if (simulated) {
    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"temperature\":%.1f,\"humidity\":%.1f,\"battery\":%.2f,\"simulated\":true}",
             NODE_ID,
             temperature,
             humidity,
             battery
           ) < (int)buflen;
  }

  return snprintf(
           buf,
           buflen,
           "{\"node_id\":\"%s\",\"temperature\":%.1f,\"humidity\":%.1f,\"battery\":%.2f}",
           NODE_ID,
           temperature,
           humidity,
           battery
         ) < (int)buflen;
}

void loop()
{
  float humidity = dht.readHumidity();
  float temperature = dht.readTemperature(); // °C

  bool simulated = false;

  // ==========================================================
  // DHT11 gagal dibaca
  // ==========================================================
  if (isnan(humidity) || isnan(temperature)) {

    Serial.println(F("DHT11 baca gagal!"));
    Serial.println(F("Menggunakan nilai simulasi..."));

    temperature = generateSimulatedTemperature();
    humidity = generateSimulatedHumidity();

    simulated = true;

    Serial.print(F("Temperature SIMULASI: "));
    Serial.print(temperature, 1);
    Serial.println(F(" C"));

    Serial.print(F("Humidity SIMULASI: "));
    Serial.print(humidity, 1);
    Serial.println(F(" %"));
  }
  else {

    Serial.print(F("Temperature DHT11: "));
    Serial.print(temperature, 1);
    Serial.println(F(" C"));

    Serial.print(F("Humidity DHT11: "));
    Serial.print(humidity, 1);
    Serial.println(F(" %"));
  }

  // ==========================================================
  // Clamp humidity
  // ==========================================================
  if (humidity < 0.0f) {
    humidity = 0.0f;
  }

  if (humidity > 100.0f) {
    humidity = 100.0f;
  }

  // ==========================================================
  // Battery
  // ==========================================================
  float battery = readBatteryVolts();

  // ==========================================================
  // Build payload
  // ==========================================================
  char payload[160];

  if (!buildPayload(
        payload,
        sizeof(payload),
        temperature,
        humidity,
        battery,
        simulated
      )) {

    Serial.println(F("Payload terlalu panjang"));

    delay(TX_INTERVAL_MS);
    return;
  }

  // ==========================================================
  // Kirim LoRa
  // ==========================================================
  Serial.print(F("TX: "));
  Serial.println(payload);

  digitalWrite(LED_BUILTIN, LOW);

  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();

  digitalWrite(LED_BUILTIN, HIGH);

  delay(TX_INTERVAL_MS);
}
