<div class="card empty" style="max-width:560px;margin:40px auto">
    <?= icon($code === 404 ? 'map-pin' : 'triangle-alert') ?>
    <h1><?= (int) $code ?></h1>
    <p><?= e($message) ?></p>
    <a class="btn" href="<?= e(url('/')) ?>">Zur Startseite</a>
</div>
