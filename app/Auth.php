<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_FAILS_USER = 8;
    private const MAX_FAILS_IP = 40;
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('ew_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (base_path() ?: '') . '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    private static function fingerprint(string $hash): string
    {
        return substr(hash('sha256', $hash), 0, 16);
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $u = Db::one(
            'SELECT u.*, s.name AS school_name, t.is_active AS teacher_active
               FROM users u
          LEFT JOIN schools s ON s.id = u.school_id
          LEFT JOIN users t ON t.id = u.teacher_id
              WHERE u.id = ?',
            [(int) $_SESSION['uid']]
        );
        $valid = $u
            && (int) $u['is_active'] === 1
            && ($u['role'] !== 'group' || (int) $u['teacher_active'] === 1)
            && hash_equals((string) ($_SESSION['pwv'] ?? ''), self::fingerprint($u['password_hash']));
        if (!$valid) {
            self::logout();
            return null;
        }
        return self::$user = $u;
    }

    /** @return array|string Benutzer oder Fehlermeldung */
    public static function attempt(string $username, string $password): array|string
    {
        $ip = client_ip();
        $failsUser = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL 15 MINUTE', [$username]);
        $failsIp = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL 15 MINUTE', [$ip]);
        if ($failsUser >= self::MAX_FAILS_USER || $failsIp >= self::MAX_FAILS_IP) {
            return 'Zu viele Fehlversuche. Bitte warte 15 Minuten und versuche es dann erneut.';
        }

        $u = Db::one('SELECT u.*, t.is_active AS teacher_active FROM users u LEFT JOIN users t ON t.id = u.teacher_id WHERE u.username = ?', [$username]);
        // Auch bei unbekanntem Namen einen Hash prüfen, damit die Antwortzeit nichts verrät
        $ok = password_verify($password, $u['password_hash'] ?? password_hash('dummy-' . random_int(0, 9), PASSWORD_DEFAULT)) && $u !== null;
        if (!$u || !$ok) {
            Db::run('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, UTC_TIMESTAMP())', [$ip, mb_substr($username, 0, 60)]);
            if (random_int(1, 50) === 1) {
                Db::run('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
            }
            if (!$u && Db::value('SELECT COUNT(*) FROM registrations WHERE username = ?', [$username])) {
                return 'Deine Registrierung wurde noch nicht freigegeben. Du bekommst Bescheid, sobald es so weit ist.';
            }
            return 'Benutzername oder Passwort ist falsch.';
        }
        if ((int) $u['is_active'] !== 1 || ($u['role'] === 'group' && (int) $u['teacher_active'] !== 1)) {
            return 'Dieses Konto ist deaktiviert.';
        }

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            $u['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [$u['password_hash'], $u['id']]);
        }
        Db::run('DELETE FROM login_attempts WHERE username = ?', [$username]);
        Db::run('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);

        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['pwv'] = self::fingerprint($u['password_hash']);
        unset($_SESSION['csrf']);
        self::$loaded = false;
        return $u;
    }

    /** Nach eigener Passwortänderung angemeldet bleiben. */
    public static function refreshFingerprint(string $newHash): void
    {
        $_SESSION['pwv'] = self::fingerprint($newHash);
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
    }

    public static function require(string ...$roles): array
    {
        $u = self::user();
        if (!$u) {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $_SESSION['after_login'] = current_path();
            }
            flash('info', 'Bitte melde dich zuerst an.');
            redirect('/login');
        }
        if ($roles && !in_array($u['role'], $roles, true)) {
            abort(403);
        }
        if ((int) $u['must_change_password'] === 1 && current_path() !== '/konto' && current_path() !== '/logout') {
            flash('info', 'Bitte vergib zuerst ein eigenes, neues Passwort.');
            redirect('/konto');
        }
        return $u;
    }

    public static function home(array $u): string
    {
        return match ($u['role']) {
            'admin' => '/admin',
            'teacher' => '/lehrer',
            default => '/gruppe',
        };
    }

    // ---------- Berechtigungen ----------

    /** Darf $u die Gruppe $groupId sehen/verwalten? */
    public static function canManageGroup(array $u, int $groupId): ?array
    {
        $g = Db::one("SELECT * FROM users WHERE id = ? AND role = 'group'", [$groupId]);
        if (!$g) {
            return null;
        }
        $ok = match ($u['role']) {
            'admin' => true,
            'teacher' => (int) $g['teacher_id'] === (int) $u['id']
                || ((int) $u['is_school_admin'] === 1 && (int) $g['school_id'] === (int) $u['school_id']),
            'group' => (int) $g['id'] === (int) $u['id'],
            default => false,
        };
        return $ok ? $g : null;
    }

    /** Darf $u die Lehrkraft $teacherId verwalten (Admin oder Schuladmin derselben Schule)? */
    public static function canManageTeacher(array $u, int $teacherId): ?array
    {
        $t = Db::one("SELECT * FROM users WHERE id = ? AND role = 'teacher'", [$teacherId]);
        if (!$t) {
            return null;
        }
        if ($u['role'] === 'admin') {
            return $t;
        }
        if ($u['role'] === 'teacher' && (int) $u['is_school_admin'] === 1 && (int) $t['school_id'] === (int) $u['school_id']) {
            return $t;
        }
        return null;
    }
}
