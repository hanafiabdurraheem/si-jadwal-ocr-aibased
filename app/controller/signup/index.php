<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once APP_ROOT . '/model/signup/index.php';

$error = '';

$result = signup_handle_submit();
if (isset($result['error'])) {
    $error = $result['error'];
}

require APP_ROOT . '/view/signup/index.php';
