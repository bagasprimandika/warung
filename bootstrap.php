<?php
require_once __DIR__ . '/config.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function require_login(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user']['role'] ?? 'customer') !== 'admin') {
        http_response_code(403);
        exit('Akses hanya tersedia untuk administrator.');
    }
}
?>
