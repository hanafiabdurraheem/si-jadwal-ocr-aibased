<?php
session_start();

require_once __DIR__ . '/../../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

header("Location: index.php?route=pengingat");
exit();
