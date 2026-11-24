<?php $me = Auth::user(); ?>
<div class="page-head"><div><h1>Verwaltung</h1><p>Schulen, Lehrkräfte und Registrierungen</p></div>
    <span class="badge <?= $registrationOpen ? 'badge-success' : 'badge-warn' ?>"><?= $registrationOpen ? 'Selbstregistrierung offen' : 'Selbstregistrierung geschlossen' ?></span>
</div>

<?php require __DIR__ . '/../partials/credentials.php'; ?>

<?php if (Mailer::problem() && $registrationOpen): ?>
    <div class="alert alert-info"><?= icon('mail') ?><div>Du bekommst noch keine E-Mail bei neuen Registrierungen. <a href="<?= e(url('/admin/system')) ?>">Jetzt unter „System &amp; Updates“ einrichten</a> (Absender + Empfänger).</div></div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="card card-tight stat"><span class="num"><?= count($schools) ?></span><span class="lbl">Schulen</span></div>
    <div class="card card-tight stat"><span class="num"><?= (int) $stats['teachers'] ?></span><span class="lbl">Lehrkräfte</span></div>
    <div class="card card-tight stat"><span class="num"><?= (int) $stats['groups'] ?> / <?= (int) $stats['stations'] ?></span><span class="lbl">Gruppen / Stationen</span></div>
    <div class="card card-tight stat"><span class="num"><?= number_format($stats['readings24'], 0, ',', '.') ?></span><span class="lbl">Messwerte (24 h)</span></div>
</div>

<div class="card card-flush" style="margin-bottom:20px">
    <div class="card-head"><h2><?= icon('mail') ?> Offene Registrierungen <?php if ($registrations): ?><span class="badge badge-danger"><?= count($registrations) ?></span><?php endif; ?></h2></div>
    <?php if (!$registrations): ?>
        <div class="empty"><?= icon('circle-check') ?><div>Keine offenen Anfragen.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <tr><th>Schule</th><th>Lehrkraft</th><th>Nachricht</th><th>Eingang</th><th></th></tr>
        <?php foreach ($registrations as $r): ?>
        <tr>
            <td><strong><?= e($r['school_name']) ?></strong><div class="small muted"><?= e($r['school_city']) ?></div></td>
            <td><?= e($r['display_name']) ?><div class="small"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></div><div class="small mono muted"><?= e($r['username']) ?></div></td>
            <td class="small" style="max-width:280px"><?= nl2br(e($r['message'] ?? '–')) ?></td>
            <td class="small muted nowrap"><?= e(fmt_dt($r['created_at'])) ?></td>
            <td>
                <div class="actions">
                    <details class="pop">
                        <summary class="btn btn-sm btn-primary"><?= icon('check', 'icon icon-sm') ?> Freigeben</summary>
                        <form class="pop-body" method="post" action="<?= e(url('/admin/registrierungen/' . $r['id'] . '/freigeben')) ?>">
                            <?= csrf_field() ?>
                            <div class="field"><label>Schule</label>
                                <select name="school_id">
                                    <option value="0">Neu anlegen: <?= e($r['school_name']) ?></option>
                                    <?php foreach ($schools as $s): ?><option value="<?= (int) $s['id'] ?>" <?= mb_strtolower($s['name']) === mb_strtolower($r['school_name']) ? 'selected' : '' ?>>Bestehende: <?= e($s['name']) ?><?= $s['city'] ? ' (' . e($s['city']) . ')' : '' ?></option><?php endforeach; ?>
                                </select>
                                <span class="hint">Die Person wird Schuladmin dieser Schule.</span>
                            </div>
                            <button class="btn btn-primary btn-sm">Jetzt freigeben</button>
                        </form>
                    </details>
                    <details class="pop">
                        <summary class="btn btn-sm btn-danger"><?= icon('x', 'icon icon-sm') ?> Ablehnen</summary>
                        <form class="pop-body" method="post" action="<?= e(url('/admin/registrierungen/' . $r['id'] . '/ablehnen')) ?>">
                            <?= csrf_field() ?>
                            <div class="field"><label>Begründung (optional)</label><textarea name="reason" rows="3"></textarea></div>
                            <label class="check" style="margin-bottom:10px"><input type="checkbox" name="notify" value="1" checked> Per E-Mail benachrichtigen</label>
                            <button class="btn btn-danger-solid btn-sm">Ablehnen &amp; löschen</button>
                        </form>
                    </details>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="card card-flush">
    <div class="card-head">
        <h2><?= icon('building-2') ?> Schulen</h2>
        <details class="pop">
            <summary class="btn btn-sm btn-primary"><?= icon('plus', 'icon icon-sm') ?> Schule anlegen</summary>
            <form class="pop-body" method="post" action="<?= e(url('/admin/schulen/neu')) ?>">
                <?= csrf_field() ?>
                <div class="field"><label>Name</label><input name="name" type="text" required></div>
                <div class="field"><label>Ort</label><input name="city" type="text"></div>
                <button class="btn btn-primary btn-sm">Anlegen</button>
            </form>
        </details>
    </div>
    <?php if (!$schools): ?>
        <div class="empty"><?= icon('school') ?><div>Noch keine Schulen. Lege eine an oder warte auf Registrierungen.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <tr><th>Schule</th><th>Lehrkräfte</th><th>Gruppen</th><th>Stationen</th><th>Letzte Messung</th><th></th></tr>
        <?php foreach ($schools as $s): ?>
        <tr>
            <td><a href="<?= e(url('/admin/schulen/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a><div class="small muted"><?= e($s['city']) ?></div></td>
            <td><?= (int) $s['teachers'] ?></td>
            <td><?= (int) $s['groups_count'] ?></td>
            <td><?= (int) $s['stations'] ?></td>
            <td class="small muted"><?= e(time_ago($s['last_seen'])) ?></td>
            <td class="right"><a class="btn btn-sm" href="<?= e(url('/admin/schulen/' . $s['id'])) ?>">Verwalten</a></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
