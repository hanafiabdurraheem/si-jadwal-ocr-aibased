<?php
session_start();

require_once __DIR__ . '/../../../config/app.php';
require_once APP_ROOT . '/model/jadwal/jadwalview/index.php';

$data = jadwalview_load();
if (!empty($data['error'])) {
    die($data['error']);
}

$header = $data['header'];
$jadwal = $data['jadwal'];
$currentDay = $data['currentDay'];
$prevDay = $data['prevDay'];
$nextDay = $data['nextDay'];

require APP_ROOT . '/view/jadwal/jadwalview/index.php';
