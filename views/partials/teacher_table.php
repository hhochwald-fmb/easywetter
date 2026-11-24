<?php
/** @var array $teachers  @var array $me  @var string $back */
?>
<div class="table-wrap"><table class="table">
    <tr><th>Name</th><th>Benutzername</th><th>Gruppen</th><th>Stationen</th><th>Zuletzt da</th><th></th></tr>
    <?php if (!$teachers): ?><tr><td colspan="6" class="muted">Noch keine Lehrkräfte.</td></tr><?php endif; ?>
    <?php foreach ($teachers as $t): $self = (int) $t['id'] === (int) $me['id']; ?>
    <tr>
        <td>
            <strong><?= e($t['display_name']) ?></strong>
            <?php if ((int) $t['is_school_admin'] === 1): ?> <span class="badge badge-primary">Schuladmin</span><?php endif; ?>
            <?php if ((int) $t['is_active'] !== 1): ?> <span class="badge badge-danger">deaktiviert</span><?php endif; ?>
            <?php if ((int) $t['must_change_password'] === 1): ?> <span class="badge badge-warn" title="Hat sich noch nicht mit eigenem Passwort angemeldet">vorläufiges PW</span><?php endif; ?>
            <?php if (!empty($t['email'])): ?><div class="small muted"><?= e($t['email']) ?></div><?php endif; ?>
        </td>
        <td class="mono small"><?= e($t['username']) ?></td>
        <td><?= (int) $t['groups_count'] ?></td>
        <td><?= (int) $t['stations'] ?></td>
        <td class="small muted nowrap"><?= e(time_ago($t['last_login_at'])) ?></td>
        <td>
            <?php if (!$self): ?>
            <div class="actions">
                <form method="post" action="<?= e(url('/lehrkraefte/' . $t['id'] . '/passwort')) ?>" data-confirm="Neues vorläufiges Passwort für <?= e($t['display_name']) ?> erzeugen? Das alte gilt dann nicht mehr.">
                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="btn btn-sm" title="Neues Passwort"><?= icon('key-round', 'icon icon-sm') ?></button>
                </form>
                <form method="post" action="<?= e(url('/lehrkraefte/' . $t['id'] . '/schuladmin')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="btn btn-sm" title="<?= (int) $t['is_school_admin'] === 1 ? 'Schuladmin-Rechte entziehen' : 'Zum Schuladmin machen' ?>"><?= icon((int) $t['is_school_admin'] === 1 ? 'shield-check' : 'shield', 'icon icon-sm') ?></button>
                </form>
                <form method="post" action="<?= e(url('/lehrkraefte/' . $t['id'] . '/status')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="btn btn-sm"><?= (int) $t['is_active'] === 1 ? 'Deaktivieren' : 'Aktivieren' ?></button>
                </form>
                <form method="post" action="<?= e(url('/lehrkraefte/' . $t['id'] . '/loeschen')) ?>" data-confirm="<?= e($t['display_name']) ?> wirklich löschen? Alle Gruppen, Stationen und Messwerte dieser Lehrkraft werden ebenfalls gelöscht!">
                    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
                    <button class="btn btn-sm btn-danger" title="Löschen"><?= icon('trash-2', 'icon icon-sm') ?></button>
                </form>
            </div>
            <?php else: ?><span class="small muted">Du</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table></div>
