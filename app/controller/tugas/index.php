<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

$username = $_SESSION['username'];

require_once APP_ROOT . '/model/tugas/index.php';

$submitMessage = tugas_handle_submit($username);
if ($submitMessage) {
    echo $submitMessage;
}

$mataKuliahList = tugas_get_mata_kuliah_list($username);

require APP_ROOT . '/view/tugas/index.php';
