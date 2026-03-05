<?php
require_once __DIR__ . '/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/class_store.php';

$code = $_POST['class_code'] ?? ($_GET['code'] ?? '');
$code = strtoupper(trim((string)$code));
if ($code === '') {
    header("Location: ../kelas/index.php?error=" . urlencode("Kode kelas wajib diisi."));
    exit();
}

$kelas = class_get_by_code($code);
if (!$kelas) {
    header("Location: ../kelas/index.php?error=" . urlencode("Kode kelas tidak ditemukan."));
    exit();
}

$joined = class_join($_SESSION['username'], (int)$kelas['id']);
if (!$joined) {
    header("Location: ../kelas/index.php?error=" . urlencode("Gagal bergabung ke kelas."));
    exit();
}

class_sync_member($_SESSION['username'], (int)$kelas['id']);

header("Location: ../kelas/index.php?notice=" . urlencode("kelas_joined"));
exit();
?>
