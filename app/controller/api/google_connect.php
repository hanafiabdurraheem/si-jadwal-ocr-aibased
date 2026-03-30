<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    appRedirect('index.php?route=login');
}

require_once PROJECT_ROOT . '/google-calendar-sync/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appRedirect('index.php?route=pengaturan&tab=akun');
}

verifyCsrfOrFail($_POST['csrf_token'] ?? null);

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    flash('Akun login tidak valid untuk koneksi Google.', 'error');
    appRedirect('index.php?route=pengaturan&tab=akun');
}

try {
    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth2_state'] = $state;
    $_SESSION['oauth2_pending_user_id'] = $userId;
    $_SESSION['oauth2_pending_username'] = $_SESSION['username'] ?? '';

    $client = getGoogleClientBase();
    $client->setState($state);

    $authUrl = $client->createAuthUrl();
    appRedirect((string)filter_var($authUrl, FILTER_SANITIZE_URL));
} catch (Throwable $e) {
    flash('Gagal memulai OAuth Google: ' . $e->getMessage(), 'error');
    appRedirect('index.php?route=pengaturan&tab=akun');
}
