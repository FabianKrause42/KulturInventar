<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/auth/session.php';
require_once __DIR__ . '/../../../src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

$inventarnummer = trim($_GET['inventarnummer'] ?? '');

if (!preg_match('/^\d{4}$/', $inventarnummer)) {
    echo json_encode(['id' => null]);
    exit;
}

if ($pdo === null) {
    echo json_encode(['id' => null]);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM inventar WHERE inventarnummer = ? LIMIT 1');
$stmt->execute([$inventarnummer]);
$row = $stmt->fetch();

echo json_encode(['id' => $row ? (int)$row['id'] : null]);
