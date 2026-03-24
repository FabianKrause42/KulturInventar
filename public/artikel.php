<?php
declare(strict_types=1);

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/placeholder.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$artikel = null;
if ($id > 0 && $pdo !== null) {
    $stmt = $pdo->prepare(
        'SELECT * FROM inventar WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $artikel = $stmt->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $artikel ? htmlspecialchars($artikel['bezeichnung']) : 'Artikel' ?> – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <style>
        .detail-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 2rem);
            gap: var(--spacing);
        }

        .detail-img {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            border-radius: var(--radius);
            background: #d9d9d9;
            display: block;
        }

        .detail-nummer {
            font-size: 0.85rem;
            color: #666;
            font-weight: 500;
            letter-spacing: 0.04em;
        }

        .detail-bezeichnung {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--color-text);
            margin: 0;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
        }

        .detail-table tr {
            border-bottom: 1px solid var(--color-border);
        }

        .detail-table tr:last-child {
            border-bottom: none;
        }

        .detail-table th {
            text-align: left;
            font-weight: 500;
            color: #666;
            padding: 0.6rem 0.5rem 0.6rem 0;
            width: 40%;
            vertical-align: top;
        }

        .detail-table td {
            padding: 0.6rem 0 0.6rem 0.5rem;
            color: var(--color-text);
            vertical-align: top;
        }

        .detail-bemerkung {
            white-space: pre-wrap;
        }

        .detail-actions {
            margin-top: auto;
            position: sticky;
            bottom: 0;
            background: var(--color-bg);
            padding: var(--spacing) 0 0;
        }

        .not-found {
            text-align: center;
            color: #666;
            padding: 2rem 0;
        }
    </style>
</head>
<body>
    <main class="detail-wrap">

        <?php if ($artikel === null): ?>

            <p class="not-found">
                <?= $id === 0 ? 'Keine Artikel-ID angegeben.' : 'Artikel nicht gefunden.' ?>
            </p>

        <?php else: ?>

            <img
                class="detail-img"
                src="<?= get_thumbnail($artikel) ?>"
                alt="<?= htmlspecialchars($artikel['bezeichnung']) ?>"
            >

            <div>
                <p class="detail-nummer">#<?= htmlspecialchars($artikel['inventarnummer']) ?></p>
                <h1 class="detail-bezeichnung"><?= htmlspecialchars($artikel['bezeichnung']) ?></h1>
            </div>

            <table class="detail-table">
                <tr>
                    <th>Kategorie</th>
                    <td><?= htmlspecialchars($artikel['kategorie']) ?></td>
                </tr>
                <?php if (!empty($artikel['standort'])): ?>
                <tr>
                    <th>Standort</th>
                    <td><?= htmlspecialchars($artikel['standort']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Menge</th>
                    <td><?= (int) $artikel['menge'] ?></td>
                </tr>
                <?php if (!empty($artikel['masse'])): ?>
                <tr>
                    <th>Maße</th>
                    <td><?= htmlspecialchars($artikel['masse']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($artikel['bemerkung'])): ?>
                <tr>
                    <th>Bemerkung</th>
                    <td class="detail-bemerkung"><?= htmlspecialchars($artikel['bemerkung']) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Angelegt</th>
                    <td><?= (new DateTime($artikel['erstellt_am']))->format('d.m.Y') ?></td>
                </tr>
            </table>

        <?php endif; ?>

        <div class="detail-actions">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-back">Zurück zur Suche</a>
        </div>

    </main>
</body>
</html>
