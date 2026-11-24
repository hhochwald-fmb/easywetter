<?php
declare(strict_types=1);

// ======================= Oberadmin =======================

function admin_index(): never
{
    Auth::require('admin');
    $schools = Db::all(
        "SELECT s.*,
                (SELECT COUNT(*) FROM users t WHERE t.school_id = s.id AND t.role = 'teacher') AS teachers,
                (SELECT COUNT(*) FROM users g WHERE g.school_id = s.id AND g.role = 'group') AS groups_count,
                (SELECT COUNT(*) FROM stations st JOIN users g ON g.id = st.group_id WHERE g.school_id = s.id) AS stations,
                (SELECT MAX(st.last_seen_at) FROM stations st JOIN users g ON g.id = st.group_id WHERE g.school_id = s.id) AS last_seen
           FROM schools s ORDER BY s.name"
    );
    view('admin/index', [
        'title' => 'Verwaltung',
        'registrations' => Db::all('SELECT * FROM registrations ORDER BY created_at'),
        'schools' => $schools,
        'stats' => [
            'teachers' => (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'teacher'"),
            'groups' => (int) Db::value("SELECT COUNT(*) FROM users WHERE role = 'group'"),
            'stations' => (int) Db::value('SELECT COUNT(*) FROM stations'),
            'readings24' => (int) Db::value('SELECT COUNT(*) FROM readings WHERE recorded_at > UTC_TIMESTAMP() - INTERVAL 1 DAY'),
        ],
        'credentials' => unstash('credentials'),
        'registrationOpen' => registration_open(),
    ]);
}

function admin_registration_approve(int $id): never
{
    Auth::require('admin');
    $r = Db::one('SELECT * FROM registrations WHERE id = ?', [$id]);
    if (!$r) {
        flash('error', 'Diese Registrierung gibt es nicht mehr.');
        redirect('/admin');
    }
    if ((int) Db::value('SELECT COUNT(*) FROM users WHERE username = ?', [$r['username']]) > 0) {
        flash('error', 'Der Benutzername „' . $r['username'] . '“ ist inzwischen vergeben. Bitte Registrierung ablehnen und neu anfragen lassen.');
        redirect('/admin');
    }
    $schoolId = (int) post('school_id', '0');
    Db::transaction(function () use ($r, &$schoolId) {
        if ($schoolId <= 0 || !Db::value('SELECT id FROM schools WHERE id = ?', [$schoolId])) {
            $schoolId = Db::insert('INSERT INTO schools (name, city) VALUES (?, ?)', [$r['school_name'], $r['school_city']]);
        }
        Db::insert(
            "INSERT INTO users (role, school_id, username, display_name, email, password_hash, is_school_admin)
             VALUES ('teacher', ?, ?, ?, ?, ?, 1)",
            [$schoolId, $r['username'], $r['display_name'], $r['email'], $r['password_hash']]
        );
        Db::run('DELETE FROM registrations WHERE id = ?', [$r['id']]);
    });
    $mailed = Mailer::send(
        $r['email'],
        'EasyWetter: Dein Zugang ist freigeschaltet',
        "Hallo {$r['display_name']},\n\ndein EasyWetter-Zugang für {$r['school_name']} wurde freigeschaltet.\n"
        . "Du kannst dich jetzt mit deinem Benutzernamen „{$r['username']}“ anmelden:\n" . absolute_url('login') . "\n\n"
        . "Als Schuladmin kannst du auch Kolleginnen und Kollegen deiner Schule anlegen.\n\nViel Spaß beim Messen!\n"
    );
    flash('success', 'Freigegeben: ' . $r['display_name'] . ' (' . $r['school_name'] . ').' . ($mailed ? ' Eine Bestätigung wurde per E-Mail verschickt.' : ' Bitte gib der Person kurz Bescheid (E-Mail-Versand ist nicht eingerichtet).'));
    redirect('/admin');
}

function admin_registration_reject(int $id): never
{
    Auth::require('admin');
    $r = Db::one('SELECT * FROM registrations WHERE id = ?', [$id]);
    if ($r) {
        Db::run('DELETE FROM registrations WHERE id = ?', [$id]);
        $reason = mb_substr(post('reason'), 0, 1000);
        if (post('notify') === '1') {
            Mailer::send($r['email'], 'EasyWetter: Deine Registrierung',
                "Hallo {$r['display_name']},\n\ndeine Registrierung bei EasyWetter wurde leider nicht freigegeben.\n"
                . ($reason !== '' ? "\nBegründung: $reason\n" : '') . "\nViele Grüße\n");
        }
        flash('success', 'Registrierung von ' . $r['display_name'] . ' wurde abgelehnt und gelöscht.');
    }
    redirect('/admin');
}

function admin_school_create(): never
{
    Auth::require('admin');
    $name = str_limit(post('name'), 150);
    $city = str_limit(post('city'), 100);
    if (mb_strlen($name) < 2) {
        flash('error', 'Bitte einen Schulnamen angeben.');
        redirect('/admin');
    }
    $id = Db::insert('INSERT INTO schools (name, city) VALUES (?, ?)', [$name, $city]);
    flash('success', 'Schule „' . $name . '“ angelegt. Lege jetzt eine Lehrkraft (Schuladmin) an.');
    redirect('/admin/schulen/' . $id);
}

function admin_school_show(int $id): never
{
    Auth::require('admin');
    $school = Db::one('SELECT * FROM schools WHERE id = ?', [$id]) ?? abort(404);
    view('admin/school', [
        'title' => $school['name'],
        'school' => $school,
        'teachers' => school_teachers($id),
        'credentials' => unstash('credentials'),
    ]);
}

function admin_school_update(int $id): never
{
    Auth::require('admin');
    $name = str_limit(post('name'), 150);
    if (mb_strlen($name) < 2) {
        flash('error', 'Bitte einen Schulnamen angeben.');
    } else {
        Db::run('UPDATE schools SET name = ?, city = ? WHERE id = ?', [$name, str_limit(post('city'), 100), $id]);
        flash('success', 'Gespeichert.');
    }
    redirect('/admin/schulen/' . $id);
}

function admin_school_delete(int $id): never
{
    Auth::require('admin');
    $school = Db::one('SELECT * FROM schools WHERE id = ?', [$id]);
    if ($school) {
        if (post('confirm_name') !== $school['name']) {
            flash('error', 'Zum Löschen bitte den Schulnamen exakt eintippen.');
            redirect('/admin/schulen/' . $id);
        }
        Db::run('DELETE FROM schools WHERE id = ?', [$id]);
        flash('success', 'Schule „' . $school['name'] . '“ mit allen Konten und Messwerten gelöscht.');
    }
    redirect('/admin');
}

function school_teachers(int $schoolId): array
{
    return Db::all(
        "SELECT t.*,
                (SELECT COUNT(*) FROM users g WHERE g.teacher_id = t.id) AS groups_count,
                (SELECT COUNT(*) FROM stations st JOIN users g ON g.id = st.group_id WHERE g.teacher_id = t.id) AS stations
           FROM users t WHERE t.role = 'teacher' AND t.school_id = ? ORDER BY t.display_name",
        [$schoolId]
    );
}

// ======================= System / Updates =======================

function admin_system(): never
{
    Auth::require('admin');
    view('admin/system', [
        'title' => 'System & Updates',
        'pending' => array_keys(Migrator::pending()),
        'applied' => Migrator::applied(),
        'checks' => setup_checks(),
        'isGit' => Updater::isGitCheckout(),
        'repo' => Updater::repo(),
        'repoFromConfig' => (string) config('update_repo', '') !== '',
        'latest' => unstash('latest'),
        'backups' => Updater::backups(),
        'registrationOpen' => registration_open(),
        'adminEmail' => Mailer::adminRecipient(),
        'mailFrom' => Mailer::from(),
        'mailProblem' => Mailer::problem(),
        'serverHost' => preg_replace('/:\d+$/', '', (string) parse_url(absolute_url('/'), PHP_URL_HOST)),
        'readings' => (int) Db::value('SELECT COUNT(*) FROM readings'),
        'retention' => (int) config('retention_days', 365),
        'imprint' => (string) setting('imprint_text', ''),
        'privacy' => (string) setting('privacy_text', '') ?: default_privacy_text(),
    ]);
}

function admin_settings_save(): never
{
    Auth::require('admin');
    set_setting('registration_open', post('registration_open') === '1' ? '1' : '0');
    $email = post('admin_email');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Die E-Mail-Adresse ist ungültig.');
        redirect('/admin/system');
    }
    set_setting('admin_email', $email);
    $from = post('mail_from');
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Die Absenderadresse ist ungültig.');
        redirect('/admin/system');
    }
    set_setting('mail_from', $from);
    $repo = post('update_repo');
    if ($repo !== '' && !preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
        flash('error', 'Repository bitte im Format „besitzer/name“ angeben.');
        redirect('/admin/system');
    }
    set_setting('update_repo', $repo);
    flash('success', 'Einstellungen gespeichert.');
    redirect('/admin/system');
}

