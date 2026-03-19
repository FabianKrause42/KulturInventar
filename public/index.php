<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth/session.php';
require_login();

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/placeholder.php';

$query   = trim($_GET['q'] ?? '');
$treffer = [];
$gesucht = false;

if ($query !== '') {
    $gesucht = true;
    if ($pdo !== null) {
        $like = '%' . $query . '%';
        $stmt = $pdo->prepare(
            'SELECT id, inventarnummer, bezeichnung, kategorie, standort, bild_pfad
             FROM inventar
             WHERE inventarnummer LIKE ?
                OR bezeichnung    LIKE ?
                OR kategorie      LIKE ?
                OR standort       LIKE ?
                OR bemerkung      LIKE ?
             ORDER BY bezeichnung ASC'
        );
        $stmt->execute([$like, $like, $like, $like, $like]);
        $treffer = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KulturInventar</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>

    <main>

        <!-- ── Suche ─────────────────────────────────── -->
        <section class="search-section">
            <form method="get" action="/index.php" role="search" autocomplete="off">
                <div class="search-row">
                    <div class="search-field-wrap">
                        <span class="search-field-icon" aria-hidden="true">
                            <img src="/assets/img/icons/search.png" width="20" height="20" alt="">
                        </span>
                        <input
                            type="search"
                            name="q"
                            id="q"
                            class="search-input"
                            placeholder="Suchen"
                            value="<?= htmlspecialchars($query) ?>"
                            autofocus
                        >
                    </div>
                    <button
                        type="button"
                        class="btn-scan"
                        title="QR-Code scannen (bald verfügbar)"
                        disabled
                        aria-label="QR-Code scannen"
                    >
                        <img src="/assets/img/icons/qr-code.png" width="30" height="30" alt="">
                    </button>
                </div>
            </form>
        </section>

        <!-- ── Trefferliste ──────────────────────────── -->
        <section class="result-section">
            <?php if ($gesucht && count($treffer) === 0): ?>

                <p class="no-results">
                    Keine Treffer für „<?= htmlspecialchars($query) ?>".
                </p>

            <?php elseif ($gesucht): ?>

                <p class="result-label"><?= count($treffer) ?> Ergebnisse</p>

                <ul class="result-list">
                    <?php foreach ($treffer as $artikel): ?>
                        <li>
                            <a href="/artikel.php?id=<?= (int)$artikel['id'] ?>" class="result-row">
                                <img
                                    class="result-thumb"
                                    src="<?= get_thumbnail($artikel) ?>"
                                    alt="<?= htmlspecialchars($artikel['bezeichnung']) ?>"
                                    loading="lazy"
                                    width="100"
                                    height="95"
                                >
                                <div class="result-info">
                                    <span class="result-bezeichnung">
                                        <?= htmlspecialchars($artikel['bezeichnung']) ?>
                                    </span>
                                    <?php if (!empty($artikel['standort'])): ?>
                                        <span class="result-standort">
                                            Standort: <?= htmlspecialchars($artikel['standort']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php endif; ?>
        </section>

        <!-- ── Neuer Artikel ─────────────────────────── -->
        <div class="new-item-wrap">
            <a href="/artikel_neu.php" class="btn">Neuen Artikel anlegen</a>
        </div>

    </main>

</body>
</html>
