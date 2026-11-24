/*
 * EasyWetter – Grundlagen
 * Sendet jede Minute Testwerte. Funktioniert ohne Sensor – ideal zum Ausprobieren.
 * Danach einfach die Testwerte durch echte Sensorwerte ersetzen.
 */
#include <EasyWetter.h>

// ---- Hier eure Daten eintragen ----
const char* WLAN_NAME     = "Schul-WLAN";
const char* WLAN_PASSWORT = "geheim";
const char* SERVER        = "https://eure-seite.de/api/v1/upload";
const char* TOKEN         = "ew_...";   // steht auf der Stationen-Seite

void setup() {
  Serial.begin(115200);
  EasyWetter.begin(WLAN_NAME, WLAN_PASSWORT, SERVER, TOKEN);
}

void loop() {
  // Testwerte – später durch Sensorwerte ersetzen
  float temperatur = 20.0 + random(0, 50) / 10.0;
  float feuchte    = 40.0 + random(0, 200) / 10.0;

  EasyWetter.add("temp", temperatur);
  EasyWetter.add("hum", feuchte);
  EasyWetter.send();

  delay(60000);   // 60 Sekunden warten
}
