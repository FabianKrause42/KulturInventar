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
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
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

    /* ── Bild-Viewer ──────────────────────────────────────── */
    var container = document.getElementById('bild-container');
    var img       = document.getElementById('bild-img');

    // Transform-State
    var tx = 0, ty = 0, sc = 1;
    var fitSc = 1;   // Skalierung bei "passt in Viewport"
    var maxSc = 8;   // maximaler Zoom

    function imgW() { return img.naturalWidth  * sc; }
    function imgH() { return img.naturalHeight * sc; }

    /* Clamp: Bild kann nicht komplett aus dem Viewport verschoben werden.
       Wenn das Bild kleiner als der Container ist, wird es zentriert.
       Wenn es größer ist, kann es bis zu seinen Rändern gescrollt werden. */
    function clampTx(v) {
        var cw = container.clientWidth, iw = imgW();
        if (iw <= cw) return (cw - iw) / 2;
        return Math.max(cw - iw, Math.min(0, v));
    }
    function clampTy(v) {
        var ch = container.clientHeight, ih = imgH();
        if (ih <= ch) return (ch - ih) / 2;
        return Math.max(ch - ih, Math.min(0, v));
    }

    function apply() {
        tx = clampTx(tx);
        ty = clampTy(ty);
        img.style.transform = 'translate(' + tx + 'px,' + ty + 'px) scale(' + sc + ')';
    }

    /* Zoom um einen Punkt (px, py) auf Skalierung newSc */
    function zoomAt(px, py, newSc) {
        newSc = Math.max(fitSc, Math.min(maxSc, newSc));
        // Punkt bleibt am selben Ort: newTx = px - (px - tx) * (newSc / sc)
        tx = px - (px - tx) * (newSc / sc);
        ty = py - (py - ty) * (newSc / sc);
        sc = newSc;
        apply();
    }

    /* Initialisierung: Bild zentriert, "fit" in Container */
    function initView() {
        var cw = container.clientWidth, ch = container.clientHeight;
        fitSc = Math.min(cw / img.naturalWidth, ch / img.naturalHeight);
        sc    = fitSc;
        tx    = (cw - imgW()) / 2;
        ty    = (ch - imgH()) / 2;
        apply();
    }

    img.addEventListener('load', initView);
    if (img.complete && img.naturalWidth) initView();

    /* ── Touch-Events ────────────────────────────────── */
    var t1x = 0, t1y = 0;
    var pinchDist0 = 0, pinchSc0 = 1;
    var mode = 'idle'; // 'pan' | 'pinch'
    var lastPinchEnd = 0; // Zeitstempel letztes Pinch-Ende (blockiert Double-Tap)

    function touchMid(touches) {
        return {
            x: (touches[0].clientX + touches[1].clientX) / 2,
            y: (touches[0].clientY + touches[1].clientY) / 2
        };
    }
    function touchDist(touches) {
        return Math.hypot(
            touches[1].clientX - touches[0].clientX,
            touches[1].clientY - touches[0].clientY
        );
    }
    function containerOffset(e) {
        var r = container.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    container.addEventListener('touchstart', function (e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            mode = 'pan';
            t1x  = e.touches[0].clientX;
            t1y  = e.touches[0].clientY;
        } else if (e.touches.length >= 2) {
            mode       = 'pinch';
            pinchDist0 = touchDist(e.touches);
            pinchSc0   = sc; // aktuellen Zoom merken
        }
    }, { passive: false });

    container.addEventListener('touchmove', function (e) {
        e.preventDefault();
        if (mode === 'pan' && e.touches.length === 1) {
            var dx = e.touches[0].clientX - t1x;
            var dy = e.touches[0].clientY - t1y;
            t1x = e.touches[0].clientX;
            t1y = e.touches[0].clientY;
            tx += dx;
            ty += dy;
            apply();
        } else if (mode === 'pinch' && e.touches.length === 2) {
            var dist   = touchDist(e.touches);
            var newSc  = pinchSc0 * (dist / pinchDist0);
            // Auch Pinch-Mitte neu berechnen für flüssiges Verschieben
            var mid    = touchMid(e.touches);
            var r      = container.getBoundingClientRect();
            var midX   = mid.x - r.left;
            var midY   = mid.y - r.top;
            zoomAt(midX, midY, newSc);
        }
    }, { passive: false });

    container.addEventListener('touchend', function (e) {
        if (e.touches.length === 0) {
            if (mode === 'pinch') lastPinchEnd = Date.now();
            mode = 'idle';
        } else if (e.touches.length === 1) {
            if (mode === 'pinch') lastPinchEnd = Date.now();
            mode = 'pan';
            t1x  = e.touches[0].clientX;
            t1y  = e.touches[0].clientY;
        }
    });

    /* Doppeltipp: zoom auf 2.5x / zurück zu fit */
    var lastTap = 0;
    container.addEventListener('touchend', function (e) {
        if (e.changedTouches.length !== 1) return;
        var now = Date.now();
        // Nach einem Pinch 400 ms ignorieren damit die Finger-Release-Events
        // nicht fälschlich als Double-Tap gewertet werden
        if (now - lastPinchEnd < 400) { lastTap = 0; return; }
        if (now - lastTap < 300) {
            var r   = container.getBoundingClientRect();
            var px  = e.changedTouches[0].clientX - r.left;
            var py  = e.changedTouches[0].clientY - r.top;
            if (sc > fitSc * 1.2) {
                sc = fitSc;
                tx = (container.clientWidth  - imgW()) / 2;
                ty = (container.clientHeight - imgH()) / 2;
                apply();
            } else {
                zoomAt(px, py, fitSc * 2.5);
            }
            lastTap = 0; // nach Double-Tap zurücksetzen
        } else {
            lastTap = now;
        }
    });

    /* ── Maus (Desktop) ────────────────────────────────── */
    var mouseDown = false, msx = 0, msy = 0;
    container.addEventListener('mousedown', function (e) {
        mouseDown = true; msx = e.clientX; msy = e.clientY;
        e.preventDefault();
    });
    document.addEventListener('mousemove', function (e) {
        if (!mouseDown) return;
        tx += e.clientX - msx; ty += e.clientY - msy;
        msx = e.clientX; msy = e.clientY;
        apply();
    });
    document.addEventListener('mouseup', function () { mouseDown = false; });

    container.addEventListener('wheel', function (e) {
        e.preventDefault();
        var r     = container.getBoundingClientRect();
        var px    = e.clientX - r.left;
        var py    = e.clientY - r.top;
        var delta = e.deltaY > 0 ? 0.9 : 1.1;
        zoomAt(px, py, sc * delta);
    }, { passive: false });

    /* ── Bild ändern ──────────────────────────────────── */
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
