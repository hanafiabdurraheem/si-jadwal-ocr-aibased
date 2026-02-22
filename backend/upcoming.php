<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'] ?? '';

$files = glob("uploads/$username/*.json"); // atau *.txt sesuai tipe file
foreach ($files as $file) {
    $data = file_get_contents($file);
    // Proses file sesuai kebutuhan kamu
}


date_default_timezone_set('Asia/Jakarta');
$hariIni = date('l');

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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'] ?? '';
$csvPath = __DIR__ . '/../uploads/' . $username . '/Kartu-Rencana-Studi_Aktif.csv';


if (!file_exists($csvPath)) {
    echo '
    <form id="uploadForm" action="../backend/upload.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="fileToUpload" id="fileToUpload" required style="display:none;" />
        
        <a href="#" class="tambah-button" style="color:white;" 
           onclick="document.getElementById(\'fileToUpload\').click(); return false;">
           Tekan untuk upload KRS!
        </a>
    </form>

    <script>
        document.getElementById("fileToUpload").addEventListener("change", function () {
            document.getElementById("uploadForm").submit();
        });
    </script>
    ';
    exit;
}



$data = array_map('str_getcsv', file($csvPath));
$header = array_shift($data);

// Kolom yang ingin ditampilkan
$kolomYangDitampilkan = ["Nama Matakuliah"];

// Temukan index kolom
$indexHari = array_search("Hari", $header);
$indexJam = array_search("Jam Mulai", $header);

if ($indexHari === false || $indexJam === false) {
    echo "❌ Kolom 'Hari' atau 'Jam Mulai' tidak ditemukan.";
    exit;
}

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

// Urutkan berdasarkan "Jam Mulai"
usort($filtered, function ($a, $b) use ($indexJam) {
    $timeA = strtotime($a[$indexJam]);
    $timeB = strtotime($b[$indexJam]);
    return $timeA <=> $timeB;
});

$now = strtotime(date('H:i'));

$jadwalTerdekat = null;
foreach ($filtered as $row) {
    $jadwalTime = strtotime($row[$indexJam] ?? '');
    if ($jadwalTime !== false && $jadwalTime >= $now) {
        $jadwalTerdekat = $row;
        break; // Dapatkan hanya yang pertama yang belum lewat
    }
}


echo "<h2></h2>";
if (!$jadwalTerdekat) {
    echo "<p style='color:white;'>Tidak ada dalam waktu dekat</p>";
} else {
    echo "<div class='jadwal-container'>";
foreach ($indexKolom as $idx) {
    echo "<div class='jadwal-value-only'>" . htmlspecialchars($jadwalTerdekat[$idx] ?? '-') . "</div>";
}
echo "</div>";
}
?>


