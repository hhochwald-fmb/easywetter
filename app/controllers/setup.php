<?php
declare(strict_types=1);

/**
 * Installationsassistent. Läuft nur, solange EasyWetter nicht eingerichtet ist.
 * Schritt 1: Datenbank-Zugang (schreibt config/config.php)
 * Schritt 2: Oberadmin anlegen
 */
function setup_controller(string $method, bool $hasConfig): never
{
    if (current_path() !== '/setup') {
        redirect('/setup');
    }
    $checks = setup_checks();
    $errors = [];
    $old = $_POST;
    $manualConfig = null;

    if ($method === 'POST') {
        verify_csrf();
        if (!$hasConfig) {
            $db = [
                'host' => post('db_host', 'localhost'),
                'port' => (int) (post('db_port', '3306') ?: 3306),
                'name' => post('db_name'),
                'user' => post('db_user'),
                'pass' => (string) ($_POST['db_pass'] ?? ''),
            ];
            if ($db['name'] === '' || $db['user'] === '') {
                $errors[] = 'Bitte Datenbankname und Benutzer angeben.';
            } else {
                try {
                    Db::connect($db);
                } catch (PDOException $e) {
                    $errors[] = 'Verbindung fehlgeschlagen: ' . $e->getMessage();
                }
            }
            if (!$errors) {
                $content = setup_config_file($db, post('base_url'), post('admin_email'));
                $file = EW_ROOT . '/config/config.php';
                if (is_writable(EW_ROOT . '/config') && file_put_contents($file, $content, LOCK_EX) !== false) {
                    @chmod($file, 0640);
                    redirect('/setup');
                }
                $manualConfig = $content;
            }
        } else {
            $username = post('username');
            $name = str_limit(post('display_name'), 120);
            $email = post('email');
            $pass = (string) ($_POST['password'] ?? '');
            if (!valid_username($username)) {
                $errors[] = 'Benutzername: 3–60 Zeichen, nur Buchstaben, Ziffern, Punkt, Binde- und Unterstrich.';
            }
            if ($name === '') {
                $errors[] = 'Bitte einen Namen angeben.';
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Die E-Mail-Adresse ist ungültig.';
            }
            if ($p = password_problem($pass)) {
                $errors[] = $p;
            } elseif ($pass !== ($_POST['password2'] ?? '')) {
                $errors[] = 'Die Passwörter stimmen nicht überein.';
            }
            if (!$errors) {
                Db::insert(
                    "INSERT INTO users (role, username, display_name, email, password_hash) VALUES ('admin', ?, ?, ?, ?)",
                    [$username, $name, $email ?: null, password_hash($pass, PASSWORD_DEFAULT)]
                );
                if ($email !== '') {
                    set_setting('admin_email', $email);
                }
                set_setting('installed', '1');
                set_setting('registration_open', '1');
                Auth::attempt($username, $pass);
                flash('success', 'EasyWetter ist eingerichtet. Willkommen!');
                redirect('/admin');
            }
        }
    }

    view('setup', [
        'step' => $hasConfig ? 2 : 1,
        'checks' => $checks,
        'errors' => $errors,
        'old' => $old,
        'manualConfig' => $manualConfig,
        'title' => 'Einrichtung',
    ], 'layout_plain');
}

function setup_checks(): array
{
    return [
        ['PHP ' . EW_MIN_PHP . ' oder neuer (gefunden: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, EW_MIN_PHP, '>='), true],
        ['PHP-Erweiterung pdo_mysql', extension_loaded('pdo_mysql'), true],
        ['PHP-Erweiterung mbstring', extension_loaded('mbstring'), true],
        ['Ordner storage/ beschreibbar', is_writable(EW_ROOT . '/storage'), true],
        ['Ordner config/ beschreibbar (sonst Datei von Hand anlegen)', is_writable(EW_ROOT . '/config'), false],
        ['PHP-Erweiterung zip (für Updates per Upload)', class_exists('ZipArchive'), false],
        ['cURL (für Updates aus dem Internet)', function_exists('curl_init'), false],
    ];
}

function setup_config_file(array $db, string $baseUrl, string $adminEmail): string
{
    $export = fn($v) => var_export($v, true);
    return "<?php\n// EasyWetter – Konfiguration (wird bei Updates nicht überschrieben)\nreturn [\n"
        . "    'db' => [\n"
        . "        'host' => {$export($db['host'])},\n"
        . "        'port' => {$export($db['port'])},\n"
        . "        'name' => {$export($db['name'])},\n"
        . "        'user' => {$export($db['user'])},\n"
        . "        'pass' => {$export($db['pass'])},\n"
        . "    ],\n"
        . "    // Vollständige Adresse, falls die automatische Erkennung nicht passt, z. B. 'https://wetter.meine-schule.de'\n"
        . "    'base_url' => {$export(rtrim($baseUrl, '/'))},\n"
        . "    // Benachrichtigung bei neuen Registrierungen (leer = aus)\n"
        . "    'admin_email' => {$export($adminEmail)},\n"
        . "    // Absenderadresse für E-Mails (leer = keine E-Mails verschicken)\n"
        . "    'mail_from' => '',\n"
        . "    // Messwerte, die älter sind, werden automatisch gelöscht\n"
        . "    'retention_days' => 365,\n"
        . "    // Mindestabstand zwischen zwei Uploads einer Station (Sekunden)\n"
        . "    'ingest_min_interval' => 5,\n"
        . "    // GitHub-Repository für Online-Updates, z. B. 'meinname/easywetter' (leer = aus)\n"
        . "    'update_repo' => '',\n"
        . "    'registration_open' => true,\n"
        . "    'debug' => false,\n"
        . "];\n";
}
