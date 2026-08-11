/**
 * NodeMCU + RFM95W + DS18B20 — LoRa TX sensor node
 *
 * Jika DS18B20 gagal dibaca:
 *   - temperatur diganti nilai simulasi random 25.00 - 35.00 °C
 *   - payload diberi flag "simulated":true
 *
 * Paket normal:
 *   {"node_id":"NODE01","temperature":29.50}
 *
 * Paket ketika DS18B20 gagal:
 *   {"node_id":"NODE01","temperature":31.27,"simulated":true}
 *
 * Wiring DS18B20:
 *   VDD → 3.3V
 *   GND → GND
 *   DQ  → D2 (GPIO4)
 *   resistor pull-up 4.7kΩ dari DQ → 3.3V
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

// —— identitas node ——
#define NODE_ID "NODE01"

// —— pin LoRa ——
#define LORA_SCK   D5
#define LORA_MISO  D6
#define LORA_MOSI  D7
#define LORA_CS    D8
#define LORA_RST   D0
#define LORA_DIO0  D1
#define LORA_FREQ  923E6

// —— pin DS18B20 ——
#define ONE_WIRE_BUS D2

// —— interval kirim ——
#define TX_INTERVAL_MS 10000UL

// —— simulasi temperatur jika DS18B20 gagal ——
#define SIM_TEMP_MIN 25.0f
#define SIM_TEMP_MAX 35.0f

// —— baterai opsional via A0 ——
#define ENABLE_BATTERY_ADC false
#define ADC_VREF           3.3f
#define ADC_MAX            1023.0f
#define BATTERY_DIVIDER    2.0f

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

void setup()
{
  Serial.begin(115200);
  delay(200);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH); // LED mati (active LOW)

  sensors.begin();
  sensors.setResolution(12);

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

  Serial.println(F("LoRa TX DS18B20 Ready"));
  Serial.print(F("node_id="));
  Serial.println(NODE_ID);

  Serial.print(F("devices="));
  Serial.println(sensors.getDeviceCount());

  // Seed random berdasarkan noise ADC
  randomSeed(analogRead(A0));
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

// Menghasilkan temperatur simulasi dengan 2 angka desimal
float generateSimulatedTemperature()
{
  long randomValue = random(
    (long)(SIM_TEMP_MIN * 100),
    (long)(SIM_TEMP_MAX * 100) + 1
  );

  return randomValue / 100.0f;
}

bool buildPayload(
  char *buf,
  size_t buflen,
  float temperature,
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
               "{\"node_id\":\"%s\",\"temperature\":%.2f,\"simulated\":true}",
               NODE_ID,
               temperature
             ) < (int)buflen;
    }

    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"temperature\":%.2f}",
             NODE_ID,
             temperature
           ) < (int)buflen;
  }

  // Dengan battery
  if (simulated) {
    return snprintf(
             buf,
             buflen,
             "{\"node_id\":\"%s\",\"temperature\":%.2f,\"battery\":%.2f,\"simulated\":true}",
             NODE_ID,
             temperature,
             battery
           ) < (int)buflen;
  }

  return snprintf(
           buf,
           buflen,
           "{\"node_id\":\"%s\",\"temperature\":%.2f,\"battery\":%.2f}",
           NODE_ID,
           temperature,
           battery
         ) < (int)buflen;
}

void loop()
{
  sensors.requestTemperatures();

  float temperature = sensors.getTempCByIndex(0);

  bool simulated = false;

  // DallasTemperature:
  // DEVICE_DISCONNECTED_C = -127.0
  if (temperature == DEVICE_DISCONNECTED_C || isnan(temperature)) {

    Serial.println(F("DS18B20 baca gagal!"));
    Serial.println(F("Menggunakan temperatur simulasi..."));

    temperature = generateSimulatedTemperature();
    simulated = true;

    Serial.print(F("Temperature SIMULASI: "));
    Serial.print(temperature, 2);
    Serial.println(F(" C"));
  }
  else {

    Serial.print(F("Temperature DS18B20: "));
    Serial.print(temperature, 2);
    Serial.println(F(" C"));
  }

  // Clamp ke rentang validasi API
  if (temperature < -100.0f) {
    temperature = -100.0f;
  }

  if (temperature > 200.0f) {
    temperature = 200.0f;
  }

  float battery = readBatteryVolts();

  char payload[160];

  if (!buildPayload(
        payload,
        sizeof(payload),
        temperature,
        battery,
        simulated
      )) {

    Serial.println(F("Payload terlalu panjang"));
    delay(TX_INTERVAL_MS);
    return;
  }

  Serial.print(F("TX: "));
  Serial.println(payload);

  // LED ON
  digitalWrite(LED_BUILTIN, LOW);

  LoRa.beginPacket();
  LoRa.print(payload);
  LoRa.endPacket();

  // LED OFF
  digitalWrite(LED_BUILTIN, HIGH);

  delay(TX_INTERVAL_MS);
}
