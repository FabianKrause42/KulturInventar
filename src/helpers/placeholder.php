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
        'Audio'     => '/assets/img/placeholder/audio.png',
        'Licht'     => '/assets/img/placeholder/licht.png',
        'Video'     => '/assets/img/placeholder/video.png',
        'IT'        => '/assets/img/placeholder/it.png',
        'Bühne'     => '/assets/img/placeholder/buehne.png',
        'Möbel'     => '/assets/img/placeholder/moebel.png',
        'Kabel'     => '/assets/img/placeholder/kabel.png',
        'Werkzeug'  => '/assets/img/placeholder/werkzeug.png',
        'Sonstiges' => '/assets/img/placeholder/sonstiges.png',
    ];

    return $map[$artikel['kategorie']] ?? '/assets/img/placeholder/sonstiges.svg';
}
