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
require_once __DIR__ . '/db.php';

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || empty($input['scheduleId']) || !isset($input['name'])) {
    echo json_encode(["ok" => false, "message" => "Payload tidak valid"]);
    exit();
}

$username = $_SESSION['username'];
$scheduleId = trim((string)$input['scheduleId']);
$name = trim((string)$input['name']);

if ($name === '') {
    echo json_encode(["ok" => false, "message" => "Nama tidak boleh kosong"]);
    exit();
}

$conn = db_connect();
$stmt = $conn->prepare("UPDATE `schedule` SET name=? WHERE username=? AND set_id=?");
$stmt->bind_param('sss', $name, $username, $scheduleId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

if ($affected <= 0) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

echo json_encode(["ok" => true, "name" => $name]);
?>
