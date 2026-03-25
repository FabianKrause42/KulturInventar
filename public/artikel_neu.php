<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/upload.php';

$fehler   = [];
$erfolg   = false;

$kategorien = ['Audio', 'Licht', 'Video', 'IT', 'Bühne', 'Möbel', 'Kabel', 'Werkzeug', 'Sonstiges'];
$standorte  = ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

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
        $newId = (int) $pdo->lastInsertId();

        // Bild hochladen falls mitgeschickt
        if (!empty($_FILES['bild']) && $_FILES['bild']['error'] === UPLOAD_ERR_OK) {
            try {
                verarbeite_bild_upload($_FILES['bild'], $inventarnummer, $newId, $pdo);
            } catch (RuntimeException $e) {
                // Bild-Fehler ist nicht fatal – Artikel wurde angelegt
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['redirect' => BASE_URL . '/artikel.php?id=' . $newId . '&neu=1']);
            exit;
        }

        header('Location: ' . BASE_URL . '/artikel.php?id=' . $newId . '&neu=1');
        exit;
    }

    if ($isAjax && !empty($fehler)) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['errors' => $fehler]);
        exit;
    }
}

// Formularwerte: POST hat Priorität (Fehlerfall), dann GET (Rückkehr vom Scanner), dann leer
$f = [
    'inventarnummer' => $_POST['inventarnummer'] ?? $_GET['inventarnummer'] ?? '',
    'bezeichnung'    => $_POST['bezeichnung']    ?? $_GET['bezeichnung']    ?? '',
    'kategorie'      => $_POST['kategorie']      ?? $_GET['kategorie']      ?? '',
    'standort'       => $_POST['standort']       ?? $_GET['standort']       ?? '',
    'menge'          => $_POST['menge']          ?? $_GET['menge']          ?? '1',
    'masse'          => $_POST['masse']          ?? $_GET['masse']          ?? '',
    'bemerkung'      => $_POST['bemerkung']      ?? $_GET['bemerkung']      ?? '',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artikel anlegen – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <script src="<?= BASE_URL ?>/assets/js/crop.js"></script>
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

        <form method="post" action="<?= BASE_URL ?>/artikel_neu.php" autocomplete="off" novalidate enctype="multipart/form-data">

            <!-- ── Vorschaubild ──────────────────────── -->
            <label for="bild-input" class="form-image-placeholder" id="bild-preview-wrap">
                <img src="<?= BASE_URL ?>/assets/img/icons/camera.png" alt="" class="form-image-icon" id="bild-preview-icon">
                <span id="bild-preview-text">Bild hinzufügen</span>
            </label>
            <input type="file" name="bild" id="bild-input" accept="image/*" style="display:none">

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
                    id="btn-scan-neu"
                    aria-label="QR-Code scannen"
                >
                    <img src="<?= BASE_URL ?>/assets/img/icons/qr-code.png" width="30" height="30" alt="">
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

                <!-- Menge + Größe nebeneinander -->
                <div class="form-row">
                    <div class="menge-wrap form-row-small">
                        <span class="menge-label">Menge:</span>
                        <input
                            type="number"
                            name="menge"
                            value="<?= htmlspecialchars($f['menge']) ?>"
                            min="1"
                            inputmode="numeric"
                        >
                    </div>
                    <input
                        type="text"
                        name="masse"
                        placeholder="Größe"
                        value="<?= htmlspecialchars($f['masse']) ?>"
                        class="form-row-large"
                    >
                </div>

                <textarea
                    name="bemerkung"
                    placeholder="Bemerkung"
                ><?= htmlspecialchars($f['bemerkung']) ?></textarea>

            </div>

            <!-- ── Aktionen ──────────────────────────── -->
            <div class="form-actions">
                <button type="submit" class="btn btn-save">Artikel speichern</button>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-back">Zurück zur Suche</a>
            </div>

        </form>

    </main>

    <script>
    var BASE_URL = '<?= BASE_URL ?>';

    var croppedFile = null;

    // Bildvorschau mit Crop-Overlay
    document.getElementById('bild-input').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        zeigeCropOverlay(file, function (cf, previewUrl) {
            croppedFile = cf;
            var wrap = document.getElementById('bild-preview-wrap');
            wrap.style.backgroundImage    = 'url(' + previewUrl + ')';
            wrap.style.backgroundSize     = 'cover';
            wrap.style.backgroundPosition = 'center';
            document.getElementById('bild-preview-icon').style.display = 'none';
            document.getElementById('bild-preview-text').style.display = 'none';
        });
    });

    // Formular-Submit: bei gecroptem Bild via AJAX senden
    document.querySelector('form').addEventListener('submit', function (e) {
        if (!croppedFile) return; // kein Bild → normaler Submit
        e.preventDefault();
        var fd = new FormData(this);
        fd.delete('bild');
        fd.append('bild', croppedFile, 'bild.jpg');
        fetch(BASE_URL + '/artikel_neu.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.redirect) { window.location.href = data.redirect; }
            else if (data.errors) { alert(data.errors.join('\n')); }
        })
        .catch(function () { alert('Fehler beim Senden.'); });
    });

    document.getElementById('btn-scan-neu').addEventListener('click', function () {
        var params = new URLSearchParams({ context: 'neu' });
        ['bezeichnung','kategorie','standort','menge','masse','bemerkung'].forEach(function (name) {
            var el = document.querySelector('[name="' + name + '"]');
            if (el) params.set(name, el.value);
        });
        window.location.href = BASE_URL + '/scanner.php?' + params.toString();
    });
    </script>

</body>
</html>
