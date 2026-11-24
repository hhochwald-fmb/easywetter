<?php /** @var ?int $schoolId  @var string $back */ ?>
<form method="post" action="<?= e(url('/lehrkraefte/neu')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <?php if (!empty($schoolId)): ?><input type="hidden" name="school_id" value="<?= (int) $schoolId ?>"><?php endif; ?>
    <div class="form-grid">
        <div class="field"><label>Name</label><input name="display_name" type="text" required placeholder="z. B. Herr Schmidt"></div>
        <div class="field"><label>Benutzername</label><input name="username" type="text" required autocapitalize="none" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,59}" placeholder="z. B. schmidt"></div>
        <div class="field"><label>E-Mail (optional)</label><input name="email" type="email"></div>
    </div>
    <label class="check" style="margin-bottom:14px"><input type="checkbox" name="is_school_admin" value="1"> Schuladmin (darf weitere Lehrkräfte dieser Schule verwalten)</label>
    <button class="btn btn-primary"><?= icon('plus') ?> Lehrkraft anlegen</button>
    <span class="small muted">Ein vorläufiges Passwort wird erzeugt und einmalig angezeigt.</span>
</form>
