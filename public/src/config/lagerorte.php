<?php
declare(strict_types=1);

// Zentrale Liste der Lagerplätze und ihrer Regalbereiche (falls vorhanden)
return [
    'standorte' => ['RON', 'Schubertsaal', 'Großes Magazin', 'Theaterkeller', 'Werkzeuglager', 'In Gebrauch'],
    'regalBereiche' => [
        'RON'            => range(301, 306),
        'Großes Magazin' => range(101, 111),
    ],
];
