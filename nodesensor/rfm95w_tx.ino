#include <SPI.h>
#include <LoRa.h>

#define LORA_SCK   D5
#define LORA_MISO  D6
#define LORA_MOSI  D7
#define LORA_CS    D8
#define LORA_RST   D0
#define LORA_DIO0  D1

void setup()
{
  Serial.begin(115200);

  // LED internal
  pinMode(LED_BUILTIN, OUTPUT);
  digitalWrite(LED_BUILTIN, HIGH);   // Mati (active LOW)

  LoRa.setPins(LORA_CS, LORA_RST, LORA_DIO0);

  if (!LoRa.begin(923E6))
  {
    Serial.println("LoRa gagal!");
    while (1);
  }

  Serial.println("LoRa TX Ready");
}

int counter = 0;

void loop()
{
  Serial.print("Mengirim Paket ");
  Serial.println(counter);

  // LED ON
  digitalWrite(LED_BUILTIN, LOW);

  LoRa.beginPacket();
  LoRa.print("Hello ");
  LoRa.print(counter);
  LoRa.endPacket();      // Menunggu hingga transmisi selesai

  // LED OFF
  digitalWrite(LED_BUILTIN, HIGH);

  counter++;

  delay(2000);
}

