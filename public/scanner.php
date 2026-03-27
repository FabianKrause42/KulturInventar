<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

$context = $_GET['context'] ?? 'suche'; // 'suche' oder 'neu'

// Alle Felder die beim Zurücknavigieren erhalten bleiben sollen
$felder = [
    'bezeichnung' => $_GET['bezeichnung'] ?? '',
    'kategorie'   => $_GET['kategorie']   ?? '',
    'standort'    => $_GET['standort']    ?? '',
    'menge'       => $_GET['menge']       ?? '1',
    'masse'       => $_GET['masse']       ?? '',
    'bemerkung'   => $_GET['bemerkung']   ?? '',
];

// Query-String für Rücknavigation zu artikel_neu.php
$backParams = http_build_query(array_merge(['context' => $context], $felder));

$backUrl = $context === 'neu'
    ? BASE_URL . '/artikel_neu.php?' . http_build_query($felder)
    : BASE_URL . '/index.php';

$backLabel = $context === 'neu' ? 'Zurück' : 'Zurück zur Suche';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title>QR-Code scannen – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <style>
        .scanner-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 2rem);
            gap: var(--spacing);
        }

        .scanner-title {
            font-size: 1.375rem;
            font-weight: 400;
            color: var(--color-text);
            text-align: center;
            padding-top: var(--spacing);
        }

        .scanner-viewport {
            position: relative;
            width: 100%;
            background: #d9d9d9;
            border-radius: var(--radius);
            overflow: hidden;
            flex: 1;
            min-height: 300px;
        }

        .scanner-viewport video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scanner-viewport canvas {
            display: none;
        }

        .scanner-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scanner-placeholder img {
            width: 45%;
            max-width: 180px;
            opacity: 0.3;
        }

        .scanner-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        /* Fadenkreuz-Rahmen */
        .scanner-frame {
            width: 55%;
            aspect-ratio: 1;
            border: 3px solid rgba(255,255,255,0.8);
            border-radius: 12px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.35);
        }

        .scanner-status {
            text-align: center;
            font-size: 0.9rem;
            color: var(--color-muted);
            min-height: 1.4em;
        }

        .scanner-actions {
            position: sticky;
            bottom: 0;
            background: var(--color-bg);
            padding-top: var(--spacing);
            padding-bottom: var(--spacing);
            border-top: 1px solid var(--color-divider);
        }
    </style>
</head>
<body>
    <main class="scanner-wrap">

        <p class="scanner-title">Bitte QR-Code scannen</p>

        <div class="scanner-viewport" id="viewport">
            <video id="video" playsinline autoplay muted></video>
            <canvas id="canvas"></canvas>
            <div class="scanner-placeholder" id="placeholder">
                <img src="<?= BASE_URL ?>/assets/img/icons/camera.png" alt="">
            </div>
            <div class="scanner-overlay">
                <div class="scanner-frame"></div>
            </div>
        </div>

        <p class="scanner-status" id="status">Kamera wird gestartet…</p>

        <div class="scanner-actions">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-back" id="back-btn">
                <?= htmlspecialchars($backLabel) ?>
            </a>
        </div>

    </main>

    <script src="<?= BASE_URL ?>/assets/js/jsqr.min.js"></script>
    <script>
    var BASE_URL = '<?= BASE_URL ?>';
    (function () {
        var context   = <?= json_encode($context) ?>;
        var felder    = <?= json_encode($felder) ?>;
        var video     = document.getElementById('video');
        var canvas    = document.getElementById('canvas');
        var ctx       = canvas.getContext('2d');
        var status    = document.getElementById('status');
        var placeholder = document.getElementById('placeholder');
        var scanning  = true;

        // Kamera starten – nur in sicherem Kontext (HTTPS / localhost) verfügbar
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            status.textContent = 'Kamera nicht verfügbar. Bitte die Seite über HTTPS aufrufen.';
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } }
        }).then(function (stream) {
            video.srcObject = stream;
            placeholder.style.display = 'none';
            status.textContent = 'Halten Sie den QR-Code in den Rahmen';
            requestAnimationFrame(tick);
        }).catch(function (err) {
            var msg = err.name === 'NotAllowedError'
                ? 'Kamerazugriff verweigert. Bitte Berechtigung erteilen.'
                : 'Kamera nicht verfügbar: ' + err.message;
            status.textContent = msg;
        });

        function tick() {
            if (!scanning) return;

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width  = video.videoWidth;
                canvas.height = video.videoHeight;
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert'
                });

                if (code) {
                    var data = code.data.trim();
                    // Letzte 4 Zeichen extrahieren und prüfen ob vierstellige Zahl
                    // → funktioniert mit reiner "0001" UND mit URLs wie "...?ID=0001"
                    var last4 = data.slice(-4);
                    if (/^\d{4}$/.test(last4)) {
                        scanning = false;
                        status.textContent = 'Erkannt: ' + last4;
                        handleResult(last4);
                        return;
                    }
                }
            }

            requestAnimationFrame(tick);
        }

        function handleResult(nummer) {
            if (context === 'neu') {
                // Erst prüfen ob der Artikel schon existiert
                fetch(BASE_URL + '/api/lookup.php?inventarnummer=' + encodeURIComponent(nummer))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.id) {
                            // Schon vorhanden → direkt zur Detailseite
                            window.location.href = BASE_URL + '/artikel.php?id=' + data.id;
                        } else {
                            // Neu → Nummer + bisherige Felder zurück zu artikel_neu.php
                            var params = new URLSearchParams(felder);
                            params.set('inventarnummer', nummer);
                            window.location.href = BASE_URL + '/artikel_neu.php?' + params.toString();
                        }
                    })
                    .catch(function () {
                        // Lookup fehlgeschlagen → trotzdem weiterleiten
                        var params = new URLSearchParams(felder);
                        params.set('inventarnummer', nummer);
                        window.location.href = BASE_URL + '/artikel_neu.php?' + params.toString();
                    });
            } else {
                // Inventarnummer in DB nachschlagen → artikel.php
                fetch(BASE_URL + '/api/lookup.php?inventarnummer=' + encodeURIComponent(nummer))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.id) {
                            window.location.href = BASE_URL + '/artikel.php?id=' + data.id;
                        } else {
                            status.textContent = 'Artikel ' + nummer + ' nicht gefunden.';
                            scanning = true;
                            requestAnimationFrame(tick);
                        }
                    })
                    .catch(function () {
                        status.textContent = 'Fehler beim Nachschlagen.';
                        scanning = true;
                        requestAnimationFrame(tick);
                    });
            }
        }
    }());
    </script>
</body>
</html>
