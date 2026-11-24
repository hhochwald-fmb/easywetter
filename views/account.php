<div class="page-head"><div><h1>Mein Konto</h1><p>Angemeldet als <span class="mono"><?= e($user['username']) ?></span></p></div></div>
<div class="grid grid-2">
    <form method="post" action="<?= e(url('/konto/passwort')) ?>" class="card">
        <?= csrf_field() ?>
        <h2>Passwort ändern</h2>
        <?php if ((int) $user['must_change_password'] === 1): ?>
            <div class="alert alert-info"><?= icon('info') ?><div>Du hast ein vorläufiges Passwort bekommen. Bitte vergib jetzt ein eigenes.</div></div>
        <?php endif; ?>
        <div class="field"><label for="current">Aktuelles Passwort</label><input id="current" name="current" type="password" required autocomplete="current-password"></div>
        <div class="field"><label for="new">Neues Passwort (mind. 8 Zeichen)</label><input id="new" name="new" type="password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label for="new2">Neues Passwort wiederholen</label><input id="new2" name="new2" type="password" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-primary">Passwort speichern</button>
    </form>
    <?php if ($user['role'] !== 'group'): ?>
    <form method="post" action="<?= e(url('/konto/profil')) ?>" class="card">
        <?= csrf_field() ?>
        <h2>Profil</h2>
        <div class="field"><label for="display_name">Name</label><input id="display_name" name="display_name" type="text" required value="<?= e($user['display_name']) ?>"></div>
        <div class="field"><label for="email">E-Mail</label><input id="email" name="email" type="email" value="<?= e($user['email'] ?? '') ?>"></div>
        <?php if (!empty($user['school_name'])): ?><p class="muted small">Schule: <?= e($user['school_name']) ?></p><?php endif; ?>
        <button class="btn btn-primary">Profil speichern</button>
    </form>
    <?php endif; ?>
</div>
