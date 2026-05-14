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

require_once PROJECT_ROOT . '/app/model/schedule_store.php';

$requestedId = isset($_GET['schedule_id']) ? trim($_GET['schedule_id']) : null;
if ($requestedId) {
    set_active_schedule_id($username, $requestedId);
}

require_once APP_ROOT . '/model/jadwal/confirm-edit-jadwal/index.php';

$data = jadwal_confirm_edit_load($username);
if (!empty($data['error'])) {
    echo $data['error'];
    exit();
}

$activeItem = $data['activeItem'];
$header = $data['header'];
$rowsAssoc = $data['rowsAssoc'];
$jadwal = $data['jadwal'];
$daysOrder = $data['daysOrder'];
$extraDay = $data['extraDay'];

require APP_ROOT . '/view/jadwal/confirm-edit-jadwal/index.php';
