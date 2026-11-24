<?php
declare(strict_types=1);

function page_landing(): never
{
    $stats = [
        'schools' => (int) Db::value('SELECT COUNT(*) FROM schools'),
        'stations' => (int) Db::value('SELECT COUNT(*) FROM stations'),
        'active' => (int) Db::value('SELECT COUNT(*) FROM stations WHERE last_seen_at > UTC_TIMESTAMP() - INTERVAL 1 DAY'),
    ];
    $showcase = Db::all(
        "SELECT d.public_slug, u.display_name, s.name AS school_name
           FROM dashboards d
           JOIN users u ON u.id = d.group_id AND u.is_active = 1
           JOIN users t ON t.id = u.teacher_id AND t.is_active = 1
      LEFT JOIN schools s ON s.id = u.school_id
          WHERE d.is_public = 1
            AND EXISTS (SELECT 1 FROM stations st WHERE st.group_id = u.id AND st.last_seen_at > UTC_TIMESTAMP() - INTERVAL 1 DAY)
       ORDER BY d.updated_at DESC LIMIT 6"
    );
    view('landing', ['title' => 'Wetterstationen im Unterricht', 'stats' => $stats, 'showcase' => $showcase, 'registrationOpen' => registration_open()], 'layout_public');
}

function page_guide(): never
{
    view('guide', ['title' => 'Anleitung', 'uploadUrl' => absolute_url('api/v1/upload')], 'layout_public');
}

// ---------- Login ----------

function login_form(): never
{
    if ($u = Auth::user()) {
        redirect(Auth::home($u));
    }
    view('login', ['title' => 'Anmelden', 'registrationOpen' => registration_open()], 'layout_public');
}

function login_submit(): never
{
    $username = post('username');
    $result = Auth::attempt($username, (string) ($_POST['password'] ?? ''));
    if (is_string($result)) {
        flash('error', $result);
        stash('login_username', $username);
        redirect('/login');
    }
    $target = safe_back($_SESSION['after_login'] ?? null, Auth::home($result));
    unset($_SESSION['after_login']);
    redirect($target);
}

function logout_action(): never
{
    Auth::logout();
    flash('success', 'Du wurdest abgemeldet.');
    redirect('/');
}

// ---------- Registrierung (Schule) ----------

function register_form(array $errors = [], array $old = []): never
{
    if (!registration_open()) {
        view('register_closed', ['title' => 'Registrierung'], 'layout_public');
    }
    $_SESSION['reg_started'] = time();
    view('register', ['title' => 'Schule registrieren', 'errors' => $errors, 'old' => $old], 'layout_public');
}

function register_submit(): never
{
    if (!registration_open()) {
        abort(403, 'Die Registrierung ist derzeit geschlossen.');
    }
    $old = [
        'school_name' => str_limit(post('school_name'), 150),
        'school_city' => str_limit(post('school_city'), 100),
        'display_name' => str_limit(post('display_name'), 120),
        'email' => post('email'),
        'username' => post('username'),
        'message' => mb_substr(post('message'), 0, 1000),
    ];
    $errors = [];

    // Spam-Schutz: verstecktes Feld + Mindestdauer + Limit pro IP
    $tooFast = time() - (int) ($_SESSION['reg_started'] ?? 0) < 4;
    if (post('website') !== '' || $tooFast) {
        $errors[] = 'Das hat nicht geklappt. Bitte fülle das Formular in Ruhe noch einmal aus.';
    }
    $recent = (int) Db::value('SELECT COUNT(*) FROM registrations WHERE ip = ? AND created_at > UTC_TIMESTAMP() - INTERVAL 1 DAY', [client_ip()]);
    if ($recent >= 3) {
        $errors[] = 'Von diesem Anschluss wurden heute schon mehrere Registrierungen gesendet. Bitte versuche es morgen erneut.';
    }

    if (mb_strlen($old['school_name']) < 3) $errors[] = 'Bitte den Namen der Schule angeben.';
    if (mb_strlen($old['school_city']) < 2) $errors[] = 'Bitte den Ort der Schule angeben.';
    if (mb_strlen($old['display_name']) < 3) $errors[] = 'Bitte deinen Namen angeben.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
    if (!valid_username($old['username'])) {
        $errors[] = 'Benutzername: 3–60 Zeichen, nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich.';
    } elseif (username_taken($old['username'])) {
        $errors[] = 'Dieser Benutzername ist leider schon vergeben.';
    }
    $pass = (string) ($_POST['password'] ?? '');
    if ($p = password_problem($pass)) {
        $errors[] = $p;
    } elseif ($pass !== ($_POST['password2'] ?? '')) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }
    if (empty($_POST['confirm_teacher'])) {
        $errors[] = 'Bitte bestätige, dass du Lehrkraft an dieser Schule bist.';
    }

    if ($errors) {
        register_form($errors, $old);
    }

    Db::insert(
        'INSERT INTO registrations (school_name, school_city, display_name, email, username, password_hash, message, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$old['school_name'], $old['school_city'], $old['display_name'], $old['email'], $old['username'],
            password_hash($pass, PASSWORD_DEFAULT), $old['message'] ?: null, client_ip()]
    );
    unset($_SESSION['reg_started']);

    Mailer::notifyAdmin(
        'EasyWetter: Neue Registrierung – ' . $old['school_name'],
        "Neue Registrierung wartet auf Freigabe:\n\n"
        . "Schule: {$old['school_name']} ({$old['school_city']})\n"
        . "Lehrkraft: {$old['display_name']} <{$old['email']}>\n"
        . "Benutzername: {$old['username']}\n\n"
        . ($old['message'] ? "Nachricht:\n{$old['message']}\n\n" : '')
        . 'Freigeben: ' . absolute_url('admin') . "\n"
    );

    view('register_done', ['title' => 'Registrierung gesendet', 'name' => $old['display_name']], 'layout_public');
}

