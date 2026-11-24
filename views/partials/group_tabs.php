<?php
/** @var array $group  @var array $user  @var string $active */
$gid = (int) $group['id'];
$isGroup = $user['role'] === 'group';
?>
<div class="page-head">
    <div>
        <?php if (!$isGroup): ?>
            <div class="small"><a href="<?= e(url($user['role'] === 'admin' ? '/admin' : '/lehrer')) ?>">← Zurück</a></div>
        <?php endif; ?>
        <h1><?= e($group['display_name']) ?></h1>
        <p><?= $isGroup ? 'Eure Gruppe' : 'Gruppe' ?> · Lehrkraft: <?= e($group['teacher_name'] ?? '') ?> · Login: <span class="mono"><?= e($group['username']) ?></span></p>
    </div>
</div>
<nav class="tabs">
    <a href="<?= e(url("/gruppen/$gid")) ?>" class="<?= $active === 'dashboard' ? 'active' : '' ?>"><?= icon('layout-dashboard') ?> Dashboard</a>
    <a href="<?= e(url("/gruppen/$gid/gestalten")) ?>" class="<?= $active === 'designer' ? 'active' : '' ?>"><?= icon('palette') ?> Gestalten</a>
    <a href="<?= e(url("/gruppen/$gid/stationen")) ?>" class="<?= $active === 'stations' ? 'active' : '' ?>"><?= icon('radio-tower') ?> Stationen</a>
</nav>
