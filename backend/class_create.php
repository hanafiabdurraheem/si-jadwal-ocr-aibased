<?php
require_once __DIR__ . '/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/class_store.php';

$name = trim($_POST['class_name'] ?? '');
if ($name === '') {
    header("Location: ../kelas/index.php?error=" . urlencode("Nama kelas wajib diisi."));
    exit();
}

$result = class_create($_SESSION['username'], $name);
if (!$result) {
    header("Location: ../kelas/index.php?error=" . urlencode("Gagal membuat kelas."));
    exit();
}

header("Location: ../kelas/index.php?notice=" . urlencode("kelas_created"));
exit();
?>
