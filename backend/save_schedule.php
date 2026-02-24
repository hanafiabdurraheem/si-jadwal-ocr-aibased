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
$header = $input['header'];
$rows = $input['rows'];
$scheduleId = $input['scheduleId'] ?? ($_SESSION['active_schedule_id'] ?? null);

if (!$scheduleId) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

$index = load_schedule_index($username);
$item = find_schedule_item($index, $scheduleId);
if (!$item) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

$paths = schedule_item_paths($username, $item);
$csvPath = $paths['csv'];
$jsonPath = $paths['json'];

if (!$csvPath) {
    echo json_encode(["ok" => false, "message" => "Path CSV tidak valid"]);
    exit();
}

if (!$jsonPath) {
    if ($scheduleId === 'legacy') {
        $jsonPath = schedule_user_dir($username) . '/Kartu-Rencana-Studi_Aktif.json';
        $item['json'] = 'Kartu-Rencana-Studi_Aktif.json';
    } else {
        $jsonPath = dirname($csvPath) . '/result.json';
        $item['json'] = str_replace(schedule_user_dir($username) . '/', '', $jsonPath);
    }
}

if (!is_array($header)) {
    echo json_encode(["ok" => false, "message" => "Header tidak valid"]);
    exit();
}

$normalizedRows = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $cleanRow = [];
    foreach ($header as $col) {
        $cleanRow[$col] = isset($row[$col]) ? (string)$row[$col] : "";
    }
    $normalizedRows[] = $cleanRow;
}

$fp = fopen($csvPath, 'w');
fputcsv($fp, $header);
foreach ($normalizedRows as $row) {
    $line = [];
    foreach ($header as $col) {
        $line[] = $row[$col] ?? '';
    }
    fputcsv($fp, $line);
}
fclose($fp);

file_put_contents($jsonPath, json_encode($normalizedRows, JSON_PRETTY_PRINT));

$item['updated_at'] = date('Y-m-d H:i:s');
foreach ($index['items'] as $idx => $existing) {
    if (($existing['id'] ?? null) === $scheduleId) {
        $index['items'][$idx] = $item;
        break;
    }
}
$index['active_id'] = $scheduleId;
save_schedule_index($username, $index);

set_active_schedule_session($username, $item);
sync_active_schedule_copy($username, $item);

echo json_encode(["ok" => true]);
