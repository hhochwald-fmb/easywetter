<?php
/** Erzeugt dist/easywetter-<version>.zip (wird von bin/build-release.sh aufgerufen). */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/app/bootstrap.php';
$src = Downloads::build('project');
@mkdir(EW_ROOT . '/dist', 0775, true);
$dest = EW_ROOT . '/dist/' . Downloads::projectFilename();
copy($src, $dest);
echo "✓ dist/" . Downloads::projectFilename() . ' (' . round(filesize($dest) / 1024) . " KB)\n";
