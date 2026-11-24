# EasyWetter – Beispiel mit DHT22 (Temperatur + Luftfeuchte)
# DHT22-Datenpin an GPIO 15 (Pico W: GP15). Das dht-Modul ist in MicroPython schon enthalten.
import time
import dht
from machine import Pin
from easywetter import EasyWetter

WLAN_NAME     = "Schul-WLAN"
WLAN_PASSWORT = "geheim"
SERVER        = "https://eure-seite.de/api/v1/upload"
TOKEN         = "ew_..."

sensor = dht.DHT22(Pin(15))
station = EasyWetter(SERVER, TOKEN)
station.connect_wifi(WLAN_NAME, WLAN_PASSWORT)

while True:
    try:
        sensor.measure()
        station.add("temp", sensor.temperature())
        station.add("hum", sensor.humidity())
        station.send()
    except OSError as e:
        print("Sensor konnte nicht gelesen werden:", e)
    time.sleep(60)
