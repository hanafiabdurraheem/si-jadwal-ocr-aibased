<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once APP_ROOT . '/model/login/index.php';

$flashMessage = null;
if (!empty($_SESSION['message'])) {
    $flashMessage = $_SESSION['message'];
    unset($_SESSION['message']);
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
