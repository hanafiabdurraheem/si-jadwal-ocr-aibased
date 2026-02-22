
<?php
session_start();

if ($_FILES['fileToUpload']['error'] !== UPLOAD_ERR_OK) {
    die("Terjadi kesalahan upload.");
}


if (!isset($_SESSION['username'])) {
    die("Anda harus login.");
}

$username = $_SESSION['username'];

$allowedExt = ['jpg', 'jpeg', 'png'];
$maxSize = 2 * 1024 * 1024; // 2MB

if (!isset($_FILES["fileToUpload"])) {
    die("File tidak ditemukan.");
}

$extension = strtolower(pathinfo($_FILES["fileToUpload"]["name"], PATHINFO_EXTENSION));

if (!in_array($extension, $allowedExt)) {
    die("Hanya file JPG, JPEG, PNG yang diperbolehkan.");
}

if ($_FILES["fileToUpload"]["size"] > $maxSize) {
    die("Ukuran file maksimal 2MB.");
}

// ==========================
// Buat struktur folder baru
// ==========================

$basePath = realpath(__DIR__ . '/../uploads');
$userPath = $basePath . '/' . $username;

if (!file_exists($userPath)) {
    mkdir($userPath, 0777, true);
}

// folder berdasarkan timestamp
$timestamp = date("Ymd_His");
$uploadPath = $userPath . '/' . $timestamp;
mkdir($uploadPath, 0777, true);

// Simpan file asli
$originalFile = $uploadPath . '/original.' . $extension;

if (!move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $originalFile)) {
    die("Gagal upload file.");
}

// ==========================
// Panggil OCR
// ==========================

require_once 'ocr_process.php';

$resultData = processOCR($originalFile);

// ==========================
// Simpan JSON
// ==========================

$jsonPath = $uploadPath . '/result.json';
file_put_contents($jsonPath, json_encode($resultData, JSON_PRETTY_PRINT));

// ==========================
// Simpan CSV
// ==========================

$csvPath = $uploadPath . '/result.csv';

$fp = fopen($csvPath, 'w');

if (!empty($resultData)) {
    fputcsv($fp, array_keys($resultData[0]));
    foreach ($resultData as $row) {
        fputcsv($fp, $row);
    }
}

fclose($fp);

echo "OCR berhasil. File JSON dan CSV dibuat.";
?>