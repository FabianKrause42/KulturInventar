<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/placeholder.php';
require_once __DIR__ . '/src/helpers/upload.php';
require_once __DIR__ . '/src/helpers/tags.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$artikel = null;
if ($id > 0 && $pdo !== null) {
    $stmt = $pdo->prepare('SELECT * FROM inventar WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $artikel = $stmt->fetch() ?: null;
}

$standorte  = ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'];

$fehler  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $artikel !== null && $pdo !== null) {
    $bezeichnung = trim($_POST['bezeichnung'] ?? '');
    $standort    = trim($_POST['standort']    ?? '');
    $menge       = (int) ($_POST['menge']     ?? 1);
    $masse       = trim($_POST['masse']       ?? '');
    $bemerkung   = trim($_POST['bemerkung']   ?? '');
    $tagsRaw     = trim($_POST['tags']        ?? '');

    if ($bezeichnung === '') $fehler[] = 'Bezeichnung ist Pflicht.';
    if ($menge < 1) $menge = 1;

    if (empty($fehler)) {
        $pdo->prepare(
            'UPDATE inventar
             SET bezeichnung=?, standort=?, menge=?, masse=?, bemerkung=?
             WHERE id=?'
        )->execute([
            $bezeichnung,
            $standort ?: null,
            $menge,
            $masse     ?: null,
            $bemerkung ?: null,
            $id,
        ]);

        setzeTags($id, parseTags($tagsRaw), $pdo);

        header('Location: ' . BASE_URL . '/artikel.php?id=' . $id . '&gespeichert=1');
        exit;
    }

    // Fehler: Formularwerte aus POST übernehmen
    $artikel['bezeichnung'] = $bezeichnung;
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

// Tags laden
$tagsValue = '';
if ($id > 0 && $pdo !== null) {
    $tagsValue = implode(', ', ladeTags($id, $pdo));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title><?= $artikel ? htmlspecialchars($artikel['bezeichnung']) : 'Artikel' ?> – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
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
        .detail-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 2rem);
            gap: var(--spacing);
            padding-top: 0;
        }

        .detail-hero {
            width: 90%;
            margin-left: auto;
            margin-right: auto;
            aspect-ratio: 4 / 3;
            background: #eeeeee;
            border-radius: var(--radius);
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
            border-radius: var(--radius);
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
    <script src="<?= BASE_URL ?>/assets/js/tag-input.js"></script>
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
    <input type="file" id="bild-input-kamera"  accept="image/*" capture="environment" style="display:none">
    <input type="file" id="bild-input-galerie" accept="image/*" style="display:none">

    <!-- Action-Sheet Bild-Auswahl -->
    <div class="bild-sheet-backdrop" id="bild-sheet-backdrop">
        <div class="bild-sheet">
            <p class="bild-sheet-title">Bild hinzufügen</p>
            <button class="bild-sheet-btn" id="sheet-btn-kamera">📷 Kamera</button>
            <button class="bild-sheet-btn" id="sheet-btn-galerie">🖼️ Galerie</button>
            <button class="bild-sheet-btn bild-sheet-cancel" id="sheet-btn-abbrechen">Abbrechen</button>
        </div>
    </div>

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

            <div class="tag-input-wrap">
                <input
                    type="text"
                    id="tag-input"
                    name="tags"
                    placeholder="Tags, kommagetrennt (max. 3)"
                    autocomplete="off"
                    value="<?= htmlspecialchars($tagsValue) ?>"
                >
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
    var hero         = document.getElementById('hero-upload');
    var inputKamera  = document.getElementById('bild-input-kamera');
    var inputGalerie = document.getElementById('bild-input-galerie');
    var backdrop     = document.getElementById('bild-sheet-backdrop');
    var hatBild      = <?= $ersteBild ? 'true' : 'false' ?>;

    function oeffneSheet() { backdrop.classList.add('open'); }
    function schliesseSheet() { backdrop.classList.remove('open'); }

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
    }

    inputKamera.addEventListener('change',  function () { handleFileChange(this.files[0]); this.value = ''; });
    inputGalerie.addEventListener('change', function () { handleFileChange(this.files[0]); this.value = ''; });

    hero.style.cursor = 'pointer';
    hero.addEventListener('click', function () {
        if (hatBild) {
            window.location.href = BASE_URL + '/bild.php?id=' + INVENTAR_ID;
        } else {
            oeffneSheet();
        }
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

    /* ── Tag-Eingabe initialisieren ───────────────────── */
    initTagInput(document.getElementById('tag-input'), BASE_URL);
</script>

</body>
</html>
