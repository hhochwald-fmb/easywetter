<?php $me = $user; $back = '/lehrer/kollegium'; ?>
<div class="page-head"><div><h1>Kollegium</h1><p><?= e($user['school_name'] ?? '') ?> – als Schuladmin verwaltest du die Lehrkräfte deiner Schule.</p></div></div>
<?php require __DIR__ . '/../partials/credentials.php'; ?>
<div class="card card-flush" style="margin-bottom:20px">
    <div class="card-head"><h2>Lehrkräfte</h2></div>
    <?php require __DIR__ . '/../partials/teacher_table.php'; ?>
</div>
<div class="card">
    <h2>Kollegin / Kollegen hinzufügen</h2>
    <?php $schoolId = null; require __DIR__ . '/../partials/teacher_form.php'; ?>
</div>
