<?php
require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../schedule_store.php';
require_once __DIR__ . '/../db.php';

app_start_session();
header('Content-Type: application/json');

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];
$scheduleIndex = load_schedule_index($username);
$items = $scheduleIndex['items'] ?? [];
$activeId = $scheduleIndex['active_id'] ?? null;

$header = [
    'No',
    'Kode',
    'Nama Matakuliah',
    'SKS',
    'Kelas/Rombel',
    'Pengampu',
    'Jenis',
    'Ruang',
    'Hari',
    'Jam Mulai',
    'Jam Selesai',
    'Mode'
];

$schedules = [];
foreach ($items as $item) {
    $setId = $item['id'] ?? '';
    if ($setId === '') {
        continue;
    }

    $rows = get_schedule_rows($username, $setId);
    $normalizedRows = [];
    foreach ($rows as $row) {
        $normalizedRows[] = [
            'No' => $row['no_col'] ?? '',
            'Kode' => $row['kode'] ?? '',
            'Nama Matakuliah' => $row['nama_matakuliah'] ?? '',
            'SKS' => $row['sks'] ?? '',
            'Kelas/Rombel' => $row['kelas'] ?? '',
            'Pengampu' => $row['pengampu'] ?? '',
            'Jenis' => $row['jenis'] ?? '',
            'Ruang' => $row['ruang'] ?? '',
            'Hari' => $row['hari'] ?? '',
            'Jam Mulai' => $row['jam_mulai'] ?? '',
            'Jam Selesai' => $row['jam_selesai'] ?? '',
            'Mode' => $row['mode'] ?? 'luring'
        ];
    }

    $schedules[$setId] = [
        'id' => $setId,
        'name' => $item['name'] ?? 'Jadwal',
        'header' => $header,
        'rows' => $normalizedRows
    ];
}

$tasks = [];
$conn = db_connect();
$stmt = $conn->prepare('SELECT id, mata_kuliah, jenis, tanggal, jam, status, updated_at FROM task WHERE username=? ORDER BY tanggal ASC, COALESCE(jam, "23:59:59") ASC');
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
while ($task = $res->fetch_assoc()) {
    $tasks[] = [
        'id' => (int)($task['id'] ?? 0),
        'mata_kuliah' => $task['mata_kuliah'] ?? '',
        'jenis' => $task['jenis'] ?? '',
        'tanggal' => $task['tanggal'] ?? '',
        'jam' => $task['jam'] ?? '',
        'status' => $task['status'] ?? 'Belum selesai',
        'updated_at' => $task['updated_at'] ?? ''
    ];
}
$stmt->close();
$conn->close();

echo json_encode([
    'ok' => true,
    'generated_at' => date(DATE_ATOM),
    'schedule_index' => [
        'active_id' => $activeId,
        'items' => $items
    ],
    'schedules' => $schedules,
    'tasks' => $tasks
]);
