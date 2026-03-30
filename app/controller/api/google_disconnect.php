<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    header('Location: index.php?route=login');
    exit();
}

require_once PROJECT_ROOT . '/google-calendar-sync/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?route=pengaturan&tab=akun');
    exit;
}

verifyCsrfOrFail($_POST['csrf_token'] ?? null);

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    flash('Akun login tidak valid.', 'error');
    header('Location: index.php?route=pengaturan&tab=akun');
    exit;
}

try {
    removeGoogleConnection($userId);
    clearAllScheduleEventIds($userId);
    flash('Google Calendar berhasil diputus.', 'success');
} catch (Throwable $e) {
    flash('Gagal memutus koneksi: ' . $e->getMessage(), 'error');
}

header('Location: index.php?route=pengaturan&tab=akun');
exit;
