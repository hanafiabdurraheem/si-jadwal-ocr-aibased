<?php
session_start();

if (empty($_SESSION['username'])) {
    header("Location: ../../login/index.php");
    exit();
}

header("Location: ../../kelas/index.php");
exit();
?>
