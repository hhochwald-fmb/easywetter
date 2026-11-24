<div class="page-head"><div><h1>EasyWetter einrichten</h1><p>Schritt <?= (int) $step ?> von 2 – <?= $step === 1 ? 'Datenbank verbinden' : 'Oberadmin anlegen' ?></p></div></div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= icon('triangle-alert') ?><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($step === 1): ?>
<div class="card" style="margin-bottom:18px">
    <h3>Systemprüfung</h3>
    <table class="table">
        <?php foreach ($checks as [$label, $ok, $required]): ?>
            <tr><td><?= e($label) ?></td><td class="right"><?= $ok ? '<span class="badge badge-success">OK</span>' : ($required ? '<span class="badge badge-danger">Fehlt</span>' : '<span class="badge badge-warn">Optional</span>') ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>

<?php if ($manualConfig): ?>
<div class="card">
    <h3>Fast geschafft: Konfigurationsdatei anlegen</h3>
    <p>Die Verbindung zur Datenbank klappt, aber der Ordner <code>config/</code> ist nicht beschreibbar. Lege per FTP die Datei <code>config/config.php</code> mit folgendem Inhalt an und lade dann diese Seite neu:</p>
    <div class="code-box"><pre class="code" id="cfg"><?= e($manualConfig) ?></pre><button class="btn btn-sm" type="button" data-copy-target="#cfg">Kopieren</button></div>
    <p style="margin-top:14px"><a class="btn btn-primary" href="<?= e(url('/setup')) ?>">Datei ist angelegt – weiter</a></p>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/setup')) ?>" class="card">
    <?= csrf_field() ?>
    <h3>Datenbank (MySQL / MariaDB)</h3>
    <p class="muted small">Die Zugangsdaten bekommst du im Kundenbereich deines Webhosters. Die Datenbank muss bereits existieren (sie darf leer sein).</p>
    <div class="form-grid">
        <div class="field"><label for="db_host">Server</label><input id="db_host" name="db_host" type="text" value="<?= e($old['db_host'] ?? 'localhost') ?>" required></div>
        <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" type="number" value="<?= e($old['db_port'] ?? '3306') ?>"></div>
    </div>
    <div class="field"><label for="db_name">Datenbankname</label><input id="db_name" name="db_name" type="text" value="<?= e($old['db_name'] ?? '') ?>" required></div>
    <div class="form-grid">
        <div class="field"><label for="db_user">Benutzer</label><input id="db_user" name="db_user" type="text" value="<?= e($old['db_user'] ?? '') ?>" required autocomplete="off"></div>
        <div class="field"><label for="db_pass">Passwort</label><input id="db_pass" name="db_pass" type="password" autocomplete="new-password"></div>
    </div>
    <h3 style="margin-top:10px">Optional</h3>
    <div class="field"><label for="base_url">Adresse der Seite</label><input id="base_url" name="base_url" type="url" placeholder="https://wetter.meine-schule.de" value="<?= e($old['base_url'] ?? '') ?>"><span class="hint">Nur nötig, wenn Links falsch erzeugt werden. Leer lassen = automatisch.</span></div>
    <div class="field"><label for="admin_email">E-Mail für Benachrichtigungen</label><input id="admin_email" name="admin_email" type="email" value="<?= e($old['admin_email'] ?? '') ?>"><span class="hint">Hierhin werden neue Schul-Registrierungen gemeldet.</span></div>
    <button class="btn btn-primary btn-lg">Verbindung prüfen &amp; speichern</button>
</form>
<?php endif; ?>

<?php else: ?>
<form method="post" action="<?= e(url('/setup')) ?>" class="card">
    <?= csrf_field() ?>
    <div class="alert alert-success"><?= icon('circle-check') ?><div>Die Datenbank ist verbunden und eingerichtet.</div></div>
    <h3>Oberadmin-Konto anlegen</h3>
    <p class="muted small">Mit diesem Konto gibst du Schulen frei, verwaltest Lehrkräfte und spielst Updates ein.</p>
    <div class="form-grid">
        <div class="field"><label for="username">Benutzername</label><input id="username" name="username" type="text" value="<?= e($old['username'] ?? 'admin') ?>" required autocomplete="username"></div>
        <div class="field"><label for="display_name">Dein Name</label><input id="display_name" name="display_name" type="text" value="<?= e($old['display_name'] ?? '') ?>" required></div>
    </div>
    <div class="field"><label for="email">E-Mail (optional)</label><input id="email" name="email" type="email" value="<?= e($old['email'] ?? '') ?>"></div>
    <div class="form-grid">
        <div class="field"><label for="password">Passwort (mind. 8 Zeichen)</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label for="password2">Passwort wiederholen</label><input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div>
    </div>
    <button class="btn btn-primary btn-lg">Einrichtung abschließen</button>
</form>
<?php endif; ?>
