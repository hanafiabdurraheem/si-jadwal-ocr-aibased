<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

$username = $_SESSION['username'];

require_once APP_ROOT . '/model/beranda/detailed/index.php';

$data = beranda_detailed_load($username);
if (!empty($data['error'])) {
    echo $data['error'];
    exit;
}

$jadwalYangDitampilkan = $data['jadwalYangDitampilkan'];
$fields = $data['fields'];

require APP_ROOT . '/view/beranda/detailed/index.php';
