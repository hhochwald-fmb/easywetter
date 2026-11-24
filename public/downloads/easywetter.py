# EasyWetter – MicroPython-Bibliothek (Raspberry Pi Pico W, ESP32, ESP8266)
#
#   from easywetter import EasyWetter
#   station = EasyWetter(SERVER, TOKEN)
#   station.connect_wifi(WLAN_NAME, WLAN_PASSWORT)
#   station.add("temp", 21.5)
#   station.send()
#
# Diese Datei unter dem Namen "easywetter.py" auf den Mikrocontroller speichern.

import time
import network
import json

try:
    import urequests as requests
except ImportError:
    import requests

VERSION = "2.0.0"


class EasyWetter:
    def __init__(self, server, token, debug=True):
        self.server = server
        self.token = token
        self.debug = debug
        self.values = {}
        self.last_response = None
        self.wlan = network.WLAN(network.STA_IF)
        self._ssid = None
        self._password = None
        self._log("=== EasyWetter " + VERSION + " ===")
        if not (server.startswith("http://") or server.startswith("https://")):
            self._log("❌ SERVER muss mit http:// oder https:// beginnen.")
        if not str(token).startswith("ew_"):
            self._log('⚠️  Der TOKEN sieht ungewöhnlich aus – er beginnt normalerweise mit "ew_".')

    def _log(self, *msg):
        if self.debug:
            print(*msg)

    def connect_wifi(self, ssid=None, password=None, timeout=20):
        """Mit dem WLAN verbinden. Gibt True zurück, wenn es geklappt hat."""
        if ssid is not None:
            self._ssid, self._password = ssid, password
        self.wlan.active(True)
        if self.wlan.isconnected():
            return True
        self._log("📶 Verbinde mit WLAN", self._ssid)
        self.wlan.connect(self._ssid, self._password)
        start = time.time()
        while not self.wlan.isconnected() and time.time() - start < timeout:
            time.sleep(0.5)
        if not self.wlan.isconnected():
            self._log("❌ WLAN-Verbindung fehlgeschlagen. Stimmen Name und Passwort?")
            return False
        self._log("✅ WLAN verbunden, IP-Adresse:", self.wlan.ifconfig()[0])
        return True

    def add(self, name, value):
        """Messwert vormerken. Name: Kleinbuchstaben, Ziffern, _ (max. 32 Zeichen)."""
        name = str(name)
        ok = 0 < len(name) <= 32 and all(c in "abcdefghijklmnopqrstuvwxyz0123456789_" for c in name)
        if not ok:
            self._log('⚠️  Ungültiger Name "{}" – erlaubt: a-z, 0-9, _'.format(name))
            return False
        try:
            value = float(value)
        except (TypeError, ValueError):
            self._log('⚠️  Ungültiger Wert für "{}" – wird übersprungen.'.format(name))
            return False
        if value != value or value in (float("inf"), float("-inf")):
            self._log('⚠️  Ungültiger Wert für "{}" (Sensor angeschlossen?) – wird übersprungen.'.format(name))
            return False
        if name not in self.values and len(self.values) >= 24:
            self._log("⚠️  Zu viele Werte (max. 24 pro Senden).")
            return False
        self.values[name] = value
        return True

    def clear(self):
        self.values = {}

    def send(self):
        """Alle vorgemerkten Werte senden. Rückgabe: HTTP-Code (200 = OK) oder negativ bei Fehlern."""
        if not self.values:
            self._log("⚠️  Keine Werte zum Senden – erst add(...) aufrufen.")
            return -10
        if not self.wlan.isconnected() and not (self._ssid and self.connect_wifi()):
            self.clear()
            return -1
        body = json.dumps(self.values)
        self._log("📤 Sende", body)
        headers = {
            "Content-Type": "application/json",
            "X-Station-Token": self.token,
            "User-Agent": "EasyWetter-MicroPython/" + VERSION,
        }
        self.clear()
        try:
            r = requests.post(self.server, data=body, headers=headers)
            code = r.status_code
            self.last_response = r.text
            r.close()
        except Exception as e:
            self._log("❌ Keine Verbindung zum Server:", e)
            return -2
        self._explain(code)
        return code

    def _explain(self, code):
        msgs = {
            200: "✅ Gespeichert!",
            401: "❌ Token ungültig – auf der Stationen-Seite prüfen bzw. neu erzeugen.",
            404: "❌ Adresse nicht gefunden – endet SERVER auf /api/v1/upload?",
            422: "❌ Der Server hat keine gültigen Werte gefunden.",
            429: "⏳ Zu schnell gesendet – etwas länger warten (time.sleep).",
        }
        self._log(msgs.get(code, "⚠️  Antwort {}: {}".format(code, self.last_response)))

    # Schreibweise wie in der Arduino-Bibliothek
    connectWifi = connect_wifi
