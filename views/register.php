<div class="auth-wrap">
    <div class="auth-card wide">
        <div class="card">
            <h1>Schule registrieren</h1>
            <p class="muted">Du bist Lehrkraft und möchtest EasyWetter mit deinen Klassen nutzen? Nach dem Absenden prüfen wir deine Anfrage und schalten den Zugang frei. Danach kannst du dich anmelden, Schülergruppen anlegen und Kolleginnen und Kollegen deiner Schule hinzufügen.</p>
            <?php if ($errors): ?>
                <div class="alert alert-error"><?= icon('triangle-alert') ?><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/registrieren')) ?>">
                <?= csrf_field() ?>
                <div class="honeypot" aria-hidden="true"><label>Webseite <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <h3>Schule</h3>
                <div class="form-grid">
                    <div class="field"><label for="school_name">Name der Schule</label><input id="school_name" name="school_name" type="text" required maxlength="150" value="<?= e($old['school_name'] ?? '') ?>"></div>
                    <div class="field"><label for="school_city">Ort</label><input id="school_city" name="school_city" type="text" required maxlength="100" value="<?= e($old['school_city'] ?? '') ?>"></div>
                </div>
                <h3>Deine Daten</h3>
                <div class="form-grid">
                    <div class="field"><label for="display_name">Name (wie er angezeigt wird)</label><input id="display_name" name="display_name" type="text" required maxlength="120" placeholder="z. B. Frau Müller" value="<?= e($old['display_name'] ?? '') ?>"></div>
                    <div class="field"><label for="email">Dienstliche E-Mail</label><input id="email" name="email" type="email" required value="<?= e($old['email'] ?? '') ?>"></div>
                </div>
                <div class="form-grid">
                    <div class="field"><label for="username">Wunsch-Benutzername</label><input id="username" name="username" type="text" required autocapitalize="none" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,59}" value="<?= e($old['username'] ?? '') ?>"><span class="hint">Buchstaben, Ziffern, . _ -</span></div>
                    <div class="field"><label for="password">Passwort (mind. 8 Zeichen)</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
                    <div class="field"><label for="password2">Passwort wiederholen</label><input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"></div>
                </div>
                <div class="field"><label for="message">Nachricht (optional)</label><textarea id="message" name="message" maxlength="1000" placeholder="Wie möchtest du EasyWetter einsetzen? (Fach, Klassenstufe, AG …)"><?= e($old['message'] ?? '') ?></textarea></div>
                <label class="check" style="margin-bottom:18px"><input type="checkbox" name="confirm_teacher" value="1" required> Ich bin Lehrkraft an der angegebenen Schule und lege nur Konten für schulische Zwecke an.</label>
                <button class="btn btn-primary btn-lg">Registrierung absenden</button>
            </form>
        </div>
    </div>
</div>
