/**
 * ATmega328P Miniboard + RFM95W + DHT22
 * LoRa TX Sensor Node + Deep Sleep
 *
 * Frequency : 923 MHz
 * Serial    : 115200 baud
 *
 * Payload JSON:
 * {"node_id":"NODE04","seq":1,"temperature":29.50,"humidity":75.20}
 *
 * Optional battery:
 * {"node_id":"NODE04","seq":1,"temperature":29.50,"humidity":75.20,"battery":3.70}
 *
 * Field seq: nomor urut paket (mulai 1) untuk hitung PDR di gateway.
 * Bertahan antar deep sleep; reset ke 1 setelah power-cycle / upload.
 *
 * =====================================================
 * RFM95W MAPPING
 * =====================================================
 *
 * RFM95W SCK   -> ATmega D13 / PB5 / Physical Pin 19
 * RFM95W MISO  -> ATmega D12 / PB4 / Physical Pin 18
 * RFM95W MOSI  -> ATmega D11 / PB3 / Physical Pin 17
 * RFM95W NSS   -> ATmega D10 / PB2 / Physical Pin 16
 * RFM95W RST   -> ATmega D4  / PD4 / Physical Pin 6
 * RFM95W DIO0  -> ATmega D2  / PD2 / Physical Pin 4
 *
 * =====================================================
 * DHT22
 * =====================================================
 *
 * DHT22 DATA -> ATmega D3 / PD3 / Physical Pin 5
 * DHT22 VCC  -> 3.3V
 * DHT22 GND  -> GND
 *
 * Untuk DHT22 bare sensor:
 *
 *              3.3V
 *                |
 *              4.7K-10K
 *                |
 * ATmega D3 ----+---- DHT22 DATA
 *
 * =====================================================
 * Library:
 *
 * - LoRa by Sandeep Mistry
 * - DHT sensor library by Adafruit
 * - LowPower by rocketscream
 *
 * Interval uji (ubah SLEEP_INTERVAL_SEC):
 * 30 / 60 / 300 / 600
 *
 */

#include <SPI.h>
#include <LoRa.h>
#include <DHT.h>
#include <LowPower.h>


// =====================================================
// IDENTITAS NODE
// =====================================================

#define NODE_ID "NODE04"


// =====================================================
// KONFIGURASI LORA
// =====================================================

// Hardware SPI ATmega328P

#define LORA_SCK   13
#define LORA_MISO  12
#define LORA_MOSI  11


// RFM95W control pins

#define LORA_CS    10
#define LORA_RST   4
#define LORA_DIO0  2


// Frequency Indonesia / AS923

#define LORA_FREQ 923E6


// =====================================================
// DHT22
// =====================================================

#define DHT_PIN   3
#define DHT_TYPE  DHT22

DHT dht(
  DHT_PIN,
  DHT_TYPE
);


// =====================================================
// DEEP SLEEP
// =====================================================

// Interval antar pengiriman (detik).
// Matriks eksperimen: 30 / 60 / 300 / 600

const uint32_t SLEEP_INTERVAL_SEC = 30;


// =====================================================
// SEQUENCE NUMBER (PDR)
// =====================================================

// Naik 1 setiap paket yang dikirim.
// RAM ATmega tetap isi saat powerDown (WDT wake) — seq tidak hilang.

uint32_t txSeq = 0;


// =====================================================
// BATTERY ADC
// =====================================================

// Ubah menjadi true jika ingin membaca battery

#define ENABLE_BATTERY_ADC false


#if ENABLE_BATTERY_ADC

#define BATTERY_PIN A0

#define ADC_VREF 3.3f

#define ADC_MAX 1023.0f

/*
 * Contoh voltage divider:
 *
 * Battery ---- R1 ----+---- A0
 *                     |
 *                     R2
 *                     |
 *                    GND
 *
 * Jika R1 = R2:
 * BATTERY_DIVIDER = 2.0
 */

#define BATTERY_DIVIDER 2.0f

#endif


// =====================================================
// DEEP SLEEP FUNCTION
// =====================================================

