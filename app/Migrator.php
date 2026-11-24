<?php
declare(strict_types=1);

/**
 * Wendet SQL-Dateien aus /migrations der Reihe nach an (001_..., 002_...).
 * Bereits ausgeführte Migrationen werden in schema_migrations gemerkt.
 * Neue Versionen bringen einfach neue Dateien mit – nie alte Dateien ändern!
 */
final class Migrator
{
    public static function files(): array
    {
        $out = [];
        foreach (glob(EW_ROOT . '/migrations/*.sql') ?: [] as $f) {
            $out[basename($f, '.sql')] = $f;
        }
        ksort($out, SORT_NATURAL);
        return $out;
    }

    private static function ensureTable(): void
    {
        Db::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(190) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public static function applied(): array
    {
        self::ensureTable();
        return array_column(Db::all('SELECT version FROM schema_migrations'), 'version');
    }

    public static function pending(): array
    {
        return array_diff_key(self::files(), array_flip(self::applied()));
    }

    /** @return string[] angewendete Versionen */
    public static function run(): array
    {
        $done = [];
        foreach (self::pending() as $version => $file) {
            $sql = (string) file_get_contents($file);
            // Kommentarzeilen entfernen und an ";" am Zeilenende trennen
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $stmt) {
                if (trim($stmt) !== '') {
                    Db::pdo()->exec($stmt);
                }
            }
            Db::run('INSERT INTO schema_migrations (version, applied_at) VALUES (?, UTC_TIMESTAMP())', [$version]);
            $done[] = $version;
        }
        return $done;
    }

    /**
     * Wird bei jedem Request aufgerufen: Falls die Code-Version neuer ist als die
     * der Datenbank, werden ausstehende Migrationen automatisch ausgeführt.
     * @return array{installed: bool}
     */
    public static function autoUpgrade(): array
    {
        $rows = [];
        try {
            $rows = Db::all("SELECT name, value FROM settings WHERE name IN ('app_version', 'installed')");
        } catch (PDOException $e) {
            // Tabellen existieren noch nicht -> Erstinstallation
        }
        $s = array_column($rows, 'value', 'name');
        if (($s['app_version'] ?? null) !== EW_VERSION) {
            self::run();
            set_setting('app_version', EW_VERSION);
        }
        return ['installed' => ($s['installed'] ?? '0') === '1'];
    }
}
