<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];

// Path folder upload
$uploadDir = __DIR__ . "/../uploads/$username";

// Pastikan folder ada
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Ambil semua file kecuali . dan ..
$files = array_values(array_diff(scandir($uploadDir), array('.', '..')));

// Ambil hanya file pertama
$file = $files[0] ?? null;

// Amankan nama file
if ($file) {
    $file = basename($file);
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <link rel="stylesheet" href="global.css" />
  <link rel="stylesheet" href="styleguide.css" />
  <link rel="stylesheet" href="style.css?v=<?= time() ?>" />
</head>

<body>
  <div class="tambah-kurang-jadwal">
    <div class="div">

      <div class="text-wrapper">Tambah/Kurang</div>

      <!-- Upload Form -->
      <div class="frame">
        <form id="uploadForm" action="../backend/upload.php" method="POST" enctype="multipart/form-data">
          <input type="file" name="fileToUpload" id="fileToUpload" 
          accept=".jpg,.jpeg,.png" required style="display:none;" />

          <label for="fileToUpload" class="button" style="cursor: pointer; display: inline-block;">
            Upload KRS
          </label>
        </form>
      </div>

      <script>
        document.getElementById("fileToUpload").addEventListener("change", function () {
          document.getElementById("uploadForm").submit();
        });
      </script>

      <div class="overlap-group">

        <?php if ($file): ?>
            <table class="file-table">

                <!-- BARIS 1 : CSV -->
                <tr>
                    <td>Jadwal</td>
                    <td>
                        <a class="file-link"
                           href="/si-jadwal/jadwal/jadwalview/index.php"
                            target="_blank">
                            Lihat/Edit
                        </a>
                    </td>
                </tr>

                <!-- BARIS 2 : TUGAS -->
                <tr>
                    <td>Tugas</td>
                    <td>
                        <a class="file-link"
                           href="/si-jadwal/tugas/tugas-list/index.php?file=<?php echo urlencode($file); ?>">
                            Lihat
                        </a>
                    </td>
                </tr>

            </table>
        <?php else: ?>
            <p>Tidak ada file yang ditemukan.</p>
        <?php endif; ?>

      </div>

      <div class="text-wrapper-4">Jadwal anda</div>
      <img class="line" src="/../img/line.png" />

      <?php include '../nav.php'; ?>

    </div>
  </div>
</body>
</html>
