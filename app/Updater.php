<?php
declare(strict_types=1);

/**
 * Update-Mechanismus für Installationen ohne Git (normales Webhosting):
 *  - Release-ZIP hochladen ODER automatisch vom GitHub-Release laden
 *  - vorher wird ein Backup der Programmdateien in storage/backups angelegt
 *  - config/ und storage/ werden nie überschrieben
 *  - Datenbank-Migrationen laufen beim nächsten Seitenaufruf automatisch
 */
final class Updater
{
    /** Bereiche, die ein Update ersetzen darf. */
    private const ALLOWED = ['app/', 'views/', 'public/', 'migrations/', 'libraries/', 'bin/', 'docker/',
        'VERSION', 'README.md', 'LICENSE', 'CHANGELOG.md', '.htaccess', 'docker-compose.yml', 'config/config.example.php',
        'config/.htaccess', 'storage/.htaccess'];
    private const MAX_ZIP = 40 * 1024 * 1024;
    /** Offizielles Repository – wird genutzt, solange nichts anderes eingestellt ist. */
    public const DEFAULT_REPO = 'hhochwald-fmb/easywetter';
    /** Kerndateien, die ein Paket enthalten muss (Datei => Text, der darin vorkommen muss). */
    private const REQUIRED = [
        'app/bootstrap.php' => 'EW_ROOT',
        'app/routes.php' => '$routes',
        'app/Updater.php' => 'class Updater',
        'public/index.php' => 'bootstrap.php',
        'views/layout.php' => 'Auth::user',
        'migrations/001_init.sql' => 'CREATE TABLE',
    ];

    public static function zipAvailable(): bool
    {
        return class_exists('ZipArchive');
    }

    public static function isGitCheckout(): bool
    {
        return is_dir(EW_ROOT . '/.git');
    }

