<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once APP_ROOT . '/model/login/index.php';

$flashMessage = null;
$flashType = 'success';
if (!empty($_SESSION['message'])) {
    $flashMessage = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (!empty($_SESSION['message_type'])) {
    $flashType = (string)$_SESSION['message_type'];
    unset($_SESSION['message_type']);
}

$error = null;

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $result = login_attempt($username, $password);
    if ($result['ok']) {
        $_SESSION['username'] = $username;
        header("Location: index.php?route=beranda");
        exit();
    }
    $error = $result['error'];
}

require APP_ROOT . '/view/login/index.php';
