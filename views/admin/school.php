<?php $me = Auth::user(); $back = '/admin/schulen/' . (int) $school['id']; ?>
<div class="page-head">
    <div><div class="small"><a href="<?= e(url('/admin')) ?>">← Alle Schulen</a></div><h1><?= e($school['name']) ?></h1><p><?= e($school['city']) ?> · angelegt am <?= e(fmt_dt($school['created_at'], 'd.m.Y')) ?></p></div>
    <details class="pop">
        <summary class="btn"><?= icon('pencil') ?> Bearbeiten</summary>
        <div class="pop-body">
            <form method="post" action="<?= e(url($back . '/bearbeiten')) ?>">
                <?= csrf_field() ?>
                <div class="field"><label>Name</label><input name="name" type="text" required value="<?= e($school['name']) ?>"></div>
                <div class="field"><label>Ort</label><input name="city" type="text" value="<?= e($school['city']) ?>"></div>
                <button class="btn btn-primary btn-sm">Speichern</button>
            </form>
            <hr>
            <form method="post" action="<?= e(url($back . '/loeschen')) ?>">
                <?= csrf_field() ?>
                <div class="field"><label>Zum Löschen Schulnamen eintippen</label><input name="confirm_name" type="text" autocomplete="off"><span class="hint">Löscht alle Lehrkräfte, Gruppen, Stationen und Messwerte!</span></div>
                <button class="btn btn-danger-solid btn-sm">Schule löschen</button>
            </form>
        </div>
    </details>
</div>

<?php require __DIR__ . '/../partials/credentials.php'; ?>

<div class="card card-flush" style="margin-bottom:20px">
    <div class="card-head"><h2>Lehrkräfte</h2></div>
    <?php $teachers = $teachers; require __DIR__ . '/../partials/teacher_table.php'; ?>
</div>
<div class="card">
    <h2>Lehrkraft hinzufügen</h2>
    <?php $schoolId = (int) $school['id']; require __DIR__ . '/../partials/teacher_form.php'; ?>
</div>
