<?php
// ─── Gemeinsame <head>-Meta-Tags für alle Seiten ───────────────────────
// Einbinden mit: require_once __DIR__ . '/../src/helpers/head_meta.php';
// Muss NACH der BASE_URL-Definition aufgerufen werden.
?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1b1b1b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KulturInventar">
    <meta name="application-name" content="KulturInventar">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/logo_32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= BASE_URL ?>/assets/img/logo_64.png">

    <!-- iOS Home-Screen Icon -->
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/logo_128.png">

    <!-- Web-App-Manifest (für Android "Zum Startbildschirm") -->
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
