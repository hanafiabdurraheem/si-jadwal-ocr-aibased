<?php
session_start();

/* =========================
   KONFIGURASI USER & FILE
========================= */

$username = $_SESSION['username'] ?? 'hana';
$filePath = "../../uploads/$username/Kartu-Rencana-Studi_Aktif.csv";

if (!file_exists($filePath)) {
    die("File tidak ditemukan: " . $filePath);
}

/* =========================
   URUTAN HARI
========================= */

$daysOrder = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
$jadwal = [];
$header = [];

/* =========================
   BACA FILE CSV
========================= */

if (($handle = fopen($filePath, "r")) !== FALSE) {

    // Ambil header
    $header = fgetcsv($handle, 1000, ",");

    // Bersihkan spasi dan BOM
    $header = array_map(function($h) {
        return trim($h);
    }, $header);

    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

    // Baca isi
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

        $row = array_combine($header, $data);

        $hari = trim($row['Hari'] ?? '');

        if (!empty($hari)) {
            $jadwal[$hari][] = $row; // simpan semua kolom
        }
    }

    fclose($handle);
}

/* =========================
   LOGIC NEXT & PREVIOUS
========================= */

$currentIndex = 0;

if (isset($_GET['day']) && in_array($_GET['day'], $daysOrder)) {
    $currentIndex = array_search($_GET['day'], $daysOrder);
}

$currentDay = $daysOrder[$currentIndex];

$prevIndex = ($currentIndex - 1 + count($daysOrder)) % count($daysOrder);
$nextIndex = ($currentIndex + 1) % count($daysOrder);

$prevDay = $daysOrder[$prevIndex];
$nextDay = $daysOrder[$nextIndex];

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kuliah</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Jadwal Hari <?php echo $currentDay; ?></h1>

<div class="nav-buttons">
    <a href="?day=<?php echo $prevDay; ?>" class="btn">← Previous</a>
    <a href="?day=<?php echo $nextDay; ?>" class="btn">Next →</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <?php foreach ($header as $col): ?>
                    <?php if ($col != 'Hari'): ?>
                        <th><?php echo htmlspecialchars($col); ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jadwal[$currentDay] as $row): ?>
                <tr>
                    <?php foreach ($header as $col): ?>
                        <?php if ($col != 'Hari'): ?>
                            <td><?php echo htmlspecialchars($row[$col] ?? ''); ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


</body>
</html>
