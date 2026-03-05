<?php
require_once __DIR__ . '/../../backend/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../../login/index.php");
    exit();
}

header("Location: ../../kelas/index.php");
exit();
?>
