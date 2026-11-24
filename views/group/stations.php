<?php
$gid = (int) $group['id'];
$active = 'stations';
require __DIR__ . '/../partials/group_tabs.php';
?>

<?php if ($newToken): ?>
<div class="secret">
    <h3><?= icon('key-round') ?> Token für „<?= e($newToken['station']) ?>“</h3>
    <p class="small">Dieser Token wird <strong>nur jetzt</strong> angezeigt. Kopiert ihn in euer Programm – oder nehmt gleich das fertige Programm unten. Falls er verloren geht, könnt ihr jederzeit einen neuen erzeugen.</p>
    <div class="input-group" style="margin-bottom:16px"><input type="text" readonly value="<?= e($newToken['token']) ?>" id="new-token" class="mono"><button type="button" class="btn" data-copy-target="#new-token"><?= icon('copy') ?> Kopieren</button></div>
    <?php $token = $newToken['token']; require __DIR__ . '/../partials/code_snippets.php'; ?>
</div>
<?php endif; ?>

<div class="card card-flush" style="margin-bottom:20px">
    <div class="card-head"><h2>Eure Stationen</h2></div>
    <?php if (!$stations): ?>
        <div class="empty"><?= icon('radio-tower') ?><div>Noch keine Station. Legt unten eure erste an!</div></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <tr><th>Station</th><th>Letzte Werte</th><th>Zuletzt gesendet</th><th>Messwerte</th><th></th></tr>
        <?php foreach ($stations as $s):
            $ts = utc_to_ts($s['last_seen_at']);
            $online = $ts && time() - $ts < 900; ?>
        <tr>
            <td><strong><?= e($s['name']) ?></strong><div class="small muted mono">Token: ew_…<?= e($s['token_hint']) ?></div></td>
            <td class="small">
                <?php $l = $latest[(int) $s['id']] ?? []; if (!$l): ?><span class="muted">–</span><?php endif; ?>
                <?php foreach ($l as $k => $v): ?><span class="badge" style="margin:1px"><span class="mono"><?= e($k) ?></span>&nbsp;<?= e(rtrim(rtrim(number_format($v['v'], 2, ',', ''), '0'), ',')) ?></span><?php endforeach; ?>
            </td>
            <td class="small nowrap"><span class="dot <?= $online ? 'dot-on' : ($ts ? 'dot-off' : '') ?>"></span> <?= e(time_ago($s['last_seen_at'])) ?></td>
            <td><?= number_format((int) $s['readings'], 0, ',', '.') ?></td>
            <td>
                <div class="actions">
                    <a class="btn btn-sm" href="<?= e(url('/stationen/' . $s['id'] . '/export.csv')) ?>" title="Als CSV herunterladen"><?= icon('download', 'icon icon-sm') ?> CSV</a>
                    <form method="post" action="<?= e(url('/stationen/' . $s['id'] . '/token')) ?>" data-confirm="Neuen Token erzeugen? Der alte funktioniert dann nicht mehr und ihr müsst den neuen ins Programm eintragen.">
                        <?= csrf_field() ?><button class="btn btn-sm" title="Neuer Token"><?= icon('key-round', 'icon icon-sm') ?></button>
                    </form>
                    <details class="pop">
                        <summary class="btn btn-sm" title="Mehr"><?= icon('pencil', 'icon icon-sm') ?></summary>
                        <div class="pop-body">
                            <form method="post" action="<?= e(url('/stationen/' . $s['id'] . '/umbenennen')) ?>">
                                <?= csrf_field() ?>
                                <div class="field"><label>Name</label><input name="name" type="text" required maxlength="80" value="<?= e($s['name']) ?>"></div>
                                <button class="btn btn-primary btn-sm">Speichern</button>
                            </form>
                            <hr>
                            <div class="row">
                                <form method="post" action="<?= e(url('/stationen/' . $s['id'] . '/leeren')) ?>" data-confirm="Alle Messwerte von „<?= e($s['name']) ?>“ löschen? Die Station bleibt erhalten.">
                                    <?= csrf_field() ?><button class="btn btn-sm btn-danger">Messwerte löschen</button>
                                </form>
                                <form method="post" action="<?= e(url('/stationen/' . $s['id'] . '/loeschen')) ?>" data-confirm="Station „<?= e($s['name']) ?>“ mit allen Messwerten löschen?">
                                    <?= csrf_field() ?><button class="btn btn-sm btn-danger-solid">Station löschen</button>
                                </form>
                            </div>
                        </div>
                    </details>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2><?= icon('plus') ?> Neue Station</h2>
        <form method="post" action="<?= e(url("/gruppen/$gid/stationen/neu")) ?>">
            <?= csrf_field() ?>
            <div class="field"><label for="name">Name der Station</label><input id="name" name="name" type="text" required maxlength="80" placeholder="z. B. Schulhof, Dach, Klassenzimmer"></div>
            <button class="btn btn-primary">Station anlegen</button>
        </form>
    </div>
    <div class="card">
        <h2><?= icon('book-open') ?> So geht's</h2>
        <ol class="small" style="padding-left:18px;margin:0">
            <li>Station anlegen → Token wird angezeigt</li>
            <li>Bibliothek installieren (<a href="<?= e(url('/anleitung')) ?>">Anleitung</a>)</li>
            <li>Fertiges Programm kopieren, WLAN eintragen, hochladen</li>
            <li>Nach einer Minute erscheinen die Werte hier und im Dashboard</li>
        </ol>
        <p class="small" style="margin-top:10px">Die Messwert-Namen (z. B. <code>"temp"</code> in <code>add("temp", …)</code>) legt ihr <strong>nur im Programm</strong> fest – im Dashboard muss nichts angelegt werden. Neue Namen tauchen nach dem ersten Senden automatisch auf. <a href="<?= e(url('/anleitung')) ?>">Mehr dazu</a></p>
        <p class="small muted" style="margin-top:10px">Server-Adresse: <code><?= e($uploadUrl) ?></code></p>
    </div>
</div>
