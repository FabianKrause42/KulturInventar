<?php
// -------------------------------------------------------
// Basis-URL automatisch ermitteln
// Lokal (localhost) → ''      z.B. http://localhost:8080/index.php
// Produktion         → '/kulturinventar'
// -------------------------------------------------------
if (!defined('BASE_URL')) {
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    define('BASE_URL', $isLocal ? '' : '/kulturinventar');
}
