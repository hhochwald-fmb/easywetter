# EasyWetter – Beispielprogramm für MicroPython
# Als "main.py" speichern, dann startet es automatisch beim Einschalten.
import time
from easywetter import EasyWetter

# ---- Hier eure Daten eintragen ----
WLAN_NAME     = "Schul-WLAN"
WLAN_PASSWORT = "geheim"
SERVER        = "https://eure-seite.de/api/v1/upload"
TOKEN         = "ew_..."   # steht auf der Stationen-Seite

station = EasyWetter(SERVER, TOKEN)
station.connect_wifi(WLAN_NAME, WLAN_PASSWORT)

while True:
    # Testwerte – später durch echte Sensorwerte ersetzen
    station.add("temp", 21.5)
    station.add("hum", 48)
    station.send()
    time.sleep(60)
