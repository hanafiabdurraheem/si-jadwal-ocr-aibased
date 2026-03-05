<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

$username = $_SESSION['username'];

require_once APP_ROOT . '/model/kelas/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action'])) {
    $action = $_POST['task_action'];
    $taskId = (int)($_POST['task_id'] ?? 0);

    if ($taskId > 0) {
        if ($action === 'done') {
            task_update_status($username, $taskId, 'Selesai');
        } elseif ($action === 'pending') {
            task_update_status($username, $taskId, 'Belum selesai');
        } elseif ($action === 'archive') {
            task_update_status($username, $taskId, 'Arsip');
        } elseif ($action === 'delete') {
            task_delete($username, $taskId);
        }
    }

    header("Location: index.php?route=pengingat");
    exit();
}

$viewData = kelas_load_tasks($username);
extract($viewData, EXTR_SKIP);

require APP_ROOT . '/view/kelas/index.php';
