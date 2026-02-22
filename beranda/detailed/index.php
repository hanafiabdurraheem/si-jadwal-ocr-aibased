<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['username'])) {
    header("Location: ../../login/index.php");
    exit();
}

$username = $_SESSION['username'];
$csvPath = __DIR__ . '/../../uploads/' . $username . '/Kartu-Rencana-Studi_Aktif.csv';

if (!file_exists($csvPath)) {
    echo "⚠️ File tidak ditemukan.";
    exit;
}

$data = array_map('str_getcsv', file($csvPath));

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

$header = array_shift($data);

// Kolom yang ingin ditampilkan
$kolomYangDitampilkan = ["Nama Matakuliah", "Hari", "Jam Mulai", "Jam Selesai", "Ruang", "Pengampu"];

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

$indexSelesai = array_search("Jam Selesai", $header);
$jadwalTerdekat = null;
$jadwalSedangBerlangsung = null;

foreach ($filtered as $row) {
    $jamMulai = strtotime($row[$indexJam] ?? '');
    $jamSelesai = strtotime($row[$indexSelesai] ?? '');

    if ($jamMulai !== false && $jamMulai >= $now && !$jadwalTerdekat) {
        $jadwalTerdekat = $row;
    }

    if ($jamMulai !== false && $jamSelesai !== false && $now >= $jamMulai && $now <= $jamSelesai) {
        $jadwalSedangBerlangsung = $row;
    }
}

// Prioritaskan jadwal terdekat, jika tidak ada tampilkan yang sedang berlangsung
$jadwalYangDitampilkan = $jadwalTerdekat ?? $jadwalSedangBerlangsung;
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <link rel="stylesheet" href="global.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="styleguide.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="style.css?v=<?= time() ?>" />
</head>

<style>
.jadwal-container {
    background-image: url(../img/mesh-gradient-1.png);
    background-color: #6552fe;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-blend-mode: overlay; 

    color: white;
    padding: 10px;
    border-radius: 12px;
    max-width: 500px;
    margin: 0px auto;
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    gap: 10px;

    box-shadow: 0 0 12px rgba(0, 0, 0, 0.2);
}

.jadwal-value-only {
    background-color: transparent;
    padding: 10px;
    border-radius: 8px;
    text-align: left;
    padding-left: 65px;
    font-size: 16px;
    backdrop-filter: blur(4px);
}

.jadwal-foto {
    position: absolute;
    top: 100px;
    left: 40px;
    width: 30px;
    height: 30px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
    z-index: 2;
}

.jadwal-value-only {
    background-color: transparent;
    padding: 10px;
    border-radius: 8px;
    text-align: left;
    padding-left: 20px;
    font-size: 16px;
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    gap: 12px;
}

.jadwal-icon {
    width: 24px;
    height: 24px;
    object-fit: contain;
}
</style>

<body style="margin: 0; padding: 0;">
  <a href="../beranda/index.php" style="display: block; width: 100%; height: 250px; text-decoration: none; color: inherit;">
    <div class="detailed-upcoming">
      <div class="overlap-wrapper">
        
        <?php
        echo "<h2></h2>";
        if (!$jadwalYangDitampilkan) {
            echo "
            <div class='jadwal-container no-jadwal'>
              <p class='no-jadwal-text'>🎉 Horeee, tidak ada jadwal lagi hari ini!</p>
            </div>";
        } else {
            echo "<div class='jadwal-container'>";

            $iconMap = [
                "Nama Matakuliah" => "daring2.png",
                "Hari" => "hari2.png",
                "Jam Mulai" => "jam2.png",
                "Jam Selesai" => "kosong2.png",
                "Ruang" => "kelas3.png",
                "Pengampu" => "dosen3.png"
            ];

            foreach ($indexKolom as $label => $idx) {
                $iconPath = 'detailed/img/' . ($iconMap[$label] ?? 'default.png');
                $value = htmlspecialchars($jadwalYangDitampilkan[$idx] ?? '-');

                echo "<div class='jadwal-value-only'>
                        <img src='$iconPath' class='jadwal-icon' alt='$label icon' />
                        $value
                      </div>";
            }

            echo "</div>";
        }
        ?>
      </div>
    </div>
  </a>
</body>
</html>

