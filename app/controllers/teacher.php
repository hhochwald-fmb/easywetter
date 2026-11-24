<?php
declare(strict_types=1);

function teacher_index(): never
{
    $u = Auth::require('teacher');
    $groups = Db::all(
        "SELECT g.*,
                (SELECT COUNT(*) FROM stations s WHERE s.group_id = g.id) AS stations,
                (SELECT MAX(s.last_seen_at) FROM stations s WHERE s.group_id = g.id) AS last_seen,
                d.is_public
           FROM users g
      LEFT JOIN dashboards d ON d.group_id = g.id
          WHERE g.role = 'group' AND g.teacher_id = ?
       ORDER BY g.display_name",
        [$u['id']]
    );
    view('teacher/index', [
        'title' => 'Meine Gruppen',
        'user' => $u,
        'groups' => $groups,
        'credentials' => unstash('credentials'),
    ]);
}

function teacher_colleagues(): never
{
    $u = Auth::require('teacher');
    if ((int) $u['is_school_admin'] !== 1) {
        abort(403, 'Nur Schuladmins können das Kollegium verwalten.');
    }
    view('teacher/colleagues', [
        'title' => 'Kollegium',
        'user' => $u,
        'teachers' => school_teachers((int) $u['school_id']),
        'credentials' => unstash('credentials'),
    ]);
}

function teacher_overview_data(int $teacherId): array
{
    $groups = Db::all("SELECT id, display_name FROM users WHERE role = 'group' AND teacher_id = ? AND is_active = 1 ORDER BY display_name", [$teacherId]);
    foreach ($groups as &$g) {
        $g['stations'] = Dashboard::stations((int) $g['id']);
        $g['config'] = Dashboard::load($g + ['display_name' => $g['display_name']])['config'];
    }
    return $groups;
}

function teacher_overview(): never
{
    $u = Auth::require('teacher');
    view('teacher/overview', [
        'title' => 'Klassenübersicht',
        'teacher' => $u,
        'groups' => teacher_overview_data((int) $u['id']),
        'public' => false,
    ]);
}

function teacher_class_link(): never
{
    $u = Auth::require('teacher');
    $slug = post('enable') === '1' ? ($u['public_slug'] ?: random_slug(10)) : null;
    Db::run('UPDATE users SET public_slug = ? WHERE id = ?', [$slug, $u['id']]);
    flash('success', $slug ? 'Die Klassenübersicht ist jetzt öffentlich erreichbar.' : 'Die Klassenübersicht ist nicht mehr öffentlich.');
    redirect('/lehrer/uebersicht');
}

// ---------- Gruppen verwalten (Lehrkraft) ----------

function group_create(): never
{
    $u = Auth::require('teacher');
    $name = str_limit(post('display_name'), 80);
    if (mb_strlen($name) < 2) {
        flash('error', 'Bitte einen Gruppennamen angeben.');
        redirect('/lehrer');
    }
    $username = post('username');
    if ($username === '') {
        $base = slugify($name, 30) ?: 'gruppe';
        do {
            $username = $base . '-' . random_slug(4);
        } while (username_taken($username));
    } elseif (!valid_username($username)) {
        flash('error', 'Benutzername: 3–60 Zeichen, nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich.');
        redirect('/lehrer');
    } elseif (username_taken($username)) {
        flash('error', 'Der Benutzername „' . $username . '“ ist schon vergeben.');
        redirect('/lehrer');
    }
    $password = friendly_password();
    Db::insert(
        "INSERT INTO users (role, school_id, teacher_id, username, display_name, password_hash) VALUES ('group', ?, ?, ?, ?, ?)",
        [$u['school_id'], $u['id'], $username, $name, password_hash($password, PASSWORD_DEFAULT)]
    );
    stash('credentials', [
        'title' => 'Zugang für „' . $name . '“',
        'hint' => 'Gib diese Zugangsdaten an die Gruppe weiter. Das Passwort wird nur jetzt angezeigt – du kannst später jederzeit ein neues erzeugen.',
        'username' => $username,
        'password' => $password,
    ]);
    flash('success', 'Gruppe „' . $name . '“ angelegt.');
    redirect('/lehrer');
}

function group_reset_password(int $id): never
{
    $u = Auth::require('teacher', 'admin');
    $g = Auth::canManageGroup($u, $id) ?? abort(403);
    $password = friendly_password();
    Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    stash('credentials', [
        'title' => 'Neues Passwort für „' . $g['display_name'] . '“',
        'hint' => 'Das alte Passwort gilt ab sofort nicht mehr. Die Stationen senden mit ihrem Token unverändert weiter.',
        'username' => $g['username'],
        'password' => $password,
    ]);
    redirect(safe_back(post('back'), '/lehrer'));
}

function group_rename(int $id): never
{
    $u = Auth::require('teacher', 'admin');
    Auth::canManageGroup($u, $id) ?? abort(403);
    $name = str_limit(post('display_name'), 80);
    if (mb_strlen($name) < 2) {
        flash('error', 'Bitte einen Gruppennamen angeben.');
    } else {
        Db::run('UPDATE users SET display_name = ? WHERE id = ?', [$name, $id]);
        flash('success', 'Gruppe umbenannt.');
    }
    redirect(safe_back(post('back'), '/lehrer'));
}

function group_delete(int $id): never
{
    $u = Auth::require('teacher', 'admin');
    $g = Auth::canManageGroup($u, $id) ?? abort(403);
    Db::run('DELETE FROM users WHERE id = ?', [$id]);
    flash('success', 'Gruppe „' . $g['display_name'] . '“ mit allen Stationen und Messwerten gelöscht.');
    redirect('/lehrer');
}
