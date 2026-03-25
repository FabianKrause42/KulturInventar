<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/session.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/placeholder.php';

header('Content-Type: application/json; charset=utf-8');

// Nur eingeloggte Nutzer
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

$q      = trim($_GET['q']      ?? '');
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit  = 20;

if (strlen($q) < 3) {
    echo json_encode(['results' => [], 'total' => 0, 'has_more' => false]);
    exit;
}

if ($pdo === null) {
    echo json_encode(['results' => [], 'total' => 0, 'has_more' => false]);
    exit;
}

$like = '%' . $q . '%';

// Gesamtanzahl
$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM inventar
     WHERE inventarnummer LIKE ?
        OR bezeichnung    LIKE ?
        OR kategorie      LIKE ?
        OR standort       LIKE ?
        OR bemerkung      LIKE ?'
);
$countStmt->execute([$like, $like, $like, $like, $like]);
$total = (int)$countStmt->fetchColumn();

// Ergebnisse mit Pagination
$stmt = $pdo->prepare(
    'SELECT i.id, i.inventarnummer, i.bezeichnung, i.kategorie, i.standort,
            (SELECT dateiname FROM inventar_bilder
             WHERE inventar_id = i.id
             ORDER BY reihenfolge ASC LIMIT 1) AS bild_dateiname
     FROM inventar i
     WHERE i.inventarnummer LIKE ?
        OR i.bezeichnung    LIKE ?
        OR i.kategorie      LIKE ?
        OR i.standort       LIKE ?
        OR i.bemerkung      LIKE ?
     ORDER BY i.bezeichnung ASC
     LIMIT ? OFFSET ?'
);
$stmt->execute([$like, $like, $like, $like, $like, $limit, $offset]);
$rows = $stmt->fetchAll();

$results = array_map(fn($r) => [
    'id'            => (int)$r['id'],
    'inventarnummer'=> $r['inventarnummer'],
    'bezeichnung'   => $r['bezeichnung'],
    'standort'      => $r['standort'] ?? '',
    'thumb'         => get_thumbnail($r),
], $rows);

echo json_encode([
    'results'  => $results,
    'total'    => $total,
    'has_more' => ($offset + $limit) < $total,
]);
