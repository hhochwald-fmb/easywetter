<div class="page-head"><div><h1>System &amp; Updates</h1><p>Installierte Version: <strong><?= e(EW_VERSION) ?></strong></p></div></div>

<div class="grid grid-2">
    <div class="card">
        <h2><?= icon('download') ?> Update einspielen</h2>
        <?php if ($isGit): ?>
            <div class="alert alert-info"><?= icon('info') ?><div>Diese Installation ist ein Git-Checkout. Am einfachsten aktualisierst du per Kommandozeile:<br><code>php bin/update.php</code></div></div>
        <?php endif; ?>

        <?php if ($repo): ?>
            <p>Online-Updates aus <code><?= e($repo) ?></code></p>
            <form method="post" action="<?= e(url('/admin/system/update-pruefen')) ?>" class="row">
                <?= csrf_field() ?><button class="btn"><?= icon('refresh-cw') ?> Nach Updates suchen</button>
            </form>
            <?php if ($latest): ?>
                <div class="alert <?= $latest['newer'] ? 'alert-success' : 'alert-info' ?>" style="margin-top:14px"><?= icon($latest['newer'] ? 'sparkles' : 'circle-check') ?>
                    <div>
                        <?php if ($latest['newer']): ?>
                            <strong>Version <?= e($latest['version']) ?> ist verfügbar.</strong>
                            <?php if ($latest['notes']): ?><pre class="small" style="white-space:pre-wrap;margin:8px 0"><?= e($latest['notes']) ?></pre><?php endif; ?>
                            <?php if ($latest['zip_url']): ?>
                            <form method="post" action="<?= e(url('/admin/system/update-installieren')) ?>" data-confirm="Update auf <?= e($latest['version']) ?> jetzt installieren? Vorher wird automatisch ein Backup der Programmdateien angelegt.">
                                <?= csrf_field() ?><button class="btn btn-primary btn-sm">Jetzt installieren</button>
                            </form>
                            <?php else: ?><div class="small">Das Release enthält kein Installationspaket – bitte ZIP manuell hochladen.</div><?php endif; ?>
                        <?php else: ?>
                            Du hast die neueste Version (<?= e($latest['version']) ?>).
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <hr>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/admin/system/update-hochladen')) ?>" enctype="multipart/form-data" data-confirm="Update-Paket jetzt installieren? Vorher wird automatisch ein Backup der Programmdateien angelegt.">
            <?= csrf_field() ?>
            <div class="field"><label for="package">Update-Paket hochladen (easywetter-x.y.z.zip)</label><input id="package" type="file" name="package" accept=".zip" required>
                <span class="hint">Konfiguration, Datenbank und Messwerte bleiben erhalten. Max. Upload-Größe des Servers: <?= e(ini_get('upload_max_filesize')) ?></span></div>
            <button class="btn btn-primary" <?= class_exists('ZipArchive') ? '' : 'disabled' ?>><?= icon('upload') ?> Hochladen &amp; installieren</button>
            <?php if (!class_exists('ZipArchive')): ?><p class="small" style="color:var(--danger)">Die PHP-Erweiterung „zip“ fehlt – Updates bitte per FTP einspielen.</p><?php endif; ?>
        </form>
        <?php if ($backups): ?>
            <hr><h3>Backups</h3>
            <ul class="small muted"><?php foreach (array_slice($backups, 0, 5) as $b): ?><li><?= e($b['name']) ?> (<?= round($b['size'] / 1024) ?> KB)</li><?php endforeach; ?></ul>
            <p class="small muted">Liegen im Ordner <code>storage/backups/</code>.</p>
        <?php endif; ?>
    </div>

    <div class="stack">
        <form class="card" method="post" action="<?= e(url('/admin/system/einstellungen')) ?>">
            <?= csrf_field() ?>
            <h2><?= icon('settings') ?> Einstellungen</h2>
            <label class="check" style="margin-bottom:14px"><input type="checkbox" name="registration_open" value="1" <?= $registrationOpen ? 'checked' : '' ?>> Schulen dürfen sich selbst registrieren (mit Freigabe durch dich)</label>
            <h3 style="margin-top:6px"><?= icon('mail') ?> E-Mail-Benachrichtigungen</h3>
            <?php if ($mailProblem): ?>
                <div class="alert alert-warn"><?= icon('triangle-alert') ?><div><strong>E-Mails sind aus:</strong> <?= e($mailProblem) ?> Neue Registrierungen siehst du dann nur hier im Admin (rote Zahl bei „Übersicht“).</div></div>
            <?php else: ?>
                <div class="alert alert-success"><?= icon('circle-check') ?><div>E-Mails sind aktiv: Du bekommst eine Nachricht bei jeder neuen Schul-Registrierung, freigegebene Lehrkräfte eine Bestätigung.</div></div>
            <?php endif; ?>
            <div class="field"><label>Deine E-Mail (Empfänger)</label><input type="email" name="admin_email" value="<?= e($adminEmail) ?>" placeholder="du@beispiel.de">
                <span class="hint">Hierhin werden neue Registrierungen gemeldet.</span></div>
            <div class="field"><label>Absenderadresse</label><input type="email" name="mail_from" value="<?= e($mailFrom) ?>" placeholder="noreply@<?= e($serverHost) ?>">
                <span class="hint">Muss zu einer Domain deines Hosting-Pakets gehören (z. B. noreply@<?= e($serverHost) ?>), sonst landen Mails im Spam oder werden abgelehnt. Ein eigenes Postfach ist dafür meist nicht nötig.</span></div>
            <div class="field"><label>GitHub-Repository für Online-Updates</label><input type="text" name="update_repo" value="<?= e((string) setting('update_repo', '')) ?>" placeholder="<?= e(Updater::DEFAULT_REPO) ?>" <?= $repoFromConfig ? 'disabled' : '' ?>>
                <span class="hint"><?= $repoFromConfig ? 'Wird in config/config.php festgelegt.' : 'Leer lassen = offizielles Repository (' . e(Updater::DEFAULT_REPO) . '). Nur ändern, wenn du eine eigene Variante pflegst.' ?></span></div>
            <button class="btn btn-primary">Speichern</button>
        </form>
        <?php if (!$mailProblem): ?>
        <form method="post" action="<?= e(url('/admin/system/testmail')) ?>" class="card card-tight row row-between">
            <?= csrf_field() ?>
            <span class="small">Funktioniert der Versand? Schick dir eine Test-E-Mail an <strong><?= e($adminEmail) ?></strong>.</span>
            <button class="btn btn-sm"><?= icon('mail', 'icon icon-sm') ?> Test-E-Mail senden</button>
        </form>
        <?php endif; ?>

        <div class="card">
            <h2><?= icon('cpu') ?> Status</h2>
            <table class="table">
                <?php foreach ($checks as [$label, $ok, $required]): ?>
                    <tr><td class="small"><?= e($label) ?></td><td class="right"><?= $ok ? '<span class="badge badge-success">OK</span>' : ($required ? '<span class="badge badge-danger">Fehlt</span>' : '<span class="badge badge-warn">–</span>') ?></td></tr>
                <?php endforeach; ?>
                <tr><td class="small">Messwerte gesamt</td><td class="right"><?= number_format($readings, 0, ',', '.') ?></td></tr>
                <tr><td class="small">Aufbewahrung</td><td class="right"><?= (int) $retention ?> Tage</td></tr>
                <tr><td class="small">Datenbank-Migrationen</td><td class="right"><?= count($applied) ?> angewendet<?= $pending ? ', <strong>' . count($pending) . ' ausstehend</strong>' : '' ?></td></tr>
            </table>
            <?php if ($pending): ?>
            <form method="post" action="<?= e(url('/admin/system/migrieren')) ?>" style="margin-top:12px"><?= csrf_field() ?><button class="btn btn-primary btn-sm">Datenbank jetzt aktualisieren</button></form>
            <?php endif; ?>
        </div>
    </div>
