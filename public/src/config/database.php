<?php
declare(strict_types=1);

$pdo = null;

$configFile = __DIR__ . '/config.local.php';

if (!file_exists($configFile)) {
    return; // Kein Fehler – Seiten rendern im DB-losen Modus
}

$config = require $configFile;

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['db_host'],
    $config['db_name'],
    $config['db_charset']
);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
} catch (PDOException $e) {
    $pdo = null; // DB nicht erreichbar – kein fataler Fehler
}
