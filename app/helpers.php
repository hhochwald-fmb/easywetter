<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $c = $GLOBALS['ew_config'] ?? [];
    foreach (explode('.', $key) as $k) {
        if (!is_array($c) || !array_key_exists($k, $c)) {
            return $default;
        }
        $c = $c[$k];
    }
    return $c;
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Pfad, unter dem EasyWetter installiert ist (z. B. "" oder "/easywetter"). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfg = (string) config('base_url', '');
    if ($cfg !== '') {
        return $base = rtrim((string) (parse_url($cfg, PHP_URL_PATH) ?? ''), '/');
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }
    return $base = ($dir === '/' ? '' : $dir);
}

function current_path(): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    if (str_starts_with($path, '/public/')) {
        $path = substr($path, 7);
    }
    $path = '/' . trim(rawurldecode($path), '/');
    return $path;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function absolute_url(string $path = '/'): string
{
    $cfg = (string) config('base_url', '');
    if ($cfg !== '') {
        return rtrim($cfg, '/') . '/' . ltrim($path, '/');
    }
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return (is_https() ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    // Änderungsdatum anhängen, damit Browser nach jeder Änderung die neue Datei laden
    $file = EW_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = EW_VERSION . (is_file($file) ? '-' . filemtime($file) : '');
    return url('assets/' . ltrim($path, '/')) . '?v=' . rawurlencode($v);
}

function redirect(string $path): never
{
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . url($path));
    exit;
}

/** Interner Rücksprung (nur relative Pfade, keine fremden Domains). */
function safe_back(?string $back, string $fallback): string
{
    $back = (string) $back;
    if ($back === '' || !str_starts_with($back, '/') || str_starts_with($back, '//') || str_contains($back, '\\')) {
        return $fallback;
    }
    return $back;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Einmalig anzuzeigende Daten (z. B. neues Passwort/Token) über einen Redirect retten. */
function stash(string $key, mixed $value): void
{
    $_SESSION['stash'][$key] = $value;
}

function unstash(string $key): mixed
{
    $v = $_SESSION['stash'][$key] ?? null;
    unset($_SESSION['stash'][$key]);
    return $v;
}

function send_security_headers(bool $allowFraming = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    $frame = $allowFraming ? '*' : "'self'";
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors $frame; base-uri 'self'; form-action 'self'");
    if (!$allowFraming) {
        header('X-Frame-Options: SAMEORIGIN');
    }
}

function view(string $__view, array $__data = [], ?string $__layout = 'layout'): never
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        send_security_headers((bool) ($__data['allowFraming'] ?? false));
    }
    extract($__data, EXTR_SKIP);
    ob_start();
    require EW_ROOT . '/views/' . $__view . '.php';
    $content = ob_get_clean();
    if ($__layout === null) {
        echo $content;
    } else {
        require EW_ROOT . '/views/' . $__layout . '.php';
    }
    exit;
}

function json_out(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $defaults = [
        403 => 'Dafür fehlt dir die Berechtigung.',
        404 => 'Diese Seite gibt es nicht.',
        405 => 'Diese Aktion ist so nicht erlaubt.',
        429 => 'Zu viele Anfragen. Bitte warte kurz.',
    ];
    view('error', ['code' => $code, 'message' => $message ?: ($defaults[$code] ?? 'Fehler')]);
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function str_limit(string $s, int $len): string
{
    $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '');
    return mb_substr($s, 0, $len);
}

/** Leicht lesbares, trotzdem ausreichend starkes Passwort für Schülergruppen. */
function friendly_password(): string
{
    $words = ['Sonne', 'Wolke', 'Regen', 'Wind', 'Blitz', 'Nebel', 'Hagel', 'Frost', 'Sturm', 'Donner',
        'Schnee', 'Front', 'Bö', 'Luft', 'Tau', 'Eis', 'Welle', 'Kompass', 'Pegel', 'Radar', 'Ozon', 'Monsun',
        'Polar', 'Tropen', 'Wetter', 'Klima', 'Kelvin', 'Celsius', 'Barometer', 'Sensor', 'Funke', 'Orbit',
        'Komet', 'Planet', 'Mond', 'Stern', 'Gipfel', 'Fluss', 'Insel', 'Vulkan'];
    $pick = fn() => $words[random_int(0, count($words) - 1)];
    return $pick() . '-' . $pick() . '-' . $pick() . random_int(10, 99);
}

function random_slug(int $len = 10): string
{
    $chars = 'abcdefghijkmnopqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $s;
}

function slugify(string $s, int $max = 40): string
{
    $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return substr(trim($s, '-'), 0, $max);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function valid_username(string $u): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,59}$/', $u);
}

function username_taken(string $u, ?int $exceptUserId = null): bool
{
    $exists = Db::value('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$u, $exceptUserId ?? 0]);
    $pending = Db::value('SELECT COUNT(*) FROM registrations WHERE username = ?', [$u]);
    return ((int) $exists + (int) $pending) > 0;
}

function password_problem(string $p): ?string
{
    if (mb_strlen($p) < 8) {
        return 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    }
    if (mb_strlen($p) > 200) {
        return 'Das Passwort ist zu lang.';
    }
    return null;
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        abort(403, 'Die Sitzung ist abgelaufen oder das Formular ist veraltet. Bitte lade die Seite neu und versuche es noch einmal.');
    }
}

