<?php
$me = Auth::user();
$path = current_path();
$nav = [];
if ($me) {
    if ($me['role'] === 'admin') {
        $pending = (int) Db::value('SELECT COUNT(*) FROM registrations');
        $nav[] = ['/admin', 'Übersicht', 'layout-dashboard', $pending];
        $nav[] = ['/admin/system', 'System & Updates', 'settings', 0];
    } elseif ($me['role'] === 'teacher') {
        $nav[] = ['/lehrer', 'Meine Gruppen', 'users', 0];
        $nav[] = ['/lehrer/uebersicht', 'Klassenübersicht', 'school', 0];
        if ((int) $me['is_school_admin'] === 1) {
            $nav[] = ['/lehrer/kollegium', 'Kollegium', 'graduation-cap', 0];
        }
    } else {
        $nav[] = ['/gruppen/' . $me['id'], 'Dashboard', 'layout-dashboard', 0];
        $nav[] = ['/gruppen/' . $me['id'] . '/gestalten', 'Gestalten', 'palette', 0];
        $nav[] = ['/gruppen/' . $me['id'] . '/stationen', 'Stationen', 'radio-tower', 0];
    }
    $nav[] = ['/anleitung', 'Anleitung', 'book-open', 0];
}
$roleLabel = ['admin' => 'Oberadmin', 'teacher' => 'Lehrkraft', 'group' => 'Gruppe'];
require __DIR__ . '/partials/head.php';
?>
<body>
<header class="topbar">
    <div class="container">
        <a class="brand" href="<?= e(url($me ? Auth::home($me) : '/')) ?>"><span class="brand-logo"><?= icon('cloud-sun') ?></span> EasyWetter</a>
        <nav class="nav">
            <?php foreach ($nav as [$href, $label, $ic, $count]):
                $isActive = $path === $href || ($href !== '/admin' && $href !== '/lehrer' && str_starts_with($path, $href . '/')) || ($href === '/gruppen/' . ($me['id'] ?? 0) && $path === $href); ?>
                <a href="<?= e(url($href)) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= icon($ic) ?> <?= e($label) ?><?php if ($count): ?> <span class="badge badge-danger"><?= (int) $count ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($me): ?>
        <div class="userbox">
            <div class="who"><strong><?= e($me['display_name']) ?></strong><?= e($roleLabel[$me['role']] ?? '') ?><?= (int) $me['is_school_admin'] === 1 ? ' · Schuladmin' : '' ?><?= !empty($me['school_name']) ? ' · ' . e($me['school_name']) : '' ?></div>
            <a class="btn btn-ghost btn-icon" href="<?= e(url('/konto')) ?>" title="Mein Konto"><?= icon('user') ?></a>
            <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-icon" title="Abmelden"><?= icon('log-out') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</header>
<main class="page">
    <div class="container">
        <?php require __DIR__ . '/partials/flash.php'; ?>
        <?= $content ?>
    </div>
</main>
<footer class="footer no-print">
    <div class="container"><div>EasyWetter <?= e(EW_VERSION) ?></div><div class="row"><a href="<?= e(url('/impressum')) ?>">Impressum</a><a href="<?= e(url('/datenschutz')) ?>">Datenschutz</a></div></div>
</footer>
<?php require __DIR__ . '/partials/scripts.php'; ?>
</body>
</html>
