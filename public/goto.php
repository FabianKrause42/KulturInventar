<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// goto.php – Einstiegspunkt für QR-Code-Scans von der Handykamera
//
// Aufruf:  /kulturinventar/goto.php?id=0001
// Ablauf:
//   1. Nicht eingeloggt → Login-Seite, danach Rückkehr hierher
//   2. Inventarnummer in DB nachschlagen
//      a) Gefunden  → artikel.php?id={db_id}
//      b) Nicht da  → artikel_neu.php?inventarnummer=0001
// ---------------------------------------------------------------

require_once __DIR__ . '/src/auth/session.php';
require_login(); // leitet ggf. zu login.php?redirect=... und kehrt danach zurück

require_once __DIR__ . '/src/config/database.php';

// Inventarnummer aus URL holen und bereinigen
$inventarnummer = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', $_GET['id']) : '';
$inventarnummer = str_pad($inventarnummer, 4, '0', STR_PAD_LEFT);

if ($inventarnummer === '0000' || strlen($inventarnummer) !== 4) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// DB-Lookup
$stmt = $pdo->prepare('SELECT id FROM inventar WHERE inventarnummer = ? LIMIT 1');
$stmt->execute([$inventarnummer]);
$artikel = $stmt->fetch();

if ($artikel) {
    // Artikel existiert → Detailseite
    header('Location: ' . BASE_URL . '/artikel.php?id=' . $artikel['id'], true, 302);
} else {
    // Artikel existiert nicht → Neuanlage mit vorausgefüllter Inventarnummer
    header('Location: ' . BASE_URL . '/artikel_neu.php?inventarnummer=' . urlencode($inventarnummer), true, 302);
}
exit;
