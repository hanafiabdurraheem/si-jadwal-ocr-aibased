<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/schedule_store.php';

$username = $_SESSION['username'];
$scheduleId = isset($_GET['schedule_id']) ? trim($_GET['schedule_id']) : '';

if ($scheduleId === '') {
    $active = resolve_active_schedule_item($username);
    $scheduleId = $active['id'] ?? '';
}

if ($scheduleId === '') {
    http_response_code(404);
    echo "Jadwal tidak ditemukan.";
    exit();
}

$rows = get_schedule_rows($username, $scheduleId);
$index = load_schedule_index($username);
$item = find_schedule_item($index, $scheduleId);
$scheduleName = $item['name'] ?? 'jadwal';

$daysOrder = ["Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu", "Minggu"];
$orderMap = array_flip($daysOrder);

function normalize_time($value) {
    if (!$value) return '';
    if (preg_match('/(\\d{1,2})[.:](\\d{2})/', trim((string)$value), $match)) {
        return str_pad($match[1], 2, '0', STR_PAD_LEFT) . ':' . $match[2];
    }
    return '';
}

function time_to_minutes($value) {
    $normalized = normalize_time($value);
    if ($normalized === '') return null;
    [$h, $m] = array_map('intval', explode(':', $normalized));
    return ($h * 60) + $m;
}

usort($rows, function ($a, $b) use ($orderMap) {
    $dayA = trim($a['hari'] ?? '');
    $dayB = trim($b['hari'] ?? '');
    $orderA = $orderMap[$dayA] ?? 999;
    $orderB = $orderMap[$dayB] ?? 999;
    if ($orderA !== $orderB) {
        return $orderA <=> $orderB;
    }

    $timeA = time_to_minutes($a['jam_mulai'] ?? '');
    $timeB = time_to_minutes($b['jam_mulai'] ?? '');
    if ($timeA !== $timeB) {
        return ($timeA ?? 9999) <=> ($timeB ?? 9999);
    }

    return strcmp((string)($a['nama_matakuliah'] ?? ''), (string)($b['nama_matakuliah'] ?? ''));
});

$safeName = strtolower(preg_replace('/[^A-Za-z0-9_-]+/', '-', $scheduleName));
if ($safeName === '') {
    $safeName = 'jadwal';
}
$filename = $safeName . '-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
$header = ["No", "Kode", "Nama Matakuliah", "SKS", "Kelas/Rombel", "Pengampu", "Jenis", "Ruang", "Hari", "Jam Mulai", "Jam Selesai"];
fputcsv($output, $header);

foreach ($rows as $row) {
    $hari = trim($row['hari'] ?? '');
    if ($hari === '') {
        $hari = 'Tanpa Hari';
    }
    $line = [
        $row['no_col'] ?? '',
        $row['kode'] ?? '',
        $row['nama_matakuliah'] ?? '',
        $row['sks'] ?? '',
        $row['kelas'] ?? '',
        $row['pengampu'] ?? '',
        $row['jenis'] ?? '',
        $row['ruang'] ?? '',
        $hari,
        $row['jam_mulai'] ?? '',
        $row['jam_selesai'] ?? '',
    ];
    fputcsv($output, $line);
}

fclose($output);
exit();
