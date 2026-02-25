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

$active = resolve_active_schedule_item($username);
if (!$active) {
    echo "⚠️ Jadwal aktif tidak ditemukan.";
    exit;
}

$rows = get_schedule_rows($username, $active['id']);
if (empty($rows)) {
    echo "⚠️ Jadwal kosong.";
    exit;
}

// Tentukan kolom yang ingin ditampilkan
// Kolom yang ingin ditampilkan
$kolomYangDitampilkan = [
    "Nama Matakuliah" => "nama_matakuliah",
    "Jam Mulai" => "jam_mulai",
    "Jam Selesai" => "jam_selesai",
    "Ruang" => "ruang"
];

// Filter berdasarkan hari ini
// Filter berdasarkan hari ini
$filtered = array_filter($rows, function ($row) use ($hariIndonesia) {
    return isset($row['hari']) && trim($row['hari']) === $hariIndonesia;
});

// Urutkan berdasarkan "Jam Mulai" secara menaik
usort($filtered, function ($a, $b) {
    $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
    $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
    return $timeA <=> $timeB;
});




// Tampilkan tabel hasil filter
echo "<h2></h2>";
if (empty($filtered)) {
    echo "<p class='teks-putih'>Selamat, tidak ada jadwal hari ini, anda bisa turu seharian.</p>";
} else {
    echo "<table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr>";
    foreach ($kolomYangDitampilkan as $namaKolom => $key) {
        echo "<th>" . htmlspecialchars($namaKolom) . "</th>";
    }
    echo "</tr>";

    foreach ($filtered as $row) {
        echo "<tr>";
        foreach ($kolomYangDitampilkan as $key) {
            echo "<td>" . htmlspecialchars($row[$key] ?? '-') . "</td>";
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
