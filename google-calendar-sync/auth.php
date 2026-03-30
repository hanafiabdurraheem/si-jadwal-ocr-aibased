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
    flash('Please login first before connecting Google Calendar.', 'error');
    header('Location: index.php');
    exit;
}

try {
    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth2_state'] = $state;

    $client = getGoogleClientBase();
    $client->setState($state);

    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
} catch (Throwable $e) {
    flash('Failed to start Google OAuth flow: ' . $e->getMessage(), 'error');
    header('Location: index.php');
    exit;
}
