<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/placeholder.php';
require_once __DIR__ . '/src/helpers/upload.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$artikel = null;
if ($id > 0 && $pdo !== null) {
    $stmt = $pdo->prepare('SELECT * FROM inventar WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $artikel = $stmt->fetch() ?: null;
}

$kategorien = ['Audio', 'Licht', 'Video', 'IT', 'Bühne', 'Möbel', 'Kabel', 'Werkzeug', 'Sonstiges'];
$standorte  = ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'];

$fehler  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $artikel !== null && $pdo !== null) {
    $bezeichnung = trim($_POST['bezeichnung'] ?? '');
    $kategorie   = trim($_POST['kategorie']   ?? '');
    $standort    = trim($_POST['standort']    ?? '');
    $menge       = (int) ($_POST['menge']     ?? 1);
    $masse       = trim($_POST['masse']       ?? '');
    $bemerkung   = trim($_POST['bemerkung']   ?? '');

    if ($bezeichnung === '') $fehler[] = 'Bezeichnung ist Pflicht.';
    if (!in_array($kategorie, $kategorien, true)) $fehler[] = 'Ungültige Kategorie.';
    if ($menge < 1) $menge = 1;

    if (empty($fehler)) {
        $pdo->prepare(
            'UPDATE inventar
             SET bezeichnung=?, kategorie=?, standort=?, menge=?, masse=?, bemerkung=?
             WHERE id=?'
        )->execute([
            $bezeichnung,
            $kategorie,
            $standort ?: null,
            $menge,
            $masse     ?: null,
            $bemerkung ?: null,
            $id,
        ]);

        header('Location: ' . BASE_URL . '/artikel.php?id=' . $id . '&gespeichert=1');
        exit;
    }

    // Fehler: Formularwerte aus POST übernehmen
    $artikel['bezeichnung'] = $bezeichnung;
    $artikel['kategorie']   = $kategorie;
    $artikel['standort']    = $standort;
    $artikel['menge']       = $menge;
    $artikel['masse']       = $masse;
    $artikel['bemerkung']   = $bemerkung;
}

$gespeichert = isset($_GET['gespeichert']);
$istNeu      = isset($_GET['neu']);

// Bilder laden
$bilder    = [];
$ersteBild = null;
if ($id > 0 && $pdo !== null) {
    $imgStmt = $pdo->prepare(
        'SELECT * FROM inventar_bilder WHERE inventar_id = ? ORDER BY reihenfolge ASC'
    );
    $imgStmt->execute([$id]);
    $bilder    = $imgStmt->fetchAll();
    $ersteBild = $bilder[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title><?= $artikel ? htmlspecialchars($artikel['bezeichnung']) : 'Artikel' ?> – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <style>
        .detail-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 2rem);
            gap: var(--spacing);
            padding-top: 0;
        }

        .detail-hero {
            width: calc(100% + 2 * var(--spacing));
            margin-left: calc(-1 * var(--spacing));
            margin-right: calc(-1 * var(--spacing));
            aspect-ratio: 16 / 7;
            background: #eeeeee;
            border-radius: 0 0 var(--radius) var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        /* Nur das Platzhalter-Kamera-Icon bekommt opacity */
        .detail-hero #hero-icon {
            width: 28px;
            height: 28px;
            opacity: 0.4;
        }

        /* Cover-Bild füllt den Container vollständig */
        .detail-hero #hero-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0 0 8px 8px;
            display: block;
        }

        .detail-nummer {
            font-size: 1rem;
            color: var(--color-text);
            text-align: left;
        }

        .detail-actions {
            margin-top: auto;
            position: sticky;
            bottom: 0;
            background: var(--color-bg);
            padding-top: var(--spacing);
            padding-bottom: var(--spacing);
            border-top: 1px solid var(--color-divider);
            display: flex;
            flex-direction: column;
            gap: 0.57rem;
        }

        .btn-standort {
            background: var(--color-btn-standort);
            font-size: 1.25rem;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-msg {
            font-size: 0.9rem;
            color: #2a7d2e;
            text-align: center;
            padding: 0.4rem 0;
        }

        .not-found {
            text-align: center;
            color: #666;
            padding: 2rem 0;
        }
    </style>
    <script src="<?= BASE_URL ?>/assets/js/crop.js"></script>
</head>
<body>
<main class="detail-wrap">

<?php if ($artikel === null): ?>

    <p class="not-found">
        <?= $id === 0 ? 'Keine Artikel-ID angegeben.' : 'Artikel nicht gefunden.' ?>
    </p>
    <div class="detail-actions">
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-back">Zurück zur Suche</a>
    </div>

<?php else: ?>

    <?php if (!empty($fehler)): ?>
        <div class="error-message">
            <?php foreach ($fehler as $msg): ?>
                <p><?= htmlspecialchars($msg) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($gespeichert): ?>
        <p class="success-msg">✓ Änderung gespeichert</p>
    <?php endif; ?>

    <!-- Vorschaubild -->
    <div class="detail-hero" id="hero-upload">
        <?php if ($ersteBild): ?>
            <img
                src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($ersteBild['dateiname']) ?>"
                id="hero-img"
                alt=""
            >
        <?php else: ?>
            <img src="<?= BASE_URL ?>/assets/img/icons/camera.png" id="hero-icon" alt="" style="opacity:0.4;width:28px;height:28px">
        <?php endif; ?>
        <div id="hero-uploading" style="display:none;color:#555;font-size:0.9rem">Wird hochgeladen…</div>
    </div>
    <input type="file" id="bild-input" accept="image/*" style="display:none">

    <!-- Inventarnummer -->
    <p class="detail-nummer">Inventarnummer: <?= htmlspecialchars($artikel['inventarnummer']) ?></p>

    <!-- Formular -->
    <form method="post" action="<?= BASE_URL ?>/artikel.php?id=<?= $id ?>" id="edit-form" autocomplete="off" novalidate>

        <div class="form-fields">

            <input
                type="text"
                name="bezeichnung"
                placeholder="Bezeichnung"
                value="<?= htmlspecialchars($artikel['bezeichnung']) ?>"
                required
            >

            <div class="select-wrap">
                <span class="select-label">Kategorie:</span>
                <select name="kategorie">
                    <option value="" disabled>wählen</option>
                    <?php foreach ($kategorien as $kat): ?>
                        <option value="<?= htmlspecialchars($kat) ?>"
                            <?= $artikel['kategorie'] === $kat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="select-wrap">
                <span class="select-label">Standort:</span>
                <select name="standort">
                    <option value="">kein</option>
                    <?php foreach ($standorte as $ort): ?>
                        <option value="<?= htmlspecialchars($ort) ?>"
                            <?= ($artikel['standort'] ?? '') === $ort ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ort) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="menge-wrap form-row-small">
                    <span class="menge-label">Menge:</span>
                    <input
                        type="number"
                        name="menge"
                        value="<?= (int) $artikel['menge'] ?>"
                        min="1"
                        inputmode="numeric"
                    >
                </div>
                <input
                    type="text"
                    name="masse"
                    placeholder="Maße"
                    value="<?= htmlspecialchars($artikel['masse'] ?? '') ?>"
                    class="form-row-large"
                >
            </div>

            <textarea
                name="bemerkung"
                placeholder="Bemerkung"
            ><?= htmlspecialchars($artikel['bemerkung'] ?? '') ?></textarea>

        </div>

        <div class="detail-actions">
            <button
                type="button"
                id="btn-primary"
                class="btn btn-standort"
                data-standort-url="<?= BASE_URL ?>/standort.php?id=<?= $id ?>"
            >Standort ändern</button>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-back">Zurück zur Suche</a>
        </div>

    </form>

