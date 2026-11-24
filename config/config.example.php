<?php
// EasyWetter – Beispiel-Konfiguration. Kopieren nach config/config.php und anpassen
// (oder einfach die Seite aufrufen: der Einrichtungsassistent erzeugt die Datei automatisch).
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'easywetter',
        'user' => 'BENUTZER',
        'pass' => 'PASSWORT',
    ],
    // Vollständige Adresse, falls die automatische Erkennung nicht passt, z. B. 'https://wetter.meine-schule.de'
    'base_url' => '',
    // Benachrichtigung bei neuen Registrierungen (leer = aus)
    'admin_email' => '',
    // Absenderadresse für E-Mails (leer = keine E-Mails verschicken)
    'mail_from' => '',
    // Messwerte, die älter sind, werden automatisch gelöscht
    'retention_days' => 365,
    // Mindestabstand zwischen zwei Uploads einer Station (Sekunden)
    'ingest_min_interval' => 5,
    // GitHub-Repository für Online-Updates, z. B. 'meinname/easywetter' (leer = aus)
    'update_repo' => '',
    'registration_open' => true,
    'debug' => false,
];
