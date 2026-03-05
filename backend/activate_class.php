<?php
require_once __DIR__ . '/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/class_store.php';
require_once __DIR__ . '/schedule_store.php';

$kelasId = (int)($_POST['kelas_id'] ?? 0);
if ($kelasId <= 0) {
    header("Location: ../kelas/index.php?error=" . urlencode("Kelas tidak valid."));
    exit();
}

$latestSet = class_latest_schedule_set($kelasId);
if (!$latestSet || empty($latestSet['set_key'])) {
    header("Location: ../kelas/index.php?error=" . urlencode("Kelas belum memiliki jadwal."));
    exit();
}

$scheduleId = $latestSet['set_key'];
$item = set_active_schedule_id($_SESSION['username'], $scheduleId);
if (!$item) {
    header("Location: ../kelas/index.php?error=" . urlencode("Gagal mengaktifkan jadwal kelas."));
    exit();
}

$_SESSION['active_schedule_id'] = $scheduleId;
class_sync_tasks_for_member($_SESSION['username'], $kelasId);

header("Location: ../kelas/index.php?notice=" . urlencode("kelas_activated"));
exit();
?>
