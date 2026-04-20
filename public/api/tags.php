<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/session.php';
require_once __DIR__ . '/../src/config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

if ($pdo === null) {
    echo json_encode(['tags' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['tags' => []]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT name FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT 5'
);
$stmt->execute([$q . '%']);
$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(['tags' => array_values($tags)]);
