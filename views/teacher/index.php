<div class="page-head">
    <div><h1>Meine Gruppen</h1><p><?= e($user['school_name'] ?? '') ?></p></div>
    <a class="btn" href="<?= e(url('/lehrer/uebersicht')) ?>"><?= icon('school') ?> Klassenübersicht</a>
</div>

<?php require __DIR__ . '/../partials/credentials.php'; ?>

<div class="card" style="margin-bottom:20px">
    <h2><?= icon('plus') ?> Neue Schülergruppe</h2>
    <form method="post" action="<?= e(url('/gruppen/neu')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field"><label for="display_name">Gruppenname</label><input id="display_name" name="display_name" type="text" required maxlength="80" placeholder="z. B. 9b – Team Blitz"><span class="hint">Bitte keine vollständigen Schülernamen verwenden.</span></div>
            <div class="field"><label for="username">Benutzername (optional)</label><input id="username" name="username" type="text" autocapitalize="none" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,59}" placeholder="wird automatisch erzeugt"></div>
        </div>
        <button class="btn btn-primary">Gruppe anlegen</button>
        <span class="small muted">Das Passwort wird automatisch erzeugt und einmalig angezeigt.</span>
    </form>
</div>

<div class="card card-flush">
    <div class="card-head"><h2>Gruppen (<?= count($groups) ?>)</h2></div>
    <?php if (!$groups): ?>
        <div class="empty"><?= icon('users') ?><div>Noch keine Gruppen. Lege oben die erste an.</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <tr><th>Gruppe</th><th>Benutzername</th><th>Stationen</th><th>Letzte Messung</th><th></th></tr>
        <?php foreach ($groups as $g): $online = $g['last_seen'] && time() - (int) utc_to_ts($g['last_seen']) < 900; ?>
        <tr>
            <td><a href="<?= e(url('/gruppen/' . $g['id'])) ?>"><strong><?= e($g['display_name']) ?></strong></a>
                <?php if ((int) $g['is_public'] === 1): ?> <span class="badge badge-primary" title="Dashboard ist öffentlich"><?= icon('globe', 'icon icon-sm') ?> öffentlich</span><?php endif; ?></td>
            <td class="mono small"><?= e($g['username']) ?></td>
            <td><?= (int) $g['stations'] ?></td>
            <td class="small nowrap"><span class="dot <?= $online ? 'dot-on' : ($g['last_seen'] ? 'dot-off' : '') ?>"></span> <?= e(time_ago($g['last_seen'])) ?></td>
            <td>
                <div class="actions">
                    <a class="btn btn-sm" href="<?= e(url('/gruppen/' . $g['id'])) ?>"><?= icon('layout-dashboard', 'icon icon-sm') ?> Öffnen</a>
                    <form method="post" action="<?= e(url('/gruppen/' . $g['id'] . '/passwort')) ?>" data-confirm="Neues Passwort für „<?= e($g['display_name']) ?>“ erzeugen? Das alte gilt dann nicht mehr.">
                        <?= csrf_field() ?><button class="btn btn-sm" title="Neues Passwort"><?= icon('key-round', 'icon icon-sm') ?></button>
                    </form>
                    <details class="pop">
                        <summary class="btn btn-sm" title="Umbenennen"><?= icon('pencil', 'icon icon-sm') ?></summary>
                        <form class="pop-body" method="post" action="<?= e(url('/gruppen/' . $g['id'] . '/umbenennen')) ?>">
                            <?= csrf_field() ?>
                            <div class="field"><label>Gruppenname</label><input name="display_name" type="text" required value="<?= e($g['display_name']) ?>"></div>
                            <button class="btn btn-primary btn-sm">Speichern</button>
                        </form>
                    </details>
                    <form method="post" action="<?= e(url('/gruppen/' . $g['id'] . '/loeschen')) ?>" data-confirm="Gruppe „<?= e($g['display_name']) ?>“ mit allen Stationen und Messwerten wirklich löschen?">
                        <?= csrf_field() ?><button class="btn btn-sm btn-danger" title="Löschen"><?= icon('trash-2', 'icon icon-sm') ?></button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>
