<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schedule_store.php';
require_once __DIR__ . '/task_store.php';
require_once __DIR__ . '/chatbot_prompt.php';

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Belum login']);
    exit;
}

$username = $_SESSION['username'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userMessage = trim($input['message'] ?? '');
$contextPage = $input['context'] ?? 'general';

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Pesan kosong']);
    exit;
}

function format_list($items) {
    if (empty($items)) return '';
    return implode("\n", array_map(function ($i) { return "- " . $i; }, $items));
}

// Kumpulkan konteks tugas hari ini
$allTasks = task_list_by_status($username, ['Belum selesai', 'Selesai', 'Arsip']);
$today = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$todayStr = $today->format('Y-m-d');

$todayList = [];
foreach ($allTasks as $task) {
    if (($task['tanggal'] ?? '') === $todayStr) {
        $todayList[] = sprintf(
            "%s (%s) - %s %s [%s]",
            $task['mata_kuliah'] ?? 'Tugas',
            $task['jenis'] ?? '-',
            $task['tanggal'] ?? '-',
            !empty($task['jam']) ? substr($task['jam'], 0, 5) : '',
            $task['status'] ?? 'Belum selesai'
        );
    }
}

// Jadwal hari ini
$todaySchedule = [];
$activeSchedule = resolve_active_schedule_item($username);
if ($activeSchedule) {
    $rows = get_schedule_rows($username, $activeSchedule['id']);
    date_default_timezone_set('Asia/Jakarta');
    $mapHari = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
    ];
    $hariNow = $mapHari[date('l')] ?? '';
    foreach ($rows as $row) {
        if (($row['hari'] ?? '') === $hariNow) {
            $todaySchedule[] = sprintf(
                "%s • %s-%s • %s",
                $row['nama_matakuliah'] ?? 'Kelas',
                $row['jam_mulai'] ?? '-',
                $row['jam_selesai'] ?? '-',
                $row['ruang'] ?? '-'
            );
        }
    }
}

// Kelas terdekat hari ini
$active = $activeSchedule;
$nextClass = '';
if ($active) {
    $rows = get_schedule_rows($username, $active['id']);
    date_default_timezone_set('Asia/Jakarta');
    $mapHari = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
    ];
    $hariNow = $mapHari[date('l')] ?? '';
    $now = strtotime(date('H:i'));
    $candidates = array_filter($rows, function ($r) use ($hariNow) {
        return ($r['hari'] ?? '') === $hariNow;
    });
    usort($candidates, function ($a, $b) {
        return strtotime(str_replace('.', ':', $a['jam_mulai'] ?? '')) <=> strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
    });
    foreach ($candidates as $row) {
        $jm = strtotime(str_replace('.', ':', $row['jam_mulai'] ?? ''));
        if ($jm !== false && $jm >= $now) {
            $nextClass = sprintf(
                "%s • %s %s-%s • %s",
                $row['nama_matakuliah'] ?? 'Kelas',
                $row['hari'] ?? '-',
                $row['jam_mulai'] ?? '-',
                $row['jam_selesai'] ?? '-',
                $row['ruang'] ?? '-'
            );
            break;
        }
    }
}

$lower = mb_strtolower($userMessage);
if (preg_match('/to-?do hari ini|todo hari ini/', $lower)) {
    $reply = "To-do hari ini:\n";
    if (!empty($todayList)) {
        $reply .= format_list($todayList);
    } else {
        $reply .= "- Belum ada tugas hari ini.\n";
    }
    if (!empty($todaySchedule)) {
        $reply .= "\nJadwal hari ini:\n" . format_list($todaySchedule);
    } else {
        $reply .= "\nJadwal hari ini kosong. Siapkan mata kuliah berikutnya atau besok ya.";
        if ($nextClass !== '') {
            $reply .= "\nKelas terdekat: " . $nextClass;
        }
    }
    echo json_encode(['ok' => true, 'reply' => $reply]);
    exit;
}

if (preg_match('/tugas terdekat|tugas belum selesai/', $lower)) {
    $pending = array_filter($allTasks, function ($t) {
        return ($t['status'] ?? '') === 'Belum selesai';
    });
    usort($pending, function ($a, $b) {
        $aTime = strtotime(($a['tanggal'] ?? '') . ' ' . ($a['jam'] ?? '23:59:59'));
        $bTime = strtotime(($b['tanggal'] ?? '') . ' ' . ($b['jam'] ?? '23:59:59'));
        return $aTime <=> $bTime;
    });
    $pendingList = [];
    foreach ($pending as $task) {
        $pendingList[] = sprintf(
            "%s (%s) - %s %s",
            $task['mata_kuliah'] ?? 'Tugas',
            $task['jenis'] ?? '-',
            $task['tanggal'] ?? '-',
            !empty($task['jam']) ? substr($task['jam'], 0, 5) : ''
        );
        if (count($pendingList) >= 5) break;
    }
    $reply = "Tugas terdekat (belum selesai):\n";
    if (!empty($pendingList)) {
        $reply .= format_list($pendingList);
    } else {
        $reply .= "- Tidak ada tugas yang belum selesai.";
    }
    echo json_encode(['ok' => true, 'reply' => $reply]);
    exit;
}

$contextBlock = "Halaman: {$contextPage}.\n";
$contextBlock .= "Tugas hari ini: " . (!empty($todayList) ? implode(" || ", $todayList) : "tidak ada.") . "\n";
$contextBlock .= "Kelas terdekat: " . ($nextClass !== '' ? $nextClass : "belum ada jadwal berikutnya.") . "\n";

$systemPrompt = chatbot_base_prompt() . "\n\nContext:\n" . $contextBlock;

$payload = [
    "model" => "gpt-4o-mini",
    "messages" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $userMessage],
    ],
    "temperature" => 0.35,
    "max_tokens" => 400,
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal terhubung ke AI.']);
    exit;
}

$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($status >= 300 || !$data || empty($data['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Jawaban AI tidak tersedia.']);
    exit;
}

$reply = $data['choices'][0]['message']['content'];
echo json_encode(['ok' => true, 'reply' => $reply]);
exit;
