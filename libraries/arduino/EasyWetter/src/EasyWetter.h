/*
 * EasyWetter – Arduino-Bibliothek für ESP32 und ESP8266
 *
 *   EasyWetter.begin(WLAN_NAME, WLAN_PASSWORT, SERVER, TOKEN);
 *   EasyWetter.add("temp", 21.5);
 *   EasyWetter.add("hum", 48);
 *   EasyWetter.send();
 *
 * Keine weiteren Bibliotheken nötig.
 */
#ifndef EASYWETTER_H
#define EASYWETTER_H

#include <Arduino.h>

#if defined(ESP32)
  #include <WiFi.h>
  #include <HTTPClient.h>
  #include <WiFiClientSecure.h>
#elif defined(ESP8266)
  #include <ESP8266WiFi.h>
  #include <ESP8266HTTPClient.h>
  #include <WiFiClientSecure.h>
#else
  #error "EasyWetter funktioniert mit ESP32 und ESP8266. Bitte das passende Board auswählen."
#endif

#define EASYWETTER_VERSION "2.0.0"
#define EASYWETTER_MAX_VALUES 24

class EasyWetterClass {
public:
  /** WLAN verbinden und Server/Token merken. Gibt true zurück, wenn das WLAN verbunden ist. */
  bool begin(const char* ssid, const char* password, const char* serverUrl, const char* token, uint16_t timeoutSeconds = 20) {
    _ssid = ssid;
    _password = password;
    _url = serverUrl;
    _token = token;
    _timeout = timeoutSeconds;
    if (_debug) {
      Serial.println();
      Serial.println(F("=== EasyWetter " EASYWETTER_VERSION " ==="));
    }
    if (_url.length() == 0 || !(_url.startsWith("http://") || _url.startsWith("https://"))) {
      log(F("❌ SERVER muss mit http:// oder https:// beginnen."));
    }
    if (!String(token).startsWith("ew_")) {
      log(F("⚠️  Der TOKEN sieht ungewöhnlich aus – er beginnt normalerweise mit \"ew_\"."));
    }
    WiFi.mode(WIFI_STA);
    return connectWifi();
  }

