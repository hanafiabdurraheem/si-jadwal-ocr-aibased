<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/schedule_store.php';

$username = $_SESSION['username'] ?? '';


date_default_timezone_set('Asia/Jakarta'); // Pastikan zona waktu benar
$hariIni = date('l'); // Mengambil nama hari dalam bahasa Inggris

// Konversi ke format bahasa Indonesia sesuai dengan file CSV
$mapHari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];

$hariIndonesia = $mapHari[$hariIni] ?? '';

// Path ke file aktif (JSON diutamakan)
$jsonPath = resolve_active_schedule_json($username);
$csvPath = resolve_active_schedule_csv($username);

$rows = [];

if ($jsonPath && file_exists($jsonPath)) {
    $rows = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($rows)) {
        $rows = [];
    }
}

if (empty($rows) && $csvPath && file_exists($csvPath)) {
    $data = array_map('str_getcsv', file($csvPath));
    $header = array_shift($data);
    foreach ($data as $row) {
        $combined = array_combine($header, $row);
        if ($combined !== false) {
            $rows[] = $combined;
        }
    }
}

if (empty($rows)) {
    echo "⚠️ File jadwal tidak ditemukan.";
    exit;
}

// Tentukan kolom yang ingin ditampilkan
// Kolom yang ingin ditampilkan
$kolomYangDitampilkan = ["Nama Matakuliah", "Jam Mulai", "Jam Selesai", "Ruang"];

// Filter berdasarkan hari ini
// Filter berdasarkan hari ini
$filtered = array_filter($rows, function ($row) use ($hariIndonesia) {
    return isset($row['Hari']) && trim($row['Hari']) === $hariIndonesia;
});

// Urutkan berdasarkan "Jam Mulai" secara menaik
usort($filtered, function ($a, $b) {
    $timeA = strtotime(str_replace('.', ':', $a['Jam Mulai'] ?? ''));
    $timeB = strtotime(str_replace('.', ':', $b['Jam Mulai'] ?? ''));
    return $timeA <=> $timeB;
});




// Tampilkan tabel hasil filter
echo "<h2></h2>";
if (empty($filtered)) {
    echo "<p class='teks-putih'>Selamat, tidak ada jadwal hari ini, anda bisa turu seharian.</p>";
} else {
    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr>";
    foreach ($kolomYangDitampilkan as $namaKolom) {
        echo "<th>" . htmlspecialchars($namaKolom) . "</th>";
    }
    echo "</tr>";

    foreach ($filtered as $row) {
        echo "<tr>";
        foreach ($kolomYangDitampilkan as $kolom) {
            echo "<td>" . htmlspecialchars($row[$kolom] ?? '-') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}


?>

<style> .teks-putih {
  color: white;
  padding-left: 12px;
}
</style>
