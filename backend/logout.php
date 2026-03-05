<?php
require_once __DIR__ . '/session.php';
app_start_session();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/si-jadwal',
        $params['domain'] ?? '',
        !empty($params['secure']),
        !empty($params['httponly'])
    );
}

session_unset();
session_destroy();

if (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

header("Location: ../login/index.php");
exit;
