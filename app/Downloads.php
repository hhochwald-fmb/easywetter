<?php
declare(strict_types=1);

/**
 * Erzeugt die Download-Pakete direkt aus der installierten Version:
 *  - das komplette Projekt (zum Selbst-Installieren und als Update-Paket nutzbar)
 *  - die Arduino-Bibliothek als ZIP (Arduino IDE: "ZIP-Bibliothek hinzufügen")
 *  - die MicroPython-Bibliothek mit Beispielen
 * Die ZIPs werden in storage/cache zwischengespeichert und automatisch neu gebaut,
 * sobald sich Dateien ändern. Konfiguration, Zugangsdaten und Messdaten sind NIE enthalten.
 */
final class Downloads
{
    /** Ordner/Dateien, die nie in das Projekt-Paket gehören. */
    private const EXCLUDE = ['.git', '.github', 'dist', 'node_modules', 'config/config.php', '.DS_Store', '.idea', '.vscode', '__pycache__'];

    public static function available(): bool
    {
        return class_exists('ZipArchive');
    }

    public static function projectFilename(): string
    {
        return 'easywetter-' . EW_VERSION . '.zip';
    }

    /** Liste [relativer Pfad => absoluter Pfad] aller Dateien eines Pakets. */
    private static function files(string $package): array
    {
        return match ($package) {
            'project' => self::projectFiles(),
            'arduino' => self::dirFiles('libraries/arduino/EasyWetter', 'EasyWetter'),
            'micropython' => self::dirFiles('libraries/micropython', 'EasyWetter-MicroPython'),
            default => throw new InvalidArgumentException('Unbekanntes Paket'),
        };
    }

    private static function excluded(string $rel): bool
    {
        foreach (self::EXCLUDE as $ex) {
            if ($rel === $ex || str_starts_with($rel, $ex . '/') || basename($rel) === $ex || str_contains($rel, '/' . $ex . '/')) {
                return true;
            }
        }
        if (str_ends_with($rel, '.pyc')) {
            return true;
        }
        // storage: nur die Schutzdatei und leere Ordner-Platzhalter mitnehmen
        if (str_starts_with($rel, 'storage/')) {
            return !in_array($rel, ['storage/.htaccess', 'storage/logs/.gitkeep', 'storage/tmp/.gitkeep'], true);
        }
        return false;
    }

    private static function projectFiles(): array
    {
        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(EW_ROOT, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(EW_ROOT) + 1));
            if (!self::excluded($rel)) {
                $out['easywetter/' . $rel] = $f->getPathname();
            }
        }
        ksort($out);
        return $out;
    }

    private static function dirFiles(string $dir, string $prefix): array
    {
        $out = [];
        $base = EW_ROOT . '/' . $dir;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $name = $f->getFilename();
            if (!$f->isFile() || $name === '.htaccess' || $name === '.DS_Store' || str_ends_with($name, '.pyc')) {
                continue;
            }
            $out[$prefix . '/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1))] = $f->getPathname();
        }
        ksort($out);
        return $out;
    }

    /** Baut (falls nötig) das Paket und gibt den Pfad zur ZIP-Datei zurück. */
    public static function build(string $package): string
    {
        if (!self::available()) {
            throw new RuntimeException('Die PHP-Erweiterung "zip" fehlt auf diesem Server.');
        }
        $files = self::files($package);
        $sig = EW_VERSION;
        foreach ($files as $rel => $abs) {
            $sig .= '|' . $rel . ':' . filemtime($abs) . ':' . filesize($abs);
        }
        $dir = EW_ROOT . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $target = $dir . '/' . $package . '-' . substr(hash('sha256', $sig), 0, 16) . '.zip';
        if (is_file($target)) {
            return $target;
        }
        foreach (glob($dir . '/' . $package . '-*.zip') ?: [] as $old) {
            @unlink($old);
        }
        $tmp = $target . '.' . bin2hex(random_bytes(3)) . '.tmp';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Download-Paket konnte nicht erstellt werden (storage/cache beschreibbar?).');
        }
        foreach ($files as $rel => $abs) {
            $zip->addFile($abs, $rel);
        }
        $zip->close();
        rename($tmp, $target);
        return $target;
    }

    public static function send(string $package, string $filename): never
    {
        try {
            $path = self::build($package);
        } catch (Throwable $e) {
            error_log('[EasyWetter] Download: ' . $e->getMessage());
            abort(503, 'Der Download ist auf diesem Server gerade nicht verfügbar.');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');
        readfile($path);
        exit;
    }

    /** Einzelne Bibliotheks-/Beispieldatei ausliefern (nur aus der festen Liste). */
    public static function sendFile(string $name): never
    {
        $map = [
            'easywetter.py' => 'libraries/micropython/easywetter.py',
            'main.py' => 'libraries/micropython/main.py',
            'main_dht22.py' => 'libraries/micropython/main_dht22.py',
        ];
        if (!isset($map[$name]) || !is_file(EW_ROOT . '/' . $map[$name])) {
            abort(404);
        }
        header('Content-Type: text/x-python; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        readfile(EW_ROOT . '/' . $map[$name]);
        exit;
    }
}
