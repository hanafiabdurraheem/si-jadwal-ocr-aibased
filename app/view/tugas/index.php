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
                  <form id="taskForm" method="POST" action="">
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
              <option value="Praktik">Praktik</option>
              <option value="Membuat PPT">Membuat PPT</option>
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
    <script>
      (function () {
        const form = document.getElementById('taskForm');
        if (!form) return;

        function toYmd(dateStr) {
          if (!dateStr) return '';
          const parts = String(dateStr).split('-');
          if (parts.length !== 3) return '';
          return parts[0] + parts[1] + parts[2];
        }

        function addOneDayYmd(dateStr) {
          const dt = new Date(dateStr + 'T00:00:00');
          if (Number.isNaN(dt.getTime())) return '';
          dt.setDate(dt.getDate() + 1);
          const y = dt.getFullYear();
          const m = String(dt.getMonth() + 1).padStart(2, '0');
          const d = String(dt.getDate()).padStart(2, '0');
          return '' + y + m + d;
        }

        form.addEventListener('submit', function () {
          const mataKuliah = form.querySelector('select[name="mata_kuliah"]')?.value || 'Tugas';
          const jenis = form.querySelector('select[name="jenis_kegiatan"]')?.value || '';
          const deadline = form.querySelector('input[name="status_tugas"]')?.value || '';
          if (!mataKuliah || !jenis || !deadline) return;

          const title = 'Pengingat Tugas: ' + mataKuliah + (jenis ? ' (' + jenis + ')' : '');
          const start = toYmd(deadline);
          const end = addOneDayYmd(deadline);
          if (!start || !end) return;

          const googleUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            + '&text=' + encodeURIComponent(title)
            + '&details=' + encodeURIComponent('Pengingat tugas dari SI Jadwal')
            + '&dates=' + encodeURIComponent(start + '/' + end)
            + '&ctz=' + encodeURIComponent('Asia/Jakarta');

          window.open(googleUrl, '_blank', 'noopener,noreferrer');
        });
      })();
    </script>
  </body>
</html>
