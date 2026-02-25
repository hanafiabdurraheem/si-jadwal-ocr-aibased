<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit();
}

require_once __DIR__ . '/schedule_store.php';

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty($input['scheduleId'])) {
    echo json_encode(["ok" => false, "message" => "Payload tidak valid"]);
    exit();
}

$username = $_SESSION['username'];
$scheduleId = trim((string)$input['scheduleId']);

$item = set_active_schedule_id($username, $scheduleId);
if (!$item) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

$_SESSION['active_schedule_id'] = $scheduleId;

echo json_encode(["ok" => true]);
?>
