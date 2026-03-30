<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verifyCsrfOrFail($_POST['csrf_token'] ?? null);

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    flash('Please login first.', 'error');
    header('Location: index.php');
    exit;
}

try {
    removeGoogleConnection($userId);
    clearAllScheduleEventIds($userId);

    flash('Google Calendar disconnected. Stored tokens removed.', 'success');
} catch (Throwable $e) {
    flash('Disconnect failed: ' . $e->getMessage(), 'error');
}

header('Location: index.php');
exit;
