<?php /** @var array|null $credentials */ if (!empty($credentials)): ?>
<div class="secret" id="credentials">
    <h3><?= icon('key-round') ?> <?= e($credentials['title']) ?></h3>
    <p class="muted small mb-0"><?= e($credentials['hint'] ?? '') ?></p>
    <dl>
        <dt>Adresse</dt><dd><?= e(absolute_url('login')) ?></dd>
        <dt>Benutzername</dt><dd id="cred-user"><?= e($credentials['username']) ?></dd>
        <dt>Passwort</dt><dd id="cred-pass"><?= e($credentials['password']) ?></dd>
    </dl>
    <div class="row no-print">
        <button type="button" class="btn btn-sm" data-copy="<?= e("Adresse: " . absolute_url('login') . "\nBenutzername: " . $credentials['username'] . "\nPasswort: " . $credentials['password']) ?>"><?= icon('copy', 'icon icon-sm') ?> Kopieren</button>
        <button type="button" class="btn btn-sm" data-print><?= icon('printer', 'icon icon-sm') ?> Drucken</button>
    </div>
</div>
<?php endif; ?>
