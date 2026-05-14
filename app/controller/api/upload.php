<?php
session_start();
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
require_once __DIR__ . '/../../config/app.php';
require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/database/db.php';
require_once PROJECT_ROOT . '/app/model/ocr_guard.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_GET['ajax']) && $_GET['ajax'] === '1'));

function upload_error($message, $isAjax, $statusCode = 400) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code((int)$statusCode);
        echo json_encode(["ok" => false, "message" => $message]);
        exit();
    }
    http_response_code((int)$statusCode);
    die($message);
}

if (!isset($_SESSION['username'])) {
    upload_error("Anda harus login.", $isAjax);
}

$username = $_SESSION['username'];

$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$maxSize = 10 * 1024 * 1024; // 10MB
const OCR_USER_LIMIT = 10;
const OCR_THROTTLE_MAX_REQUESTS = 3;
const OCR_THROTTLE_WINDOW_SECONDS = 20;

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

function count_potential_ocr_files($files, $allowedExt, $maxSize) {
    $count = 0;
    foreach ($files as $file) {
        $fileName = $file['name'] ?? '';
        $fileError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        $fileSize = (int)($file['size'] ?? 0);
        if ($fileError !== UPLOAD_ERR_OK) {
            continue;
        }
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExt, true)) {
            continue;
        }
        if ($fileSize > $maxSize) {
            continue;
        }
        $count++;
    }
    return $count;
}

$files = normalize_uploaded_files($_FILES["fileToUpload"]);

if (count($files) === 0) {
    upload_error("File tidak ditemukan.", $isAjax);
}

$conn = db_connect();

$throttleStatus = ocr_register_request(
    $conn,
    $username,
    OCR_THROTTLE_MAX_REQUESTS,
    OCR_THROTTLE_WINDOW_SECONDS
);
if (!$throttleStatus['ok']) {
    $conn->close();
    upload_error("Gagal memproses throttle OCR. Coba lagi.", $isAjax, 500);
}
if (empty($throttleStatus['allowed'])) {
    $retryAfter = (int)($throttleStatus['retry_after'] ?? OCR_THROTTLE_WINDOW_SECONDS);
    header('Retry-After: ' . $retryAfter);
    $conn->close();
    upload_error("Terlalu banyak request OCR. Coba lagi dalam {$retryAfter} detik.", $isAjax, 429);
}

$quotaStatus = ocr_get_quota_status($conn, $username, OCR_USER_LIMIT);
if (!$quotaStatus['ok']) {
    $conn->close();
    upload_error("Gagal membaca kuota OCR. Coba lagi.", $isAjax, 500);
}
if (($quotaStatus['remaining'] ?? 0) <= 0) {
    $conn->close();
    upload_error("Batas OCR Anda sudah habis (maksimal 10x).", $isAjax, 403);
}

$potentialFiles = count_potential_ocr_files($files, $allowedExt, $maxSize);
if ($potentialFiles > (int)$quotaStatus['remaining']) {
    $remaining = (int)$quotaStatus['remaining'];
    $conn->close();
    upload_error("Sisa kuota OCR Anda {$remaining}x. Pilih maksimal {$remaining} file untuk diproses.", $isAjax, 403);
}

// ==========================
// Buat struktur folder baru
// ==========================

$uploadsRoot = PROJECT_ROOT . '/app/uploads';
if (!is_dir($uploadsRoot) && !@mkdir($uploadsRoot, 0775, true)) {
    $conn->close();
    upload_error("Folder uploads tidak dapat dibuat di server hosting.", $isAjax, 500);
}

$basePath = realpath($uploadsRoot);
if ($basePath === false) {
    $basePath = $uploadsRoot;
}

if (!is_dir($basePath) || !is_writable($basePath)) {
    $conn->close();
    upload_error("Folder uploads tidak writable. Periksa permission hosting.", $isAjax, 500);
}

$userPath = $basePath . '/' . $username;

if (!is_dir($userPath) && !@mkdir($userPath, 0775, true)) {
    $conn->close();
    upload_error("Folder upload user tidak dapat dibuat.", $isAjax, 500);
}

if (!is_writable($userPath)) {
    $conn->close();
    upload_error("Folder upload user tidak writable.", $isAjax, 500);
}

require_once PROJECT_ROOT . '/app/model/ocr_process.php';

$lastScheduleId = null;
$processedCount = 0;
$quotaExceeded = false;
$lastProcessingError = '';
$totalSelected = isset($_POST['total_files']) ? (int)$_POST['total_files'] : count($files);
$logFile = PROJECT_ROOT . '/app/database/debug_upload_log.txt';

$batchMode = count($files) > 1;
$batchId = null;
$batchUploadPath = null;
$mergedRows = [];
$mergedColumns = [];

if ($batchMode) {
    $uniqueId = uniqid(date("Ymd_His") . '_', true);
    $batchId = str_replace('.', '_', $uniqueId);
    $batchUploadPath = $userPath . '/' . $batchId;
    if (!@mkdir($batchUploadPath, 0775, true)) {
        $conn->close();
        upload_error("Folder batch upload tidak dapat dibuat.", $isAjax, 500);
    }
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
        if (!@mkdir($uploadPath, 0775, true)) {
            $conn->close();
            upload_error("Folder upload tidak dapat dibuat.", $isAjax, 500);
        }
    }

    $originalFileName = $batchMode ? ('original_' . ($index + 1) . '.' . $extension) : ('original.' . $extension);
    $originalFile = $uploadPath . '/' . $originalFileName;

    if (!move_uploaded_file($fileTmp, $originalFile)) {
        file_put_contents($logFile, "SKIP {$fileName} move_failed\n", FILE_APPEND);
        continue;
    }

    $consume = ocr_consume_quota($conn, $username, OCR_USER_LIMIT);
    if (!$consume['ok']) {
        $conn->close();
        upload_error("Gagal memproses kuota OCR. Coba lagi.", $isAjax, 500);
    }
    if (empty($consume['allowed'])) {
        file_put_contents($logFile, "SKIP {$fileName} quota_exceeded\n", FILE_APPEND);
        $quotaExceeded = true;
        break;
    }

    $ocrError = '';
    $resultData = processOCR($originalFile, $ocrError);
    if (empty($resultData)) {
        if ($ocrError !== '') {
            $lastProcessingError = $ocrError;
            file_put_contents($logFile, "SKIP {$fileName} ocr_failed reason={$ocrError}\n", FILE_APPEND);
        }
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
    if ($quotaExceeded) {
        $conn->close();
        upload_error("Batas OCR Anda sudah habis (maksimal 10x).", $isAjax, 403);
    }
    if ($lastProcessingError !== '') {
        $conn->close();
        upload_error($lastProcessingError, $isAjax, 422);
    }
    $conn->close();
    upload_error("Tidak ada file yang berhasil diproses.", $isAjax);
}

$_SESSION['active_schedule_id'] = $lastScheduleId;

$latestQuota = ocr_get_quota_status($conn, $username, OCR_USER_LIMIT);
$remainingQuota = $latestQuota['ok'] ? (int)$latestQuota['remaining'] : null;
$conn->close();

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        "ok" => true,
        "processed" => $processedCount,
        "ocr_remaining" => $remainingQuota,
        "redirect" => "index.php?route=jadwal-confirm-edit&schedule_id=" . urlencode($lastScheduleId)
    ]);
    exit();
}

header("Location: index.php?route=jadwal-confirm-edit&schedule_id=" . urlencode($lastScheduleId));
exit();
?>
