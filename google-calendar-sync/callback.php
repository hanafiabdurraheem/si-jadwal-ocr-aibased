<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    flash('Please login first before connecting Google Calendar.', 'error');
    header('Location: ../index.php?route=pengaturan&tab=akun');
    exit;
}

$state = $_GET['state'] ?? '';
$storedState = $_SESSION['oauth2_state'] ?? '';
unset($_SESSION['oauth2_state']);

if (!$state || !$storedState || !hash_equals($storedState, (string)$state)) {
    flash('Invalid OAuth state. Please retry connection.', 'error');
    header('Location: ../index.php?route=pengaturan&tab=akun');
    exit;
}

if (isset($_GET['error'])) {
    flash('Google authorization failed: ' . (string)$_GET['error'], 'error');
    header('Location: ../index.php?route=pengaturan&tab=akun');
    exit;
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    flash('Authorization code is missing.', 'error');
    header('Location: ../index.php?route=pengaturan&tab=akun');
    exit;
}

try {
    $client = getGoogleClientBase();
    $token = $client->fetchAccessTokenWithAuthCode($code);

    if (isset($token['error'])) {
        $errorDesc = $token['error_description'] ?? $token['error'];
        throw new RuntimeException('Token exchange failed: ' . $errorDesc);
    }

    upsertUserToken($userId, $token);

    flash('Google Calendar connected successfully.', 'success');
    header('Location: ../index.php?route=pengaturan&tab=akun');
    exit;
} catch (Throwable $e) {
    flash('OAuth callback error: ' . $e->getMessage(), 'error');
    header('Location: ../index.php?route=pengaturan&tab=akun');
    exit;
}