void sleepSeconds(uint32_t seconds)
{
  Serial.print(F("Deep sleep: "));
  Serial.print(seconds);
  Serial.println(F(" detik"));

  Serial.flush();

  // RFM95 masuk mode sleep
  LoRa.sleep();

  /*
   * LowPower ATmega328P maksimal watchdog sleep:
   *
   * 8S / 4S / 2S / 1S
   *
   * Pecah interval menjadi beberapa bagian.
   */

  while (seconds >= 8)
  {
    LowPower.powerDown(SLEEP_8S, ADC_OFF, BOD_OFF);
    seconds -= 8;
  }

  if (seconds >= 4)
  {
    LowPower.powerDown(SLEEP_4S, ADC_OFF, BOD_OFF);
    seconds -= 4;
  }

  if (seconds >= 2)
  {
    LowPower.powerDown(SLEEP_2S, ADC_OFF, BOD_OFF);
    seconds -= 2;
  }

  if (seconds >= 1)
  {
    LowPower.powerDown(SLEEP_1S, ADC_OFF, BOD_OFF);
    seconds -= 1;
  }
}


// =====================================================
// BATTERY FUNCTION
// =====================================================

float readBatteryVolts()
{
#if ENABLE_BATTERY_ADC

  int raw = analogRead(BATTERY_PIN);

  float voltage =
    ((float)raw / ADC_MAX) *
    ADC_VREF *
    BATTERY_DIVIDER;

  return voltage;

#else

  return NAN;

#endif
}


// =====================================================
// BUILD JSON PAYLOAD
// =====================================================

bool buildPayload(
  char *buf,
  size_t buflen,
  uint32_t seq,
  float temperature,
  float humidity,
  float battery
)
{
  char temperatureStr[16];
  char humidityStr[16];


  // -------------------------------------------------
  // Temperature -> string
  // -------------------------------------------------

  dtostrf(
    temperature,
    0,
    2,
    temperatureStr
  );

  char *temp = temperatureStr;

  while (*temp == ' ')
  {
    temp++;
  }


  // -------------------------------------------------
  // Humidity -> string
  // -------------------------------------------------

  dtostrf(
    humidity,
    0,
    2,
    humidityStr
  );

  char *hum = humidityStr;

  while (*hum == ' ')
  {
    hum++;
  }


  // -------------------------------------------------
  // Tanpa battery
  // -------------------------------------------------

  if (isnan(battery))
  {
    int len = snprintf(
      buf,
      buflen,
      "{\"node_id\":\"%s\",\"seq\":%lu,\"temperature\":%s,\"humidity\":%s}",
      NODE_ID,
      (unsigned long)seq,
      temp,
      hum
    );

    return (
      len > 0 &&
      len < (int)buflen
    );
  }


  // -------------------------------------------------
  // Dengan battery
  // -------------------------------------------------

  char batteryStr[16];

  dtostrf(
    battery,
    0,
    2,
    batteryStr
  );

  char *bat = batteryStr;

  while (*bat == ' ')
  {
    bat++;
  }


  int len = snprintf(
    buf,
    buflen,
    "{\"node_id\":\"%s\",\"seq\":%lu,\"temperature\":%s,\"humidity\":%s,\"battery\":%s}",
    NODE_ID,
    (unsigned long)seq,
    temp,
    hum,
    bat
  );

  return (
    len > 0 &&
    len < (int)buflen
  );
}


// =====================================================
// SETUP
// =====================================================

