<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$standorte = ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'];

// Lagerplätze mit Regalen: Standort => gültige Regalnummern
$regalBereiche = [
    'RON'            => range(301, 306),
    'Großes Magazin' => range(101, 111),
];

$artikel = null;
if ($id > 0 && $pdo !== null) {
    $stmt = $pdo->prepare('SELECT id, bezeichnung, inventarnummer FROM inventar WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $artikel = $stmt->fetch() ?: null;
}

// POST: Standort (+ ggf. Regalnummer) speichern → zurück zur Detailseite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $artikel !== null && $pdo !== null) {
    $standort = trim($_POST['standort'] ?? '');
    $regalRaw = trim($_POST['regal_nr'] ?? '');

    if (in_array($standort, $standorte, true)) {
        $regal = null;
        if (isset($regalBereiche[$standort]) && $regalRaw !== '' && in_array((int) $regalRaw, $regalBereiche[$standort], true)) {
            $regal = (int) $regalRaw;
        }

        $pdo->prepare('UPDATE inventar SET standort=?, regal_nr=? WHERE id=?')
            ->execute([$standort, $regal, $id]);
    }
    header('Location: ' . BASE_URL . '/artikel.php?id=' . $id . '&gespeichert=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title>Standort wählen – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <style>
        .standort-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 2rem);
            gap: var(--spacing);
        }

        .standort-header {
            text-align: center;
            padding-top: var(--spacing);
            margin-bottom: calc(var(--spacing) * 0.5);
        }

        .standort-header p {
            font-size: 1.375rem;
            font-weight: 400;
            color: var(--color-text);
            line-height: 1.4;
        }

        .standort-list {
            display: flex;
            flex-direction: column;
            gap: var(--spacing);
        }

        .btn-standort-item {
            width: 100%;
            background: var(--color-btn-standort);
            border: var(--border);
            border-radius: var(--radius);
            padding: 0.75rem 1rem;
            font-size: 1.375rem;
            font-weight: 400;
            color: var(--color-text);
            cursor: pointer;
            text-align: center;
            font-family: inherit;
        }

        .btn-standort-item:active {
            opacity: 0.75;
        }

        .standort-actions {
            margin-top: auto;
            position: sticky;
            bottom: 0;
            background: var(--color-bg);
            padding-top: var(--spacing);
        }

        .not-found {
            text-align: center;
            color: #666;
            padding: 2rem 0;
        }

        .standort-header-sub {
            text-align: center;
            font-size: 1.1rem;
            color: var(--color-text);
            margin-bottom: calc(var(--spacing) * 0.25);
        }

        .regal-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--spacing-sm);
        }

        .btn-kein-regal {
            width: 100%;
            background: transparent;
            margin-bottom: var(--spacing-sm);
        }

        .btn-regal-item {
            background: var(--color-btn-standort);
            border: var(--border);
            border-radius: var(--radius);
            padding: 0.75rem 0.25rem;
            font-size: 1.1rem;
            font-weight: 400;
            color: var(--color-text);
            cursor: pointer;
            text-align: center;
            font-family: inherit;
        }

        .btn-regal-item:active {
            opacity: 0.75;
        }

        .regal-back {
            margin-top: var(--spacing);
        }
    </style>
</head>
<body>
<main class="standort-wrap">

<?php if ($artikel === null): ?>

    <p class="not-found">Artikel nicht gefunden.</p>
    <div class="standort-actions">
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-back">Zurück zur Suche</a>
    </div>

<?php else: ?>

    <div class="standort-header">
        <p>Bezeichnung: <?= htmlspecialchars($artikel['bezeichnung']) ?></p>
        <p>Inventarnummer: <?= htmlspecialchars($artikel['inventarnummer']) ?></p>
    </div>

    <form method="post" action="<?= BASE_URL ?>/standort.php?id=<?= $id ?>" id="standort-form">
        <input type="hidden" name="standort" id="standort-hidden" value="">

        <div class="standort-list" id="step-standort">
            <?php foreach ($standorte as $ort): ?>
                <?php if (isset($regalBereiche[$ort])): ?>
                    <button type="button" class="btn-standort-item" data-regal-ort="<?= htmlspecialchars($ort) ?>">
                        <?= htmlspecialchars($ort) ?>
                    </button>
                <?php else: ?>
                    <button type="submit" name="standort" value="<?= htmlspecialchars($ort) ?>" class="btn-standort-item">
                        <?= htmlspecialchars($ort) ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php foreach ($regalBereiche as $ort => $nummern): ?>
            <div class="standort-list regal-step" data-ort="<?= htmlspecialchars($ort) ?>" style="display:none">
                <p class="standort-header-sub"><?= htmlspecialchars($ort) ?> – Regal wählen</p>
                <button type="submit" name="standort" value="<?= htmlspecialchars($ort) ?>" class="btn-regal-item btn-kein-regal">
                    Kein Regal
                </button>
                <div class="regal-grid">
                    <?php foreach ($nummern as $nr): ?>
                        <button type="submit" name="regal_nr" value="<?= $nr ?>" class="btn-regal-item">
                            Regal <?= $nr ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-back regal-back" data-back>Zurück</button>
            </div>
        <?php endforeach; ?>
    </form>

    <script>
    document.querySelectorAll('[data-regal-ort]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ort = this.dataset.regalOrt;
            document.getElementById('standort-hidden').value = ort;
            document.getElementById('step-standort').style.display = 'none';
            document.querySelectorAll('.regal-step').forEach(function (el) {
                el.style.display = (el.dataset.ort === ort) ? '' : 'none';
            });
        });
    });

    document.querySelectorAll('[data-back]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.regal-step').forEach(function (el) {
                el.style.display = 'none';
            });
            document.getElementById('step-standort').style.display = '';
            document.getElementById('standort-hidden').value = '';
        });
    });
    </script>

    <div class="standort-actions">
        <a href="<?= BASE_URL ?>/artikel.php?id=<?= $id ?>" class="btn btn-back">Zurück zur Suche</a>
    </div>

<?php endif; ?>

</main>
</body>
</html>
