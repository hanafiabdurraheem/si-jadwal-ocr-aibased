<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/task_store.php';

if (empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Belum login']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$mataKuliah = trim($input['mata_kuliah'] ?? '');
$jenis = trim($input['jenis'] ?? '');
$deadline = trim($input['deadline'] ?? '');

if ($mataKuliah === '' || $jenis === '' || $deadline === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Data belum lengkap']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Format deadline harus YYYY-MM-DD']);
    exit;
}

task_add($_SESSION['username'], $mataKuliah, $jenis, $deadline, null);
echo json_encode(['ok' => true]);
exit;