function admin_test_mail(): never
{
    $u = Auth::require('admin');
    $to = Mailer::adminRecipient() ?: (string) ($u['email'] ?? '');
    if (Mailer::from() === '' || $to === '') {
        flash('error', 'Bitte zuerst Absender- und Empfängeradresse eintragen und speichern.');
        redirect('/admin/system');
    }
    $ok = Mailer::send($to, 'EasyWetter: Test-E-Mail', "Hallo,\n\ndiese Test-E-Mail zeigt: Der E-Mail-Versand von EasyWetter funktioniert.\n\nAb jetzt bekommst du eine Nachricht, wenn sich eine Schule registriert.\n\n" . absolute_url('admin') . "\n");
    flash($ok ? 'success' : 'error', $ok
        ? "Test-E-Mail an $to wurde abgeschickt. Kommt sie nicht an, bitte auch im Spam-Ordner nachsehen."
        : 'Der Server hat den Versand abgelehnt. Prüfe, ob die Absenderadresse zu einer Domain deines Hosting-Pakets gehört.');
    redirect('/admin/system');
}

function admin_legal_save(): never
{
    Auth::require('admin');
    set_setting('imprint_text', mb_substr((string) ($_POST['imprint'] ?? ''), 0, 20000));
    set_setting('privacy_text', mb_substr((string) ($_POST['privacy'] ?? ''), 0, 50000));
    flash('success', 'Impressum und Datenschutzerklärung gespeichert.');
    redirect('/admin/system');
}

