<?php
require_once __DIR__ . '/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/class_store.php';

$kelasId = (int)($_POST['kelas_id'] ?? 0);
$mataKuliah = trim($_POST['mata_kuliah'] ?? '');
$jenis = trim($_POST['jenis'] ?? '');
$tanggal = trim($_POST['tanggal'] ?? '');
$jam = trim($_POST['jam'] ?? '');

$jam = $jam !== '' ? $jam : null;

if ($kelasId <= 0 || $mataKuliah === '' || $jenis === '' || $tanggal === '') {
    header("Location: ../kelas/index.php?error=" . urlencode("Lengkapi data tugas kelas."));
    exit();
}

$result = class_add_task($kelasId, $_SESSION['username'], $mataKuliah, $jenis, $tanggal, $jam);
if (empty($result['ok'])) {
    $message = $result['message'] ?? 'Gagal menambah tugas.';
    header("Location: ../kelas/index.php?error=" . urlencode($message));
    exit();
}

header("Location: ../kelas/index.php?notice=" . urlencode("tugas_shared"));
exit();
?>
