<?php
$presets = ['temp' => ['Temperatur', '°C'], 'hum' => ['Luftfeuchte', '%'], 'press' => ['Luftdruck', 'hPa'], 'co2' => ['CO₂', 'ppm'], 'light' => ['Helligkeit', 'lx'], 'wind' => ['Wind', 'km/h'], 'rain' => ['Regen', 'mm'], 'soil' => ['Bodenfeuchte', '%'], 'uv' => ['UV-Index', ''], 'pm25' => ['Feinstaub', 'µg/m³'], 'noise' => ['Lautstärke', 'dB']];
$fmt = fn(float $v) => number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.');
?>
<?php if ($public): ?><div class="dash dash-page" data-theme="hell" data-autoreload="60"><?php endif; ?>
<div class="page-head">
    <div>
        <h1><?= $public ? 'Unsere Wetterstationen' : 'Klassenübersicht' ?></h1>
        <p><?= e($teacher['display_name']) ?><?= !empty($teacher['school_name']) ? ' · ' . e($teacher['school_name']) : '' ?> · aktualisiert sich jede Minute</p>
    </div>
    <?php if (!$public): ?>
    <div class="row">
        <?php if ($teacher['public_slug']): ?>
            <a class="btn" href="<?= e(url('/klasse/' . $teacher['public_slug'])) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?> Öffentliche Ansicht</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/lehrer/klassenlink')) ?>">
            <?= csrf_field() ?><input type="hidden" name="enable" value="<?= $teacher['public_slug'] ? '0' : '1' ?>">
            <button class="btn"><?= icon('share-2') ?> <?= $teacher['public_slug'] ? 'Nicht mehr öffentlich' : 'Öffentlich teilen (z. B. für Beamer)' ?></button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php if (!$public): ?><div data-autoreload="60"></div><?php endif; ?>

<?php if (!$groups): ?>
    <div class="card empty"><?= icon('users') ?><div>Noch keine Gruppen.</div></div>
<?php endif; ?>
<div class="grid grid-3">
<?php foreach ($groups as $g): ?>
    <div class="w" style="--accent: <?= e($g['config']['accent']) ?>">
        <div class="row row-between" style="margin-bottom:8px">
            <?php if ($public): ?><strong><?= e($g['display_name']) ?></strong><?php else: ?><a href="<?= e(url('/gruppen/' . $g['id'])) ?>"><strong><?= e($g['display_name']) ?></strong></a><?php endif; ?>
        </div>
        <?php if (!$g['stations']): ?><div class="w-empty">Noch keine Station.</div><?php endif; ?>
        <?php foreach ($g['stations'] as $s): $online = $s['last_seen'] && time() - $s['last_seen'] < 900; ?>
            <div style="margin-bottom:10px">
                <div class="small" style="display:flex;gap:6px;align-items:center;color:var(--d-muted)"><span class="dot <?= $online ? 'dot-on' : ($s['last_seen'] ? 'dot-off' : '') ?>"></span> <?= e($s['name']) ?> · <?= $s['last_seen'] ? e(time_ago(gmdate('Y-m-d H:i:s', $s['last_seen']))) : 'noch keine Daten' ?></div>
                <div class="row" style="gap:6px 14px;margin-top:4px">
                    <?php foreach ((array) $s['latest'] as $k => $v): [$lbl, $unit] = $presets[$k] ?? [$k, '']; ?>
                        <div><span class="small muted"><?= e($lbl) ?></span> <strong><?= e($fmt($v['v'])) ?></strong> <span class="small muted"><?= e($unit) ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
</div>
<?php if ($public): ?><div class="dash-footer">Erstellt mit <a href="<?= e(url('/')) ?>">EasyWetter</a></div></div><?php endif; ?>
