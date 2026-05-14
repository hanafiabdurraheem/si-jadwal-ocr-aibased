<?php
require_once __DIR__ . '/google_bootstrap.php';

if (empty($_SESSION['username'])) {
    google_redirect('index.php?route=login');
}

if (!google_require_dependency()) {
    google_flash_message('Integrasi Google Calendar belum tersedia di server hosting ini.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    google_redirect('index.php?route=pengaturan&tab=akun');
}

google_verify_csrf_or_fail($_POST['csrf_token'] ?? null);

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    google_flash_message('Akun login tidak valid untuk koneksi Google.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

try {
    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth2_state'] = $state;
    $_SESSION['oauth2_pending_user_id'] = $userId;
    $_SESSION['oauth2_pending_username'] = $_SESSION['username'] ?? '';

    $client = getGoogleClientBase();
    $client->setState($state);

    $authUrl = $client->createAuthUrl();
    google_redirect((string)filter_var($authUrl, FILTER_SANITIZE_URL));
} catch (Throwable $e) {
    google_flash_message('Gagal memulai OAuth Google: ' . $e->getMessage(), 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}
