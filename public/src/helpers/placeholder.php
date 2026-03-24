<?php
declare(strict_types=1);

// -------------------------------------------------------
// Platzhalterbild passend zur Kategorie zurückgeben.
// Wenn bild_pfad gesetzt ist, dieses verwenden.
// Sonst ein Kategorie-Platzhalterbild aus dem Ordner
//   /assets/img/placeholder/
// -------------------------------------------------------

function get_thumbnail(array $artikel): string
{
    if (!empty($artikel['bild_pfad'])) {
        return htmlspecialchars($artikel['bild_pfad'], ENT_QUOTES, 'UTF-8');
    }

    $map = [
        'Audio'     => BASE_URL . '/assets/img/placeholder/audio.png',
        'Licht'     => BASE_URL . '/assets/img/placeholder/licht.png',
        'Video'     => BASE_URL . '/assets/img/placeholder/video.png',
        'IT'        => BASE_URL . '/assets/img/placeholder/it.png',
        'Bühne'     => BASE_URL . '/assets/img/placeholder/buehne.png',
        'Möbel'     => BASE_URL . '/assets/img/placeholder/moebel.png',
        'Kabel'     => BASE_URL . '/assets/img/placeholder/kabel.png',
        'Werkzeug'  => BASE_URL . '/assets/img/placeholder/werkzeug.png',
        'Sonstiges' => BASE_URL . '/assets/img/placeholder/sonstiges.png',
    ];

    return $map[$artikel['kategorie']] ?? BASE_URL . '/assets/img/placeholder/sonstiges.svg';
}
