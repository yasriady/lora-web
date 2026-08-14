/**
 * NodeMCU + DS18B20 — uji baca temperatur via Serial Monitor
 *
 * Wiring DS18B20 (parasite power TIDAK dipakai di sketch ini):
 *   VDD → 3.3V
 *   GND → GND
 *   DQ  → D2 (GPIO4) + resistor pull-up 4.7kΩ ke 3.3V
 *
 * Library:
 *   - OneWire
 *   - DallasTemperature
 */

#include <OneWire.h>
#include <DallasTemperature.h>

// —— pin DS18B20 (D2 = GPIO4; hindari D4 = LED_BUILTIN) ——
#define ONE_WIRE_BUS D2

// —— interval baca (ms) ——
#define READ_INTERVAL_MS 2000UL

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

void setup()
{
  Serial.begin(115200);
  delay(200);

  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH); // mati (active LOW)

  sensors.begin();
  sensors.setResolution(12); // ~0.0625°C, konversi ~750 ms

  Serial.println(F("DS18B20 Testing Ready"));
  Serial.print(F("devices="));
  Serial.println(sensors.getDeviceCount());
}

void loop()
{
  digitalWrite(LED_BUILTIN, LOW); // nyala saat baca
  sensors.requestTemperatures();
  float temperature = sensors.getTempCByIndex(0);
  digitalWrite(LED_BUILTIN, HIGH);

  // DallasTemperature: DEVICE_DISCONNECTED_C == -127.0
  if (temperature == DEVICE_DISCONNECTED_C || isnan(temperature)) {
    Serial.println(F("DS18B20 baca gagal"));
    delay(READ_INTERVAL_MS);
    return;
  }

  Serial.print(F("temperature="));
  Serial.print(temperature, 2);
  Serial.println(F(" C"));

  delay(READ_INTERVAL_MS);
}
