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
require_once __DIR__ . '/../../backend/schedule_store.php';
require_once __DIR__ . '/../../backend/db.php';

$active = resolve_active_schedule_item($username);
if (!$active) {
    echo "⚠️ Jadwal aktif tidak ditemukan.";
    exit;
}

$rowsDb = get_schedule_rows($username, $active['id']);
if (empty($rowsDb)) {
    echo "⚠️ Jadwal kosong.";
    exit;
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

$fields = [
    ['label' => 'Nama Matakuliah', 'key' => 'nama_matakuliah', 'icon' => 'daring2.png'],
    ['label' => 'Hari', 'key' => 'hari', 'icon' => 'hari2.png'],
    ['label' => 'Jam Mulai', 'key' => 'jam_mulai', 'icon' => 'jam2.png'],
    ['label' => 'Jam Selesai', 'key' => 'jam_selesai', 'icon' => 'kosong2.png'],
    ['label' => 'Ruang', 'key' => 'ruang', 'icon' => 'kelas3.png'],
    ['label' => 'Pengampu', 'key' => 'pengampu', 'icon' => 'dosen3.png'],
];

$filtered = array_filter($rowsDb, function ($row) use ($hariIndonesia) {
    return isset($row['hari']) && $row['hari'] === $hariIndonesia;
});

usort($filtered, function ($a, $b) {
    $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
    $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
    return $timeA <=> $timeB;
});

$now = strtotime(date('H:i'));
$jadwalTerdekat = null;
$jadwalSedangBerlangsung = null;

foreach ($filtered as $row) {
    $jamMulai = strtotime(str_replace('.', ':', $row['jam_mulai'] ?? ''));
    $jamSelesai = strtotime(str_replace('.', ':', $row['jam_selesai'] ?? ''));

    if ($jamMulai !== false && $jamMulai >= $now && !$jadwalTerdekat) {
        $jadwalTerdekat = $row;
    }

    if ($jamMulai !== false && $jamSelesai !== false && $now >= $jamMulai && $now <= $jamSelesai) {
        $jadwalSedangBerlangsung = $row;
    }
}

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

<body style="margin: 0; padding: 0; background: transparent;">
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
            foreach ($fields as $field) {
                $iconPath = 'detailed/img/' . $field['icon'];
                $value = htmlspecialchars($jadwalYangDitampilkan[$field['key']] ?? '-');
                $label = htmlspecialchars($field['label']);
                echo "<div class='jadwal-value-only'>
                        <img src='$iconPath' class='jadwal-icon' alt='{$label} icon' />
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
