<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

$username = $_SESSION['username'];

require_once APP_ROOT . '/model/jadwal/index.php';

$viewData = jadwal_load_view_data($username);
extract($viewData, EXTR_SKIP);

require APP_ROOT . '/view/jadwal/index.php';
