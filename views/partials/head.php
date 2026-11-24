<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? '') !== '' ? $title . ' · EasyWetter' : 'EasyWetter') ?></title>
    <meta name="description" content="EasyWetter – Schülerinnen und Schüler bauen Wetterstationen mit ESP32 und gestalten ihr eigenes Live-Dashboard.">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(url('assets/icon-192.png')) ?>">
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="<?= e(session_status() === PHP_SESSION_ACTIVE ? csrf_token() : '') ?>">
</head>
