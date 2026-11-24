# Changelog

## 2.0.2
- E-Mail-Benachrichtigungen komplett im Admin einstellbar (Empfänger + Absender), kein Bearbeiten der config.php mehr nötig
- Knopf „Test-E-Mail senden“ und klare Statusanzeige, ob E-Mails aktiv sind
- Hinweis in der Admin-Übersicht, solange keine Benachrichtigungen eingerichtet sind
- Einstellungen im Admin haben Vorrang vor config.php

## 2.0.1
- Einrichtungsassistent: verständliche Hinweise bei Datenbankfehlern (falscher Name, Passwort, Server)
- Hinweisseite, falls die Weiterleitung nach public/ fehlt (.htaccess nicht hochgeladen)
- Bearbeiten-Popover (Stift) wurde in Tabellen abgeschnitten – behoben
- Browser laden geänderte CSS/JS-Dateien sofort neu (kein veralteter Cache mehr)
- Anleitung: Messwert-Namen ausführlich erklärt, Download der MicroPython-Beispiele
- Code-Vorlagen: Warnhinweis bei lokaler Testumgebung (localhost)
- Online-Updates aus dem offiziellen GitHub-Repository hhochwald-fmb/easywetter ohne weitere Einstellung

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
