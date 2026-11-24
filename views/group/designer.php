<?php
$active = 'designer';
require __DIR__ . '/../partials/group_tabs.php';
$json = json_encode($dash['config'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$scripts = ['vendor/chart.min.js', 'js/forecast.js', 'js/dashboard.js', 'js/designer.js'];
?>
<div class="designer" id="designer" data-url="<?= e($dataUrl) ?>" data-save-url="<?= e($saveUrl) ?>" data-dashboard-url="<?= e(url('/gruppen/' . (int) $group['id'])) ?>">
    <aside class="designer-side card">
        <noscript><div class="alert alert-warn">Für den Designer wird JavaScript benötigt.</div></noscript>
        <div id="designer-panel"><p class="muted">Lade …</p></div>
    </aside>
    <section>
        <div class="dash" id="dash" data-theme="<?= e($dash['config']['theme']) ?>" data-preview="1"></div>
    </section>
</div>
<script type="application/json" id="dash-config"><?= $json ?></script>
