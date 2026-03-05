<?php

function app_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}

function app_session_cookie_lifetime(): int {
    return 60 * 60 * 24 * 30; // 30 hari
}

function app_configure_session(): void {
    static $configured = false;
    if ($configured) {
        return;
    }

    $configured = true;

    $lifetime = app_session_cookie_lifetime();
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/si-jadwal',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function app_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        app_configure_session();
        session_start();
    }
}
