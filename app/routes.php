<?php
declare(strict_types=1);

foreach (glob(EW_ROOT . '/app/controllers/*.php') ?: [] as $f) {
    require $f;
}

$path = current_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isDeviceApi = $path === '/api/v1/upload' || $path === '/api/v1/ping';

// --- Noch nicht eingerichtet? -> Setup-Assistent ---
if ($GLOBALS['ew_config'] === null) {
    if (str_starts_with($path, '/api/')) {
        json_out(['ok' => false, 'error' => 'EasyWetter ist noch nicht eingerichtet.'], 503);
    }
    Auth::startSession();
    setup_controller($method, false);
}

$state = Migrator::autoUpgrade();

if (!$state['installed']) {
    if (str_starts_with($path, '/api/')) {
        json_out(['ok' => false, 'error' => 'EasyWetter ist noch nicht eingerichtet.'], 503);
    }
    Auth::startSession();
    setup_controller($method, true);
}

if (!$isDeviceApi) {
    Auth::startSession();
}

// Datensparsamkeit: gelegentlich alte personenbezogene Daten löschen
if (random_int(1, 100) === 1) {
    housekeeping();
}

/** [Methode, Muster, Handler] – Muster ohne Begrenzer, Gruppen werden als int übergeben */
$routes = [
    // Öffentlich
    ['GET', '/', 'page_landing'],
    ['GET', '/anleitung', 'page_guide'],
    ['GET', '/impressum', 'page_imprint'],
    ['GET', '/datenschutz', 'page_privacy'],
    ['GET', '/login', 'login_form'],
    ['POST', '/login', 'login_submit'],
    ['POST', '/logout', 'logout_action'],
    ['GET', '/registrieren', 'register_form'],
    ['POST', '/registrieren', 'register_submit'],
    ['GET', '/d/([a-z0-9]{6,32})', 'public_dashboard'],
    ['GET', '/klasse/([a-z0-9]{6,32})', 'public_class'],

    // Konto (alle Rollen)
    ['GET', '/konto', 'account_page'],
    ['POST', '/konto/passwort', 'account_password'],
    ['POST', '/konto/profil', 'account_profile'],

    // Oberadmin
    ['GET', '/admin', 'admin_index'],
    ['POST', '/admin/registrierungen/(\d+)/freigeben', 'admin_registration_approve'],
    ['POST', '/admin/registrierungen/(\d+)/ablehnen', 'admin_registration_reject'],
    ['POST', '/admin/schulen/neu', 'admin_school_create'],
    ['GET', '/admin/schulen/(\d+)', 'admin_school_show'],
    ['POST', '/admin/schulen/(\d+)/bearbeiten', 'admin_school_update'],
    ['POST', '/admin/schulen/(\d+)/loeschen', 'admin_school_delete'],
    ['GET', '/admin/system', 'admin_system'],
    ['POST', '/admin/system/einstellungen', 'admin_settings_save'],
    ['POST', '/admin/system/rechtliches', 'admin_legal_save'],
    ['POST', '/admin/system/migrieren', 'admin_migrate'],
    ['POST', '/admin/system/update-hochladen', 'admin_update_upload'],
    ['POST', '/admin/system/update-pruefen', 'admin_update_check'],
    ['POST', '/admin/system/update-installieren', 'admin_update_install'],

    // Lehrkräfte verwalten (Oberadmin + Schuladmin)
    ['POST', '/lehrkraefte/neu', 'teacher_create'],
    ['POST', '/lehrkraefte/(\d+)/passwort', 'teacher_reset_password'],
    ['POST', '/lehrkraefte/(\d+)/status', 'teacher_toggle_active'],
    ['POST', '/lehrkraefte/(\d+)/schuladmin', 'teacher_toggle_school_admin'],
    ['POST', '/lehrkraefte/(\d+)/loeschen', 'teacher_delete'],

    // Lehrkraft
    ['GET', '/lehrer', 'teacher_index'],
    ['GET', '/lehrer/kollegium', 'teacher_colleagues'],
    ['GET', '/lehrer/uebersicht', 'teacher_overview'],
    ['POST', '/lehrer/klassenlink', 'teacher_class_link'],
    ['POST', '/gruppen/neu', 'group_create'],
    ['POST', '/gruppen/(\d+)/passwort', 'group_reset_password'],
    ['POST', '/gruppen/(\d+)/umbenennen', 'group_rename'],
    ['POST', '/gruppen/(\d+)/loeschen', 'group_delete'],

    // Gruppe (auch für zuständige Lehrkraft/Admin)
    ['GET', '/gruppe', 'group_home'],
    ['GET', '/gruppen/(\d+)', 'group_dashboard'],
    ['GET', '/gruppen/(\d+)/stationen', 'group_stations'],
    ['GET', '/gruppen/(\d+)/gestalten', 'group_designer'],
    ['POST', '/gruppen/(\d+)/gestalten', 'group_designer_save'],
    ['POST', '/gruppen/(\d+)/freigabe', 'group_share'],
    ['POST', '/gruppen/(\d+)/stationen/neu', 'station_create'],
    ['POST', '/stationen/(\d+)/token', 'station_token'],
    ['POST', '/stationen/(\d+)/umbenennen', 'station_rename'],
    ['POST', '/stationen/(\d+)/leeren', 'station_clear'],
    ['POST', '/stationen/(\d+)/loeschen', 'station_delete'],
    ['GET', '/stationen/(\d+)/export\.csv', 'station_export'],

    // API
    ['POST', '/api/v1/upload', 'api_upload'],
    ['GET', '/api/v1/ping', 'api_ping'],
    ['GET', '/api/v1/data', 'api_data'],
];

$allowed = [];
foreach ($routes as [$m, $pattern, $handler]) {
    if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
        continue;
    }
    if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) {
        $allowed[] = $m;
        continue;
    }
    if ($method === 'POST' && !$isDeviceApi) {
        verify_csrf();
    }
    $args = array_map('intval', array_slice($matches, 1));
    if ($handler === 'public_dashboard' || $handler === 'public_class') {
        $args = array_slice($matches, 1);
    }
    $handler(...$args);
    exit;
}

if ($allowed) {
    if (str_starts_with($path, '/api/')) {
        header('Allow: ' . implode(', ', array_unique($allowed)));
        json_out(['ok' => false, 'error' => 'Methode nicht erlaubt. Erlaubt: ' . implode(', ', array_unique($allowed))], 405);
    }
    abort(405);
}
if (str_starts_with($path, '/api/')) {
    json_out(['ok' => false, 'error' => 'Unbekannter Endpunkt'], 404);
}
abort(404);
