<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];
$userFolder = __DIR__ . '/../uploads/' . $username;
$csvPath = $userFolder . '/Kartu-Rencana-Studi_Aktif.csv';
$tugasPath = $userFolder . '/tugas.csv';

// Menyimpan data ke CSV jika form disubmit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mataKuliah = $_POST['mata_kuliah'] ?? '';
    $jenisKegiatan = $_POST['jenis_kegiatan'] ?? '';
    $deadline = $_POST['status_tugas'] ?? '';

    if ($mataKuliah && $jenisKegiatan && $deadline) {
        $data = [$mataKuliah, $jenisKegiatan, $deadline];
        $file = fopen($tugasPath, 'a');

        if ($file !== false) {
            fputcsv($file, $data);
            fclose($file);

            header("Location: ../tugas/tugas-list/index.php");
            exit;
        } else {
            echo "❌ Gagal menulis ke file CSV.";
        }
    } else {
        echo "⚠️ Semua field harus diisi.";
    }
}
?>



<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="styleguide.css" />
    <link rel="stylesheet" href="style.css?v=2" />
  </head>
  <body>
    <div class="daftar-tugas">
      <div class="div">
        <div class="text-wrapper">Tambah Tugas</div>
        <img class="line" src="../img/line.png" />
        <div class="text-wrapper-2">Mata Kuliah</div>
        <div class="text-wrapper-3">Jenis Tugas</div>
        <div class="text-wrapper-4">Deadline</div>

                  <!-- Form Mulai di Sini -->
                  <form method="POST" action="">
            <div class="overlap">
              <label for="mataKuliahDropdown" class="text-wrapper-5"></label>
              <select id="mataKuliahDropdown" class="dropdown" name="mata_kuliah" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php
                $username = $_SESSION['username'];
                if (file_exists($csvPath)) 
                    {
                    $data = array_map('str_getcsv', file($csvPath));
                    $header = array_shift($data);
                    $indexMatkul = array_search("Nama Matakuliah", $header);

                    if ($indexMatkul !== false) {
                        $mataKuliahList = [];

                        // Ambil semua nilai unik dari kolom "Nama Matakuliah"
                        foreach ($data as $row) {
                            if (!empty($row[$indexMatkul])) {
                                $mataKuliahList[] = trim($row[$indexMatkul]);
                            }
                        }

                        // Hapus duplikat dan urutkan
                        $mataKuliahList = array_unique($mataKuliahList);
                        sort($mataKuliahList);

                        // Tampilkan sebagai <option>
                        foreach ($mataKuliahList as $matkul) {
                            echo "<option value=\"" . htmlspecialchars($matkul) . "\">" . htmlspecialchars($matkul) . "</option>";
                        }
                    } else {
                        echo "<option disabled>Kolom 'Nama Matakuliah' tidak ditemukan</option>";
                    }
                } else {
                    echo "<option disabled>File tidak ditemukan</option>";
                }
                ?>
              </select>
            </div>

          <div class="overlap-group">
            <select class="dropdown" name="jenis_kegiatan" required>
              <option value="">-- Pilih Jenis Tugas --</option>
              <option value="Laporan">Laporan</option>
              <option value="Quiz">Quiz</option>
              <option value="Praktik">Praktik</option>
            </select>
          </div>

          <div class="div-wrapper">
            <input type="date" class="dropdown" name="status_tugas" required>
          </div>

          <button type="submit" class="tambah" style="text-decoration: none;">
            <div class="tambah-2">Tambah</div>
          </button>
        </form>
        <!-- Form Berakhir di Sini -->

        <a href="../beranda/index.php" class="batal" style="text-decoration: none;">
          <div class="batal-2">Batal</div>
        </a>
      </div>
    </div>
  </body>
</html>
