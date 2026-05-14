<?php

require_once __DIR__ . '/../config/config.php';

const OCR_PDF_MAX_BYTES = 8 * 1024 * 1024; // 8MB, ideal untuk PDF terkompres
const OCR_DEFAULT_MAX_OUTPUT_TOKENS = 2200;
const OCR_RETRY_MAX_OUTPUT_TOKENS = 6000;

function ocr_prompt_text()
{
    return "Anda adalah sistem OCR untuk membaca tabel jadwal kuliah dari gambar atau PDF.

Ekstrak semua baris tabel jadwal yang terlihat pada dokumen.

Kembalikan HANYA JSON ARRAY murni tanpa penjelasan, tanpa markdown, tanpa teks tambahan apapun.

Gunakan format berikut untuk setiap baris:

[
 {
  \"No\": \"\",
  \"Kode\": \"\",
  \"Nama Matakuliah\": \"\",
  \"SKS\": \"\",
  \"Kelas/Rombel\": \"\",
  \"Pengampu\": \"\",
  \"Jenis\": \"\",
  \"Ruang\": \"\",
  \"Hari\": \"\",
  \"Jam Mulai\": \"\",
  \"Jam Selesai\": \"\"
 }
]

Aturan penting:
- Semua 11 kolom WAJIB ada di setiap baris.
- Jika suatu kolom tidak memiliki nilai pada tabel, isi dengan string kosong \"\".
- Jangan menghilangkan kolom.
- Jangan menambahkan kolom baru.
- Semua nilai harus berupa string.
- Untuk kolom Ruang, ambil nama ruang inti saja (tanpa detail panjang seperti size/kode dalam kurung siku).
- Output harus berupa JSON array yang valid.
- Output harus MINIFIED (satu baris, tanpa enter/newline tambahan).";
}

function ocr_decode_rows_from_result($result, $logFile)
{
    $textOutput = isset($result['output_text']) ? (string)$result['output_text'] : '';

    if ($textOutput === '' && isset($result['output'])) {
        foreach ($result['output'] as $item) {
            if (isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (isset($content['text'])) {
                        $textOutput .= (string)$content['text'];
                    }
                }
            }
        }
    }

    if ($textOutput === '') {
        file_put_contents($logFile, "Tidak ada textOutput\n", FILE_APPEND);
        return [];
    }

    $textOutput = trim($textOutput);
    $textOutput = preg_replace('/```json/i', '', $textOutput);
    $textOutput = preg_replace('/```/', '', $textOutput);

    $jsonData = json_decode($textOutput, true);
    if (!$jsonData) {
        $start = strpos($textOutput, '[');
        $end = strrpos($textOutput, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($textOutput, $start, ($end - $start + 1));
            $jsonData = json_decode($slice, true);
        }
    }

    if (!$jsonData) {
        file_put_contents($logFile, "Decode JSON final gagal\n", FILE_APPEND);
        return [];
    }

    if (isset($jsonData['rows']) && is_array($jsonData['rows'])) {
        $jsonData = $jsonData['rows'];
    }

    if (!is_array($jsonData)) {
        file_put_contents($logFile, "Format output bukan array\n", FILE_APPEND);
        return [];
    }

    return $jsonData;
}

function ocr_call_responses_api($contentItems, $logFile, $rawFile, &$errorMessage, $maxOutputTokens = OCR_DEFAULT_MAX_OUTPUT_TOKENS)
{
    if (!function_exists('curl_init')) {
        $errorMessage = "Fitur OCR tidak aktif: ekstensi cURL tidak tersedia di server.";
        file_put_contents($logFile, "CURL extension missing\n", FILE_APPEND);
        return [];
    }

    if (!defined('OPENAI_API_KEY') || trim((string)OPENAI_API_KEY) === '') {
        $errorMessage = "Fitur OCR belum dikonfigurasi di server.";
        file_put_contents($logFile, "OPENAI_API_KEY missing\n", FILE_APPEND);
        return [];
    }

    $payload = [
        "model" => "gpt-4.1-mini",
        "max_output_tokens" => (int)$maxOutputTokens,
        "input" => [
            [
                "role" => "user",
                "content" => array_merge(
                    [
                        [
                            "type" => "input_text",
                            "text" => ocr_prompt_text()
                        ]
                    ],
                    $contentItems
                )
            ]
        ]
    ];

    file_put_contents($logFile, "Payload siap\n", FILE_APPEND);

    $ch = curl_init("https://api.openai.com/v1/responses");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    $payload["temperature"] = 0;
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_ENCODING, '');

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        $errorMessage = "Gagal terhubung ke layanan OCR.";
        file_put_contents($logFile, "CURL ERROR: $error\n", FILE_APPEND);
        curl_close($ch);
        return [];
    }

    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    file_put_contents($logFile, "CURL sukses status={$statusCode}\n", FILE_APPEND);

    if (file_put_contents($rawFile, $response) === false) {
        file_put_contents($logFile, "Gagal menulis debug_raw_response.json\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "debug_raw_response.json dibuat\n", FILE_APPEND);
    }

    $result = json_decode($response, true);
    if (!$result) {
        $errorMessage = "Respons OCR tidak valid.";
        file_put_contents($logFile, "JSON decode gagal\n", FILE_APPEND);
        return [];
    }

    if ($statusCode >= 300) {
        $apiError = '';
        if (isset($result['error']['message'])) {
            $apiError = (string)$result['error']['message'];
        }
        file_put_contents($logFile, "API ERROR {$statusCode}: {$apiError}\n", FILE_APPEND);
        if ($apiError !== '') {
            $errorMessage = "OCR gagal: {$apiError}";
        } else {
            $errorMessage = "OCR gagal diproses.";
        }
        return [];
    }

    $incompleteReason = (string)($result['incomplete_details']['reason'] ?? '');
    if (($result['status'] ?? '') === 'incomplete' && $incompleteReason === 'max_output_tokens') {
        file_put_contents($logFile, "Output terpotong karena max_output_tokens={$maxOutputTokens}\n", FILE_APPEND);
        $errorMessage = "__MAX_OUTPUT_TOKENS__";
        return [];
    }

    $rows = ocr_decode_rows_from_result($result, $logFile);
    if (empty($rows)) {
        if ($errorMessage === '') {
            $errorMessage = "Data OCR tidak terbaca dari dokumen.";
        }
        return [];
    }

    file_put_contents($logFile, "OCR SUCCESS\n", FILE_APPEND);
    return $rows;
}

