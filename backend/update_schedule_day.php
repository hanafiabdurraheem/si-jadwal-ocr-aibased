<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit();
}

$username = $_SESSION['username'];
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['rowIndex']) || !isset($input['newDay'])) {
    echo json_encode(["ok" => false, "message" => "Payload tidak valid"]);
    exit();
}

$rowIndex = (int)$input['rowIndex'];
$newDay = trim((string)$input['newDay']);

$daysOrder = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
$extraDay = "Tanpa Hari";

if ($newDay === $extraDay) {
    $newDayValue = "";
} elseif (in_array($newDay, $daysOrder, true)) {
    $newDayValue = $newDay;
} else {
    echo json_encode(["ok" => false, "message" => "Hari tidak valid"]);
    exit();
}

function find_latest_result_csv($userDir) {
    $candidates = glob($userDir . "/*/result.csv");
    if (empty($candidates)) {
        return null;
    }

    usort($candidates, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    return $candidates[0];
}

$userDir = __DIR__ . "/../uploads/" . $username;
$csvPath = $_SESSION['active_schedule_csv'] ?? null;

if (!$csvPath || !file_exists($csvPath)) {
    $latest = find_latest_result_csv($userDir);
    if ($latest && file_exists($latest)) {
        $csvPath = $latest;
        $_SESSION['active_schedule_csv'] = $csvPath;
    } else {
        $fallback = $userDir . "/Kartu-Rencana-Studi_Aktif.csv";
        if (file_exists($fallback)) {
            $csvPath = $fallback;
            $_SESSION['active_schedule_csv'] = $csvPath;
        }
    }
}

if (!$csvPath || !file_exists($csvPath)) {
    echo json_encode(["ok" => false, "message" => "File jadwal tidak ditemukan"]);
    exit();
}

$header = [];
$rows = [];

if (($handle = fopen($csvPath, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    if ($header) {
        $header = array_map('trim', $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    }

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rows[] = $data;
    }
    fclose($handle);
}

if (empty($header)) {
    echo json_encode(["ok" => false, "message" => "Header CSV tidak valid"]);
    exit();
}

$hariIndex = array_search('Hari', $header, true);
if ($hariIndex === false) {
    echo json_encode(["ok" => false, "message" => "Kolom 'Hari' tidak ditemukan"]);
    exit();
}

if (!isset($rows[$rowIndex])) {
    echo json_encode(["ok" => false, "message" => "Baris tidak ditemukan"]);
    exit();
}

$rows[$rowIndex][$hariIndex] = $newDayValue;

$fp = fopen($csvPath, 'w');
fputcsv($fp, $header);
foreach ($rows as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

echo json_encode(["ok" => true]);
