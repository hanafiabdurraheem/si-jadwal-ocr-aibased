<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$userId = currentUserId();
$user = $userId ? getUserById($userId) : null;
$token = $userId ? getTokenRow($userId) : null;
$csrf = ensureCsrfToken();
$flash = pullFlash();

$isConnected = (bool)$token;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Calendar Sync</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 2rem; background: #f3f6fb; color: #1d2433; }
        .card { max-width: 640px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        .status { margin: 1rem 0; padding: 12px; border-radius: 8px; }
        .status.success { background: #e7f9ee; color: #0f6d32; }
        .status.error { background: #fdecec; color: #9f1f1f; }
        .status.warning { background: #fff6e5; color: #8b5d00; }
        .status.info { background: #eaf2ff; color: #174ea6; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 1rem; }
        button { border: 0; border-radius: 8px; padding: 10px 14px; cursor: pointer; color: #fff; }
        .btn-connect { background: #1a73e8; }
        .btn-sync { background: #188038; }
        .btn-disconnect { background: #d93025; }
        .muted { color: #5f6368; font-size: 0.95rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Google Calendar Sync</h1>

    <?php if ($flash): ?>
        <div class="status <?= htmlspecialchars((string)$flash['type'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars((string)$flash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$user): ?>
        <div class="status error">No logged-in user found. Set <code>$_SESSION['user_id']</code> from your login system first.</div>
    <?php else: ?>
        <p><strong>User:</strong> <?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></p>
        <p class="muted">
            Connection status:
            <strong><?= $isConnected ? 'Connected to Google' : 'Not connected' ?></strong>
        </p>

        <div class="actions">
            <form method="post" action="auth.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-connect">Connect Google Calendar</button>
            </form>

            <form method="post" action="sync.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-sync" <?= $isConnected ? '' : 'disabled' ?>>Sync Now</button>
            </form>

            <form method="post" action="disconnect.php" onsubmit="return confirm('Disconnect Google Calendar?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-disconnect" <?= $isConnected ? '' : 'disabled' ?>>Disconnect</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
