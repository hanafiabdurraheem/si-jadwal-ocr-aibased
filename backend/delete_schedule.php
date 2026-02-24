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

$index = load_schedule_index($username);
$item = find_schedule_item($index, $scheduleId);
if (!$item) {
    echo json_encode(["ok" => false, "message" => "Jadwal tidak ditemukan"]);
    exit();
}

if ($scheduleId === 'legacy') {
    echo json_encode(["ok" => false, "message" => "Jadwal legacy tidak dapat dihapus"]);
    exit();
}

$paths = schedule_item_paths($username, $item);
$csvPath = $paths['csv'];
$folder = $csvPath ? dirname($csvPath) : null;
$userDir = schedule_user_dir($username);

function delete_recursive($path) {
    if (!file_exists($path)) {
        return true;
    }
    if (is_file($path) || is_link($path)) {
        return unlink($path);
    }
    $items = scandir($path);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!delete_recursive($path . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($path);
}

if ($folder && strpos(realpath($folder) ?: '', realpath($userDir)) === 0) {
    if (!delete_recursive($folder)) {
        echo json_encode(["ok" => false, "message" => "Gagal menghapus folder jadwal"]);
        exit();
    }
}

$index['items'] = array_values(array_filter($index['items'], function ($row) use ($scheduleId) {
    return ($row['id'] ?? null) !== $scheduleId;
}));

$activeId = $index['active_id'] ?? null;
if ($activeId === $scheduleId) {
    $index['active_id'] = $index['items'][0]['id'] ?? null;
}

save_schedule_index($username, $index);

if (!empty($index['active_id'])) {
    set_active_schedule_id($username, $index['active_id']);
} else {
    unset($_SESSION['active_schedule_id'], $_SESSION['active_schedule_csv'], $_SESSION['active_schedule_json']);
}

echo json_encode(["ok" => true]);
