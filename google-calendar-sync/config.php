<?php

declare(strict_types=1);

// Compatibility shim: Google sync config is now centralized in app/config/config.php
require_once __DIR__ . '/../app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
} else {
    date_default_timezone_set('Asia/Jakarta');
}
