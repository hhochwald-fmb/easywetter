<main class="page"><div class="container container-narrow stack">
    <div class="page-head"><div><h1>EasyWetter selbst installieren</h1><p>Für Schulen und Lehrkräfte, die EasyWetter auf dem eigenen Webspace oder Schulserver betreiben möchten.</p></div></div>

    <div class="card">
        <div class="row row-between">
            <div>
                <h2 class="mb-0"><?= icon('download') ?> EasyWetter <?= e(EW_VERSION) ?></h2>
                <p class="muted small mb-0">Komplettes Programm inkl. Arduino- und MicroPython-Bibliothek, Beispielen und README. Enthält keine Zugangsdaten, Konfiguration oder Messdaten dieser Installation.</p>
            </div>
            <?php if ($zipAvailable): ?>
                <a class="btn btn-primary btn-lg" href="<?= e(url('download/easywetter.zip')) ?>"><?= icon('download') ?> Herunterladen (ZIP)</a>
            <?php else: ?>
                <span class="badge badge-warn">Download auf diesem Server nicht verfügbar</span>
            <?php endif; ?>
        </div>
        <?php if ($repo): ?>
            <hr>
            <p class="small mb-0">Quellcode &amp; Versionen auf GitHub: <a href="https://github.com/<?= e($repo) ?>" target="_blank" rel="noopener">github.com/<?= e($repo) ?></a></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Voraussetzungen</h2>
        <ul class="small">
            <li>Webspace mit <strong>PHP 8.1</strong> oder neuer (Erweiterungen <code>pdo_mysql</code>, <code>mbstring</code>; empfohlen <code>zip</code> und <code>curl</code>)</li>
            <li>Eine <strong>MySQL- (ab 5.7) oder MariaDB-Datenbank</strong> (ab 10.3) – darf leer sein</li>
            <li>Apache mit <code>.htaccess</code>-Unterstützung (bei fast allen Webhostern Standard)</li>
            <li>Am besten HTTPS (Let's-Encrypt-Zertifikat beim Hoster aktivieren)</li>
        </ul>
        <p class="small muted mb-0">Keine Kommandozeile, kein Composer, kein Build-Schritt nötig.</p>
    </div>

    <div class="card">
        <h2>Installation</h2>
        <div data-tabs>
            <div class="tabs" role="tablist">
                <button type="button" class="active" data-tab="inst-web"><?= icon('globe') ?> Webhosting (FTP)</button>
                <button type="button" data-tab="inst-git"><?= icon('git-branch') ?> Mit Git</button>
                <button type="button" data-tab="inst-docker"><?= icon('box') ?> Docker</button>
            </div>
            <div id="inst-web">
                <ol class="small">
                    <li>ZIP herunterladen und entpacken.</li>
                    <li>Den Inhalt des Ordners <code>easywetter</code> per FTP auf den Webspace hochladen (z. B. in den Ordner der Subdomain <code>wetter.meine-schule.de</code>).<br>
                        <span class="muted">Tipp: Wenn dein Hoster es erlaubt, das Zielverzeichnis der Domain auf den Unterordner <code>public/</code> setzen. Sonst funktioniert es auch so – die mitgelieferte <code>.htaccess</code> schützt alle internen Ordner.</span></li>
                    <li>Beim Hoster eine Datenbank anlegen und Zugangsdaten notieren.</li>
                    <li>Die Adresse im Browser öffnen: Der <strong>Einrichtungsassistent</strong> prüft den Server, fragt die Datenbank-Daten ab und legt dein Admin-Konto an.</li>
                    <li>Im Admin unter <em>System &amp; Updates</em> Impressum und Datenschutzerklärung ausfüllen.</li>
                </ol>
                <p class="small muted mb-0">Falls der Assistent die Konfiguration nicht speichern darf, zeigt er dir den Inhalt für <code>config/config.php</code> zum Hochladen an.</p>
            </div>
            <div id="inst-git" hidden>
                <p class="small">Für eigene Server mit SSH-Zugang – Updates gehen dann mit einem Befehl.</p>
                <div class="code-box"><pre class="code" id="git-code"><?= e(($repo ? "git clone https://github.com/$repo.git easywetter" : "git clone <adresse-des-repositorys> easywetter") . "\ncd easywetter\n# Webserver auf den Ordner easywetter/public zeigen lassen,\n# dann die Seite im Browser öffnen und dem Assistenten folgen.\n\n# Später aktualisieren:\nphp bin/update.php") ?></pre><button type="button" class="btn btn-sm" data-copy-target="#git-code">Kopieren</button></div>
            </div>
            <div id="inst-docker" hidden>
                <p class="small">Im Paket liegt eine <code>docker-compose.yml</code> mit Webserver, Datenbank und phpMyAdmin – ideal zum Ausprobieren auf dem eigenen Rechner:</p>
                <div class="code-box"><pre class="code" id="docker-code">cd easywetter
docker compose up -d --build
# Seite:      http://localhost:8090
# phpMyAdmin: http://localhost:8091</pre><button type="button" class="btn btn-sm" data-copy-target="#docker-code">Kopieren</button></div>
                <p class="small muted mb-0" style="margin-top:10px">Für den Dauerbetrieb im Netz vorher in <code>docker-compose.yml</code> die Datenbank-Passwörter ändern, <code>EW_DEBUG</code> auf <code>"0"</code> setzen, phpMyAdmin entfernen und einen Reverse-Proxy mit HTTPS davorschalten.</p>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Updates</h2>
        <table class="table">
            <tr><td><strong>Webhosting</strong></td><td class="small">Neue Version herunterladen → im Admin unter <em>System &amp; Updates</em> die ZIP-Datei hochladen. Vorher wird automatisch ein Backup angelegt; Einstellungen und Messdaten bleiben erhalten.</td></tr>
            <tr><td><strong>Online</strong></td><td class="small">Ist ein GitHub-Repository eingetragen, prüft EasyWetter per Knopfdruck auf neue Versionen und installiert sie selbst.</td></tr>
            <tr><td><strong>Git</strong></td><td class="small"><code>php bin/update.php</code></td></tr>
        </table>
        <p class="small muted mb-0" style="margin-top:10px">Datenbank-Änderungen neuer Versionen werden immer automatisch übernommen. Alle Details stehen in der <code>README.md</code> im Paket.</p>
    </div>

    <div class="card">
        <h2><?= icon('shield-check') ?> Datenschutz</h2>
        <p class="small mb-0">EasyWetter lädt keine externen Skripte, Schriften oder Dienste – alles kommt vom eigenen Server. Es gibt nur ein technisch notwendiges Sitzungs-Cookie, Passwörter werden verschlüsselt gespeichert und Schülerinnen und Schüler brauchen keine Klarnamen. Eine Vorlage für die Datenschutzerklärung ist enthalten.</p>
    </div>
</div></main>