</div>

<form class="card" method="post" action="<?= e(url('/admin/system/rechtliches')) ?>" style="margin-top:18px">
    <?= csrf_field() ?>
    <h2><?= icon('shield-check') ?> Impressum &amp; Datenschutz</h2>
    <p class="small muted">Wird unter <a href="<?= e(url('/impressum')) ?>">/impressum</a> und <a href="<?= e(url('/datenschutz')) ?>">/datenschutz</a> angezeigt. Formatierung: Leerzeile = neuer Absatz, „## “ am Zeilenanfang = Überschrift. Die Datenschutz-Vorlage bitte prüfen und die Stellen in [eckigen Klammern] ergänzen.</p>
    <div class="grid grid-2">
        <div class="field"><label for="imprint">Impressum</label><textarea id="imprint" name="imprint" rows="14" placeholder="## Angaben gemäß § 5 DDG&#10;Name&#10;Straße&#10;PLZ Ort&#10;&#10;## Kontakt&#10;E-Mail: …"><?= e($imprint) ?></textarea></div>
        <div class="field"><label for="privacy">Datenschutzerklärung</label><textarea id="privacy" name="privacy" rows="14"><?= e($privacy) ?></textarea></div>
    </div>
    <button class="btn btn-primary">Speichern</button>
</form>
