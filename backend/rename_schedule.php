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

$index = load_schedule_index($username);
$found = false;
foreach ($index['items'] as $idx => $item) {
    if (($item['id'] ?? null) === $scheduleId) {
        $index['items'][$idx]['name'] = $name;
        $index['items'][$idx]['updated_at'] = date('Y-m-d H:i:s');
        $found = true;
        break;
    }
}

if (!$found) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

save_schedule_index($username, $index);

echo json_encode(["ok" => true, "name" => $name]);
