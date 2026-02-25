<?php
session_start();
set_time_limit(0);
require_once __DIR__ . '/schedule_store.php';
require_once __DIR__ . '/db.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']) && $_GET['ajax'] === '1'));

function upload_error($message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(["ok" => false, "message" => $message]);
        exit();
    }
    die($message);
}

if (!isset($_SESSION['username'])) {
    upload_error("Anda harus login.", $isAjax);
}

$username = $_SESSION['username'];

$allowedExt = ['jpg', 'jpeg', 'png'];
$maxSize = 10 * 1024 * 1024; // 10MB

function parse_size_to_bytes($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return 0;
    }
    $last = strtolower($value[strlen($value) - 1]);
    $number = (int)$value;
    switch ($last) {
        case 'g':
            return $number * 1024 * 1024 * 1024;
        case 'm':
            return $number * 1024 * 1024;
        case 'k':
            return $number * 1024;
        default:
            return (int)$value;
    }
}

$iniUploadMax = parse_size_to_bytes(ini_get('upload_max_filesize'));
$iniPostMax = parse_size_to_bytes(ini_get('post_max_size'));
$iniLimit = 0;
if ($iniUploadMax > 0 && $iniPostMax > 0) {
    $iniLimit = min($iniUploadMax, $iniPostMax);
} elseif ($iniUploadMax > 0) {
    $iniLimit = $iniUploadMax;
} elseif ($iniPostMax > 0) {
    $iniLimit = $iniPostMax;
}

if ($iniLimit > 0 && $maxSize > $iniLimit) {
    $maxSize = $iniLimit;
}

if (!isset($_FILES["fileToUpload"])) {
    upload_error("File tidak ditemukan.", $isAjax);
}

function normalize_uploaded_files($files) {
    $normalized = [];
    if (!isset($files['name'])) {
        return $normalized;
    }
    if (!is_array($files['name'])) {
        $normalized[] = $files;
        return $normalized;
    }
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0
        ];
    }
    return $normalized;
}

$files = normalize_uploaded_files($_FILES["fileToUpload"]);

if (count($files) === 0) {
    upload_error("File tidak ditemukan.", $isAjax);
}

// ==========================
// Buat struktur folder baru
// ==========================

$basePath = realpath(__DIR__ . '/../uploads');
$userPath = $basePath . '/' . $username;

if (!file_exists($userPath)) {
    mkdir($userPath, 0777, true);
}

require_once 'ocr_process.php';

$lastScheduleId = null;
$processedCount = 0;
$totalSelected = isset($_POST['total_files']) ? (int)$_POST['total_files'] : count($files);
$logFile = __DIR__ . '/debug_upload_log.txt';

$batchMode = count($files) > 1;
$batchId = null;
$batchUploadPath = null;
$mergedRows = [];
$mergedColumns = [];

if ($batchMode) {
    $uniqueId = uniqid(date("Ymd_His") . '_', true);
    $batchId = str_replace('.', '_', $uniqueId);
    $batchUploadPath = $userPath . '/' . $batchId;
    mkdir($batchUploadPath, 0777, true);
}

file_put_contents(
    $logFile,
    "UPLOAD START (" . date('Y-m-d H:i:s') . ") total_selected={$totalSelected}, received=" . count($files) . " batch=" . ($batchMode ? '1' : '0') . "\n",
    FILE_APPEND
);

foreach ($files as $index => $file) {
    $fileName = $file['name'] ?? '';
    $fileError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    $fileTmp = $file['tmp_name'] ?? '';
    $fileSize = $file['size'] ?? 0;

    if ($fileError !== UPLOAD_ERR_OK) {
        file_put_contents($logFile, "SKIP {$fileName} error={$fileError}\n", FILE_APPEND);
        continue;
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExt)) {
        file_put_contents($logFile, "SKIP {$fileName} invalid_ext\n", FILE_APPEND);
        continue;
    }

    if ($fileSize > $maxSize) {
        file_put_contents($logFile, "SKIP {$fileName} size_exceed\n", FILE_APPEND);
        continue;
    }

    if ($batchMode) {
        $uploadPath = $batchUploadPath;
    } else {
        $uniqueId = uniqid(date("Ymd_His") . '_', true);
        $safeId = str_replace('.', '_', $uniqueId);
        $uploadPath = $userPath . '/' . $safeId;
        mkdir($uploadPath, 0777, true);
    }

    $originalFileName = $batchMode ? ('original_' . ($index + 1) . '.' . $extension) : ('original.' . $extension);
    $originalFile = $uploadPath . '/' . $originalFileName;

    if (!move_uploaded_file($fileTmp, $originalFile)) {
        file_put_contents($logFile, "SKIP {$fileName} move_failed\n", FILE_APPEND);
        continue;
    }

    $resultData = processOCR($originalFile);
    if (empty($resultData)) {
        usleep(600000);
        $resultData = processOCR($originalFile);
    }
    if (empty($resultData)) {
        file_put_contents($logFile, "SKIP {$fileName} ocr_failed\n", FILE_APPEND);
        continue;
    }

    if ($batchMode) {
        foreach ($resultData as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $key) {
                if (!in_array($key, $mergedColumns, true)) {
                    $mergedColumns[] = $key;
                }
            }
            $mergedRows[] = $row;
        }
    } else {
        $displayName = pathinfo($fileName, PATHINFO_FILENAME);
        if ($displayName === '') {
            $displayName = 'Jadwal ' . basename($uploadPath);
        }
        $setId = basename($uploadPath);
        add_schedule_set($username, $setId, $displayName, $resultData, true);
        $lastScheduleId = $setId;
    }

    $processedCount++;
    file_put_contents($logFile, "OK {$fileName} -> " . ($batchMode ? $batchId : $lastScheduleId) . "\n", FILE_APPEND);
}

if ($batchMode && $processedCount > 0) {
    $normalizedRows = [];
    foreach ($mergedRows as $row) {
        $clean = [];
        foreach ($mergedColumns as $col) {
            $clean[$col] = isset($row[$col]) ? (string)$row[$col] : "";
        }
        $normalizedRows[] = $clean;
    }

    $firstName = pathinfo($files[0]['name'] ?? '', PATHINFO_FILENAME);
    $displayName = $firstName !== '' ? $firstName : ('Batch ' . date('Ymd_His'));
    if ($processedCount > 1) {
        $displayName .= ' + ' . ($processedCount - 1) . ' foto';
    }

    $setId = basename($batchUploadPath);
    add_schedule_set($username, $setId, $displayName, $normalizedRows, true);
    $lastScheduleId = $setId;
}

if (!$lastScheduleId) {
    upload_error("Tidak ada file yang berhasil diproses.", $isAjax);
}

$_SESSION['active_schedule_id'] = $lastScheduleId;

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        "ok" => true,
        "processed" => $processedCount,
        "redirect" => "../jadwal/confirm-edit-jadwal/index.php?schedule_id=" . urlencode($lastScheduleId)
    ]);
    exit();
}

header("Location: ../jadwal/confirm-edit-jadwal/index.php?schedule_id=" . urlencode($lastScheduleId));
exit();
?>