// ---------- Konto ----------

function account_page(): never
{
    $u = Auth::require();
    view('account', ['title' => 'Mein Konto', 'user' => $u]);
}

function account_password(): never
{
    $u = Auth::require();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    if (!password_verify($current, $u['password_hash'])) {
        flash('error', 'Das aktuelle Passwort ist nicht korrekt.');
        redirect('/konto');
    }
    if ($p = password_problem($new)) {
        flash('error', $p);
        redirect('/konto');
    }
    if ($new !== ($_POST['new2'] ?? '')) {
        flash('error', 'Die neuen Passwörter stimmen nicht überein.');
        redirect('/konto');
    }
    if (hash_equals($current, $new)) {
        flash('error', 'Bitte wähle ein anderes Passwort als das bisherige.');
        redirect('/konto');
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    Db::run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [$hash, $u['id']]);
    Auth::refreshFingerprint($hash);
    flash('success', 'Dein Passwort wurde geändert.');
    redirect(Auth::home($u));
}

function account_profile(): never
{
    $u = Auth::require('admin', 'teacher');
    $name = str_limit(post('display_name'), 120);
    $email = post('email');
    if ($name === '') {
        flash('error', 'Bitte einen Namen angeben.');
        redirect('/konto');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Die E-Mail-Adresse ist ungültig.');
        redirect('/konto');
    }
    Db::run('UPDATE users SET display_name = ?, email = ? WHERE id = ?', [$name, $email ?: null, $u['id']]);
    flash('success', 'Profil gespeichert.');
    redirect('/konto');
}

// ---------- Öffentliche Ansichten ----------

function public_dashboard(string $slug): never
{
    $g = Db::one(
        "SELECT u.* FROM dashboards d
           JOIN users u ON u.id = d.group_id AND u.role = 'group' AND u.is_active = 1
           JOIN users t ON t.id = u.teacher_id AND t.is_active = 1
          WHERE d.public_slug = ? AND d.is_public = 1",
        [$slug]
    );
    if (!$g) {
        abort(404, 'Dieses Dashboard gibt es nicht oder es ist nicht (mehr) öffentlich.');
    }
    $dash = Dashboard::load($g);
    view('group/dashboard', [
        'title' => $dash['config']['title'],
        'group' => $g,
        'dash' => $dash,
        'dataUrl' => url('api/v1/data') . '?slug=' . rawurlencode($slug),
        'public' => true,
        'allowFraming' => true,
    ], 'layout_dash');
}

function public_class(string $slug): never
{
    $t = Db::one("SELECT u.*, s.name AS school_name FROM users u LEFT JOIN schools s ON s.id = u.school_id WHERE u.public_slug = ? AND u.role = 'teacher' AND u.is_active = 1", [$slug]);
    if (!$t) {
        abort(404, 'Diese Klassenübersicht gibt es nicht oder sie ist nicht (mehr) öffentlich.');
    }
    view('teacher/overview', [
        'title' => 'Klassenübersicht',
        'teacher' => $t,
        'groups' => teacher_overview_data((int) $t['id']),
        'public' => true,
        'allowFraming' => true,
    ], 'layout_dash');
}

// ---------- Rechtliches ----------

function page_imprint(): never
{
    $text = (string) setting('imprint_text', '');
    view('legal', ['title' => 'Impressum', 'html' => $text !== '' ? simple_text($text) : '<p class="muted">Der Betreiber hat noch kein Impressum hinterlegt.</p>'], 'layout_public');
}

function page_privacy(): never
{
    $text = (string) setting('privacy_text', '');
    view('legal', ['title' => 'Datenschutzerklärung', 'html' => simple_text($text !== '' ? $text : default_privacy_text())], 'layout_public');
}
