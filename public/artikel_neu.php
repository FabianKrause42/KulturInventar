<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/session.php';
require_login();

require_once __DIR__ . '/../src/config/database.php';

$fehler   = [];
$erfolg   = false;

$kategorien = ['Audio', 'Licht', 'Video', 'IT', 'Bühne', 'Möbel', 'Kabel', 'Werkzeug', 'Sonstiges'];
$standorte  = ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inventarnummer = trim($_POST['inventarnummer'] ?? '');
    $bezeichnung    = trim($_POST['bezeichnung']    ?? '');
    $kategorie      = trim($_POST['kategorie']      ?? '');
    $standort       = trim($_POST['standort']       ?? '');
    $menge          = (int) ($_POST['menge']        ?? 1);
    $masse          = trim($_POST['masse']          ?? '');
    $bemerkung      = trim($_POST['bemerkung']      ?? '');

    // Validierung
    if ($inventarnummer === '') {
        $fehler[] = 'Inventarnummer ist Pflicht.';
    } elseif (!preg_match('/^\d{4}$/', $inventarnummer)) {
        $fehler[] = 'Inventarnummer muss genau 4 Ziffern haben.';
    }
    if ($bezeichnung === '') {
        $fehler[] = 'Bezeichnung ist Pflicht.';
    }
    if ($kategorie === '' || !in_array($kategorie, $kategorien, true)) {
        $fehler[] = 'Bitte eine gültige Kategorie wählen.';
    }
    if ($menge < 1) {
        $menge = 1;
    }

    if (empty($fehler) && $pdo !== null) {
        // Inventarnummer eindeutig prüfen
        $check = $pdo->prepare('SELECT id FROM inventar WHERE inventarnummer = ?');
        $check->execute([$inventarnummer]);
        if ($check->fetch()) {
            $fehler[] = 'Inventarnummer „' . htmlspecialchars($inventarnummer) . '" ist bereits vergeben.';
        }
    }

    if (empty($fehler) && $pdo !== null) {
        $stmt = $pdo->prepare(
            'INSERT INTO inventar
               (inventarnummer, bezeichnung, kategorie, standort, menge, masse, bemerkung)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $inventarnummer,
            $bezeichnung,
            $kategorie,
            $standort ?: null,
            $menge,
            $masse     ?: null,
            $bemerkung ?: null,
        ]);

        header('Location: /index.php');
        exit;
    }
}

// Formularwerte für Neuanzeige nach Fehler
$f = [
    'inventarnummer' => $_POST['inventarnummer'] ?? '',
    'bezeichnung'    => $_POST['bezeichnung']    ?? '',
    'kategorie'      => $_POST['kategorie']      ?? '',
    'standort'       => $_POST['standort']       ?? '',
    'menge'          => $_POST['menge']          ?? '1',
    'masse'          => $_POST['masse']          ?? '',
    'bemerkung'      => $_POST['bemerkung']      ?? '',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel anlegen – KulturInventar</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>

    <main>

        <?php if (!empty($fehler)): ?>
            <div class="error-message">
                <?php foreach ($fehler as $f_msg): ?>
                    <p><?= htmlspecialchars($f_msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/artikel_neu.php" autocomplete="off" novalidate>

            <!-- ── Inventarnummer + QR ──────────────── -->
            <div class="form-header">
                <input
                    type="text"
                    name="inventarnummer"
                    placeholder="Inventarnummer"
                    value="<?= htmlspecialchars($f['inventarnummer']) ?>"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    inputmode="numeric"
                    required
                    autofocus
                >
                <button
                    type="button"
                    class="btn-scan-form"
                    title="QR-Code scannen (bald verfügbar)"
                    disabled
                    aria-label="QR-Code scannen"
                >
                    <img src="/assets/img/icons/qr-code.png" width="30" height="30" alt="">
                </button>
            </div>

            <!-- ── Formularfelder ────────────────────── -->
            <div class="form-fields">

                <input
                    type="text"
                    name="bezeichnung"
                    placeholder="Bezeichnung"
                    value="<?= htmlspecialchars($f['bezeichnung']) ?>"
                    required
                >

                <select name="kategorie">
                    <option value="" disabled <?= $f['kategorie'] === '' ? 'selected' : '' ?>>Kategorie</option>
                    <?php foreach ($kategorien as $kat): ?>
                        <option value="<?= htmlspecialchars($kat) ?>"
                            <?= $f['kategorie'] === $kat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="standort">
                    <option value="" <?= $f['standort'] === '' ? 'selected' : '' ?>>Standort</option>
                    <?php foreach ($standorte as $ort): ?>
                        <option value="<?= htmlspecialchars($ort) ?>"
                            <?= $f['standort'] === $ort ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ort) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input
                    type="number"
                    name="menge"
                    placeholder="Menge"
                    value="<?= htmlspecialchars($f['menge']) ?>"
                    min="1"
                >

                <input
                    type="text"
                    name="masse"
                    placeholder="Maße"
                    value="<?= htmlspecialchars($f['masse']) ?>"
                >

                <textarea
                    name="bemerkung"
                    placeholder="Bemerkung"
                ><?= htmlspecialchars($f['bemerkung']) ?></textarea>

            </div>

            <!-- ── Aktionen ──────────────────────────── -->
            <div class="form-actions">
                <button type="submit" class="btn btn-save">Artikel speichern</button>
                <a href="/index.php" class="btn btn-back">Zurück zur Suche</a>
            </div>

        </form>

    </main>

</body>
</html>
