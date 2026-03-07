<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/database/db.php';

function pengaturan_ensure_theme_color_schema(): void
{
    $conn = db_connect();
    if (!$conn) return;

    $result = $conn->query("SHOW COLUMNS FROM user_preference LIKE 'theme_color'");
    if ($result && $row = $result->fetch_assoc()) {
        $type = strtolower($row['Type'] ?? '');
        if (strpos($type, 'enum') === 0) {
            $conn->query("ALTER TABLE user_preference MODIFY theme_color VARCHAR(20) NOT NULL");
        }
    }
    $conn->close();
}

// Ensure user_preference table exists (commented out since table already exists with different structure)
// function ensure_user_preference_table() {
//     $conn = db_connect();
//     if (!$conn) return;

//     $createTable = "
//         CREATE TABLE IF NOT EXISTS `user_preference` (
//           `id` INT AUTO_INCREMENT PRIMARY KEY,
//           `username` VARCHAR(191) NOT NULL,
//           `preference_key` VARCHAR(50) NOT NULL,
//           `preference_value` VARCHAR(255) NOT NULL,
//           `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//           `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//           UNIQUE KEY `idx_user_key` (`username`, `preference_key`),
//           KEY `idx_username` (`username`)
//         ) ENGINE=InnoDB;
//     ";

//     $conn->query($createTable);
//     $conn->close();
// }

// ensure_user_preference_table();

function pengaturan_load_schedule_items(string $username): array
{
    $scheduleIndex = load_schedule_index($username);
    $scheduleItems = $scheduleIndex['items'] ?? [];

    usort($scheduleItems, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return $scheduleItems;
}

function pengaturan_user_dir(string $username): string
{
    return PROJECT_ROOT . '/app/uploads/' . $username;
}

function pengaturan_get_user_preference(string $username, string $key, string $default = ''): string
{
    try {
        pengaturan_ensure_theme_color_schema();
        $conn = db_connect();
        if (!$conn) return $default;

        $stmt = $conn->prepare("SELECT theme_color FROM user_preference WHERE username = ?");
        if (!$stmt) return $default;

        $stmt->bind_param("s", $username);
        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            return $default;
        }

        $stmt->bind_result($value);
        if ($stmt->fetch()) {
            $stmt->close();
            $conn->close();
            if ($key === 'accent_color') {
                $legacyMap = [
                    'ungu' => '#6552fe',
                    'kuning' => '#f59e0b',
                    'biru' => '#0ea5e9',
                    'hijau' => '#10b981',
                    'magenta' => '#f43f5e'
                ];
                $lower = strtolower(trim((string)$value));
                if ($lower !== '' && $lower[0] !== '#' && isset($legacyMap[$lower])) {
                    return $legacyMap[$lower];
                }
            }
            return $value;
        }
        $stmt->close();
        $conn->close();
        return $default;
    } catch (Exception $e) {
        return $default;
    }
}

function pengaturan_set_user_preference(string $username, string $key, string $value): array
{
    try {
        pengaturan_ensure_theme_color_schema();
        $conn = db_connect();
        if (!$conn) return ['success' => false, 'error' => 'Database connection failed'];

        $stmt = $conn->prepare("INSERT INTO user_preference (username, theme_color) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme_color = VALUES(theme_color)");
        if (!$stmt) return ['success' => false, 'error' => 'Prepare failed: ' . $conn->error];

        $stmt->bind_param("ss", $username, $value);
        $success = $stmt->execute();
        if (!$success) {
            $error = $stmt->error;
            $stmt->close();
            $conn->close();
            return ['success' => false, 'error' => 'Execute failed: ' . $error];
        }

        $stmt->close();
        $conn->close();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}
