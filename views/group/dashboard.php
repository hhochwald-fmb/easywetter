<?php
$cfg = $dash['config'];
$json = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$gid = (int) $group['id'];
?>
<?php if ($public): ?>
    <div class="dash dash-page" id="dash" data-theme="<?= e($cfg['theme']) ?>" data-url="<?= e($dataUrl) ?>" data-public="1" data-home-url="<?= e(url('/')) ?>"></div>
    <script type="application/json" id="dash-config"><?= $json ?></script>
    <?php $scripts = ['vendor/chart.min.js', 'js/dashboard.js']; ?>
<?php else: ?>
    <?php $active = 'dashboard'; require __DIR__ . '/../partials/group_tabs.php'; ?>
    <?php require __DIR__ . '/../partials/credentials.php'; ?>

    <?php if ($stationCount === 0): ?>
        <div class="alert alert-info"><?= icon('info') ?><div><strong>Los geht's!</strong> Legt zuerst unter <a href="<?= e(url("/gruppen/$gid/stationen")) ?>">Stationen</a> eure erste Station an. Dort bekommt ihr auch das fertige Programm für euren Mikrocontroller.</div></div>
    <?php endif; ?>

    <div class="row row-between" style="margin-bottom:14px">
        <div class="row">
            <a class="btn btn-primary" href="<?= e(url("/gruppen/$gid/gestalten")) ?>"><?= icon('palette') ?> Dashboard gestalten</a>
            <?php if ($dash['is_public'] && $dash['public_slug']): ?>
                <a class="btn" href="<?= e(url('/d/' . $dash['public_slug'])) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?> Vollbild / öffentlich</a>
            <?php endif; ?>
        </div>
        <details class="pop">
            <summary class="btn"><?= icon('share-2') ?> Teilen <?= $dash['is_public'] ? '<span class="badge badge-success">öffentlich</span>' : '' ?></summary>
            <div class="pop-body" style="width:360px">
                <?php if ($dash['is_public'] && $dash['public_slug']): $link = absolute_url('d/' . $dash['public_slug']); ?>
                    <p class="small">Jeder mit diesem Link kann euer Dashboard ansehen (nicht bearbeiten):</p>
                    <div class="input-group" style="margin-bottom:10px"><input type="text" readonly value="<?= e($link) ?>" id="share-link"><button type="button" class="btn btn-sm" data-copy-target="#share-link"><?= icon('copy', 'icon icon-sm') ?></button></div>
                    <p class="small">Auf einer Webseite einbetten:</p>
                    <div class="input-group" style="margin-bottom:12px"><input type="text" readonly id="embed-code" value="<?= e('<iframe src="' . $link . '" width="100%" height="700" style="border:0"></iframe>') ?>"><button type="button" class="btn btn-sm" data-copy-target="#embed-code"><?= icon('copy', 'icon icon-sm') ?></button></div>
                    <form method="post" action="<?= e(url("/gruppen/$gid/freigabe")) ?>"><?= csrf_field() ?><input type="hidden" name="enable" value="0"><button class="btn btn-sm btn-danger">Nicht mehr öffentlich</button></form>
                <?php else: ?>
                    <p class="small">Macht euer Dashboard öffentlich, um es per Link zu teilen oder auf der Schulhomepage einzubinden. Es ist dann nur ansehbar, nicht veränderbar.</p>
                    <form method="post" action="<?= e(url("/gruppen/$gid/freigabe")) ?>"><?= csrf_field() ?><input type="hidden" name="enable" value="1"><button class="btn btn-primary btn-sm"><?= icon('globe', 'icon icon-sm') ?> Öffentlich machen</button></form>
                <?php endif; ?>
            </div>
        </details>
    </div>

    <div class="dash" id="dash" data-theme="<?= e($cfg['theme']) ?>" data-url="<?= e($dataUrl) ?>" data-stations-url="<?= e(url("/gruppen/$gid/stationen")) ?>" data-designer-url="<?= e(url("/gruppen/$gid/gestalten")) ?>"></div>
    <script type="application/json" id="dash-config"><?= $json ?></script>
    <?php $scripts = ['vendor/chart.min.js', 'js/dashboard.js']; ?>
<?php endif; ?>
