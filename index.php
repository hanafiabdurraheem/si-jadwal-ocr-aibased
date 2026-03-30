<?php
session_start();

require_once __DIR__ . '/app/config/app.php';

$route = $_GET['route'] ?? null;

if ($route === null || $route === '') {
    if (!empty($_SESSION['username'])) {
        $route = 'beranda';
    } else {
        $route = 'login';
    }
}

$routes = [
    'beranda' => APP_ROOT . '/controller/beranda/index.php',
    'beranda-detailed' => APP_ROOT . '/controller/beranda/detailed/index.php',
    'jadwal' => APP_ROOT . '/controller/jadwal/index.php',
    'jadwal-confirm-edit' => APP_ROOT . '/controller/jadwal/confirm-edit-jadwal/index.php',
    'jadwal-view' => APP_ROOT . '/controller/jadwal/jadwalview/index.php',
    'tugas' => APP_ROOT . '/controller/tugas/index.php',
    'tugas-list' => APP_ROOT . '/controller/tugas/tugas-list/index.php',
    'pengingat' => APP_ROOT . '/controller/kelas/index.php',
    'pengaturan' => APP_ROOT . '/controller/pengaturan/index.php',
    'login' => APP_ROOT . '/controller/login/index.php',
    'signup' => APP_ROOT . '/controller/signup/index.php',
    'api-upload' => APP_ROOT . '/controller/api/upload.php',
    'api-set-active-schedule' => APP_ROOT . '/controller/api/set_active_schedule.php',
    'api-rename-schedule' => APP_ROOT . '/controller/api/rename_schedule.php',
    'api-delete-schedule' => APP_ROOT . '/controller/api/delete_schedule.php',
    'api-export-schedule' => APP_ROOT . '/controller/api/export_schedule.php',
    'api-save-schedule' => APP_ROOT . '/controller/api/save_schedule.php',
    'api-logout' => APP_ROOT . '/controller/api/logout.php',
    'api-chatbot' => APP_ROOT . '/controller/api/chatbot.php',
    'api-task-quick-add' => APP_ROOT . '/controller/api/task_quick_add.php',
    'api-update-schedule-day' => APP_ROOT . '/controller/api/update_schedule_day.php',
    'api-bacacsv-tugas' => APP_ROOT . '/controller/api/bacacsv-tugas.php',
    'api-google-connect' => APP_ROOT . '/controller/api/google_connect.php',
    'api-google-callback' => APP_ROOT . '/controller/api/google_callback.php',
    'api-google-sync' => APP_ROOT . '/controller/api/google_sync.php',
    'api-google-disconnect' => APP_ROOT . '/controller/api/google_disconnect.php',
];

if (!isset($routes[$route])) {
    http_response_code(404);
    echo "Halaman tidak ditemukan.";
    exit();
}

require $routes[$route];
