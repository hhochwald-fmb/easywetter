<?php
declare(strict_types=1);

function group_home(): never
{
    $u = Auth::require();
    if ($u['role'] !== 'group') {
        redirect(Auth::home($u));
    }
    redirect('/gruppen/' . $u['id']);
}

/** @return array{0: array, 1: array} [aktueller Benutzer, Gruppe] */
function group_context(int $groupId): array
{
    $u = Auth::require();
    $g = Auth::canManageGroup($u, $groupId) ?? abort(403);
    $g['teacher_name'] = (string) Db::value('SELECT display_name FROM users WHERE id = ?', [$g['teacher_id']]);
    return [$u, $g];
}

function group_dashboard(int $id): never
{
    [$u, $g] = group_context($id);
    $dash = Dashboard::load($g);
    view('group/dashboard', [
        'title' => $dash['config']['title'],
        'user' => $u,
        'group' => $g,
        'dash' => $dash,
        'dataUrl' => url('api/v1/data') . '?group=' . (int) $g['id'],
        'public' => false,
        'credentials' => unstash('credentials'),
        'stationCount' => (int) Db::value('SELECT COUNT(*) FROM stations WHERE group_id = ?', [$id]),
    ]);
}

function group_stations(int $id): never
{
    [$u, $g] = group_context($id);
    $stations = Db::all(
        'SELECT s.*, (SELECT COUNT(*) FROM readings r WHERE r.station_id = s.id) AS readings
           FROM stations s WHERE s.group_id = ? ORDER BY s.id',
        [$id]
    );
    $latest = [];
    foreach (Dashboard::stations($id) as $s) {
        $latest[$s['id']] = (array) $s['latest'];
    }
    view('group/stations', [
        'title' => 'Stationen',
        'user' => $u,
        'group' => $g,
        'stations' => $stations,
        'latest' => $latest,
        'newToken' => unstash('new_token'),
        'uploadUrl' => absolute_url('api/v1/upload'),
    ]);
}

function group_designer(int $id): never
{
    [$u, $g] = group_context($id);
    $dash = Dashboard::load($g);
    view('group/designer', [
        'title' => 'Dashboard gestalten',
        'user' => $u,
        'group' => $g,
        'dash' => $dash,
        'dataUrl' => url('api/v1/data') . '?group=' . (int) $g['id'],
        'saveUrl' => url('gruppen/' . (int) $g['id'] . '/gestalten'),
    ]);
}

function group_designer_save(int $id): never
{
    [, $g] = group_context($id);
    $raw = file_get_contents('php://input', false, null, 0, 200000);
    $in = json_decode((string) $raw, true);
    if (!is_array($in)) {
        json_out(['ok' => false, 'error' => 'Ungültige Daten'], 400);
    }
    $stationIds = array_map('intval', array_column(Db::all('SELECT id FROM stations WHERE group_id = ?', [$id]), 'id'));
    $config = Dashboard::sanitize($in, $stationIds);
    Dashboard::save($id, $config);
    json_out(['ok' => true, 'config' => $config]);
}

function group_share(int $id): never
{
    [, $g] = group_context($id);
    $public = post('enable') === '1';
    Dashboard::setPublic($id, $public);
    flash('success', $public ? 'Euer Dashboard ist jetzt öffentlich – teilt den Link!' : 'Das Dashboard ist nicht mehr öffentlich.');
    redirect('/gruppen/' . $id);
}

// ---------- Stationen ----------

function new_station_token(): array
{
    $token = 'ew_' . bin2hex(random_bytes(20));
    return [$token, hash('sha256', $token), substr($token, -4)];
}

/** @return array{0: array, 1: array, 2: array} [Benutzer, Station, Gruppe] */
function station_context(int $stationId): array
{
    $u = Auth::require();
    $s = Db::one('SELECT * FROM stations WHERE id = ?', [$stationId]) ?? abort(404);
    $g = Auth::canManageGroup($u, (int) $s['group_id']) ?? abort(403);
    return [$u, $s, $g];
}

