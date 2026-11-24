<?php
$icons = ['success' => 'circle-check', 'error' => 'triangle-alert', 'info' => 'info', 'warn' => 'triangle-alert'];
foreach (take_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>" role="status"><?= icon($icons[$f['type']] ?? 'info') ?><div><?= e($f['message']) ?></div></div>
<?php endforeach; ?>
