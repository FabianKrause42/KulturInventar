<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Tag-Hilfsfunktionen
// ---------------------------------------------------------------

/**
 * Komma-getrennten Tag-String in bereinigtes Array umwandeln (max. 3).
 */
function parseTags(string $input): array
{
    $parts = explode(',', $input);
    $tags  = [];
    foreach ($parts as $p) {
        $p = mb_substr(trim($p), 0, 50);
        if ($p !== '' && !in_array($p, $tags, true)) {
            $tags[] = $p;
        }
    }
    return array_slice($tags, 0, 3);
}

/**
 * Tags für einen Artikel speichern (löscht vorher alle bestehenden Verknüpfungen).
 * Neue Tags werden automatisch in die tags-Tabelle eingefügt.
 */
function setzeTags(int $inventar_id, array $tags, PDO $pdo): void
{
    $pdo->prepare('DELETE FROM inventar_tags WHERE inventar_id = ?')->execute([$inventar_id]);

    foreach ($tags as $name) {
        $name = mb_substr(trim($name), 0, 50);
        if ($name === '') continue;

        $pdo->prepare('INSERT IGNORE INTO tags (name) VALUES (?)')->execute([$name]);

        $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $tagId = $stmt->fetchColumn();

        if ($tagId) {
            $pdo->prepare(
                'INSERT IGNORE INTO inventar_tags (inventar_id, tag_id) VALUES (?, ?)'
            )->execute([$inventar_id, (int) $tagId]);
        }
    }
}

/**
 * Tags eines Artikels als Array laden.
 */
function ladeTags(int $inventar_id, PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT t.name FROM tags t
         JOIN inventar_tags it ON it.tag_id = t.id
         WHERE it.inventar_id = ?
         ORDER BY t.name ASC'
    );
    $stmt->execute([$inventar_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