<?php endif; ?>

</main>

<script>
(function () {
    var BASE_URL    = '<?= BASE_URL ?>';
    var INVENTAR_ID = <?= $id ?>;

    // ── Bild Upload ──────────────────────────────────────────
    var hero      = document.getElementById('hero-upload');
    var bildInput = document.getElementById('bild-input');
    var hatBild   = <?= $ersteBild ? 'true' : 'false' ?>;

    hero.style.cursor = 'pointer';
    hero.addEventListener('click', function () {
        if (hatBild) {
            window.location.href = BASE_URL + '/bild.php?id=' + INVENTAR_ID;
        } else {
            bildInput.click();
        }
    });

    bildInput.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;

        zeigeCropOverlay(file, function (croppedFile, previewUrl) {
            hero.innerHTML =
                '<img src="' + previewUrl + '" id="hero-img" alt="">' +
                '<div id="hero-uploading" style="position:absolute;bottom:6px;right:8px;font-size:0.75rem;color:#555;background:rgba(255,255,255,0.8);padding:2px 6px;border-radius:4px">Speichert\u2026</div>';
            hero.style.position = 'relative';
            hatBild = true;

            var fd = new FormData();
            fd.append('bild', croppedFile);
            fd.append('inventar_id', INVENTAR_ID);

            fetch(BASE_URL + '/api/upload.php', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var overlay = document.getElementById('hero-uploading');
                    if (data.success) {
                        if (overlay) overlay.remove();
                    } else {
                        if (overlay) overlay.textContent = data.error || 'Fehler beim Upload';
                    }
                })
                .catch(function () {
                    var overlay = document.getElementById('hero-uploading');
                    if (overlay) overlay.textContent = 'Upload fehlgeschlagen';
                });
        });
    });

    // ── Formular-Änderungs-Detektion ────────────────────────
    var form        = document.getElementById('edit-form');
    var btn         = document.getElementById('btn-primary');
    if (!form || !btn) return;

    var standortUrl = btn.dataset.standortUrl;
    var changed     = false;

    function setChanged() {
        if (changed) return;
        changed = true;
        btn.textContent = 'Änderung speichern';
        btn.type = 'submit';
        btn.classList.remove('btn-standort');
        btn.classList.add('btn-save');
    }

    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('change', setChanged);
        el.addEventListener('input',  setChanged);
    });

    btn.addEventListener('click', function () {
        if (!changed) {
            window.location.href = standortUrl;
        }
    });
}());
</script>

</body>
</html>
