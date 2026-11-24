# EasyWetter 2

Schülerinnen und Schüler bauen mit ESP32 & Co. eigene Wetterstationen, senden die Messwerte per WLAN an EasyWetter und gestalten dazu ihr eigenes Live-Dashboard.

- **Oberadmin** gibt Schul-Registrierungen frei, verwaltet Schulen und spielt Updates ein
- **Schuladmin** (Lehrkraft) legt Kolleginnen und Kollegen der eigenen Schule an
- **Lehrkraft** legt Schülergruppen an, sieht die Klassenübersicht
- **Schülergruppe** legt Stationen an (je Station ein Token), gestaltet und teilt ihr Dashboard

Technik: PHP ≥ 8.1, MySQL ≥ 5.7 / MariaDB ≥ 10.3, Apache mit `.htaccess` – läuft auf normalem Webhosting, kein Build-Schritt, kein Composer.

**Datenschutz:** Keine externen Skripte, Schriften oder CDNs. Alles wird vom eigenen Server geladen, eine Content-Security-Policy verhindert zusätzlich jedes Nachladen von fremden Servern. Nur ein technisch notwendiges Sitzungs-Cookie. Passwörter und Stations-Tokens werden nur gehasht gespeichert. Login-IP-Adressen werden nach 24 h gelöscht.

---

## Download

Jede EasyWetter-Installation bietet unter **/selbst-installieren** das komplette Programm in der installierten Version als ZIP an. Unter **/anleitung** gibt es die Arduino-Bibliothek (`EasyWetter-Arduino.zip`) und die MicroPython-Bibliothek mit Beispielen (`EasyWetter-MicroPython.zip`). Die Pakete werden automatisch aus den aktuellen Dateien erzeugt und enthalten nie Zugangsdaten, Konfiguration oder Messdaten.

## Installation auf Webhosting

1. ZIP herunterladen (siehe oben) und entpacken – oder `git clone`.
2. Alle Dateien per FTP in einen Ordner auf dem Webspace hochladen.
   Am besten den Document-Root der (Sub-)Domain auf den Ordner `public/` setzen. Falls das nicht geht, funktioniert es auch so – die `.htaccess` im Hauptordner leitet alles nach `public/` um und sperrt die internen Ordner.
3. Beim Hoster eine leere MySQL-Datenbank anlegen.
4. Die Seite im Browser aufrufen → der **Einrichtungsassistent** fragt die Datenbank-Zugangsdaten ab, legt die Tabellen an und erstellt das Oberadmin-Konto.
5. Unter *System & Updates* Impressum und Datenschutzerklärung ausfüllen.

Ordner, die der Webserver beschreiben können muss: `storage/` und (nur für den Assistenten) `config/`.

## Installation mit Git (für Kolleginnen und Kollegen mit eigenem Server)

```bash
git clone https://github.com/hhochwald-fmb/easywetter.git easywetter
cd easywetter
# Seite im Browser öffnen und dem Assistenten folgen
```

## Updates

| Installation | So geht's |
|---|---|
| Git | `php bin/update.php` (holt die neue Version und aktualisiert die Datenbank) |
| Webhosting | Admin → *System & Updates* → Release-ZIP hochladen |
| Webhosting, online | Admin → *System & Updates* → „Nach Updates suchen“ → „Jetzt installieren“ (lädt das neueste Release von [GitHub](https://github.com/hhochwald-fmb/easywetter/releases)) |

Bei jedem Update werden die Programmdateien vorher in `storage/backups/` gesichert. `config/` und `storage/` werden nie überschrieben. Datenbank-Änderungen (Dateien in `migrations/`) laufen automatisch beim ersten Seitenaufruf nach dem Update.

### Neue Version veröffentlichen (für Entwickler)

1. Änderungen machen, Datenbank-Änderungen immer als **neue** Datei `migrations/00X_beschreibung.sql` (alte nie ändern).
2. `VERSION` erhöhen, `CHANGELOG.md` ergänzen.
3. `bin/build-release.sh` → erzeugt `dist/easywetter-<version>.zip` (identisch mit dem Download auf der Seite).
4. Commit, Git-Tag `v<version>` setzen, pushen und bei GitHub ein Release mit der ZIP-Datei als Anhang anlegen:
   `gh release create v<version> dist/easywetter-<version>.zip --notes-file …`
   Alle Installationen finden das Update dann über „Nach Updates suchen“.

## Lokale Entwicklung mit Docker

```bash
docker compose up -d --build
```

- Seite: http://localhost:8090
- phpMyAdmin: http://localhost:8091

Die Datenbank-Zugangsdaten kommen in Docker aus Umgebungsvariablen (siehe `docker-compose.yml`), eine `config/config.php` ist dort nicht nötig.

## API für Stationen

```
POST /api/v1/upload
X-Station-Token: ew_…
Content-Type: application/json

{"temp": 21.5, "hum": 48, "press": 1013.2}
```

- bis zu 24 Werte, Namen `a-z 0-9 _` (max. 32 Zeichen), Werte: Zahlen
- Antworten: `200` ok · `401` Token ungültig · `422` keine gültigen Werte · `429` zu schnell · `413` zu groß
- `GET /api/v1/ping` (optional mit Token-Header) zum Testen der Verbindung

## Bibliotheken für die Stationen

| | Ordner | Inhalt |
|---|---|---|
| Arduino (ESP32/ESP8266) | `libraries/arduino/EasyWetter` | Bibliothek ohne Abhängigkeiten, Beispiele „Grundlagen“ und „BME280“ |
| MicroPython (Pico W, ESP32) | `libraries/micropython` | `easywetter.py`, Beispiele `main.py` und `main_dht22.py`, `LIESMICH.txt` |

```python
from easywetter import EasyWetter
station = EasyWetter(SERVER, TOKEN)
station.connect_wifi(WLAN_NAME, WLAN_PASSWORT)
station.add("temp", 21.5)
station.send()
```

## Ordnerstruktur

```
app/          PHP-Code (Router, Controller, Datenbank, Auth, Updater)
views/        Templates
public/       einziger öffentlicher Ordner (index.php, CSS/JS, Downloads)
migrations/   Datenbank-Schema, versioniert
libraries/    Arduino- und MicroPython-Bibliothek
bin/          update.php, build-release.sh
config/       config.php (wird vom Assistenten erzeugt, nicht im Git)
storage/      Logs, Backups, temporäre Dateien
```
