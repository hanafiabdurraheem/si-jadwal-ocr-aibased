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
    google_flash_message('Akun login tidak valid.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

try {
    removeGoogleConnection($userId);
    clearAllScheduleEventIds($userId);
    google_flash_message('Google Calendar berhasil diputus.', 'success');
} catch (Throwable $e) {
    google_flash_message('Gagal memutus koneksi: ' . $e->getMessage(), 'error');
}

google_redirect('index.php?route=pengaturan&tab=akun');