function station_create(int $groupId): never
{
    [, $g] = group_context($groupId);
    $name = str_limit(post('name'), 80);
    if (mb_strlen($name) < 1) {
        flash('error', 'Bitte einen Namen für die Station angeben.');
        redirect('/gruppen/' . $groupId . '/stationen');
    }
    if ((int) Db::value('SELECT COUNT(*) FROM stations WHERE group_id = ?', [$groupId]) >= 20) {
        flash('error', 'Eine Gruppe kann höchstens 20 Stationen haben.');
        redirect('/gruppen/' . $groupId . '/stationen');
    }
    [$token, $hash, $hint] = new_station_token();
    $alt = post_altitude();
    if ($alt === false) {
        flash('error', 'Die Höhe bitte als ganze Zahl in Metern angeben (z. B. 250).');
        redirect('/gruppen/' . $groupId . '/stationen');
    }
    $sid = Db::insert('INSERT INTO stations (group_id, name, altitude, token_hash, token_hint) VALUES (?, ?, ?, ?, ?)', [$groupId, $name, $alt, $hash, $hint]);
    stash('new_token', ['station_id' => $sid, 'station' => $name, 'token' => $token]);
    flash('success', 'Station „' . $name . '“ angelegt.');
    redirect('/gruppen/' . $groupId . '/stationen');
}

function station_token(int $id): never
{
    [, $s] = station_context($id);
    [$token, $hash, $hint] = new_station_token();
    Db::run('UPDATE stations SET token_hash = ?, token_hint = ? WHERE id = ?', [$hash, $hint, $id]);
    stash('new_token', ['station_id' => $id, 'station' => $s['name'], 'token' => $token]);
    flash('info', 'Neuer Token erzeugt. Der alte Token funktioniert ab sofort nicht mehr – trage den neuen in euer Programm ein.');
    redirect('/gruppen/' . $s['group_id'] . '/stationen');
}

/** Höhe aus dem Formular: leer = unbekannt (NULL), sonst ganze Meter. */
function post_altitude(): int|false|null
{
    $raw = str_replace([' ', 'm'], '', post('altitude'));
    if ($raw === '') {
        return null;
    }
    if (!preg_match('/^-?\d{1,4}$/', $raw) || (int) $raw < -500 || (int) $raw > 9000) {
        return false;
    }
    return (int) $raw;
}

function station_rename(int $id): never
{
    [, $s] = station_context($id);
    $name = str_limit(post('name'), 80);
    $alt = post_altitude();
    if ($alt === false) {
        flash('error', 'Die Höhe bitte als ganze Zahl in Metern angeben (z. B. 250).');
        redirect('/gruppen/' . $s['group_id'] . '/stationen');
    }
    if ($name !== '') {
        Db::run('UPDATE stations SET name = ?, altitude = ? WHERE id = ?', [$name, $alt, $id]);
        flash('success', 'Station gespeichert.');
    }
    redirect('/gruppen/' . $s['group_id'] . '/stationen');
}

function station_clear(int $id): never
{
    [, $s] = station_context($id);
    $n = Db::run('DELETE FROM readings WHERE station_id = ?', [$id])->rowCount();
    flash('success', "$n Messwerte von „{$s['name']}“ gelöscht.");
    redirect('/gruppen/' . $s['group_id'] . '/stationen');
}

function station_delete(int $id): never
{
    [, $s] = station_context($id);
    Db::run('DELETE FROM stations WHERE id = ?', [$id]);
    flash('success', 'Station „' . $s['name'] . '“ gelöscht.');
    redirect('/gruppen/' . $s['group_id'] . '/stationen');
}

/** CSV-Export (Excel-freundlich: Semikolon, Dezimalkomma, Ortszeit) */
function station_export(int $id): never
{
    [, $s] = station_context($id);
    $keys = array_column(Db::all('SELECT DISTINCT sensor_key FROM readings WHERE station_id = ? ORDER BY sensor_key', [$id]), 'sensor_key');
    $filename = slugify($s['name']) ?: 'station';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="easywetter-' . $filename . '-' . date('Y-m-d') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM, damit Excel UTF-8 erkennt
    fputcsv($out, array_merge(['Zeit'], $keys), ';', '"', '');
    $st = Db::run('SELECT UNIX_TIMESTAMP(recorded_at) AS t, sensor_key, value FROM readings WHERE station_id = ? ORDER BY recorded_at, sensor_key', [$id]);
    $flush = function (?int $t, array $row) use ($out, $keys) {
        if ($t === null) {
            return;
        }
        $line = [date('d.m.Y H:i:s', $t)];
        foreach ($keys as $k) {
            $line[] = isset($row[$k]) ? str_replace('.', ',', (string) $row[$k]) : '';
        }
        fputcsv($out, $line, ';', '"', '');
    };
    $curT = null;
    $row = [];
    while ($r = $st->fetch()) {
        $t = (int) $r['t'];
        if ($t !== $curT) {
            $flush($curT, $row);
            $curT = $t;
            $row = [];
        }
        $row[$r['sensor_key']] = (float) $r['value'];
    }
    $flush($curT, $row);
    fclose($out);
    exit;
}
