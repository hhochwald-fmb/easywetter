<?php
declare(strict_types=1);

/**
 * Dashboard-Konfiguration einer Schülergruppe (wird von der Gruppe selbst gestaltet)
 * und Bereitstellung der Messdaten dafür.
 */
final class Dashboard
{
    public const THEMES = ['hell', 'dunkel', 'ozean', 'sonnenuntergang', 'wald', 'weltraum'];
    public const TYPES = ['value', 'gauge', 'chart', 'minmax', 'forecast', 'text', 'status'];
    public const FORECAST_MODELS = ['pressure', 'zambretti', 'trend', 'dewpoint'];
    public const SIZES = ['s', 'm', 'l'];
    public const CHART_STYLES = ['line', 'area', 'bar'];
    /** Zeitraum => [Sekunden, Bucket-Größe in Sekunden] */
    public const RANGES = [
        '6h' => [21600, 60],
        '24h' => [86400, 300],
        '7d' => [604800, 1800],
        '30d' => [2592000, 10800],
    ];
    public const MAX_WIDGETS = 24;

    public static function defaultConfig(string $title): array
    {
        return [
            'title' => $title,
            'subtitle' => '',
            'theme' => 'hell',
            'accent' => '#4f46e5',
            'refresh' => 60,
            'widgets' => [],
        ];
    }

    public static function load(array $group): array
    {
        $row = Db::one('SELECT * FROM dashboards WHERE group_id = ?', [$group['id']]);
        $cfg = $row ? json_decode((string) $row['config'], true) : null;
        $stationIds = array_map('intval', array_column(Db::all('SELECT id FROM stations WHERE group_id = ?', [$group['id']]), 'id'));
        $cfg = self::sanitize(is_array($cfg) ? $cfg : self::defaultConfig($group['display_name']), $stationIds);
        return [
            'config' => $cfg,
            'is_public' => $row ? (int) $row['is_public'] === 1 : false,
            'public_slug' => $row['public_slug'] ?? null,
        ];
    }

    public static function save(int $groupId, array $config): void
    {
        Db::run(
            'INSERT INTO dashboards (group_id, config, updated_at) VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE config = VALUES(config), updated_at = VALUES(updated_at)',
            [$groupId, json_encode($config, JSON_UNESCAPED_UNICODE)]
        );
    }

    public static function setPublic(int $groupId, bool $public): ?string
    {
        $row = Db::one('SELECT public_slug FROM dashboards WHERE group_id = ?', [$groupId]);
        $slug = $row['public_slug'] ?? null;
        if ($public && !$slug) {
            $slug = random_slug(10);
        }
        $g = Db::one('SELECT display_name FROM users WHERE id = ?', [$groupId]);
        Db::run(
            'INSERT INTO dashboards (group_id, config, is_public, public_slug, updated_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE is_public = VALUES(is_public), public_slug = VALUES(public_slug)',
            [$groupId, json_encode(self::defaultConfig($g['display_name'] ?? 'Dashboard')), $public ? 1 : 0, $slug]
        );
        return $slug;
    }

    private static function color(mixed $c, string $fallback): string
    {
        return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $fallback;
    }

    private static function num(mixed $v, float $fallback): float
    {
        return is_numeric($v) && is_finite((float) $v) ? max(-1e9, min(1e9, (float) $v)) : $fallback;
    }

