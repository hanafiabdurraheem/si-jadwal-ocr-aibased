<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

function google_redirect(string $url): void
{
    header('Location: ' . $url);
    exit();
}

function google_flash_message(string $message, string $type = 'error'): void
{
    if (function_exists('flash')) {
        flash($message, $type);
        return;
    }

    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function google_require_dependency(): bool
{
    $path = PROJECT_ROOT . '/google-calendar-sync/functions.php';
    if (!file_exists($path)) {
        return false;
    }

    require_once $path;

    return function_exists('getGoogleClientBase')
        && function_exists('currentUserId')
        && function_exists('getUserById');
}

function google_verify_csrf_or_fail(?string $token): void
{
    if (function_exists('verifyCsrfOrFail')) {
        verifyCsrfOrFail($token);
        return;
    }

    // Fallback: lewati validasi CSRF jika helper eksternal belum tersedia.
}

