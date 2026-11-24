# Changelog

## 2.0.0
Kompletter Neubau.
- PHP + MySQL statt JSON-Dateien, Passwörter und Stations-Tokens gehasht
- Rollen: Oberadmin, Schuladmin, Lehrkraft, Schülergruppe
- Selbstregistrierung von Schulen mit Freigabe durch den Oberadmin
- Schülergruppen melden sich an, verwalten Stationen und gestalten ihr Dashboard (Werte, Zeiger, Verläufe, Min/Max, Notizen, Themes)
- Öffentliche Dashboards und Klassenübersicht per Link, einbettbar
- CSV-Export, automatische Löschfristen, Impressum/Datenschutz-Seiten
- Neue Arduino-Bibliothek (ohne Abhängigkeiten) und MicroPython-Klasse, Token-basierte API
- Installationsassistent, Updates per Git, ZIP-Upload oder GitHub-Release
