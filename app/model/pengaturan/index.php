<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/database/db.php';

function pengaturan_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->close();
    return $exists;
}

function pengaturan_detect_user_preference_mode(mysqli $conn): string
{
    if (!pengaturan_table_exists($conn, 'user_preference')) {
        return 'missing';
    }

    $hasThemeColor = false;
    $themeResult = $conn->query("SHOW COLUMNS FROM user_preference LIKE 'theme_color'");
    if ($themeResult) {
        $hasThemeColor = $themeResult->num_rows > 0;
        $themeResult->close();
    }
    if ($hasThemeColor) {
        return 'theme_color';
    }

    $hasPreferenceKey = false;
    $keyResult = $conn->query("SHOW COLUMNS FROM user_preference LIKE 'preference_key'");
    if ($keyResult) {
        $hasPreferenceKey = $keyResult->num_rows > 0;
        $keyResult->close();
    }

    $hasPreferenceValue = false;
    $valueResult = $conn->query("SHOW COLUMNS FROM user_preference LIKE 'preference_value'");
    if ($valueResult) {
        $hasPreferenceValue = $valueResult->num_rows > 0;
        $valueResult->close();
    }

    if ($hasPreferenceKey && $hasPreferenceValue) {
        return 'key_value';
    }

    return 'unknown';
}

function pengaturan_ensure_theme_color_schema(): void
{
    $conn = db_connect();
    if (!$conn) {
        return;
    }

    try {
        if (!pengaturan_table_exists($conn, 'user_preference')) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS `user_preference` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `username` VARCHAR(191) NOT NULL,
                  `preference_key` VARCHAR(50) NOT NULL,
                  `preference_value` VARCHAR(255) NOT NULL,
                  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY `idx_user_key` (`username`, `preference_key`),
                  KEY `idx_username` (`username`)
                ) ENGINE=InnoDB
            ");
            return;
        }

        $result = $conn->query("SHOW COLUMNS FROM user_preference LIKE 'theme_color'");
        if ($result && ($row = $result->fetch_assoc())) {
            $type = strtolower((string)($row['Type'] ?? ''));
            if (strpos($type, 'enum') === 0) {
                $conn->query("ALTER TABLE user_preference MODIFY theme_color VARCHAR(20) NOT NULL");
            }
        }
        if ($result) {
            $result->close();
        }
    } catch (Throwable $e) {
        // Gunakan default apabila schema tidak bisa dimigrasi otomatis.
    } finally {
        $conn->close();
    }
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
    $legacyMap = [
        'ungu' => '#6552fe',
        'kuning' => '#f59e0b',
        'biru' => '#0ea5e9',
        'hijau' => '#10b981',
        'magenta' => '#f43f5e'
    ];

    $normalize = static function (string $value) use ($key, $legacyMap): string {
        if ($key === 'accent_color') {
            $lower = strtolower(trim($value));
            if ($lower !== '' && $lower[0] !== '#' && isset($legacyMap[$lower])) {
                return $legacyMap[$lower];
            }
        }
        return $value;
    };

    try {
        pengaturan_ensure_theme_color_schema();
        $conn = db_connect();
        if (!$conn) {
            return $default;
        }

        $mode = pengaturan_detect_user_preference_mode($conn);

        if ($mode === 'theme_color') {
            $stmt = $conn->prepare("SELECT theme_color FROM user_preference WHERE username = ? LIMIT 1");
            if (!$stmt) {
                $conn->close();
                return $default;
            }
            $stmt->bind_param("s", $username);
        } elseif ($mode === 'key_value') {
            $stmt = $conn->prepare("SELECT preference_value FROM user_preference WHERE username = ? AND preference_key = ? LIMIT 1");
            if (!$stmt) {
                $conn->close();
                return $default;
            }
            $stmt->bind_param("ss", $username, $key);
        } else {
            $conn->close();
            return $default;
        }

        if (!$stmt->execute()) {
            $stmt->close();
            $conn->close();
            return $default;
        }

        $stmt->bind_result($value);
        if ($stmt->fetch()) {
            $stmt->close();
            $conn->close();
            return $normalize((string)$value);
        }
        $stmt->close();
        $conn->close();
        return $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function pengaturan_set_user_preference(string $username, string $key, string $value): array
{
    try {
        pengaturan_ensure_theme_color_schema();
        $conn = db_connect();
        if (!$conn) {
            return ['success' => false, 'error' => 'Database connection failed'];
        }

        $mode = pengaturan_detect_user_preference_mode($conn);

        if ($mode === 'theme_color') {
            $update = $conn->prepare("UPDATE user_preference SET theme_color = ? WHERE username = ?");
            if (!$update) {
                $conn->close();
                return ['success' => false, 'error' => 'Prepare update failed: ' . $conn->error];
            }

            $update->bind_param("ss", $value, $username);
            if (!$update->execute()) {
                $error = $update->error;
                $update->close();
                $conn->close();
                return ['success' => false, 'error' => 'Execute update failed: ' . $error];
            }

            $updatedRows = $update->affected_rows;
            $update->close();

            if ($updatedRows === 0) {
                $insert = $conn->prepare("INSERT INTO user_preference (username, theme_color) VALUES (?, ?)");
                if (!$insert) {
                    $conn->close();
                    return ['success' => false, 'error' => 'Prepare insert failed: ' . $conn->error];
                }
                $insert->bind_param("ss", $username, $value);
                if (!$insert->execute()) {
                    $error = $insert->error;
                    $insert->close();
                    $conn->close();
                    return ['success' => false, 'error' => 'Execute insert failed: ' . $error];
                }
                $insert->close();
            }
        } elseif ($mode === 'key_value') {
            $stmt = $conn->prepare("
                INSERT INTO user_preference (username, preference_key, preference_value)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)
            ");
            if (!$stmt) {
                $conn->close();
                return ['success' => false, 'error' => 'Prepare failed: ' . $conn->error];
            }

            $stmt->bind_param("sss", $username, $key, $value);
            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                $conn->close();
                return ['success' => false, 'error' => 'Execute failed: ' . $error];
            }
            $stmt->close();
        } else {
            $conn->close();
            return ['success' => false, 'error' => 'Schema user_preference tidak dikenali.'];
        }

        $conn->close();
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }
}