    /** Erzwingt eine gültige, sichere Konfiguration (alles Unbekannte fliegt raus). */
    public static function sanitize(array $in, array $stationIds): array
    {
        $out = [
            'title' => str_limit((string) ($in['title'] ?? 'Unsere Wetterstation'), 80) ?: 'Unsere Wetterstation',
            'subtitle' => str_limit((string) ($in['subtitle'] ?? ''), 160),
            'theme' => in_array($in['theme'] ?? '', self::THEMES, true) ? $in['theme'] : 'hell',
            'accent' => self::color($in['accent'] ?? null, '#4f46e5'),
            'refresh' => in_array((int) ($in['refresh'] ?? 60), [30, 60, 300], true) ? (int) ($in['refresh'] ?? 60) : 60,
            'widgets' => [],
        ];
        $widgets = is_array($in['widgets'] ?? null) ? array_slice(array_values($in['widgets']), 0, self::MAX_WIDGETS) : [];
        foreach ($widgets as $w) {
            if (!is_array($w)) {
                continue;
            }
            $type = in_array($w['type'] ?? '', self::TYPES, true) ? $w['type'] : 'value';
            $station = (int) ($w['station'] ?? 0);
            if (!in_array($station, $stationIds, true)) {
                $station = $stationIds[0] ?? 0;
            }
            $sensor = strtolower((string) ($w['sensor'] ?? ''));
            if (!preg_match('/^[a-z0-9_]{1,32}$/', $sensor)) {
                $sensor = 'temp';
            }
            $min = self::num($w['min'] ?? 0, 0);
            $max = self::num($w['max'] ?? 100, 100);
            if ($max <= $min) {
                $max = $min + 1;
            }
            $sensor2 = strtolower((string) ($w['sensor2'] ?? 'hum'));
            if (!preg_match('/^[a-z0-9_]{1,32}$/', $sensor2)) {
                $sensor2 = 'hum';
            }
            $id = (string) ($w['id'] ?? '');
            $out['widgets'][] = [
                'id' => preg_match('/^[a-z0-9]{1,16}$/', $id) ? $id : random_slug(8),
                'type' => $type,
                'station' => $station,
                'sensor' => $sensor,
                'label' => str_limit((string) ($w['label'] ?? ''), 40),
                'unit' => str_limit((string) ($w['unit'] ?? ''), 12),
                'color' => self::color($w['color'] ?? null, '#4f46e5'),
                'icon' => is_string($w['icon'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', $w['icon']) ? $w['icon'] : 'gauge',
                'min' => $min,
                'max' => $max,
                'decimals' => max(0, min(3, (int) ($w['decimals'] ?? 1))),
                'range' => array_key_exists($w['range'] ?? '', self::RANGES) ? $w['range'] : '24h',
                'size' => in_array($w['size'] ?? '', self::SIZES, true) ? $w['size'] : 's',
                'style' => in_array($w['style'] ?? '', self::CHART_STYLES, true) ? $w['style'] : 'area',
                'text' => mb_substr(str_replace("\r", '', (string) ($w['text'] ?? '')), 0, 600),
                'model' => in_array($w['model'] ?? '', self::FORECAST_MODELS, true) ? $w['model'] : 'pressure',
                'sensor2' => $sensor2,
                'altitude' => (int) max(-500, min(9000, (int) self::num($w['altitude'] ?? 0, 0))),
                'horizon' => in_array((int) ($w['horizon'] ?? 1), [1, 2, 3], true) ? (int) ($w['horizon'] ?? 1) : 1,
                'qnh' => !empty($w['qnh']),
            ];
        }
        return $out;
    }

    /** Stationen der Gruppe inkl. letzter Werte je Sensor. */
    public static function stations(int $groupId): array
    {
        $stations = Db::all('SELECT id, name, altitude, last_seen_at FROM stations WHERE group_id = ? ORDER BY id', [$groupId]);
        $out = [];
        foreach ($stations as $s) {
            $latest = [];
            $rows = Db::all(
                'SELECT r.sensor_key, r.value, UNIX_TIMESTAMP(r.recorded_at) AS t
                   FROM readings r
                   JOIN (SELECT sensor_key, MAX(recorded_at) AS m FROM readings WHERE station_id = ? GROUP BY sensor_key) x
                     ON x.sensor_key = r.sensor_key AND x.m = r.recorded_at
                  WHERE r.station_id = ?',
                [$s['id'], $s['id']]
            );
            foreach ($rows as $r) {
                $latest[$r['sensor_key']] = ['v' => (float) $r['value'], 't' => (int) $r['t']];
            }
            ksort($latest);
            $out[] = [
                'id' => (int) $s['id'],
                'name' => $s['name'],
                'last_seen' => utc_to_ts($s['last_seen_at']),
                'altitude' => $s['altitude'] === null ? null : (int) $s['altitude'],
                'latest' => (object) $latest,
            ];
        }
        return $out;
    }

    /**
     * Zeitreihen für angeforderte Schlüssel "stationId:sensor:range".
     * Nur Stationen aus $allowedStationIds werden beantwortet.
     */
    public static function series(array $keys, array $allowedStationIds): array
    {
        $out = [];
        foreach (array_slice(array_unique($keys), 0, self::MAX_WIDGETS) as $key) {
            if (!preg_match('/^(\d+):([a-z0-9_]{1,32}):(6h|24h|7d|30d)$/', (string) $key, $m)) {
                continue;
            }
            $sid = (int) $m[1];
            if (!in_array($sid, $allowedStationIds, true)) {
                continue;
            }
            [$seconds, $bucket] = self::RANGES[$m[3]];
            $rows = Db::all(
                'SELECT FLOOR(UNIX_TIMESTAMP(recorded_at) / ?) * ? AS t, AVG(value) AS a, MIN(value) AS mn, MAX(value) AS mx
                   FROM readings
                  WHERE station_id = ? AND sensor_key = ? AND recorded_at >= UTC_TIMESTAMP() - INTERVAL ? SECOND
               GROUP BY t ORDER BY t',
                [$bucket, $bucket, $sid, $m[2], $seconds]
            );
            $out[$key] = array_map(fn($r) => [(int) $r['t'], round((float) $r['a'], 3), (float) $r['mn'], (float) $r['mx']], $rows);
        }
        return $out;
    }
}
