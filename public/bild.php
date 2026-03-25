<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/upload.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$artikel = null;
$bilder  = [];

if ($id > 0 && $pdo !== null) {
    $stmt = $pdo->prepare('SELECT id, inventarnummer, bezeichnung FROM inventar WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $artikel = $stmt->fetch() ?: null;

    if ($artikel) {
        $imgStmt = $pdo->prepare(
            'SELECT * FROM inventar_bilder WHERE inventar_id = ? ORDER BY reihenfolge ASC'
        );
        $imgStmt->execute([$id]);
        $bilder = $imgStmt->fetchAll();
    }
}

// Kein Artikel oder keine Bilder → zurück zur Detailseite
if ($artikel === null || empty($bilder)) {
    header('Location: ' . BASE_URL . '/artikel.php?id=' . $id);
    exit;
}

$ersteBild = $bilder[0];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($artikel['bezeichnung']) ?> – Bild</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <style>
        body {
            padding: 0;
            overflow: hidden;
            height: 100dvh;
            max-width: 100%;
            background: #000;
        }

        .bild-viewer {
            position: fixed;
            inset: 0;
            display: flex;
            flex-direction: column;
            background: #000;
        }

        .bild-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            cursor: grab;
        }

        .bild-container:active {
            cursor: grabbing;
        }

        #bild-img {
            position: absolute;
            transform-origin: 0 0;
            will-change: transform;
            max-width: none;
        }

        .bild-actions {
            background: var(--color-bg);
            padding: 0.75rem var(--spacing);
            display: flex;
            flex-direction: column;
            gap: 0.57rem;
            flex-shrink: 0;
        }

        .btn-aendern {
            background: var(--color-btn-save);
            font-size: 1.25rem;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-zurueck {
            background: var(--color-btn-back);
            font-size: 1.25rem;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
    <script src="<?= BASE_URL ?>/assets/js/crop.js"></script>
</head>
<body>

<div class="bild-viewer">

    <div class="bild-container" id="bild-container">
        <img
            id="bild-img"
            src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($ersteBild['dateiname']) ?>"
            alt="<?= htmlspecialchars($artikel['bezeichnung']) ?>"
        >
    </div>

    <input type="file" id="bild-input" accept="image/*" style="display:none">

    <div class="bild-actions">
        <button class="btn btn-aendern" id="btn-aendern">Bild ändern</button>
        <a href="<?= BASE_URL ?>/artikel.php?id=<?= $id ?>" class="btn btn-zurueck">Zurück</a>
    </div>

</div>

<script>
(function () {
    var BASE_URL    = '<?= BASE_URL ?>';
    var INVENTAR_ID = <?= $id ?>;

    /* ── Bild-Viewer: Pan + Pinch-Zoom ── */
    var container = document.getElementById('bild-container');
    var img       = document.getElementById('bild-img');
    var tx = 0, ty = 0, sc = 1, minSc = 0.5;
    var dragging = false, startX = 0, startY = 0;
    var pinchDist0 = 0, pinchSc0 = 1;

    function apply() {
        img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + sc + ')';
    }

    img.addEventListener('load', function () {
        var cw = container.clientWidth, ch = container.clientHeight;
        sc     = Math.min(cw / img.naturalWidth, ch / img.naturalHeight);
        minSc  = sc * 0.75;
        tx     = (cw - img.naturalWidth  * sc) / 2;
        ty     = (ch - img.naturalHeight * sc) / 2;
        apply();
    });
    // Trigger load wenn Bild schon gecacht ist
    if (img.complete) img.dispatchEvent(new Event('load'));

    container.addEventListener('touchstart', function (e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            dragging = true;
            startX = e.touches[0].clientX - tx;
            startY = e.touches[0].clientY - ty;
        } else if (e.touches.length === 2) {
            dragging = false;
            pinchDist0 = Math.hypot(
                e.touches[1].clientX - e.touches[0].clientX,
                e.touches[1].clientY - e.touches[0].clientY);
            pinchSc0 = sc;
        }
    }, { passive: false });

    container.addEventListener('touchmove', function (e) {
        e.preventDefault();
        if (dragging && e.touches.length === 1) {
            tx = e.touches[0].clientX - startX;
            ty = e.touches[0].clientY - startY;
            apply();
        } else if (e.touches.length === 2) {
            var dist = Math.hypot(
                e.touches[1].clientX - e.touches[0].clientX,
                e.touches[1].clientY - e.touches[0].clientY);
            sc = Math.max(minSc, pinchSc0 * (dist / pinchDist0));
            apply();
        }
    }, { passive: false });

    container.addEventListener('touchend', function () { dragging = false; });

    /* Maus-Fallback (Desktop) */
    container.addEventListener('mousedown', function (e) {
        dragging = true; startX = e.clientX - tx; startY = e.clientY - ty;
    });
    document.addEventListener('mousemove', function (e) {
        if (!dragging) return;
        tx = e.clientX - startX; ty = e.clientY - startY; apply();
    });
    document.addEventListener('mouseup', function () { dragging = false; });
    container.addEventListener('wheel', function (e) {
        e.preventDefault();
        sc = Math.max(minSc, sc * (e.deltaY < 0 ? 1.1 : 0.9));
        apply();
    }, { passive: false });

    /* ── Bild ändern ── */
    var bildInput = document.getElementById('bild-input');

    document.getElementById('btn-aendern').addEventListener('click', function () {
        bildInput.value = '';
        bildInput.click();
    });

    bildInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        zeigeCropOverlay(file, function (croppedFile, previewUrl) {
            // Vorschau sofort zeigen
            img.src = previewUrl;
            img.dispatchEvent(new Event('load'));

            // Upload
            var fd = new FormData();
            fd.append('bild', croppedFile);
            fd.append('inventar_id', INVENTAR_ID);
            fetch(BASE_URL + '/api/upload.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        img.src = data.url;
                    } else {
                        alert('Upload-Fehler: ' + (data.error || 'Unbekannt'));
                    }
                })
                .catch(function () { alert('Upload fehlgeschlagen.'); });
        });
    });

}());
</script>

</body>
</html>