function ocr_prepare_image_payload($imagePath, $logFile)
{
    $binary = @file_get_contents($imagePath);
    if ($binary === false || $binary === '') {
        file_put_contents($logFile, "Gagal membaca file gambar\n", FILE_APPEND);
        return null;
    }

    $info = @getimagesize($imagePath);
    $mime = isset($info['mime']) ? $info['mime'] : 'image/jpeg';
    $fileSize = @filesize($imagePath);

    $canOptimize = function_exists('imagecreatefromstring')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled')
        && function_exists('imagejpeg');

    if (!$canOptimize || !$info || !isset($info[0], $info[1])) {
        return [
            'mime' => $mime,
            'data_url' => "data:" . $mime . ";base64," . base64_encode($binary),
            'optimized' => false
        ];
    }

    $width = (int)$info[0];
    $height = (int)$info[1];
    $longSide = max($width, $height);
    $maxLongSide = 1700;
    $compressThreshold = 1200 * 1024;

    $needsResize = $longSide > $maxLongSide;
    $needsCompress = ($fileSize !== false && $fileSize > $compressThreshold) || $mime !== 'image/jpeg';

    if (!$needsResize && !$needsCompress) {
        return [
            'mime' => $mime,
            'data_url' => "data:" . $mime . ";base64," . base64_encode($binary),
            'optimized' => false
        ];
    }

    $src = @imagecreatefromstring($binary);
    if (!$src) {
        file_put_contents($logFile, "Optimasi gambar gagal, fallback ke file asli\n", FILE_APPEND);
        return [
            'mime' => $mime,
            'data_url' => "data:" . $mime . ";base64," . base64_encode($binary),
            'optimized' => false
        ];
    }

    $targetWidth = $width;
    $targetHeight = $height;
    if ($needsResize && $longSide > 0) {
        $scale = $maxLongSide / $longSide;
        $targetWidth = max(1, (int)round($width * $scale));
        $targetHeight = max(1, (int)round($height * $scale));
    }

    $dst = imagecreatetruecolor($targetWidth, $targetHeight);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    ob_start();
    imagejpeg($dst, null, 74);
    $jpegBinary = ob_get_clean();

    imagedestroy($src);
    imagedestroy($dst);

    if ($jpegBinary === false || $jpegBinary === '') {
        file_put_contents($logFile, "Encode optimasi gagal, fallback ke file asli\n", FILE_APPEND);
        return [
            'mime' => $mime,
            'data_url' => "data:" . $mime . ";base64," . base64_encode($binary),
            'optimized' => false
        ];
    }

    return [
        'mime' => 'image/jpeg',
        'data_url' => "data:image/jpeg;base64," . base64_encode($jpegBinary),
        'optimized' => true
    ];
}

