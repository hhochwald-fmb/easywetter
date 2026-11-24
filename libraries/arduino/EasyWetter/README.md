# EasyWetter – Arduino-Bibliothek (ESP32 / ESP8266)

Installation: Arduino IDE → *Sketch → Bibliothek einbinden → .ZIP-Bibliothek hinzufügen* → `EasyWetter-Arduino.zip` wählen.

```cpp
#include <EasyWetter.h>

void setup() {
  Serial.begin(115200);
  EasyWetter.begin("WLAN-Name", "WLAN-Passwort", "https://…/api/v1/upload", "ew_…");
}

void loop() {
  EasyWetter.add("temp", 21.5);
  EasyWetter.add("hum", 48);
  EasyWetter.send();   // 200 = gespeichert
  delay(60000);
}
```

Beispiele: *Datei → Beispiele → EasyWetter* → **Grundlagen** (ohne Sensor) oder **BME280**.
Keine weiteren Bibliotheken nötig. HTTPS wird unterstützt (ohne Zertifikatsprüfung; auf dem ESP32 optional mit `setCACert()`).
