<?php
require_once __DIR__ . '/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/class_store.php';

$kelasId = (int)($_POST['kelas_id'] ?? 0);
$scheduleId = trim($_POST['schedule_id'] ?? '');

if ($kelasId <= 0 || $scheduleId === '') {
    header("Location: ../kelas/index.php?error=" . urlencode("Pilih kelas dan jadwal."));
    exit();
}

$result = class_share_schedule_set($kelasId, $_SESSION['username'], $scheduleId);
if (empty($result['ok'])) {
    $message = $result['message'] ?? 'Gagal membagikan jadwal.';
    header("Location: ../kelas/index.php?error=" . urlencode($message));
    exit();
}

header("Location: ../kelas/index.php?notice=" . urlencode("jadwal_shared"));
exit();
?>