function ocr_try_compress_pdf($pdfPath, $logFile)
{
    if (!function_exists('shell_exec') || !function_exists('exec')) {
        return $pdfPath;
    }

    $gsPath = trim((string)shell_exec('command -v gs 2>/dev/null'));
    if ($gsPath === '') {
        return $pdfPath;
    }

    $tmpBase = tempnam(sys_get_temp_dir(), 'ocr_pdf_');
    if ($tmpBase === false) {
        return $pdfPath;
    }
    $compressedPath = $tmpBase . '.pdf';
    @unlink($tmpBase);

    $command = escapeshellarg($gsPath)
        . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook'
        . ' -dNOPAUSE -dQUIET -dBATCH'
        . ' -sOutputFile=' . escapeshellarg($compressedPath)
        . ' ' . escapeshellarg($pdfPath) . ' 2>&1';

    exec($command, $output, $code);
    if ($code !== 0 || !file_exists($compressedPath)) {
        @unlink($compressedPath);
        file_put_contents($logFile, "Kompresi PDF via gs gagal\n", FILE_APPEND);
        return $pdfPath;
    }

    $originalSize = @filesize($pdfPath);
    $compressedSize = @filesize($compressedPath);
    if ($originalSize !== false && $compressedSize !== false && $compressedSize < $originalSize) {
        file_put_contents($logFile, "PDF dikompres {$originalSize} -> {$compressedSize} bytes\n", FILE_APPEND);
        return $compressedPath;
    }

    @unlink($compressedPath);
    return $pdfPath;
}

function processOCR($filePath, &$errorMessage = '')
{
    $debugDir = __DIR__ . '/../database';
    $logFile = $debugDir . "/debug_log.txt";
    $rawFile = $debugDir . "/debug_raw_response.json";
    $errorMessage = '';

    file_put_contents($logFile, "=== OCR START ===\n");

    if (!file_exists($filePath)) {
        $errorMessage = "Dokumen OCR tidak ditemukan.";
        file_put_contents($logFile, "Dokumen tidak ditemukan\n", FILE_APPEND);
        return [];
    }

    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $contentItems = [];

    if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
        $preparedImage = ocr_prepare_image_payload($filePath, $logFile);
        if (!$preparedImage || empty($preparedImage['data_url'])) {
            $errorMessage = "Gagal menyiapkan gambar untuk OCR.";
            file_put_contents($logFile, "Gagal menyiapkan payload gambar\n", FILE_APPEND);
            return [];
        }
        if (!empty($preparedImage['optimized'])) {
            file_put_contents($logFile, "Gambar dioptimasi sebelum OCR\n", FILE_APPEND);
        }
        $contentItems[] = [
            "type" => "input_image",
            "image_url" => $preparedImage['data_url']
        ];
    } elseif ($extension === 'pdf') {
        $pdfPathToUse = ocr_try_compress_pdf($filePath, $logFile);
        $cleanupPdf = ($pdfPathToUse !== $filePath);

        $pdfSize = @filesize($pdfPathToUse);
        if ($pdfSize === false || $pdfSize <= 0) {
            if ($cleanupPdf) {
                @unlink($pdfPathToUse);
            }
            $errorMessage = "PDF tidak valid untuk OCR.";
            file_put_contents($logFile, "PDF invalid size\n", FILE_APPEND);
            return [];
        }

        if ($pdfSize > OCR_PDF_MAX_BYTES) {
            if ($cleanupPdf) {
                @unlink($pdfPathToUse);
            }
            $maxMb = (int)round(OCR_PDF_MAX_BYTES / 1024 / 1024);
            $errorMessage = "PDF terlalu besar. Kompres PDF hingga <= {$maxMb}MB lalu coba lagi.";
            file_put_contents($logFile, "PDF melebihi batas: {$pdfSize}\n", FILE_APPEND);
            return [];
        }

        $pdfBinary = @file_get_contents($pdfPathToUse);
        if ($cleanupPdf) {
            @unlink($pdfPathToUse);
        }
        if ($pdfBinary === false || $pdfBinary === '') {
            $errorMessage = "Gagal membaca PDF untuk OCR.";
            file_put_contents($logFile, "Gagal membaca PDF\n", FILE_APPEND);
            return [];
        }

        file_put_contents($logFile, "PDF siap diproses OCR\n", FILE_APPEND);
        $contentItems[] = [
            "type" => "input_file",
            "filename" => basename($filePath),
            "file_data" => "data:application/pdf;base64," . base64_encode($pdfBinary)
        ];
    } else {
        $errorMessage = "Format file tidak didukung untuk OCR.";
        file_put_contents($logFile, "Ekstensi tidak didukung: {$extension}\n", FILE_APPEND);
        return [];
    }

    $rows = ocr_call_responses_api($contentItems, $logFile, $rawFile, $errorMessage, OCR_DEFAULT_MAX_OUTPUT_TOKENS);
    if (!empty($rows)) {
        return $rows;
    }

    if ($errorMessage === "__MAX_OUTPUT_TOKENS__") {
        file_put_contents($logFile, "Retry OCR dengan max_output_tokens lebih besar\n", FILE_APPEND);
        $errorMessage = '';
        $rows = ocr_call_responses_api($contentItems, $logFile, $rawFile, $errorMessage, OCR_RETRY_MAX_OUTPUT_TOKENS);
        if (!empty($rows)) {
            return $rows;
        }
        if ($errorMessage === "__MAX_OUTPUT_TOKENS__") {
            $errorMessage = "Data OCR terlalu panjang. Coba kompres PDF lebih kecil atau pecah per halaman.";
        }
    }

    return [];
}
