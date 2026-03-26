<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$standorte = ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'];

$artikel = null;
if ($id > 0 && $pdo !== null) {
    $stmt = $pdo->prepare('SELECT id, bezeichnung, inventarnummer FROM inventar WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $artikel = $stmt->fetch() ?: null;
}

// POST: Standort speichern → zurück zur Detailseite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $artikel !== null && $pdo !== null) {
    $standort = trim($_POST['standort'] ?? '');
    if (in_array($standort, $standorte, true)) {
        $pdo->prepare('UPDATE inventar SET standort=? WHERE id=?')
            ->execute([$standort, $id]);
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

    <form method="post" action="<?= BASE_URL ?>/standort.php?id=<?= $id ?>">
        <div class="standort-list">
            <?php foreach ($standorte as $ort): ?>
                <button type="submit" name="standort" value="<?= htmlspecialchars($ort) ?>" class="btn-standort-item">
                    <?= htmlspecialchars($ort) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="standort-actions">
        <a href="<?= BASE_URL ?>/artikel.php?id=<?= $id ?>" class="btn btn-back">Zurück zur Suche</a>
    </div>

<?php endif; ?>

</main>
</body>
</html>