void setup()
{
  Serial.begin(115200);

  delay(500);


  Serial.println();
  Serial.println(F("================================"));
  Serial.println(F("ATmega328P Miniboard LoRa Node"));
  Serial.println(F("RFM95W + DHT22"));
  Serial.println(F("MODE: DEEP SLEEP"));
  Serial.println(F("================================"));


  // -------------------------------------------------
  // DHT22 INIT
  // -------------------------------------------------

  Serial.println(F("Init DHT22..."));

  dht.begin();

  delay(2000);

  Serial.println(F("DHT22 initialized"));


  // -------------------------------------------------
  // SPI INIT
  // -------------------------------------------------

  SPI.begin();


  // -------------------------------------------------
  // LORA INIT
  // -------------------------------------------------

  LoRa.setPins(
    LORA_CS,
    LORA_RST,
    LORA_DIO0
  );


  Serial.println(F("Init LoRa..."));


  if (!LoRa.begin(LORA_FREQ))
  {
    Serial.println(F("ERROR: LoRa gagal init!"));

    while (true)
    {
      delay(1000);

      Serial.println(F("Periksa wiring:"));

      Serial.println(F("SCK  -> D13"));
      Serial.println(F("MISO -> D12"));
      Serial.println(F("MOSI -> D11"));
      Serial.println(F("CS   -> D10"));
      Serial.println(F("RST  -> D4"));
      Serial.println(F("DIO0 -> D2"));
    }
  }


  // -------------------------------------------------
  // LORA PARAMETERS
  // -------------------------------------------------

  LoRa.setTxPower(17);

  LoRa.setSpreadingFactor(7);

  LoRa.setSignalBandwidth(125E3);

  LoRa.setCodingRate4(5);

  LoRa.setPreambleLength(8);

  LoRa.enableCrc();


  Serial.println(F("LoRa OK"));

  Serial.print(F("Frequency: "));
  Serial.println(F("923 MHz"));

  Serial.print(F("Node ID: "));
  Serial.println(NODE_ID);

  Serial.print(F("Sleep interval: "));
  Serial.print(SLEEP_INTERVAL_SEC);
  Serial.println(F(" sec"));

  Serial.println(F("seq starts at 1 after boot/reset"));

  Serial.println(F("================================"));
}


// =====================================================
// LOOP
// =====================================================

void loop()
{
  // -------------------------------------------------
  // BACA DHT22
  // -------------------------------------------------

  Serial.println();

  Serial.println(F("Reading DHT22..."));


  float humidity =
    dht.readHumidity();

  float temperature =
    dht.readTemperature();


  // -------------------------------------------------
  // VALIDASI SENSOR
  // -------------------------------------------------

  if (
    isnan(temperature) ||
    isnan(humidity)
  )
  {
    Serial.println(
      F("ERROR: DHT22 tidak terbaca!")
    );

    sleepSeconds(SLEEP_INTERVAL_SEC);

    return;
  }


  // -------------------------------------------------
  // BATASI RANGE TEMPERATURE
  // -------------------------------------------------

  if (temperature < -40.0f)
  {
    temperature = -40.0f;
  }

  if (temperature > 80.0f)
  {
    temperature = 80.0f;
  }


  // -------------------------------------------------
  // BATASI RANGE HUMIDITY
  // -------------------------------------------------

  if (humidity < 0.0f)
  {
    humidity = 0.0f;
  }

  if (humidity > 100.0f)
  {
    humidity = 100.0f;
  }


  // -------------------------------------------------
  // BACA BATTERY
  // -------------------------------------------------

  float battery =
    readBatteryVolts();


  // -------------------------------------------------
  // SEQUENCE + BUILD PAYLOAD
  // -------------------------------------------------

  // seq hanya naik jika payload berhasil dibangun (akan dikirim)
  uint32_t thisSeq = txSeq + 1;

  char payload[128];


  bool success =
    buildPayload(
      payload,
      sizeof(payload),
      thisSeq,
      temperature,
      humidity,
      battery
    );


  if (!success)
  {
    Serial.println(
      F("ERROR: Payload terlalu panjang")
    );

    sleepSeconds(SLEEP_INTERVAL_SEC);

    return;
  }

  txSeq = thisSeq;


  // -------------------------------------------------
  // TAMPILKAN DATA
  // -------------------------------------------------

  Serial.print(F("seq: "));
  Serial.println(txSeq);

  Serial.print(F("Temperature: "));

  Serial.print(temperature, 2);

  Serial.println(F(" C"));


  Serial.print(F("Humidity: "));

  Serial.print(humidity, 2);

  Serial.println(F(" %"));


#if ENABLE_BATTERY_ADC

  Serial.print(F("Battery: "));

  Serial.print(battery, 2);

  Serial.println(F(" V"));

#endif


  Serial.print(F("TX Payload: "));

  Serial.println(payload);


  // -------------------------------------------------
  // KIRIM LORA
  // -------------------------------------------------

  LoRa.beginPacket();

  LoRa.print(payload);

  int result =
    LoRa.endPacket();


  if (result == 1)
  {
    Serial.println(
      F("LoRa TX SUCCESS")
    );
  }
  else
  {
    Serial.println(
      F("LoRa TX FAILED")
    );
  }


  // -------------------------------------------------
  // DEEP SLEEP
  // -------------------------------------------------

  sleepSeconds(SLEEP_INTERVAL_SEC);
}
