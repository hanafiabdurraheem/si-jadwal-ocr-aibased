<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['username'];
$files = glob("uploads/$username/*.json"); // atau *.txt sesuai tipe file
foreach ($files as $file) {
    $data = file_get_contents($file);
    // Proses file sesuai kebutuhan kamu
}


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

// Path ke file CSV
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'] ?? '';

$csvPath = __DIR__ . '/../uploads/' . $username . '/Kartu-Rencana-Studi_Aktif.csv';

if (!file_exists($csvPath)) {
    echo "⚠️ File krs6.csv tidak ditemukan.";
    exit;
}

$data = array_map('str_getcsv', file($csvPath));
$header = array_shift($data);

// Tentukan kolom yang ingin ditampilkan
$kolomYangDitampilkan = ["Nama Matakuliah", "Jam Mulai", "Jam Selesai", "Ruang"];

// Temukan index kolom 'Hari'
$indexHari = array_search("Hari", $header);
if ($indexHari === false) {
    echo "❌ Kolom 'Hari' tidak ditemukan dalam CSV.";
    exit;
}

// Temukan index kolom-kolom yang ingin ditampilkan
$indexKolom = [];
foreach ($kolomYangDitampilkan as $kolom) {
    $idx = array_search($kolom, $header);
    if ($idx !== false) {
        $indexKolom[$kolom] = $idx;
    }
}

// Filter berdasarkan hari ini
$filtered = array_filter($data, function ($row) use ($indexHari, $hariIndonesia) {
    return isset($row[$indexHari]) && $row[$indexHari] === $hariIndonesia;
});
// Temukan index kolom "Jam Mulai"
$indexJamMulai = array_search("Jam Mulai", $header);

// Urutkan berdasarkan "Jam Mulai" secara menaik
if ($indexJamMulai !== false) {
    usort($filtered, function ($a, $b) use ($indexJamMulai) {
        $timeA = strtotime($a[$indexJamMulai]);
        $timeB = strtotime($b[$indexJamMulai]);
        return $timeA <=> $timeB;
    });
}




// Tampilkan tabel hasil filter
echo "<h2></h2>";
if (empty($filtered)) {
    echo "<p class='teks-putih'>Selamat, tidak ada jadwal hari ini, anda bisa turu seharian.</p>";
} else {
    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr>";
    foreach ($indexKolom as $namaKolom => $idx) {
        echo "<th>" . htmlspecialchars($namaKolom) . "</th>";
    }
    echo "</tr>";

    foreach ($filtered as $row) {
        echo "<tr>";
        foreach ($indexKolom as $idx) {
            echo "<td>" . htmlspecialchars($row[$idx] ?? '-') . "</td>";
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