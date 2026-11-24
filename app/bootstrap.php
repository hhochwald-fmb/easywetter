<?php
declare(strict_types=1);

define('EW_ROOT', dirname(__DIR__));
define('EW_VERSION', trim((string) @file_get_contents(EW_ROOT . '/VERSION')) ?: '0.0.0');
define('EW_MIN_PHP', '8.1.0');

if (version_compare(PHP_VERSION, EW_MIN_PHP, '<')) {
    http_response_code(500);
    exit('EasyWetter benötigt PHP ' . EW_MIN_PHP . ' oder neuer (gefunden: ' . PHP_VERSION . ').');
}

require EW_ROOT . '/app/helpers.php';
require EW_ROOT . '/app/Db.php';
require EW_ROOT . '/app/Auth.php';
require EW_ROOT . '/app/Migrator.php';
require EW_ROOT . '/app/Dashboard.php';
require EW_ROOT . '/app/Updater.php';
require EW_ROOT . '/app/Mailer.php';

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

$GLOBALS['ew_config'] = load_config();

$debug = (bool) config('debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(EW_ROOT . '/storage/logs') && is_writable(EW_ROOT . '/storage/logs')) {
    ini_set('error_log', EW_ROOT . '/storage/logs/php-error.log');
}
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) use ($debug) {
    error_log('[EasyWetter] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (str_starts_with(current_path(), '/api/')) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'error' => 'Interner Serverfehler']);
        return;
    }
    $msg = $debug ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Es ist ein interner Fehler aufgetreten.';
    echo '<!doctype html><meta charset="utf-8"><title>Fehler</title><div style="font-family:sans-serif;max-width:40rem;margin:4rem auto"><h1>Ups.</h1><p>' . e($msg) . '</p></div>';
});

/**
 * Lädt die Konfiguration: config/config.php oder (für Docker) Umgebungsvariablen.
 * Gibt null zurück, wenn noch nichts eingerichtet ist -> Setup-Assistent.
 */
function load_config(): ?array
{
    $file = EW_ROOT . '/config/config.php';
    if (is_file($file)) {
        $cfg = require $file;
        return is_array($cfg) ? $cfg : null;
    }
    if (getenv('EW_DB_HOST')) {
        return [
            'db' => [
                'host' => getenv('EW_DB_HOST'),
                'port' => (int) (getenv('EW_DB_PORT') ?: 3306),
                'name' => getenv('EW_DB_NAME') ?: 'easywetter',
                'user' => getenv('EW_DB_USER') ?: 'easywetter',
                'pass' => getenv('EW_DB_PASS') ?: '',
            ],
            'base_url' => getenv('EW_BASE_URL') ?: '',
            'debug' => getenv('EW_DEBUG') === '1',
            'admin_email' => getenv('EW_ADMIN_EMAIL') ?: '',
            'mail_from' => getenv('EW_MAIL_FROM') ?: '',
            'retention_days' => (int) (getenv('EW_RETENTION_DAYS') ?: 365),
            'ingest_min_interval' => (int) (getenv('EW_INGEST_MIN_INTERVAL') ?: 5),
            'update_repo' => getenv('EW_UPDATE_REPO') ?: '',
            'registration_open' => getenv('EW_REGISTRATION_OPEN') !== '0',
        ];
    }
    return null;
}
