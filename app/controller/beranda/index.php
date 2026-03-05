<?php
session_start();

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

require_once APP_ROOT . '/model/beranda/index.php';

$viewData = beranda_build_view_data();
extract($viewData, EXTR_SKIP);

require APP_ROOT . '/view/beranda/index.php';