// ---------- Zeit ----------

function utc_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function utc_to_ts(?string $utc): ?int
{
    if (!$utc) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
    return $dt ? $dt->getTimestamp() : null;
}

function fmt_dt(?string $utc, string $format = 'd.m.Y H:i'): string
{
    $ts = utc_to_ts($utc);
    return $ts ? date($format, $ts) : '–';
}

function time_ago(?string $utc): string
{
    $ts = utc_to_ts($utc);
    if (!$ts) {
        return 'noch nie';
    }
    $d = time() - $ts;
    if ($d < 60) return 'gerade eben';
    if ($d < 3600) return 'vor ' . floor($d / 60) . ' Min.';
    if ($d < 86400) return 'vor ' . floor($d / 3600) . ' Std.';
    if ($d < 86400 * 30) return 'vor ' . floor($d / 86400) . ' Tagen';
    return 'am ' . date('d.m.Y', $ts);
}

function setting(string $name, ?string $default = null): ?string
{
    $v = Db::value('SELECT value FROM settings WHERE name = ?', [$name]);
    return $v === null || $v === false ? $default : (string) $v;
}

function set_setting(string $name, ?string $value): void
{
    Db::run('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

function registration_open(): bool
{
    return (bool) config('registration_open', true) && setting('registration_open', '1') === '1';
}

function icon(string $name, string $class = 'icon'): string
{
    return '<i data-lucide="' . e($name) . '" class="' . e($class) . '" aria-hidden="true"></i>';
}

/** Löscht Daten, die nicht mehr gebraucht werden (IP-Adressen, alte Anfragen). */
function housekeeping(): void
{
    Db::run('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    Db::run('DELETE FROM registrations WHERE created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY');
}

/** Sehr einfache Textformatierung für Impressum/Datenschutz: "## " = Überschrift, Leerzeile = Absatz. */
function simple_text(string $text): string
{
    $out = '';
    foreach (preg_split("/\R{2,}/", trim($text)) ?: [] as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        if (str_starts_with($block, '## ')) {
            $lines = preg_split("/\R/", $block, 2);
            $out .= '<h2>' . e(substr($lines[0], 3)) . '</h2>';
            if (!empty($lines[1])) {
                $out .= '<p>' . nl2br(e($lines[1])) . '</p>';
            }
        } else {
            $out .= '<p>' . nl2br(e($block)) . '</p>';
        }
    }
    return $out;
}

function default_privacy_text(): string
{
    return <<<TXT
## Verantwortlicher
[Name, Anschrift und E-Mail-Adresse des Betreibers eintragen]

## Welche Daten verarbeitet EasyWetter?
EasyWetter ist eine Plattform für den Schulunterricht. Verarbeitet werden nur Daten, die für den Betrieb nötig sind:
- Lehrkräfte: Name (frei wählbar), Benutzername, dienstliche E-Mail-Adresse, Schule, verschlüsseltes Passwort, Zeitpunkt der letzten Anmeldung.
- Schülergruppen: frei gewählter Gruppenname und Benutzername, verschlüsseltes Passwort. Es werden keine Namen einzelner Schülerinnen und Schüler benötigt.
- Messstationen: Name der Station, Messwerte (z. B. Temperatur) mit Zeitpunkt.

## Server-Protokolle und IP-Adressen
Bei fehlgeschlagenen Anmeldungen wird die IP-Adresse zum Schutz vor Angriffen gespeichert und nach spätestens 24 Stunden gelöscht. Bei einer Schul-Registrierung wird die IP-Adresse zum Schutz vor Missbrauch gespeichert und mit der Freigabe oder Ablehnung gelöscht, spätestens nach 30 Tagen. Ob der Webserver selbst Zugriffe protokolliert, hängt vom Hoster ab: [ergänzen].

## Cookies
Es wird ausschließlich ein technisch notwendiges Sitzungs-Cookie („ew_session“) verwendet, solange du angemeldet bist. Es gibt keine Analyse-, Tracking- oder Werbe-Cookies.

## Keine Einbindung von Drittanbietern
Alle Schriften, Skripte und Symbole werden von diesem Server geladen. Es werden keine Daten an Dritte (z. B. Google, CDNs, soziale Netzwerke) übertragen.

## Öffentliche Dashboards
Gruppen können ihr Dashboard freiwillig öffentlich teilen. Dann sind Gruppenname, Stationsnamen und Messwerte für alle mit dem Link sichtbar. Die Freigabe kann jederzeit zurückgenommen werden.

## Speicherdauer
Messwerte werden nach [365] Tagen automatisch gelöscht. Konten werden gelöscht, wenn die Lehrkraft bzw. der Schuladmin sie entfernt. Mit einer Gruppe werden alle ihre Stationen und Messwerte gelöscht.

## Rechtsgrundlage
Art. 6 Abs. 1 lit. e DSGVO i. V. m. dem schulischen Bildungsauftrag bzw. Art. 6 Abs. 1 lit. b DSGVO für die Nutzung durch Lehrkräfte.

## Deine Rechte
Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Widerspruch sowie auf Beschwerde bei einer Datenschutz-Aufsichtsbehörde.
TXT;
}
