<?php
$me = Auth::user();
$heroPage = $heroPage ?? false;
require __DIR__ . '/partials/head.php';
?>
<body>
<header class="public-top <?= $heroPage ? '' : 'solid' ?>">
    <div class="container">
        <a class="brand" href="<?= e(url('/')) ?>"><span class="brand-logo"><?= icon('cloud-sun') ?></span> EasyWetter</a>
        <nav>
            <a class="link" href="<?= e(url('/anleitung')) ?>">Anleitung</a>
            <?php if ($me): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url(Auth::home($me))) ?>"><?= icon('layout-dashboard', 'icon icon-sm') ?> Zum Bereich</a>
            <?php else: ?>
                <?php if (registration_open()): ?><a class="link" href="<?= e(url('/registrieren')) ?>">Schule registrieren</a><?php endif; ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('/login')) ?>"><?= icon('lock', 'icon icon-sm') ?> Anmelden</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php if (!$heroPage): ?><div class="container" style="padding-top:20px"><?php require __DIR__ . '/partials/flash.php'; ?></div><?php endif; ?>
<?= $content ?>
<footer class="footer">
    <div class="container">
        <div>EasyWetter <?= e(EW_VERSION) ?> · Wetterstationen im Unterricht</div>
        <div class="row"><a href="<?= e(url('/anleitung')) ?>">Anleitung</a><a href="<?= e(url('/selbst-installieren')) ?>">Selbst installieren</a><a href="<?= e(url('/impressum')) ?>">Impressum</a><a href="<?= e(url('/datenschutz')) ?>">Datenschutz</a></div>
    </div>
</footer>
<?php require __DIR__ . '/partials/scripts.php'; ?>
</body>
</html>
