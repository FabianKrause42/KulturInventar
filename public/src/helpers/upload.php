<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Bild-Upload-Hilfsfunktion
// Verarbeitet ein hochgeladenes Bild (GD), speichert es unter
// uploads/{inventarnummer}_{timestamp}.jpg und trägt es in
// inventar_bilder ein.
// ---------------------------------------------------------------

/**
 * @param array  $file          $_FILES['bild']
 * @param string $inventarnummer z.B. "0015"
 * @param int    $inventar_id   ID aus inventar.id
 * @param PDO    $pdo
 * @return string               Dateiname (z.B. "0015_1742890000.jpg")
 * @throws RuntimeException     Bei ungültigem Format, Größe oder GD-Fehler
 */
function verarbeite_bild_upload(array $file, string $inventarnummer, int $inventar_id, PDO $pdo): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload-Fehler (Code ' . $file['error'] . ')');
    }

    // MIME via Magic-Bytes prüfen; als Fallback den vom Browser gesendeten Typ verwenden
    $mime    = @mime_content_type($file['tmp_name']) ?: '';
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($mime, $allowed, true)) {
        // Fallback: vom Client gemeldeter MIME-Typ (wird bei Canvas-Blobs korrekt gesetzt)
        $clientMime = strtolower(trim($file['type'] ?? ''));
        if (in_array($clientMime, $allowed, true)) {
            $mime = $clientMime;
        } else {
            throw new RuntimeException('Ungültiges Dateiformat – nur JPG, PNG, WEBP oder GIF erlaubt.');
        }
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Datei zu groß (max. 10 MB).');
    }

    $uploadDir = __DIR__ . '/../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Dateiname: inventarnummer_timestamp.jpg
    $dateiname = $inventarnummer . '_' . time() . '.jpg';
    $zielPfad  = $uploadDir . $dateiname;

    // Bild mit GD laden
    $image = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
        'image/gif'  => imagecreatefromgif($file['tmp_name']),
        default      => false,
    };

    if ($image === false) {
        throw new RuntimeException('Bild konnte nicht verarbeitet werden.');
    }

    // Skalieren auf max. 1200 × 1200 px
    $maxSize = 1200;
    $origW   = imagesx($image);
    $origH   = imagesy($image);

    if ($origW > $maxSize || $origH > $maxSize) {
        $ratio  = min($maxSize / $origW, $maxSize / $origH);
        $newW   = (int) ($origW * $ratio);
        $newH   = (int) ($origH * $ratio);
        $canvas = imagecreatetruecolor($newW, $newH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagedestroy($image);
        $image = $canvas;
    }

    imagejpeg($image, $zielPfad, 85);
    imagedestroy($image);

    // DB: nächste Reihenfolge ermitteln und eintragen
    $maxRei = $pdo->prepare(
        'SELECT COALESCE(MAX(reihenfolge), 0) FROM inventar_bilder WHERE inventar_id = ?'
    );
    $maxRei->execute([$inventar_id]);
    $nextRei = (int) $maxRei->fetchColumn() + 1;

    $pdo->prepare(
        'INSERT INTO inventar_bilder (inventar_id, dateiname, reihenfolge) VALUES (?, ?, ?)'
    )->execute([$inventar_id, $dateiname, $nextRei]);

    return $dateiname;
}
