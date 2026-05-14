<?php

require_once __DIR__ . '/../database/db.php';

function ocr_ensure_storage($conn)
{
    static $ready = false;
    if ($ready) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS `ocr_usage` (
        `username` VARCHAR(191) NOT NULL PRIMARY KEY,
        `used_count` INT NOT NULL DEFAULT 0,
        `throttle_hits` INT NOT NULL DEFAULT 0,
        `throttle_window_start` DATETIME NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$conn->query($sql)) {
        return false;
    }

    $ready = true;
    return true;
}

function ocr_ensure_user_row($conn, $username)
{
    $stmt = $conn->prepare(
        "INSERT INTO ocr_usage (username, used_count, throttle_hits, throttle_window_start)
         VALUES (?, 0, 0, NULL)
         ON DUPLICATE KEY UPDATE username = username"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $username);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function ocr_get_quota_status($conn, $username, $limit = 10)
{
    if (!ocr_ensure_storage($conn) || !ocr_ensure_user_row($conn, $username)) {
        return [
            'ok' => false,
            'message' => 'Gagal menyiapkan storage OCR.'
        ];
    }

    $stmt = $conn->prepare("SELECT used_count FROM ocr_usage WHERE username = ? LIMIT 1");
    if (!$stmt) {
        return [
            'ok' => false,
            'message' => 'Gagal membaca kuota OCR.'
        ];
    }

    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        $stmt->close();
        return [
            'ok' => false,
            'message' => 'Gagal membaca kuota OCR.'
        ];
    }

    $rows = db_stmt_fetch_all_assoc($stmt);
    $row = $rows[0] ?? null;
    $stmt->close();

    $used = isset($row['used_count']) ? (int)$row['used_count'] : 0;
    $remaining = max(0, (int)$limit - $used);

    return [
        'ok' => true,
        'limit' => (int)$limit,
        'used' => $used,
        'remaining' => $remaining
    ];
}

function ocr_register_request($conn, $username, $maxRequests = 3, $windowSeconds = 20)
{
    if (!ocr_ensure_storage($conn) || !ocr_ensure_user_row($conn, $username)) {
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal menyiapkan throttle OCR.'
        ];
    }

    if (!$conn->begin_transaction()) {
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal membuka transaksi throttle OCR.'
        ];
    }

    $stmt = $conn->prepare(
        "SELECT throttle_hits, throttle_window_start
         FROM ocr_usage
         WHERE username = ?
         FOR UPDATE"
    );
    if (!$stmt) {
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal memproses throttle OCR.'
        ];
    }

    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal memproses throttle OCR.'
        ];
    }

    $rows = db_stmt_fetch_all_assoc($stmt);
    $row = $rows[0] ?? null;
    $stmt->close();

    $now = time();
    $hits = isset($row['throttle_hits']) ? (int)$row['throttle_hits'] : 0;
    $windowStartRaw = $row['throttle_window_start'] ?? null;
    $windowStartTs = $windowStartRaw ? strtotime($windowStartRaw) : 0;
    $elapsed = $windowStartTs > 0 ? ($now - $windowStartTs) : PHP_INT_MAX;
    $mustReset = ($windowStartTs <= 0) || ($elapsed >= (int)$windowSeconds);

    if ($mustReset) {
        $newHits = 1;
        $newWindowStart = date('Y-m-d H:i:s', $now);
    } else {
        if ($hits >= (int)$maxRequests) {
            $conn->commit();
            return [
                'ok' => true,
                'allowed' => false,
                'retry_after' => max(1, (int)$windowSeconds - max(0, $elapsed)),
                'hits' => $hits
            ];
        }
        $newHits = $hits + 1;
        $newWindowStart = $windowStartRaw;
    }

    $update = $conn->prepare(
        "UPDATE ocr_usage
         SET throttle_hits = ?, throttle_window_start = ?, updated_at = CURRENT_TIMESTAMP
         WHERE username = ?"
    );
    if (!$update) {
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal menyimpan throttle OCR.'
        ];
    }

    $update->bind_param('iss', $newHits, $newWindowStart, $username);
    $ok = $update->execute();
    $update->close();

    if (!$ok) {
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal menyimpan throttle OCR.'
        ];
    }

    $conn->commit();

    return [
        'ok' => true,
        'allowed' => true,
        'retry_after' => 0,
        'hits' => $newHits
    ];
}

function ocr_consume_quota($conn, $username, $limit = 10)
{
    if (!ocr_ensure_storage($conn) || !ocr_ensure_user_row($conn, $username)) {
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal menyiapkan kuota OCR.'
        ];
    }

    if (!$conn->begin_transaction()) {
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal membuka transaksi kuota OCR.'
        ];
    }

    $stmt = $conn->prepare(
        "SELECT used_count
         FROM ocr_usage
         WHERE username = ?
         FOR UPDATE"
    );
    if (!$stmt) {
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal membaca kuota OCR.'
        ];
    }

    $stmt->bind_param('s', $username);
    if (!$stmt->execute()) {
        $stmt->close();
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal membaca kuota OCR.'
        ];
    }

    $rows = db_stmt_fetch_all_assoc($stmt);
    $row = $rows[0] ?? null;
    $stmt->close();

    $used = isset($row['used_count']) ? (int)$row['used_count'] : 0;
    if ($used >= (int)$limit) {
        $conn->commit();
        return [
            'ok' => true,
            'allowed' => false,
            'limit' => (int)$limit,
            'used' => $used,
            'remaining' => 0
        ];
    }

    $newUsed = $used + 1;
    $update = $conn->prepare("UPDATE ocr_usage SET used_count = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
    if (!$update) {
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal menyimpan kuota OCR.'
        ];
    }

    $update->bind_param('is', $newUsed, $username);
    $ok = $update->execute();
    $update->close();

    if (!$ok) {
        $conn->rollback();
        return [
            'ok' => false,
            'allowed' => false,
            'message' => 'Gagal menyimpan kuota OCR.'
        ];
    }

    $conn->commit();

    return [
        'ok' => true,
        'allowed' => true,
        'limit' => (int)$limit,
        'used' => $newUsed,
        'remaining' => max(0, (int)$limit - $newUsed)
    ];
}