function admin_migrate(): never
{
    Auth::require('admin');
    $done = Migrator::run();
    set_setting('app_version', EW_VERSION);
    flash('success', $done ? 'Datenbank aktualisiert: ' . implode(', ', $done) : 'Die Datenbank ist bereits aktuell.');
    redirect('/admin/system');
}

function admin_update_upload(): never
{
    Auth::require('admin');
    $f = $_FILES['package'] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        flash('error', 'Bitte eine Update-Datei (ZIP) auswählen. Achtung: Der Server erlaubt evtl. nur kleine Uploads (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').');
        redirect('/admin/system');
    }
    admin_apply_update($f['tmp_name']);
}

function admin_update_check(): never
{
    Auth::require('admin');
    try {
        stash('latest', Updater::checkLatest());
    } catch (Throwable $e) {
        flash('error', 'Update-Prüfung fehlgeschlagen: ' . $e->getMessage());
    }
    redirect('/admin/system');
}

function admin_update_install(): never
{
    Auth::require('admin');
    try {
        $zip = Updater::downloadLatest();
    } catch (Throwable $e) {
        flash('error', 'Download fehlgeschlagen: ' . $e->getMessage());
        redirect('/admin/system');
    }
    admin_apply_update($zip, true);
}

function admin_apply_update(string $zipPath, bool $deleteAfter = false): never
{
    try {
        $version = Updater::applyZip($zipPath);
        flash('success', "Update auf Version $version installiert. Datenbank-Änderungen wurden automatisch angewendet.");
    } catch (Throwable $e) {
        flash('error', 'Update fehlgeschlagen: ' . $e->getMessage());
    } finally {
        if ($deleteAfter) {
            @unlink($zipPath);
        }
    }
    // Der nächste Aufruf läuft mit dem neuen Code und führt Migrationen automatisch aus
    redirect('/admin/system');
}

// ======================= Lehrkräfte (Oberadmin + Schuladmin) =======================

function teacher_manage_back(array $u, array $teacherOrSchool): string
{
    $fallback = $u['role'] === 'admin' ? '/admin/schulen/' . (int) $teacherOrSchool['school_id'] : '/lehrer/kollegium';
    return safe_back(post('back'), $fallback);
}

