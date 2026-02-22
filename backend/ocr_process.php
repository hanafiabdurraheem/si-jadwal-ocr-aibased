<?php

require_once 'config.php';

function processOCR($imagePath)
{
    $debugDir = __DIR__; // folder backend
    $logFile = $debugDir . "/debug_log.txt";
    $rawFile = $debugDir . "/debug_raw_response.json";

    file_put_contents($logFile, "=== OCR START ===\n");

    // ===============================
    // 1. Cek file gambar
    // ===============================
    if (!file_exists($imagePath)) {
        file_put_contents($logFile, "Gambar tidak ditemukan\n", FILE_APPEND);
        return [];
    }

    file_put_contents($logFile, "Gambar ditemukan\n", FILE_APPEND);

    $imageData = base64_encode(file_get_contents($imagePath));

    // ===============================
    // 2. Payload
    // ===============================
    $payload = [
    "model" => "gpt-4.1-mini",
    "input" => [
        [
            "role" => "user",
            "content" => [
                [
    "type" => "input_text",
    "text" => "Anda adalah sistem OCR untuk membaca tabel jadwal kuliah dari gambar.

Ekstrak semua baris tabel jadwal yang terlihat pada gambar.

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
- Output harus berupa JSON array yang valid."
],
                [
                    "type" => "input_image",
                    "image_url" => "data:image/jpeg;base64," . $imageData
                ]
            ]
        ]
    ]
];

    file_put_contents($logFile, "Payload siap\n", FILE_APPEND);

    // ===============================
    // 3. CURL
    // ===============================
    $ch = curl_init("https://api.openai.com/v1/responses");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        file_put_contents($logFile, "CURL ERROR: $error\n", FILE_APPEND);
        curl_close($ch);
        return [];
    }

    file_put_contents($logFile, "CURL sukses\n", FILE_APPEND);

    curl_close($ch);

    // ===============================
    // 4. Simpan response mentah
    // ===============================
    if (file_put_contents($rawFile, $response) === false) {
        file_put_contents($logFile, "Gagal menulis debug_raw_response.json\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "debug_raw_response.json dibuat\n", FILE_APPEND);
    }

    // ===============================
    // 5. Decode
    // ===============================
    $result = json_decode($response, true);

    if (!$result) {
        file_put_contents($logFile, "JSON decode gagal\n", FILE_APPEND);
        return [];
    }

    // ===============================
    // 6. Ambil semua text
    // ===============================
    $textOutput = '';

    if (isset($result['output'])) {
        foreach ($result['output'] as $item) {
            if (isset($item['content'])) {
                foreach ($item['content'] as $content) {
                    if (isset($content['text'])) {
                        $textOutput .= $content['text'];
                    }
                }
            }
        }
    }

    if (empty($textOutput)) {
        file_put_contents($logFile, "Tidak ada textOutput\n", FILE_APPEND);
        return [];
    }

    $textOutput = trim($textOutput);
    $textOutput = preg_replace('/```json/i', '', $textOutput);
    $textOutput = preg_replace('/```/', '', $textOutput);

    $jsonData = json_decode($textOutput, true);

    if (!$jsonData) {
        file_put_contents($logFile, "Decode JSON final gagal\n", FILE_APPEND);
        return [];
    }

    file_put_contents($logFile, "OCR SUCCESS\n", FILE_APPEND);

    return $jsonData;
}