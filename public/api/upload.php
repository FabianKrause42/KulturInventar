<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/session.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/upload.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$inventar_id = (int) ($_POST['inventar_id'] ?? 0);

if ($inventar_id <= 0 || $pdo === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Anfrage']);
    exit;
}

// Inventarnummer für Dateiname holen
$stmt = $pdo->prepare('SELECT inventarnummer FROM inventar WHERE id = ? LIMIT 1');
$stmt->execute([$inventar_id]);
$artikel = $stmt->fetch();

if (!$artikel) {
    http_response_code(404);
    echo json_encode(['error' => 'Artikel nicht gefunden']);
    exit;
}

if (empty($_FILES['bild'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine Datei übermittelt']);
    exit;
}

try {
    $dateiname = verarbeite_bild_upload(
        $_FILES['bild'],
        $artikel['inventarnummer'],
        $inventar_id,
        $pdo
    );

    echo json_encode([
        'success'   => true,
        'dateiname' => $dateiname,
        'url'       => BASE_URL . '/uploads/' . $dateiname,
    ]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
}
