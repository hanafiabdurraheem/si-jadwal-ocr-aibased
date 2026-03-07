<!DOCTYPE html>
<html>
  <head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <?php include PROJECT_ROOT . '/app/view/theme.php'; ?>
  <link rel="stylesheet" href="app/view/tugas/global.css" />
    <link rel="stylesheet" href="app/view/tugas/styleguide.css" />
    <link rel="stylesheet" href="app/view/tugas/style.css?v=2" />
  </head>
  <body data-chat-context="tugas">
    <div class="daftar-tugas">
      <div class="div">
        <div class="text-wrapper">Tambah Tugas</div>
        <img class="line" src="app/view/assets/img/line.png" />
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

        <a href="index.php?route=beranda" class="batal" style="text-decoration: none;">
          <div class="batal-2">Batal</div>
        </a>
      </div>
    </div>
    <?php include PROJECT_ROOT . '/app/view/chat/widget.php'; ?>
  </body>
</html>