function teacher_create(): never
{
    $u = Auth::require('admin', 'teacher');
    if ($u['role'] === 'teacher' && (int) $u['is_school_admin'] !== 1) {
        abort(403);
    }
    $schoolId = $u['role'] === 'admin' ? (int) post('school_id') : (int) $u['school_id'];
    if (!Db::value('SELECT id FROM schools WHERE id = ?', [$schoolId])) {
        flash('error', 'Bitte eine Schule auswählen.');
        redirect($u['role'] === 'admin' ? '/admin' : '/lehrer/kollegium');
    }
    $back = teacher_manage_back($u, ['school_id' => $schoolId]);
    $name = str_limit(post('display_name'), 120);
    $username = post('username');
    $email = post('email');
    if (mb_strlen($name) < 2) {
        flash('error', 'Bitte den Namen der Lehrkraft angeben.');
        redirect($back);
    }
    if (!valid_username($username)) {
        flash('error', 'Benutzername: 3–60 Zeichen, nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich.');
        redirect($back);
    }
    if (username_taken($username)) {
        flash('error', 'Der Benutzername „' . $username . '“ ist schon vergeben.');
        redirect($back);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Die E-Mail-Adresse ist ungültig.');
        redirect($back);
    }
    $password = friendly_password();
    Db::insert(
        "INSERT INTO users (role, school_id, username, display_name, email, password_hash, is_school_admin, must_change_password)
         VALUES ('teacher', ?, ?, ?, ?, ?, ?, 1)",
        [$schoolId, $username, $name, $email ?: null, password_hash($password, PASSWORD_DEFAULT), post('is_school_admin') === '1' ? 1 : 0]
    );
    stash('credentials', [
        'title' => 'Zugang für ' . $name,
        'hint' => 'Beim ersten Anmelden muss ein eigenes Passwort vergeben werden.',
        'username' => $username,
        'password' => $password,
    ]);
    flash('success', 'Lehrkraft angelegt.');
    redirect($back);
}

function teacher_reset_password(int $id): never
{
    $u = Auth::require('admin', 'teacher');
    $t = Auth::canManageTeacher($u, $id) ?? abort(403);
    if ((int) $t['id'] === (int) $u['id']) {
        flash('error', 'Dein eigenes Passwort änderst du unter „Mein Konto“.');
        redirect(teacher_manage_back($u, $t));
    }
    $password = friendly_password();
    Db::run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    stash('credentials', [
        'title' => 'Neues Passwort für ' . $t['display_name'],
        'hint' => 'Beim nächsten Anmelden muss ein eigenes Passwort vergeben werden.',
        'username' => $t['username'],
        'password' => $password,
    ]);
    redirect(teacher_manage_back($u, $t));
}

function teacher_toggle_active(int $id): never
{
    $u = Auth::require('admin', 'teacher');
    $t = Auth::canManageTeacher($u, $id) ?? abort(403);
    if ((int) $t['id'] === (int) $u['id']) {
        flash('error', 'Du kannst dich nicht selbst deaktivieren.');
    } else {
        $new = (int) $t['is_active'] === 1 ? 0 : 1;
        Db::run('UPDATE users SET is_active = ? WHERE id = ?', [$new, $id]);
        flash('success', $t['display_name'] . ($new ? ' ist wieder aktiv.' : ' wurde deaktiviert. Die Gruppen dieser Lehrkraft können sich nicht mehr anmelden und keine Daten senden.'));
    }
    redirect(teacher_manage_back($u, $t));
}

function teacher_toggle_school_admin(int $id): never
{
    $u = Auth::require('admin', 'teacher');
    $t = Auth::canManageTeacher($u, $id) ?? abort(403);
    if ((int) $t['id'] === (int) $u['id']) {
        flash('error', 'Du kannst deine eigenen Rechte nicht ändern.');
    } else {
        $new = (int) $t['is_school_admin'] === 1 ? 0 : 1;
        Db::run('UPDATE users SET is_school_admin = ? WHERE id = ?', [$new, $id]);
        flash('success', $t['display_name'] . ($new ? ' ist jetzt Schuladmin.' : ' ist kein Schuladmin mehr.'));
    }
    redirect(teacher_manage_back($u, $t));
}

function teacher_delete(int $id): never
{
    $u = Auth::require('admin', 'teacher');
    $t = Auth::canManageTeacher($u, $id) ?? abort(403);
    if ((int) $t['id'] === (int) $u['id']) {
        flash('error', 'Du kannst dein eigenes Konto nicht löschen.');
    } else {
        Db::run('DELETE FROM users WHERE id = ?', [$id]);
        flash('success', $t['display_name'] . ' wurde mit allen Gruppen, Stationen und Messwerten gelöscht.');
    }
    redirect(teacher_manage_back($u, $t));
}
