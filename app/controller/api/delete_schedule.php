<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/app.php';
require_once PROJECT_ROOT . '/app/model/schedule_store.php';

if (empty($_SESSION['username'])) {
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty($input['scheduleId'])) {
    echo json_encode(["ok" => false, "message" => "Payload tidak valid"]);
    exit();
}

$username = $_SESSION['username'];
$scheduleId = trim((string)$input['scheduleId']);

if (delete_schedule_set($username, $scheduleId)) {
    $index = load_schedule_index($username);
    $activeId = $index['active_id'] ?? null;
    if ($activeId) {
        set_active_schedule_id($username, $activeId);
        $_SESSION['active_schedule_id'] = $activeId;
    } else {
        unset($_SESSION['active_schedule_id']);
    }
    echo json_encode(["ok" => true]);
    exit();
}

echo json_encode(["ok" => false, "message" => "Gagal menghapus jadwal"]);
?>
