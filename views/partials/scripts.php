<script src="<?= e(asset('vendor/lucide.min.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $s): ?>
<script src="<?= e(asset($s)) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
