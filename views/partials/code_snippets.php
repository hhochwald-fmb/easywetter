<?php
/** @var string $uploadUrl  @var string|null $token */
$tok = $token ?? 'ew_DEIN_TOKEN';
$uid = 'snip' . random_slug(5);
$arduino = <<<CPP
#include <EasyWetter.h>

// ---- Hier eure Daten eintragen ----
const char* WLAN_NAME     = "Schul-WLAN";
const char* WLAN_PASSWORT = "geheim";
const char* SERVER        = "{$uploadUrl}";
const char* TOKEN         = "{$tok}";

void setup() {
  Serial.begin(115200);
  EasyWetter.begin(WLAN_NAME, WLAN_PASSWORT, SERVER, TOKEN);
}

void loop() {
  float temperatur = 21.5;   // hier euren Sensor auslesen
  float feuchte    = 48.0;

  EasyWetter.add("temp", temperatur);
  EasyWetter.add("hum", feuchte);
  EasyWetter.send();         // schickt alle Werte auf einmal

  delay(60000);              // jede Minute
}
CPP;
$python = <<<PY
import time
from easywetter import EasyWetter

# ---- Hier eure Daten eintragen ----
WLAN_NAME     = "Schul-WLAN"
WLAN_PASSWORT = "geheim"
SERVER        = "{$uploadUrl}"
TOKEN         = "{$tok}"

station = EasyWetter(SERVER, TOKEN)
station.connect_wifi(WLAN_NAME, WLAN_PASSWORT)

while True:
    temperatur = 21.5   # hier euren Sensor auslesen
    feuchte = 48.0

    station.add("temp", temperatur)
    station.add("hum", feuchte)
    station.send()      # schickt alle Werte auf einmal

    time.sleep(60)      # jede Minute
PY;
$curl = "curl -X POST \"{$uploadUrl}\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"X-Station-Token: {$tok}\" \\\n  -d '{\"temp\": 21.5, \"hum\": 48}'";
?>
<div data-tabs>
    <div class="tabs" role="tablist">
        <button type="button" class="active" data-tab="<?= $uid ?>-a"><?= icon('cpu') ?> Arduino / C++</button>
        <button type="button" data-tab="<?= $uid ?>-p"><?= icon('code-xml') ?> MicroPython</button>
        <button type="button" data-tab="<?= $uid ?>-c"><?= icon('radio') ?> Test mit curl</button>
    </div>
    <div id="<?= $uid ?>-a" class="code-box"><pre class="code" id="<?= $uid ?>-a-code"><?= e($arduino) ?></pre><button type="button" class="btn btn-sm" data-copy-target="#<?= $uid ?>-a-code">Kopieren</button></div>
    <div id="<?= $uid ?>-p" class="code-box" hidden><pre class="code" id="<?= $uid ?>-p-code"><?= e($python) ?></pre><button type="button" class="btn btn-sm" data-copy-target="#<?= $uid ?>-p-code">Kopieren</button></div>
    <div id="<?= $uid ?>-c" class="code-box" hidden><pre class="code" id="<?= $uid ?>-c-code"><?= e($curl) ?></pre><button type="button" class="btn btn-sm" data-copy-target="#<?= $uid ?>-c-code">Kopieren</button></div>
</div>