  /** (Neu) mit dem WLAN verbinden. */
  bool connectWifi() {
    if (WiFi.status() == WL_CONNECTED) return true;
    if (_debug) {
      Serial.print(F("📶 Verbinde mit WLAN \""));
      Serial.print(_ssid);
      Serial.print(F("\" "));
    }
    WiFi.begin(_ssid, _password);
    unsigned long start = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - start < (unsigned long)_timeout * 1000UL) {
      delay(500);
      if (_debug) Serial.print('.');
    }
    if (WiFi.status() != WL_CONNECTED) {
      if (_debug) Serial.println();
      log(F("❌ WLAN-Verbindung fehlgeschlagen. Stimmen Name und Passwort?"));
      return false;
    }
    if (_debug) {
      Serial.println();
      Serial.print(F("✅ WLAN verbunden, IP-Adresse: "));
      Serial.println(WiFi.localIP());
    }
    return true;
  }

  /** Messwert vormerken. Name: Kleinbuchstaben, Ziffern, _ (z. B. "temp", "co2", "temp_innen"). */
  bool add(const char* name, float value) {
    if (isnan(value) || isinf(value)) {
      if (_debug) { Serial.print(F("⚠️  Ungültiger Wert für \"")); Serial.print(name); Serial.println(F("\" (Sensor angeschlossen?) – wird übersprungen.")); }
      return false;
    }
    if (!validName(name)) {
      if (_debug) { Serial.print(F("⚠️  Ungültiger Name \"")); Serial.print(name); Serial.println(F("\" – erlaubt: a-z, 0-9, _ (max. 32 Zeichen).")); }
      return false;
    }
    for (uint8_t i = 0; i < _count; i++) {
      if (strcmp(_names[i], name) == 0) { _values[i] = value; return true; }
    }
    if (_count >= EASYWETTER_MAX_VALUES) {
      log(F("⚠️  Zu viele Werte (max. 24 pro Senden)."));
      return false;
    }
    strncpy(_names[_count], name, 32);
    _names[_count][32] = '\0';
    _values[_count] = value;
    _count++;
    return true;
  }

  bool add(const char* name, int value) { return add(name, (float)value); }
  bool add(const char* name, long value) { return add(name, (float)value); }
  bool add(const char* name, double value) { return add(name, (float)value); }

  /** Alle vorgemerkten Werte senden. Rückgabe: HTTP-Code (200 = OK) oder negativ bei Verbindungsfehlern. */
  int send() {
    if (_count == 0) {
      log(F("⚠️  Keine Werte zum Senden – erst add(...) aufrufen."));
      return -10;
    }
    if (WiFi.status() != WL_CONNECTED && !connectWifi()) {
      clear();
      return -1;
    }

    String json = "{";
    for (uint8_t i = 0; i < _count; i++) {
      if (i) json += ',';
      json += '"';
      json += _names[i];
      json += "\":";
      json += String(_values[i], 3);
    }
    json += '}';

    if (_debug) { Serial.print(F("📤 Sende ")); Serial.println(json); }

    HTTPClient http;
    int code;
    bool https = _url.startsWith("https://");
    WiFiClient plain;
    WiFiClientSecure secure;
    if (https) {
#if defined(ESP32)
      if (_caCert) secure.setCACert(_caCert); else secure.setInsecure();
#else
      secure.setInsecure();
#endif
    }
    http.setTimeout(15000);
    if (!http.begin(https ? (WiFiClient&)secure : plain, _url)) {
      log(F("❌ Server-Adresse ungültig."));
      clear();
      return -2;
    }
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-Station-Token", _token);
    http.addHeader("User-Agent", "EasyWetter-Arduino/" EASYWETTER_VERSION);
    code = http.POST(json);
    _response = code > 0 ? http.getString() : String();
    http.end();
    clear();

    if (_debug) explain(code);
    return code;
  }

  /** Vorgemerkte Werte verwerfen. */
  void clear() { _count = 0; }

  /** Antwort des Servers beim letzten send(). */
  const String& lastResponse() const { return _response; }

  /** Ausgaben im Seriellen Monitor an/aus (Standard: an). */
  void setDebug(bool on) { _debug = on; }

#if defined(ESP32)
  /** Optional: Root-Zertifikat für echte HTTPS-Prüfung setzen (sonst wird nicht geprüft). */
  void setCACert(const char* cert) { _caCert = cert; }
#endif

private:
  const char* _ssid = "";
  const char* _password = "";
  String _url;
  const char* _token = "";
  uint16_t _timeout = 20;
  bool _debug = true;
  const char* _caCert = nullptr;
  char _names[EASYWETTER_MAX_VALUES][33];
  float _values[EASYWETTER_MAX_VALUES];
  uint8_t _count = 0;
  String _response;

  void log(const __FlashStringHelper* msg) { if (_debug) Serial.println(msg); }

  static bool validName(const char* n) {
    size_t len = strlen(n);
    if (len == 0 || len > 32) return false;
    for (size_t i = 0; i < len; i++) {
      char c = n[i];
      if (!((c >= 'a' && c <= 'z') || (c >= '0' && c <= '9') || c == '_')) return false;
    }
    return true;
  }

  void explain(int code) {
    switch (code) {
      case 200: Serial.println(F("✅ Gespeichert!")); break;
      case 401: Serial.println(F("❌ Token ungültig – auf der Stationen-Seite prüfen bzw. neu erzeugen.")); break;
      case 422: Serial.println(F("❌ Der Server hat keine gültigen Werte gefunden.")); break;
      case 429: Serial.println(F("⏳ Zu schnell gesendet – etwas länger warten (delay).")); break;
      case 404: Serial.println(F("❌ Adresse nicht gefunden – endet SERVER auf /api/v1/upload?")); break;
      default:
        if (code < 0) { Serial.print(F("❌ Keine Verbindung zum Server (Fehler ")); Serial.print(code); Serial.println(F(")")); }
        else { Serial.print(F("⚠️  Antwort ")); Serial.print(code); Serial.print(F(": ")); Serial.println(_response); }
    }
  }
};

static EasyWetterClass EasyWetter;

#endif
