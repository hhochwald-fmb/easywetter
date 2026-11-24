<main class="page"><div class="container container-narrow stack">
    <div class="page-head"><div><h1>Anleitung</h1><p>Von der Station zum Live-Dashboard – Schritt für Schritt.</p></div></div>

    <div class="card">
        <h2>1. Zugang bekommen</h2>
        <p><strong>Lehrkräfte</strong> registrieren ihre Schule (wird vom Betreiber freigegeben) oder werden vom Schuladmin angelegt. Danach legen sie unter „Meine Gruppen“ für jede Schülergruppe einen Zugang an und geben die Zugangsdaten weiter.</p>
        <p class="mb-0"><strong>Schülergruppen</strong> melden sich mit diesen Daten an. Unter „Stationen“ legt ihr eine Station an und bekommt einen <em>Token</em> – das ist der geheime Schlüssel, mit dem eure Station Daten senden darf.</p>
    </div>

    <div class="card">
        <h2>2. Bibliothek installieren</h2>
        <div class="grid grid-2">
            <div>
                <h3><?= icon('cpu') ?> Arduino IDE (ESP32 / ESP8266)</h3>
                <ol class="small">
                    <li><a href="<?= e(url('download/EasyWetter-Arduino.zip')) ?>">EasyWetter-Arduino.zip</a> herunterladen</li>
                    <li>Arduino IDE: <em>Sketch → Bibliothek einbinden → .ZIP-Bibliothek hinzufügen</em></li>
                    <li>Beispiel öffnen: <em>Datei → Beispiele → EasyWetter</em><br><span class="muted">„Grundlagen“ (ohne Sensor) oder „BME280“ (Temperatur, Feuchte, Druck)</span></li>
                </ol>
                <p class="small muted">Keine weiteren Bibliotheken nötig (für das BME280-Beispiel zusätzlich „Adafruit BME280“).</p>
            </div>
            <div>
                <h3><?= icon('code-xml') ?> MicroPython (Pico W / ESP32)</h3>
                <ol class="small">
                    <li><a href="<?= e(url('download/EasyWetter-MicroPython.zip')) ?>">EasyWetter-MicroPython.zip</a> herunterladen (Bibliothek + Beispiele + Anleitung) – oder einzeln:
                        <a href="<?= e(url('download/easywetter.py')) ?>">easywetter.py</a>,
                        <a href="<?= e(url('download/main.py')) ?>">main.py</a> (Grundlagen),
                        <a href="<?= e(url('download/main_dht22.py')) ?>">main_dht22.py</a> (DHT22-Sensor)</li>
                    <li>In Thonny <code>easywetter.py</code> unter genau diesem Namen auf den Mikrocontroller speichern</li>
                    <li>Ein Beispiel öffnen, WLAN + Token eintragen und als <code>main.py</code> auf den Mikrocontroller speichern – es startet dann automatisch</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>3. Programm anpassen</h2>
        <p>WLAN-Daten und euren Token eintragen. Auf der Stationen-Seite bekommt ihr dieses Programm schon fertig mit eurem Token ausgefüllt.</p>
        <?php $token = null; require __DIR__ . '/partials/code_snippets.php'; ?>
    </div>

    <div class="card">
        <h2>4. Messwert-Namen</h2>
        <p>Jeder Wert, den eure Station sendet, hat einen <strong>Messwert-Namen</strong> – das ist der Text in Anführungszeichen:</p>
        <pre class="code" style="margin-bottom:14px">EasyWetter.add("temp", temperatur);
//              ^^^^    ^^^^^^^^^^
//      Messwert-Name   Variable mit dem Wert (Name egal)</pre>
        <div class="alert alert-info"><?= icon('info') ?><div><strong>Im Dashboard müsst ihr nichts vorher anlegen.</strong> Sobald eure Station einen neuen Namen zum ersten Mal sendet, wird er automatisch gespeichert. Danach erscheint er sofort in der automatischen Ansicht und im Designer unter „Messwert“ (mit ✓ markiert) – dort ordnet ihr ihn einem Baustein zu und gebt ihm eine schöne Beschriftung wie „Temperatur auf dem Schulhof“.</div></div>
        <p><strong>Regeln für den Namen:</strong> nur Kleinbuchstaben <code>a–z</code>, Ziffern und <code>_</code>, höchstens 32 Zeichen, bis zu 24 Werte pro Senden.<br>
            ✔ <code>temp</code>, <code>co2</code>, <code>temp_innen</code>, <code>bodenfeuchte2</code> &nbsp; ✘ <code>Temperatur</code>, <code>temp innen</code>, <code>feuchte%</code></p>
        <p class="small muted">Tipp: Wenn ihr einen Namen später im Programm ändert, bleiben die alten Werte unter dem alten Namen gespeichert – stellt den Baustein im Designer dann auf den neuen Namen um.</p>
        <p>Für diese Namen kennt das Dashboard schon passende Symbole, Einheiten und Farben:</p>
        <div class="table-wrap"><table class="table">
            <tr><th>Name</th><th>Bedeutung</th><th>Einheit</th></tr>
            <tr><td><code>temp</code></td><td>Temperatur</td><td>°C</td></tr>
            <tr><td><code>hum</code></td><td>Luftfeuchtigkeit</td><td>%</td></tr>
            <tr><td><code>press</code></td><td>Luftdruck</td><td>hPa</td></tr>
            <tr><td><code>co2</code></td><td>CO₂-Gehalt</td><td>ppm</td></tr>
            <tr><td><code>light</code></td><td>Helligkeit</td><td>lx</td></tr>
            <tr><td><code>uv</code></td><td>UV-Index</td><td></td></tr>
            <tr><td><code>wind</code></td><td>Windgeschwindigkeit</td><td>km/h</td></tr>
            <tr><td><code>rain</code></td><td>Niederschlag</td><td>mm</td></tr>
            <tr><td><code>soil</code></td><td>Bodenfeuchte</td><td>%</td></tr>
            <tr><td><code>pm25</code></td><td>Feinstaub PM2.5</td><td>µg/m³</td></tr>
            <tr><td><code>noise</code></td><td>Lautstärke</td><td>dB</td></tr>
        </table></div>
    </div>

    <div class="card">
        <h2>5. Für Profis: die API</h2>
        <p class="small muted">EasyWetter an der eigenen Schule betreiben? → <a href="<?= e(url('/selbst-installieren')) ?>">Selbst installieren</a></p>
        <p><strong>POST</strong> <code><?= e($uploadUrl) ?></code> mit JSON-Body und Header <code>X-Station-Token</code>.</p>
        <ul class="small">
            <li>Body: <code>{"temp": 21.5, "hum": 48}</code> – bis zu 24 Werte pro Upload</li>
            <li>Antwort <code>200</code>: <code>{"ok": true, "stored": 2, …}</code></li>
            <li><code>401</code> Token falsch · <code>422</code> keine gültigen Werte · <code>429</code> zu schnell (mind. <?= (int) config('ingest_min_interval', 5) ?> s Abstand)</li>
            <li>Verbindungstest: <strong>GET</strong> <code><?= e(absolute_url('api/v1/ping')) ?></code> (mit Token-Header wird auch der Token geprüft)</li>
        </ul>
    </div>
</div></main>