    public static function repo(): string
    {
        $r = (string) config('update_repo', '') ?: (string) setting('update_repo', '') ?: self::DEFAULT_REPO;
        return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $r) ? $r : '';
    }

    private static function httpGet(string $url, ?string $saveTo = null): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, ['api.github.com', 'github.com', 'codeload.github.com', 'objects.githubusercontent.com'], true)) {
            throw new RuntimeException('Unerlaubte Update-Adresse.');
        }
        $ua = 'EasyWetter-Updater/' . EW_VERSION;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $fh = $saveTo ? fopen($saveTo, 'wb') : null;
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_USERAGENT => $ua,
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json, application/octet-stream'],
            ]);
            if ($fh) {
                curl_setopt($ch, CURLOPT_FILE, $fh);
            } else {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }
            $res = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($fh) {
                fclose($fh);
            }
            if ($res === false || $code >= 400) {
                throw new RuntimeException('Download fehlgeschlagen (' . ($err ?: 'HTTP ' . $code) . ').');
            }
            return $fh ? '' : (string) $res;
        }
        if (!ini_get('allow_url_fopen')) {
            throw new RuntimeException('Der Server kann keine Updates herunterladen (weder cURL noch allow_url_fopen verfügbar). Bitte ZIP manuell hochladen.');
        }
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: $ua\r\nAccept: application/vnd.github+json\r\n", 'timeout' => 120, 'follow_location' => 1]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            throw new RuntimeException('Download fehlgeschlagen.');
        }
        if ($saveTo) {
            file_put_contents($saveTo, $data);
            return '';
        }
        return $data;
    }

    /** @return array{version:string, notes:string, zip_url:?string, newer:bool} */
    public static function checkLatest(): array
    {
        $repo = self::repo();
        if ($repo === '') {
            throw new RuntimeException('Kein Update-Repository eingestellt.');
        }
        $json = json_decode(self::httpGet("https://api.github.com/repos/$repo/releases/latest"), true);
        if (!is_array($json) || empty($json['tag_name'])) {
            throw new RuntimeException('Keine Release-Informationen gefunden.');
        }
        $version = ltrim((string) $json['tag_name'], 'vV');
        $zip = null;
        foreach ($json['assets'] ?? [] as $a) {
            if (preg_match('/^easywetter-.*\.zip$/i', (string) ($a['name'] ?? ''))) {
                $zip = (string) $a['browser_download_url'];
                break;
            }
        }
        return [
            'version' => $version,
            'notes' => mb_substr((string) ($json['body'] ?? ''), 0, 4000),
            'zip_url' => $zip,
            'newer' => version_compare($version, EW_VERSION, '>'),
        ];
    }

    public static function downloadLatest(): string
    {
        $info = self::checkLatest();
        if (!$info['zip_url']) {
            throw new RuntimeException('Das Release enthält kein easywetter-*.zip.');
        }
        $target = self::tmpDir() . '/update-' . bin2hex(random_bytes(4)) . '.zip';
        self::httpGet($info['zip_url'], $target);
        return $target;
    }

    private static function tmpDir(): string
    {
        $d = EW_ROOT . '/storage/tmp';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        if (!is_writable($d)) {
            throw new RuntimeException('Der Ordner storage/tmp ist nicht beschreibbar.');
        }
        return $d;
    }

    private static function allowed(string $rel): bool
    {
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, '\\') || str_starts_with($rel, '/') || str_contains($rel, "\0")) {
            return false;
        }
        if ($rel === 'config/config.php' || str_starts_with($rel, 'storage/') && $rel !== 'storage/.htaccess') {
            return false;
        }
        foreach (self::ALLOWED as $a) {
            if ($rel === $a || (str_ends_with($a, '/') && str_starts_with($rel, $a))) {
                return true;
            }
        }
        return false;
    }

    /** Prüft und installiert ein Release-ZIP. Gibt die neue Version zurück. */
    public static function applyZip(string $zipPath): string
    {
        if (!self::zipAvailable()) {
            throw new RuntimeException('Die PHP-Erweiterung "zip" fehlt auf dem Server.');
        }
        if (!is_file($zipPath) || filesize($zipPath) > self::MAX_ZIP) {
            throw new RuntimeException('Update-Datei fehlt oder ist zu groß.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Die Datei ist kein gültiges ZIP-Archiv.');
        }
        try {
            // Wurzel des Pakets finden (Ordner, der VERSION und app/bootstrap.php enthält)
            $prefix = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if (preg_match('#^(.*/)?VERSION$#', $n, $m)) {
                    $p = $m[1] ?? '';
                    if ($zip->locateName($p . 'app/bootstrap.php') !== false && ($prefix === null || strlen($p) < strlen($prefix))) {
                        $prefix = $p;
                    }
                }
            }
            if ($prefix === null) {
                throw new RuntimeException('Das ZIP ist kein EasyWetter-Paket (VERSION/app fehlen).');
            }
            $newVersion = trim((string) $zip->getFromName($prefix . 'VERSION'));
            if (!preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $newVersion)) {
                throw new RuntimeException('Ungültige Versionsangabe im Paket.');
            }
            if (version_compare($newVersion, EW_VERSION, '<')) {
                throw new RuntimeException("Das Paket ($newVersion) ist älter als die installierte Version (" . EW_VERSION . ').');
            }

            // Liste der zu schreibenden Dateien
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = (string) $zip->getNameIndex($i);
                if (!str_starts_with($n, $prefix) || str_ends_with($n, '/')) {
                    continue;
                }
                $rel = substr($n, strlen($prefix));
                if (self::allowed($rel)) {
                    $entries[$i] = $rel;
                }
            }
            if (!$entries) {
                throw new RuntimeException('Im Paket wurden keine Programmdateien gefunden.');
            }
            // Vollständigkeit prüfen, damit ein kaputtes/teilweises Paket die Seite nicht lahmlegt
            foreach (self::REQUIRED as $req => $marker) {
                $content = $zip->getFromName($prefix . $req);
                if ($content === false || !str_contains($content, $marker)) {
                    throw new RuntimeException("Das Paket ist unvollständig oder beschädigt ($req fehlt) – nichts wurde verändert.");
                }
            }
            if (count($entries) < 40) {
                throw new RuntimeException('Das Paket enthält zu wenige Dateien – ist es wirklich ein vollständiges EasyWetter-Release?');
            }

            // Schreibrechte prüfen, bevor irgendetwas verändert wird
            foreach ($entries as $rel) {
                $target = EW_ROOT . '/' . $rel;
                $dir = dirname($target);
                while (!is_dir($dir)) {
                    $dir = dirname($dir);
                }
                if ((file_exists($target) && !is_writable($target)) || !is_writable($dir)) {
                    throw new RuntimeException("Keine Schreibrechte für $rel – Update abgebrochen, nichts wurde verändert.");
                }
            }

            self::backup(array_values($entries));

            foreach ($entries as $i => $rel) {
                $target = EW_ROOT . '/' . $rel;
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0775, true);
                }
                $data = $zip->getFromIndex($i);
                if ($data === false || file_put_contents($target, $data, LOCK_EX) === false) {
                    throw new RuntimeException("Fehler beim Schreiben von $rel. Ein Backup liegt in storage/backups.");
                }
            }
        } finally {
            $zip->close();
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        return $newVersion;
    }

    private static function backup(array $relPaths): void
    {
        $dir = EW_ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/backup-' . EW_VERSION . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Backup konnte nicht angelegt werden – Update abgebrochen.');
        }
        foreach ($relPaths as $rel) {
            if (is_file(EW_ROOT . '/' . $rel)) {
                $zip->addFile(EW_ROOT . '/' . $rel, $rel);
            }
        }
        $zip->close();
    }

    public static function backups(): array
    {
        $files = glob(EW_ROOT . '/storage/backups/backup-*.zip') ?: [];
        rsort($files);
        return array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)], $files);
    }
}
