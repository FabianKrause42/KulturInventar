<?php
declare(strict_types=1);

// -------------------------------------------------------
// Session-Helper
// Starten der Session und Zugriffsprüfung
// -------------------------------------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login(): void
{
    // Auf localhost automatisch einloggen (nur für lokale Entwicklung)
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if ($local && !is_logged_in()) {
        $_SESSION['user_id']   = -1;
        $_SESSION['user_name'] = 'Dev';
    }

    if (!is_logged_in()) {
        $current = $_SERVER['REQUEST_URI'] ?? '';
        $redirect = $current !== '' ? '?redirect=' . urlencode($current) : '';
        header('Location: ' . BASE_URL . '/login.php' . $redirect);
        exit;
    }
}
