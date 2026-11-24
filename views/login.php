<div class="auth-wrap">
    <div class="auth-card">
        <div class="card">
            <h1>Anmelden</h1>
            <p class="muted">Für Lehrkräfte und Schülergruppen.</p>
            <form method="post" action="<?= e(url('/login')) ?>">
                <?= csrf_field() ?>
                <div class="field"><label for="username">Benutzername</label><input id="username" name="username" type="text" required autocomplete="username" autocapitalize="none" value="<?= e((string) unstash('login_username')) ?>" autofocus></div>
                <div class="field"><label for="password">Passwort</label>
                    <div class="input-group"><input id="password" name="password" type="password" required autocomplete="current-password"><button type="button" class="btn btn-icon" data-toggle-password="#password" title="Passwort anzeigen"><?= icon('eye') ?></button></div>
                </div>
                <button class="btn btn-primary btn-lg" style="width:100%">Anmelden</button>
            </form>
            <hr>
            <p class="small muted mb-0">Schülergruppen bekommen ihre Zugangsdaten von der Lehrkraft. Passwort vergessen? Deine Lehrkraft bzw. dein Schuladmin kann ein neues erzeugen.</p>
            <?php if ($registrationOpen): ?><p class="small mb-0" style="margin-top:8px">Neue Schule? <a href="<?= e(url('/registrieren')) ?>">Jetzt registrieren</a></p><?php endif; ?>
        </div>
    </div>
</div>
