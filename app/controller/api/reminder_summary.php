<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/app.php';
require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/model/task_store.php';

if (empty($_SESSION['username'])) {
    echo json_encode(["ok" => false, "message" => "Unauthorized"]);
    exit();
}

date_default_timezone_set('Asia/Jakarta');
$username = (string)$_SESSION['username'];

$upcomingSchedule = null;
$active = resolve_active_schedule_item($username);
if ($active) {
    $hariIni = date('l');
    $mapHari = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    $hariIndonesia = $mapHari[$hariIni] ?? '';
    $rows = get_schedule_rows($username, $active['id']);
    $filtered = array_filter($rows, function ($row) use ($hariIndonesia) {
        return isset($row['hari']) && $row['hari'] === $hariIndonesia;
    });
    usort($filtered, function ($a, $b) {
        $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
        $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
        return $timeA <=> $timeB;
    });

    $now = strtotime(date('H:i'));
    foreach ($filtered as $row) {
        $jadwalTime = strtotime(str_replace('.', ':', $row['jam_mulai'] ?? ''));
        if ($jadwalTime !== false && $jadwalTime >= $now) {
            $upcomingSchedule = [
                'mata_kuliah' => $row['nama_matakuliah'] ?? '',
                'jam_mulai' => $row['jam_mulai'] ?? ''
            ];
            break;
        }
    }
}

$tasks = task_list_by_status($username, ['Belum selesai']);
$nowTs = time();
$pendingTasks = [];
foreach ($tasks as $task) {
    $deadlineStr = ($task['tanggal'] ?? '') . ' ' . (($task['jam'] ?? '') !== null ? $task['jam'] : '23:59:59');
    $timestamp = strtotime($deadlineStr);
    $pendingTasks[] = [
        'id' => (int)($task['id'] ?? 0),
        'mata_kuliah' => $task['mata_kuliah'] ?? '',
        'jenis' => $task['jenis'] ?? '',
        'tanggal' => $task['tanggal'] ?? '',
        'jam' => $task['jam'] ?? null,
        'is_overdue' => $timestamp ? ($timestamp < $nowTs) : false
    ];
}

usort($pendingTasks, function ($a, $b) {
    $timeA = strtotime(($a['tanggal'] ?? '') . ' ' . (($a['jam'] ?? '') ?: '23:59:59'));
    $timeB = strtotime(($b['tanggal'] ?? '') . ' ' . (($b['jam'] ?? '') ?: '23:59:59'));
    return ($timeA ?: PHP_INT_MAX) <=> ($timeB ?: PHP_INT_MAX);
});

echo json_encode([
    "ok" => true,
    "data" => [
        "upcoming_schedule" => $upcomingSchedule,
        "pending_tasks" => $pendingTasks
    ]
]);

