<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <?php include PROJECT_ROOT . '/app/view/theme.php'; ?>
    <link rel="stylesheet" href="app/view/pengaturan/global.css" />
    <link rel="stylesheet" href="app/view/pengaturan/styleguide.css" />
    <link rel="stylesheet" href="app/view/pengaturan/style.css?v=<?= time() ?>" />
  </head>
  <body>
    <div class="pengaturan">
      <div class="container">
        <div class="header">
          <h1>Pengaturan</h1>
          <p>Kelola jadwal dan akun Anda.</p>
        </div>

        <?php if (!empty($messages)): ?>
          <div class="alert success"><?php echo htmlspecialchars(implode(' ', $messages)); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="alert error"><?php echo htmlspecialchars(implode(' ', $errors)); ?></div>
        <?php endif; ?>

        <div class="tabs">
          <a class="tab <?php echo $tab === 'tampilan' ? 'active' : ''; ?>" href="index.php?route=pengaturan&tab=tampilan">Tampilan</a>
          <a class="tab <?php echo $tab === 'jadwal' ? 'active' : ''; ?>" href="index.php?route=pengaturan&tab=jadwal">Jadwal</a>
          <a class="tab <?php echo $tab === 'pengingat' ? 'active' : ''; ?>" href="index.php?route=pengaturan&tab=pengingat">Pengingat</a>
          <a class="tab <?php echo $tab === 'akun' ? 'active' : ''; ?>" href="index.php?route=pengaturan&tab=akun">Akun</a>
        </div>

        <?php if ($tab === 'jadwal'): ?>
        <section class="section">
          <div class="section-title">List Jadwal</div>
          <?php if (empty($scheduleItems)): ?>
            <div class="empty-state">Belum ada jadwal yang tersimpan.</div>
          <?php else: ?>
            <div class="schedule-list">
              <?php foreach ($scheduleItems as $item): ?>
                <?php
                  $itemId = $item['id'] ?? '';
                  $photoFiles = [];
                  $folder = ($itemId && $itemId !== 'legacy') ? ($userDir . '/' . $itemId) : null;
                  if ($folder && is_dir($folder)) {
                      $photoFiles = glob($folder . '/original*');
                  }
                  $createdAt = $item['created_at'] ?? '';
                  $createdLabel = $createdAt ? date('d M Y H:i', strtotime($createdAt)) : '';
                ?>
                <div class="schedule-card" data-id="<?php echo htmlspecialchars($itemId); ?>">
                  <div class="schedule-header">
                    <div>
                      <div class="schedule-name"><?php echo htmlspecialchars($item['name'] ?? 'Jadwal'); ?></div>
                      <div class="schedule-meta"><?php echo htmlspecialchars($createdLabel); ?></div>
                    </div>
                    <div class="schedule-actions">
                      <?php if (!empty($itemId)): ?>
                        <a class="btn-link" href="index.php?route=api-export-schedule&schedule_id=<?php echo urlencode($itemId); ?>">Download</a>
                      <?php endif; ?>
                      <?php if ($itemId !== 'legacy'): ?>
                        <button class="btn-danger delete-schedule" type="button" data-id="<?php echo htmlspecialchars($itemId); ?>">Hapus</button>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="photo-strip">
                    <?php if (!empty($photoFiles)): ?>
                      <?php foreach ($photoFiles as $photo): ?>
                        <?php $relative = str_replace($userDir . '/', '', $photo); ?>
                        <a href="app/uploads/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($relative); ?>" target="_blank">
                          <img src="app/uploads/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($relative); ?>" alt="Foto Jadwal">
                        </a>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <div class="no-photo">Tidak ada foto yang tersimpan.</div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'akun'): ?>
        <section class="section">
          <div class="section-title">Akun</div>
          <div class="account-grid">
            <?php if ($username === 'hanafi'): ?>
            <div class="account-card">
              <div class="card-title">Admin</div>
              <p style="font-size: 12px; color: #b0b0b8;">Akses dashboard admin untuk melihat data user, jadwal, dan tugas.</p>
              <a class="btn-primary" href="admin/index.php">Login sebagai admin</a>
            </div>
            <?php endif; ?>

            <div class="account-card">
              <div class="card-title">Google Calendar</div>
              <p style="font-size: 12px; color: #b0b0b8;">
                Export jadwal ke file <strong>.ics</strong> lalu import manual ke Google Calendar.
              </p>
              <div class="google-actions">
                <a class="btn-primary" href="index.php?route=api-export-calendar-ics">Download File ICS</a>
              </div>
            </div>

            <form class="account-card" method="POST">
              <input type="hidden" name="action" value="update_username">
              <div class="card-title">Ganti Username</div>
              <label>Username baru</label>
              <input type="text" name="new_username" placeholder="Username baru" required>
              <label>Password saat ini</label>
              <input type="password" name="current_password" placeholder="Password saat ini" required>
              <button type="submit" class="btn-primary">Simpan</button>
            </form>

            <form class="account-card" method="POST">
              <input type="hidden" name="action" value="update_password">
              <div class="card-title">Ganti Password</div>
              <label>Password saat ini</label>
              <input type="password" name="current_password" placeholder="Password saat ini" required>
              <label>Password baru</label>
              <input type="password" name="new_password" placeholder="Password baru" required>
              <label>Konfirmasi password</label>
              <input type="password" name="confirm_password" placeholder="Konfirmasi password" required>
              <button type="submit" class="btn-primary">Simpan</button>
            </form>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'tampilan'): ?>
        <section class="section">
          <div class="section-title">Preferensi Warna</div>
          <div class="pref-card">
            <div class="pref-help">Pilih warna aksen utama aplikasi. Preferensi disimpan di akun Anda.</div>
            <div class="pref-grid" id="colorPrefGrid">
              <label class="pref-option" data-color="#6552fe">
                <input type="radio" name="accentColor" value="#6552fe">
                <span class="pref-swatch" style="background:#6552fe"></span>
                Indigo
              </label>
              <label class="pref-option" data-color="#8b5cf6">
                <input type="radio" name="accentColor" value="#8b5cf6">
                <span class="pref-swatch" style="background:#8b5cf6"></span>
                Violet
              </label>
              <label class="pref-option" data-color="#0ea5e9">
                <input type="radio" name="accentColor" value="#0ea5e9">
                <span class="pref-swatch" style="background:#0ea5e9"></span>
                Sky
              </label>
              <label class="pref-option" data-color="#10b981">
                <input type="radio" name="accentColor" value="#10b981">
                <span class="pref-swatch" style="background:#10b981"></span>
                Emerald
              </label>
              <label class="pref-option" data-color="#f59e0b">
                <input type="radio" name="accentColor" value="#f59e0b">
                <span class="pref-swatch" style="background:#f59e0b"></span>
                Amber
              </label>
              <label class="pref-option" data-color="#f43f5e">
                <input type="radio" name="accentColor" value="#f43f5e">
                <span class="pref-swatch" style="background:#f43f5e"></span>
                Rose
              </label>
              <label class="pref-option" data-color="#22c55e">
                <input type="radio" name="accentColor" value="#22c55e">
                <span class="pref-swatch" style="background:#22c55e"></span>
                Green
              </label>
              <label class="pref-option" data-color="#64748b">
                <input type="radio" name="accentColor" value="#64748b">
                <span class="pref-swatch" style="background:#64748b"></span>
                Slate
              </label>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'pengingat'): ?>
        <section class="section">
          <div class="section-title">Pengingat Browser</div>
          <div class="pref-card">
            <div class="pref-help">
              Aktifkan notifikasi browser untuk jadwal terdekat dan tugas yang belum selesai.
            </div>
            <div class="reminder-status" id="browserReminderStatus">Status: Memeriksa izin browser...</div>
            <label class="reminder-toggle">
              <input id="enableBrowserReminder" type="checkbox">
              Aktifkan pengingat browser
            </label>
            <label class="reminder-toggle">
              <input id="enableScheduleReminder" type="checkbox">
              Pengingat jadwal yang akan datang
            </label>
            <label class="reminder-toggle">
              <input id="enableTaskReminder" type="checkbox">
              Pengingat tugas belum dikerjakan
            </label>
            <button type="button" class="btn-primary" id="saveReminderPrefs">Simpan Pengingat</button>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'akun'): ?>
          <a href="index.php?route=api-logout" class="logout">Logout</a>
        <?php endif; ?>
      </div>
    </div>

    <?php include PROJECT_ROOT . '/app/view/nav.php'; ?>

    <script>
      (function initColorPreference() {
        const grid = document.getElementById('colorPrefGrid');
        if (!grid) return;

        function applyAccent(color) {
          if (!color) return;
          document.documentElement.style.setProperty('--theme-accent', color);
        }

        const saved = <?php echo json_encode($userPreferences['accent_color'] ?: '#6552fe'); ?>;
        applyAccent(saved);

        grid.querySelectorAll('.pref-option').forEach(option => {
          const color = option.dataset.color || '';
          const input = option.querySelector('input[type="radio"]');
          const isActive = color.toLowerCase() === String(saved || '').toLowerCase();
          if (input) input.checked = isActive;
          option.classList.toggle('active', isActive);

          option.addEventListener('click', async () => {
            const selected = option.dataset.color || '';
            if (!selected) return;

            // Save to database
            try {
              const response = await fetch('index.php?route=pengaturan&tab=tampilan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                  action: 'update_preference',
                  preference_value: selected
                })
              });
              const data = await response.json();
              if (!data.ok) {
                throw new Error(data.error || 'Failed to save preference');
              }
              // Apply locally
              applyAccent(selected);
              grid.querySelectorAll('.pref-option').forEach(item => item.classList.remove('active'));
              grid.querySelectorAll('.pref-option input[type="radio"]').forEach(inp => inp.checked = false);
              option.classList.add('active');
              const thisInput = option.querySelector('input[type="radio"]');
              if (thisInput) thisInput.checked = true;
            } catch (err) {
              alert('Gagal menyimpan preferensi warna: ' + err.message);
            }
          });
        });
      })();

      document.querySelectorAll('.delete-schedule').forEach(button => {
        button.addEventListener('click', async () => {
          const scheduleId = button.dataset.id;
          if (!confirm('Hapus jadwal ini?')) {
            return;
          }
          try {
            const response = await fetch('index.php?route=api-delete-schedule', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ scheduleId })
            });
            const data = await response.json();
            if (!data.ok) {
              throw new Error(data.message || 'Gagal menghapus jadwal');
            }
            const card = button.closest('.schedule-card');
            if (card) {
              card.remove();
            }
          } catch (err) {
            alert('Gagal menghapus jadwal.');
          }
        });
      });

      (function initReminderPreference() {
        const enableMain = document.getElementById('enableBrowserReminder');
        const enableSchedule = document.getElementById('enableScheduleReminder');
        const enableTask = document.getElementById('enableTaskReminder');
        const saveButton = document.getElementById('saveReminderPrefs');
        const statusEl = document.getElementById('browserReminderStatus');
        if (!enableMain || !enableSchedule || !enableTask || !saveButton || !statusEl) return;

        const storageKey = 'si_jadwal_reminder_settings_' + <?php echo json_encode($username); ?>;

        function getPermissionLabel() {
          if (!('Notification' in window)) return 'Browser tidak mendukung notifikasi.';
          if (Notification.permission === 'granted') return 'Izin notifikasi: diizinkan.';
          if (Notification.permission === 'denied') return 'Izin notifikasi: ditolak.';
          return 'Izin notifikasi: belum diminta.';
        }

        function readState() {
          try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return { enabled: false, schedule: true, task: true };
            const parsed = JSON.parse(raw);
            return {
              enabled: !!parsed.enabled,
              schedule: parsed.schedule !== false,
              task: parsed.task !== false
            };
          } catch (e) {
            return { enabled: false, schedule: true, task: true };
          }
        }

        function writeState(nextState) {
          localStorage.setItem(storageKey, JSON.stringify(nextState));
          localStorage.setItem('si_jadwal_reminder_dirty', String(Date.now()));
        }

        const state = readState();
        enableMain.checked = state.enabled;
        enableSchedule.checked = state.schedule;
        enableTask.checked = state.task;
        statusEl.textContent = 'Status: ' + getPermissionLabel();

        saveButton.addEventListener('click', async () => {
          if (!('Notification' in window)) {
            alert('Browser ini belum mendukung notifikasi.');
            return;
          }

          if (enableMain.checked && Notification.permission !== 'granted') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
              alert('Notifikasi belum diizinkan.');
            }
          }

          writeState({
            enabled: enableMain.checked,
            schedule: enableSchedule.checked,
            task: enableTask.checked
          });
          statusEl.textContent = 'Status: ' + getPermissionLabel();
          alert('Pengaturan pengingat tersimpan.');
        });
      })();
    </script>
  </body>
</html>
