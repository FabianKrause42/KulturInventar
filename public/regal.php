<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// regal.php – Übersicht aller Artikel in einem Regal
//
// Aufruf:  /kulturinventar/regal.php?id=101
// Normalerweise erreicht über den Redirect von kir.php (Regal-QR-Etikett).
// Regalnummern sind eindeutig über alle Lagerorte hinweg (siehe
// src/config/lagerorte.php), daher genügt regal_nr als Suchkriterium.
// ---------------------------------------------------------------

require_once __DIR__ . '/src/auth/session.php';
require_login();

require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/placeholder.php';

$lagerorte     = require __DIR__ . '/src/config/lagerorte.php';
$regalBereiche = $lagerorte['regalBereiche'];

$regalNr = isset($_GET['id']) ? (int) preg_replace('/[^0-9]/', '', (string) $_GET['id']) : 0;

// Zu welchem Lagerort gehört die Regalnummer?
$standortName = null;
foreach ($regalBereiche as $ort => $nummern) {
    if (in_array($regalNr, $nummern, true)) {
        $standortName = $ort;
        break;
    }
}

$artikelListe = [];
if ($standortName !== null && $pdo !== null) {
    $stmt = $pdo->prepare(
        'SELECT i.id, i.inventarnummer, i.bezeichnung, i.menge,
                (SELECT dateiname FROM inventar_bilder
                 WHERE inventar_id = i.id
                 ORDER BY reihenfolge ASC LIMIT 1) AS bild_dateiname
         FROM inventar i
         WHERE i.regal_nr = ?
         ORDER BY i.bezeichnung ASC'
    );
    $stmt->execute([$regalNr]);
    $artikelListe = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php require_once __DIR__ . '/src/helpers/head_meta.php'; ?>
    <title>Regal <?= $regalNr ?> – KulturInventar</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    <style>
        .regal-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 2rem);
        }

        .not-found {
            text-align: center;
            color: #666;
            padding: 2rem 0;
        }

        .regal-actions {
            margin-top: auto;
            position: sticky;
            bottom: 0;
            background: var(--color-bg);
            padding-top: var(--spacing);
        }
    </style>
</head>
<body>
<main class="regal-wrap">

    <section class="result-section">
        <?php if ($standortName === null): ?>
            <p class="not-found">Unbekanntes Regal „<?= htmlspecialchars((string) $regalNr) ?>".</p>
        <?php else: ?>
            <p class="result-label">
                <?= htmlspecialchars($standortName) ?> – Regal <?= $regalNr ?>
                (<?= count($artikelListe) ?> Artikel)
            </p>

            <?php if (empty($artikelListe)): ?>
                <p class="no-results">Dieses Regal ist aktuell leer.</p>
            <?php else: ?>
                <ul class="result-list">
                    <?php foreach ($artikelListe as $item): ?>
                        <li>
                            <a href="<?= BASE_URL ?>/artikel.php?id=<?= $item['id'] ?>" class="result-row">
                                <img
                                    class="result-thumb"
                                    src="<?= get_thumbnail($item) ?>"
                                    alt=""
                                    loading="lazy" width="100" height="95"
                                >
                                <div class="result-info">
                                    <span class="result-bezeichnung"><?= htmlspecialchars($item['bezeichnung']) ?></span>
                                    <span class="result-standort">
                                        Nr. <?= htmlspecialchars($item['inventarnummer']) ?>
                                        <?php if ((int) $item['menge'] > 1): ?>
                                            · <?= (int) $item['menge'] ?>x
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <div class="regal-actions">
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-back">Zurück zur Suche</a>
    </div>

</main>
</body>
</html>
