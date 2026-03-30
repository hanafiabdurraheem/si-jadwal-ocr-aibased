<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

require_once PROJECT_ROOT . '/google-calendar-sync/functions.php';

if (empty($_SESSION['username']) && !empty($_SESSION['oauth2_pending_username'])) {
    $_SESSION['username'] = (string)$_SESSION['oauth2_pending_username'];
}

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    $pendingUserId = $_SESSION['oauth2_pending_user_id'] ?? null;
    if (is_numeric($pendingUserId) && getUserById((int)$pendingUserId)) {
        $userId = (int)$pendingUserId;
        $_SESSION['user_id'] = $userId;
    }
}

if (!$userId || !getUserById($userId)) {
    flash('Akun login tidak valid untuk koneksi Google.', 'error');
    appRedirect('index.php?route=login');
}

$state = $_GET['state'] ?? '';
$storedState = $_SESSION['oauth2_state'] ?? '';
unset($_SESSION['oauth2_state']);

if ((!$state || !$storedState || !hash_equals($storedState, (string)$state)) && isset($_GET['code'])) {
    flash('State OAuth tidak cocok, tetapi token tetap diproses.', 'warning');
} else if (!$state || !$storedState || !hash_equals($storedState, (string)$state)) {
    flash('State OAuth tidak valid. Coba hubungkan ulang.', 'error');
    appRedirect('index.php?route=pengaturan&tab=akun');
}

if (isset($_GET['error'])) {
    flash('Otorisasi Google gagal: ' . (string)$_GET['error'], 'error');
    appRedirect('index.php?route=pengaturan&tab=akun');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    flash('Authorization code tidak ditemukan.', 'error');
    appRedirect('index.php?route=pengaturan&tab=akun');
}

try {
    $client = getGoogleClientBase();
    $token = $client->fetchAccessTokenWithAuthCode($code);

    if (isset($token['error'])) {
        $errorDesc = $token['error_description'] ?? $token['error'];
        throw new RuntimeException('Token exchange gagal: ' . $errorDesc);
    }

    upsertUserToken($userId, $token);
    flash('Google Calendar berhasil terhubung.', 'success');
} catch (Throwable $e) {
    flash('Callback OAuth gagal: ' . $e->getMessage(), 'error');
}

unset($_SESSION['oauth2_pending_user_id'], $_SESSION['oauth2_pending_username']);
appRedirect('index.php?route=pengaturan&tab=akun');
