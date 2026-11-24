<?php require __DIR__ . '/partials/head.php'; ?>
<body>
<header class="public-top solid">
    <div class="container"><span class="brand"><span class="brand-logo"><?= icon('cloud-sun') ?></span> EasyWetter</span></div>
</header>
<main class="page"><div class="container container-narrow">
    <?php require __DIR__ . '/partials/flash.php'; ?>
    <?= $content ?>
</div></main>
<?php require __DIR__ . '/partials/scripts.php'; ?>
</body>
</html>
