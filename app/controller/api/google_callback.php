<?php
require_once __DIR__ . '/google_bootstrap.php';

if (!google_require_dependency()) {
    google_flash_message('Integrasi Google Calendar belum tersedia di server hosting ini.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

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
    google_flash_message('Akun login tidak valid untuk koneksi Google.', 'error');
    google_redirect('index.php?route=login');
}

$state = $_GET['state'] ?? '';
$storedState = $_SESSION['oauth2_state'] ?? '';
unset($_SESSION['oauth2_state']);

if ((!$state || !$storedState || !hash_equals($storedState, (string)$state)) && isset($_GET['code'])) {
    google_flash_message('State OAuth tidak cocok, tetapi token tetap diproses.', 'warning');
} else if (!$state || !$storedState || !hash_equals($storedState, (string)$state)) {
    google_flash_message('State OAuth tidak valid. Coba hubungkan ulang.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

if (isset($_GET['error'])) {
    google_flash_message('Otorisasi Google gagal: ' . (string)$_GET['error'], 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    google_flash_message('Authorization code tidak ditemukan.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

try {
    $client = getGoogleClientBase();
    $token = $client->fetchAccessTokenWithAuthCode($code);

    if (isset($token['error'])) {
        $errorDesc = $token['error_description'] ?? $token['error'];
        throw new RuntimeException('Token exchange gagal: ' . $errorDesc);
    }

    upsertUserToken($userId, $token);
    google_flash_message('Google Calendar berhasil terhubung.', 'success');
} catch (Throwable $e) {
    google_flash_message('Callback OAuth gagal: ' . $e->getMessage(), 'error');
}

unset($_SESSION['oauth2_pending_user_id'], $_SESSION['oauth2_pending_username']);
google_redirect('index.php?route=pengaturan&tab=akun');
