<?php $heroPage = true; ?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="badge" style="background:rgba(255,255,255,.1);color:#c7d2fe;margin-bottom:14px"><?= icon('sparkles', 'icon icon-sm') ?> Für Physik, NwT, Informatik &amp; AGs</span>
            <h1>Eigene Wetterstation bauen.<br><span>Live-Daten erleben.</span></h1>
            <p class="lead">Schülerinnen und Schüler bauen mit einem ESP32 und Sensoren ihre eigene Messstation, schicken die Werte über WLAN an EasyWetter und gestalten dazu ihr ganz persönliches Live-Dashboard.</p>
            <div class="row">
                <a class="btn btn-primary btn-lg" href="<?= e(url('/login')) ?>"><?= icon('lock') ?> Anmelden</a>
                <?php if ($registrationOpen): ?><a class="btn btn-lg" href="<?= e(url('/registrieren')) ?>"><?= icon('school') ?> Schule registrieren</a><?php endif; ?>
                <a class="btn btn-lg" href="<?= e(url('/anleitung')) ?>"><?= icon('book-open') ?> Anleitung</a>
            </div>
            <div class="hero-stats">
                <div><div class="num"><?= (int) $stats['schools'] ?></div><div class="lbl">Schulen</div></div>
                <div><div class="num"><?= (int) $stats['stations'] ?></div><div class="lbl">Stationen</div></div>
                <div><div class="num"><?= (int) $stats['active'] ?></div><div class="lbl">heute aktiv</div></div>
            </div>
        </div>
        <div class="hero-card" aria-hidden="true">
            <div class="row row-between" style="margin-bottom:12px"><strong>Wetterstation Gruppe A</strong><span class="badge badge-success"><span class="dot dot-on"></span> online</span></div>
            <div class="mini-widgets">
                <div class="mini-widget"><div class="k"><?= icon('thermometer', 'icon icon-sm') ?> Temperatur</div><div class="v">21,4 °C</div></div>
                <div class="mini-widget"><div class="k"><?= icon('droplets', 'icon icon-sm') ?> Luftfeuchte</div><div class="v">48 %</div></div>
                <div class="mini-widget"><div class="k"><?= icon('gauge', 'icon icon-sm') ?> Luftdruck</div><div class="v">1013 hPa</div></div>
                <div class="mini-widget"><div class="k"><?= icon('leaf', 'icon icon-sm') ?> CO₂</div><div class="v">612 ppm</div></div>
                <svg class="mini-chart" viewBox="0 0 300 90" preserveAspectRatio="none"><defs><linearGradient id="hg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#818cf8" stop-opacity=".5"/><stop offset="1" stop-color="#818cf8" stop-opacity="0"/></linearGradient></defs><path d="M0,60 C30,55 45,30 75,35 S120,70 150,55 S210,15 240,25 S285,45 300,30 L300,90 L0,90 Z" fill="url(#hg)"/><path d="M0,60 C30,55 45,30 75,35 S120,70 150,55 S210,15 240,25 S285,45 300,30" fill="none" stroke="#a5b4fc" stroke-width="2.5"/></svg>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-title"><h2>So funktioniert's</h2><p>Von der Steckplatine bis zum Live-Diagramm in einer Doppelstunde.</p></div>
        <div class="grid grid-3 steps">
            <div class="step"><h3>Station bauen</h3><p class="muted">ESP32 (oder ESP8266, Raspberry Pi Pico W) mit Sensoren wie BME280, DHT22 oder SCD40 verbinden.</p></div>
            <div class="step"><h3>Daten senden</h3><p class="muted">Mit unserer Arduino-Bibliothek oder MicroPython-Klasse reichen drei Zeilen Code: <code>add()</code>, <code>add()</code>, <code>send()</code>.</p></div>
            <div class="step"><h3>Dashboard gestalten</h3><p class="muted">Jede Gruppe gestaltet ihr eigenes Dashboard mit Diagrammen, Anzeigen und Farben – und kann es öffentlich teilen.</p></div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-title"><h2>Was EasyWetter kann</h2></div>
        <div class="grid grid-3">
            <div class="feature"><div class="ficon"><?= icon('palette') ?></div><h3>Eigenes Dashboard</h3><p>Werte, Zeiger-Anzeigen, Verläufe, Min/Max und Notizen – frei anordnen, Farben und Themes wählen.</p></div>
            <div class="feature"><div class="ficon"><?= icon('activity') ?></div><h3>Beliebige Sensoren</h3><p>Temperatur, Luftfeuchte, Druck, CO₂, Licht, Wind, Bodenfeuchte … jeder Messwert mit eigenem Namen.</p></div>
            <div class="feature"><div class="ficon"><?= icon('school') ?></div><h3>Für Schulen gemacht</h3><p>Lehrkräfte legen Gruppen an und behalten in der Klassenübersicht alle Stationen im Blick.</p></div>
            <div class="feature"><div class="ficon"><?= icon('shield-check') ?></div><h3>Sicher &amp; datensparsam</h3><p>Keine Klarnamen von Schülern nötig, verschlüsselte Passwörter, jede Station mit eigenem Token.</p></div>
            <div class="feature"><div class="ficon"><?= icon('download') ?></div><h3>Daten exportieren</h3><p>Messreihen als CSV für Excel oder Python herunterladen und auswerten.</p></div>
            <div class="feature"><div class="ficon"><?= icon('share-2') ?></div><h3>Teilen &amp; einbetten</h3><p>Dashboards per Link teilen oder auf der Schulhomepage einbinden – live aktualisiert.</p></div>
        </div>
    </div>
</section>

<?php if ($showcase): ?>
<section class="section">
    <div class="container">
        <div class="section-title"><h2>Gerade live</h2><p>Öffentliche Dashboards von Schülergruppen.</p></div>
        <div class="grid grid-3">
            <?php foreach ($showcase as $s): ?>
                <a class="card" href="<?= e(url('/d/' . $s['public_slug'])) ?>" style="color:inherit">
                    <div class="row row-between"><strong><?= e($s['display_name']) ?></strong><span class="dot dot-on"></span></div>
                    <div class="muted small"><?= e($s['school_name'] ?? '') ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section <?= $showcase ? 'section-alt' : '' ?>">
    <div class="container roles">
        <div class="section-title"><h2>Wer macht was?</h2></div>
        <div class="grid grid-3">
            <div class="card"><h3><?= icon('graduation-cap') ?> Lehrkräfte</h3><ul><li>Schule registrieren oder vom Schuladmin angelegt werden</li><li>Schülergruppen anlegen &amp; Zugangsdaten ausdrucken</li><li>Klassenübersicht mit allen Stationen</li></ul></div>
            <div class="card"><h3><?= icon('users') ?> Schülergruppen</h3><ul><li>Mit Gruppen-Login anmelden</li><li>Stationen anlegen und Token ins Programm kopieren</li><li>Dashboard gestalten und teilen</li></ul></div>
            <div class="card"><h3><?= icon('cpu') ?> Stationen</h3><ul><li>ESP32 / ESP8266 mit Arduino-Bibliothek</li><li>Pico W / ESP32 mit MicroPython</li><li>Alles, was HTTP + JSON kann</li></ul></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cta">
            <h2>Bereit für die erste Messung?</h2>
            <p>Anleitung lesen, Bibliothek herunterladen und loslegen.</p>
            <div class="row" style="justify-content:center"><a class="btn btn-lg" href="<?= e(url('/anleitung')) ?>"><?= icon('book-open') ?> Zur Anleitung</a></div>
        </div>
    </div>
</section>
