<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/session.php';
app_start_session();

// Check if user is logged in
if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/../backend/schedule_store.php';
require_once __DIR__ . '/../backend/task_store.php';
require_once __DIR__ . '/../backend/class_store.php';

$classes = class_list_for_user($username);
$adminClasses = [];
foreach ($classes as $classItem) {
    if (($classItem['role'] ?? '') === 'admin') {
        $adminClasses[] = $classItem;
    }
}

$scheduleActive = resolve_active_schedule_item($username);
$rowsMatkul = $scheduleActive ? get_schedule_rows($username, $scheduleActive['id']) : [];
$mataKuliahList = [];
foreach ($rowsMatkul as $row) {
    if (!empty($row['nama_matakuliah'])) {
        $mataKuliahList[] = trim($row['nama_matakuliah']);
    }
}
$mataKuliahList = array_values(array_unique($mataKuliahList));
sort($mataKuliahList);

// Menyimpan data ke DB jika form disubmit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $taskScope = $_POST['task_scope'] ?? 'pribadi';
    $mataKuliah = $_POST['mata_kuliah'] ?? '';
    $jenisKegiatan = $_POST['jenis_kegiatan'] ?? '';
    $deadline = $_POST['status_tugas'] ?? '';
    $kelasId = (int)($_POST['kelas_id'] ?? 0);

    if ($mataKuliah && $jenisKegiatan && $deadline) {
        if ($taskScope === 'kelas') {
            if ($kelasId <= 0 || !class_is_admin($username, $kelasId)) {
                echo "⚠️ Anda bukan admin kelas yang dipilih.";
                exit;
            }
            $result = class_add_task($kelasId, $username, $mataKuliah, $jenisKegiatan, $deadline, null);
            if (!empty($result['ok'])) {
                header("Location: ../kelas/index.php?notice=tugas_shared");
                exit;
            }
            $message = $result['message'] ?? 'Gagal menambahkan tugas kelas.';
            echo "⚠️ " . htmlspecialchars($message);
            exit;
        }

        task_add($username, $mataKuliah, $jenisKegiatan, $deadline, null);
        header("Location: ../kelas/index.php?notice=task_added");
        exit;
    }

    echo "⚠️ Semua field harus diisi.";
}
?>



<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <link rel="manifest" href="/si-jadwal/manifest.webmanifest" />
    <meta name="theme-color" content="#121212" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="styleguide.css" />
    <link rel="stylesheet" href="/si-jadwal/backend/theme.php?v=<?= time() ?>" />
    <link rel="stylesheet" href="style.css?v=2" />
  </head>
  <body data-chat-context="tugas">
    <div class="daftar-tugas">
      <div class="div">
        <div class="text-wrapper">Tambah Tugas</div>
        <img class="line" src="../img/line.png" />
        <?php if (!empty($adminClasses)): ?>
          <div class="text-wrapper-scope">Jenis Tugas</div>
          <div class="overlap-scope">
            <select class="dropdown" name="task_scope" id="taskScopeSelect" required>
              <option value="pribadi">Tugas Pribadi</option>
              <option value="kelas">Tugas Kelas</option>
            </select>
          </div>

          <div class="text-wrapper-class" id="kelasLabel" style="display:none;">Kelas</div>
          <div class="overlap-class" id="kelasSelectWrap" style="display:none;">
            <select class="dropdown" name="kelas_id">
              <option value="">Pilih Kelas</option>
              <?php foreach ($adminClasses as $classItem): ?>
                <option value="<?= (int)($classItem['id'] ?? 0) ?>">
                  <?= htmlspecialchars($classItem['nama'] ?? 'Kelas') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="task_scope" value="pribadi">
        <?php endif; ?>

        <div class="text-wrapper-2">Mata Kuliah</div>
        <div class="text-wrapper-3">Jenis Tugas</div>
        <div class="text-wrapper-4">Deadline</div>

                  <!-- Form Mulai di Sini -->
                  <form method="POST" action="">
            <div class="overlap">
              <label for="mataKuliahDropdown" class="text-wrapper-5"></label>
              <select id="mataKuliahDropdown" class="dropdown" name="mata_kuliah" required>
                <option value="">-- Pilih Mata Kuliah --</option>
                <?php if (!empty($mataKuliahList)): ?>
                    <?php foreach ($mataKuliahList as $matkul): ?>
                        <option value="<?= htmlspecialchars($matkul) ?>"><?= htmlspecialchars($matkul) ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option disabled>Jadwal aktif tidak ditemukan</option>
                <?php endif; ?>
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
    <?php include __DIR__ . '/../chat/widget.php'; ?>
    <script>
      const scopeSelect = document.getElementById('taskScopeSelect');
      const kelasWrap = document.getElementById('kelasSelectWrap');
      const kelasLabel = document.getElementById('kelasLabel');
      if (scopeSelect && kelasWrap) {
        const syncScope = () => {
          const isKelas = scopeSelect.value === 'kelas';
          kelasWrap.style.display = isKelas ? 'block' : 'none';
          if (kelasLabel) {
            kelasLabel.style.display = isKelas ? 'block' : 'none';
          }
          const selectEl = kelasWrap.querySelector('select');
          if (selectEl) {
            selectEl.required = isKelas;
          }
        };
        scopeSelect.addEventListener('change', syncScope);
        syncScope();
      }
    </script>
  </body>
</html>
