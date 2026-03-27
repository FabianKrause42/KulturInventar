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
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title>Artikel anlegen – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <script src="<?= BASE_URL ?>/assets/js/crop.js"></script>
    <style>
        /* ── Bild-Auswahl Action-Sheet ─────────────────────── */
        .bild-sheet-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 900;
        }
        .bild-sheet-backdrop.open { display: block; }
        .bild-sheet {
            position: fixed;
            bottom: 0;
            left: 0; right: 0;
            background: var(--color-surface, #fff);
            border-radius: 16px 16px 0 0;
            padding: 1.25rem 1rem 2rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            z-index: 901;
            transform: translateY(100%);
            transition: transform 0.22s ease;
        }
        .bild-sheet-backdrop.open .bild-sheet { transform: translateY(0); }
        .bild-sheet-title {
            font-size: 0.8rem;
            color: var(--color-text-muted, #888);
            text-align: center;
            margin-bottom: 0.25rem;
        }
        .bild-sheet-btn {
            display: block;
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: var(--radius, 8px);
            background: var(--color-input-bg, #e8e8e8);
            font-size: 1rem;
            font-family: inherit;
            cursor: pointer;
            text-align: center;
        }
        .bild-sheet-btn:active { opacity: 0.7; }
        .bild-sheet-cancel {
            background: transparent;
            color: var(--color-text-muted, #888);
            font-size: 0.9rem;
        }
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
            <div class="form-image-placeholder" id="bild-preview-wrap">
                <img src="<?= BASE_URL ?>/assets/img/icons/camera.png" alt="" class="form-image-icon" id="bild-preview-icon">
                <span id="bild-preview-text">Bild hinzufügen</span>
            </div>
            <input type="file" id="bild-input-kamera"  accept="image/*" capture="environment" style="display:none">
            <input type="file" id="bild-input-galerie" accept="image/*" style="display:none">

            <!-- Action-Sheet Bild-Auswahl -->
            <div class="bild-sheet-backdrop" id="bild-sheet-backdrop">
                <div class="bild-sheet">
                    <p class="bild-sheet-title">Bild hinzufügen</p>
                    <button type="button" class="bild-sheet-btn" id="sheet-btn-kamera">📷 Kamera</button>
                    <button type="button" class="bild-sheet-btn" id="sheet-btn-galerie">🖼️ Galerie</button>
                    <button type="button" class="bild-sheet-btn bild-sheet-cancel" id="sheet-btn-abbrechen">Abbrechen</button>
                </div>
            </div>

            <!-- ── Inventarnummer + QR ──────────────── -->
            <div class="form-header">
                <input
                    type="text"
                    name="inventarnummer"
                    id="inventarnummer-input"
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

                <div class="select-wrap">
                    <span class="select-label">Kategorie:</span>
                    <select name="kategorie">
                        <option value="" disabled <?= $f['kategorie'] === '' ? 'selected' : '' ?>>wählen</option>
                        <?php foreach ($kategorien as $kat): ?>
                            <option value="<?= htmlspecialchars($kat) ?>"
                                <?= $f['kategorie'] === $kat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="select-wrap">
                    <span class="select-label">Standort:</span>
                    <select name="standort">
                        <option value="" <?= $f['standort'] === '' ? 'selected' : '' ?>>kein</option>
                        <?php foreach ($standorte as $ort): ?>
                            <option value="<?= htmlspecialchars($ort) ?>"
                                <?= $f['standort'] === $ort ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ort) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
    var STORAGE_KEY = 'artikel_neu_bild';

    /* ── Hilfsfunktionen ──────────────────────────────── */
    function setPreviewImage(dataUrl) {
        var wrap = document.getElementById('bild-preview-wrap');
        wrap.style.backgroundImage    = 'url(' + dataUrl + ')';
        wrap.style.backgroundSize     = 'cover';
        wrap.style.backgroundPosition = 'center';
        document.getElementById('bild-preview-icon').style.display = 'none';
        document.getElementById('bild-preview-text').style.display = 'none';
    }

    function dataUrlToFile(dataUrl, name) {
        var parts = dataUrl.split(',');
        var mime  = parts[0].match(/:(.*?);/)[1];
        var raw   = atob(parts[1]);
        var arr   = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return new File([arr], name, { type: mime });
    }

    /* ── Beim Laden: Bild aus sessionStorage wiederherstellen (nach Scanner-Rücksprung) ── */
    (function () {
        var fromScanner = (new URLSearchParams(window.location.search)).get('context') === 'neu';
        if (!fromScanner) {
            // Frischer Aufruf – altes gespeichertes Bild löschen
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }
        var stored = sessionStorage.getItem(STORAGE_KEY);
        if (stored) {
            setPreviewImage(stored);
            croppedFile = dataUrlToFile(stored, 'bild.jpg');
        }
    }());
    /* ── Action-Sheet Bild-Auswahl ────────────────────────────── */
    var backdrop     = document.getElementById('bild-sheet-backdrop');
    var inputKamera  = document.getElementById('bild-input-kamera');
    var inputGalerie = document.getElementById('bild-input-galerie');

    function oeffneSheet() { backdrop.classList.add('open'); }
    function schliesseSheet() { backdrop.classList.remove('open'); }

    document.getElementById('bild-preview-wrap').addEventListener('click', oeffneSheet);
    document.getElementById('sheet-btn-kamera').addEventListener('click', function () {
        schliesseSheet(); inputKamera.click();
    });
    document.getElementById('sheet-btn-galerie').addEventListener('click', function () {
        schliesseSheet(); inputGalerie.click();
    });
    document.getElementById('sheet-btn-abbrechen').addEventListener('click', schliesseSheet);
    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) schliesseSheet();
    });

    function handleFileChange(file) {
        if (!file) return;
        zeigeCropOverlay(file, function (cf) {
            croppedFile = cf;
            var reader = new FileReader();
            reader.onload = function (e) {
                var dataUrl = e.target.result;
                try { sessionStorage.setItem(STORAGE_KEY, dataUrl); } catch (ex) {}
                setPreviewImage(dataUrl);
            };
            reader.readAsDataURL(cf);
        });
    }

    inputKamera.addEventListener('change',  function () { handleFileChange(this.files[0]); this.value = ''; });
    inputGalerie.addEventListener('change', function () { handleFileChange(this.files[0]); this.value = ''; });

    /* ── Formular-Submit: gecroptes Bild via AJAX senden ─────── */
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
            if (data.redirect) {
                sessionStorage.removeItem(STORAGE_KEY); // aufräumen
                window.location.href = data.redirect;
            } else if (data.errors) {
                alert(data.errors.join('\n'));
            }
        })
        .catch(function () { alert('Fehler beim Senden.'); });
    });

    /* ── Inventarnummer-Duplikatcheck (Enter / Fokusverlust bei 4 Ziffern) ─── */
    (function () {
        var input   = document.getElementById('inventarnummer-input');
        var pending = null;

        function check(nummer) {
            if (!/^\d{4}$/.test(nummer)) return;
            clearTimeout(pending);
            pending = setTimeout(function () {
                fetch(BASE_URL + '/api/lookup.php?inventarnummer=' + encodeURIComponent(nummer))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.id) {
                            window.location.href = BASE_URL + '/artikel.php?id=' + data.id;
                        }
                    })
                    .catch(function () {});
            }, 150);
        }

        // Enter im Inventarnummer-Feld
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                check(this.value.trim());
            }
        });

        // Fokusverlust (z.B. Wechsel zum nächsten Feld)
        input.addEventListener('blur', function () {
            check(this.value.trim());
        });

        // Bei 4 vollständigen Ziffern sofort prüfen
        input.addEventListener('input', function () {
            if (/^\d{4}$/.test(this.value.trim())) check(this.value.trim());
        });
    }());

    /* ── Scanner-Button ───────────────────────────────────── */
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
