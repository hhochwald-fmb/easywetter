<?php
declare(strict_types=1);

/** Token aus Header (bevorzugt) oder JSON-Body lesen. */
function api_token(?array $body = null): string
{
    $t = $_SERVER['HTTP_X_STATION_TOKEN'] ?? '';
    if ($t === '') {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/^Bearer\s+(\S+)$/i', (string) $auth, $m)) {
            $t = $m[1];
        }
    }
    if ($t === '' && is_array($body) && is_string($body['token'] ?? null)) {
        $t = $body['token'];
    }
    return trim((string) $t);
}

function api_station_by_token(string $token): ?array
{
    if (!preg_match('/^ew_[a-f0-9]{40}$/', $token)) {
        return null;
    }
    return Db::one(
        "SELECT s.*, UNIX_TIMESTAMP(s.last_seen_at) AS last_ts
           FROM stations s
           JOIN users g ON g.id = s.group_id AND g.is_active = 1
           JOIN users t ON t.id = g.teacher_id AND t.is_active = 1
          WHERE s.token_hash = ?",
        [hash('sha256', $token)]
    );
}

/**
 * POST /api/v1/upload
 * Header:  X-Station-Token: ew_...   (alternativ "Authorization: Bearer ew_..." oder "token" im JSON)
 * Body:    {"temp": 21.5, "hum": 48, "press": 1013.2}
 *     oder {"values": {"temp": 21.5, ...}}
 */
function api_upload(): never
{
    $raw = (string) file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw) > 16384) {
        json_out(['ok' => false, 'error' => 'Zu viele Daten auf einmal (max. 16 KB).'], 413);
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        json_out(['ok' => false, 'error' => 'Ungültiges JSON. Beispiel: {"temp": 21.5, "hum": 48}'], 400);
    }

    $token = api_token($body);
    if ($token === '') {
        json_out(['ok' => false, 'error' => 'Token fehlt. Sende ihn im Header X-Station-Token.'], 401);
    }
    $station = api_station_by_token($token);
    if (!$station) {
        json_out(['ok' => false, 'error' => 'Token ungültig. Prüfe ihn auf der Stationen-Seite (evtl. wurde ein neuer erzeugt).'], 401);
    }

    $minInterval = max(1, (int) config('ingest_min_interval', 5));
    if ($station['last_ts'] !== null && time() - (int) $station['last_ts'] < $minInterval) {
        header('Retry-After: ' . $minInterval);
        json_out(['ok' => false, 'error' => "Zu schnell: bitte höchstens alle $minInterval Sekunden senden."], 429);
    }

    $values = is_array($body['values'] ?? null) ? $body['values'] : $body;
    unset($values['token']);
    $valid = [];
    $ignored = [];
    foreach ($values as $key => $val) {
        $k = strtolower(trim((string) $key));
        if (!preg_match('/^[a-z0-9_]{1,32}$/', $k)) {
            $ignored[] = (string) $key;
            continue;
        }
        if (is_bool($val)) {
            $val = $val ? 1 : 0;
        }
        if ((!is_int($val) && !is_float($val) && !(is_string($val) && is_numeric($val))) || !is_finite((float) $val) || abs((float) $val) > 1e9) {
            $ignored[] = $k;
            continue;
        }
        $valid[$k] = (float) $val;
        if (count($valid) >= 24) {
            break;
        }
    }
    if (!$valid) {
        json_out(['ok' => false, 'error' => 'Keine gültigen Messwerte gefunden. Schlüssel: a–z, 0–9, _ (max. 32 Zeichen), Werte: Zahlen.', 'ignored' => $ignored], 422);
    }

    $now = utc_now();
    $placeholders = implode(',', array_fill(0, count($valid), '(?, ?, ?, ?)'));
    $params = [];
    foreach ($valid as $k => $v) {
        array_push($params, $station['id'], $k, $v, $now);
    }
    Db::run("INSERT INTO readings (station_id, sensor_key, value, recorded_at) VALUES $placeholders", $params);
    Db::run('UPDATE stations SET last_seen_at = ? WHERE id = ?', [$now, $station['id']]);

    // Gelegentlich alte Messwerte aufräumen
    if (random_int(1, 500) === 1) {
        $days = max(7, (int) config('retention_days', 365));
        Db::run('DELETE FROM readings WHERE recorded_at < UTC_TIMESTAMP() - INTERVAL ? DAY LIMIT 20000', [$days]);
    }

    json_out([
        'ok' => true,
        'station' => $station['name'],
        'stored' => count($valid),
        'ignored' => $ignored,
        'server_time' => time(),
    ]);
}

/** GET /api/v1/ping – Verbindungstest; mit Token wird dieser zusätzlich geprüft. */
function api_ping(): never
{
    $res = ['ok' => true, 'app' => 'EasyWetter', 'version' => EW_VERSION, 'server_time' => time()];
    $token = api_token();
    if ($token !== '') {
        $s = api_station_by_token($token);
        $res['token_valid'] = (bool) $s;
        if ($s) {
            $res['station'] = $s['name'];
        }
    }
    json_out($res);
}

/**
 * GET /api/v1/data?group=ID | ?slug=XYZ  &series=stationId:sensor:range,...
 * Liefert Stationen (mit letzten Werten) und angeforderte Zeitreihen für das Dashboard.
 */
function api_data(): never
{
    $slug = (string) ($_GET['slug'] ?? '');
    $groupId = 0;
    if ($slug !== '') {
        $groupId = (int) Db::value(
            "SELECT d.group_id FROM dashboards d
               JOIN users u ON u.id = d.group_id AND u.is_active = 1
               JOIN users t ON t.id = u.teacher_id AND t.is_active = 1
              WHERE d.public_slug = ? AND d.is_public = 1",
            [$slug]
        );
    } else {
        $u = Auth::user();
        if (!$u) {
            json_out(['ok' => false, 'error' => 'Nicht angemeldet'], 401);
        }
        $g = Auth::canManageGroup($u, (int) ($_GET['group'] ?? 0));
        $groupId = $g ? (int) $g['id'] : 0;
    }
    if ($groupId <= 0) {
        json_out(['ok' => false, 'error' => 'Nicht gefunden'], 404);
    }
    $stations = Dashboard::stations($groupId);
    $ids = array_column($stations, 'id');
    $keys = array_filter(explode(',', (string) ($_GET['series'] ?? '')));
    json_out([
        'ok' => true,
        'now' => time(),
        'stations' => $stations,
        'series' => (object) Dashboard::series($keys, $ids),
    ]);
}
