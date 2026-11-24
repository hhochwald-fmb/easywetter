<?php
/**
 * EasyWetter – Update per Kommandozeile (für Git-Installationen)
 *
 *   php bin/update.php            Code per "git pull" holen und Datenbank aktualisieren
 *   php bin/update.php --db       nur die Datenbank aktualisieren (z. B. nach FTP-Upload)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur über die Kommandozeile.\n");
}

$root = dirname(__DIR__);
$dbOnly = in_array('--db', $argv, true);

if (!$dbOnly) {
    if (!is_dir($root . '/.git')) {
        fwrite(STDERR, "Kein Git-Checkout gefunden. Updates bitte im Admin-Bereich (System & Updates) einspielen\noder mit --db nur die Datenbank aktualisieren.\n");
        exit(1);
    }
    echo "→ Hole neueste Version (git pull) …\n";
    passthru('cd ' . escapeshellarg($root) . ' && git pull --ff-only', $code);
    if ($code !== 0) {
        fwrite(STDERR, "git pull ist fehlgeschlagen (lokale Änderungen?). Abbruch.\n");
        exit(1);
    }
    // Neuen Code in einem frischen Prozess für die Migrationen laden
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --db', $code);
    exit($code);
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/app/bootstrap.php';
if ($GLOBALS['ew_config'] === null) {
    fwrite(STDERR, "Noch nicht eingerichtet (config/config.php fehlt). Bitte zuerst die Seite im Browser aufrufen.\n");
    exit(1);
}
echo "→ Prüfe Datenbank …\n";
$done = Migrator::run();
set_setting('app_version', EW_VERSION);
echo $done ? '✓ Angewendet: ' . implode(', ', $done) . "\n" : "✓ Datenbank ist aktuell.\n";
echo '✓ EasyWetter ' . EW_VERSION . " ist bereit.\n";
