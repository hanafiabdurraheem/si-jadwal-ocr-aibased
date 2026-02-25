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
if (!$input || empty($input['header']) || empty($input['rows'])) {
    echo json_encode(["ok" => false, "message" => "Payload tidak valid"]);
    exit();
}

$username = $_SESSION['username'];
$scheduleId = $input['scheduleId'] ?? ($_SESSION['active_schedule_id'] ?? null);
if (!$scheduleId) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

$header = $input['header'];
$rows = $input['rows'];
$name = $input['name'] ?? 'Jadwal';

$normalizedRows = [];
foreach ($rows as $row) {
    if (!is_array($row)) continue;
    $clean = [];
    foreach ($header as $col) {
        $clean[$col] = isset($row[$col]) ? (string)$row[$col] : "";
    }
    $normalizedRows[] = $clean;
}

replace_schedule_set($username, $scheduleId, $name, $normalizedRows, true);

$_SESSION['active_schedule_id'] = $scheduleId;

echo json_encode(["ok" => true]);
?>
