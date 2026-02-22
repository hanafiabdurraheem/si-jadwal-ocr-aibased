<?php
// Atur lokal dan zona waktu
setlocale(LC_TIME, 'id_ID');
date_default_timezone_set('Asia/Jakarta');

// Ambil hari ini dalam format yang sesuai dengan CSV, contoh: "Rabu"
$hariIni = strftime('%A');

// Buka file CSV
$csvFile = fopen("upload/krs6.csv", "r");
$headers = fgetcsv($csvFile); // Lewati baris header

$matakuliahHariIni = "Tidak ada jadwal hari ini";

// Loop cari matakuliah sesuai hari ini
while (($row = fgetcsv($csvFile)) !== FALSE) {
    // Kolom ke-8 = Hari (indeks 8)
    if (isset($row[8]) && trim($row[8]) === $hariIni) {
        $matakuliahHariIni = $row[2]; // Kolom ke-3 = Nama Matakuliah
        break; // Ambil yang pertama ditemukan
    }
}
fclose($csvFile);
?>
