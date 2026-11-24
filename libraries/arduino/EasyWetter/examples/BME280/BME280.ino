/*
 * EasyWetter – Wetterstation mit BME280 (Temperatur, Luftfeuchte, Luftdruck)
 *
 * Benötigt zusätzlich: "Adafruit BME280 Library" (über den Bibliotheksverwalter installieren)
 * Verkabelung ESP32: VIN -> 3V3, GND -> GND, SDA -> GPIO 21, SCL -> GPIO 22
 */
#include <EasyWetter.h>
#include <Wire.h>
#include <Adafruit_BME280.h>

const char* WLAN_NAME     = "Schul-WLAN";
const char* WLAN_PASSWORT = "geheim";
const char* SERVER        = "https://eure-seite.de/api/v1/upload";
const char* TOKEN         = "ew_...";

Adafruit_BME280 bme;

void setup() {
  Serial.begin(115200);
  if (!bme.begin(0x76) && !bme.begin(0x77)) {
    Serial.println("BME280 nicht gefunden – Verkabelung prüfen!");
  }
  EasyWetter.begin(WLAN_NAME, WLAN_PASSWORT, SERVER, TOKEN);
}

void loop() {
  EasyWetter.add("temp", bme.readTemperature());          // °C
  EasyWetter.add("hum", bme.readHumidity());              // %
  EasyWetter.add("press", bme.readPressure() / 100.0F);   // hPa
  EasyWetter.send();

  delay(60000);
}
