<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
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
          <a class="tab <?php echo $tab === 'jadwal' ? 'active' : ''; ?>" href="index.php?route=pengaturan&tab=jadwal">Jadwal</a>
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

        <a href="index.php?route=api-logout" class="logout">Logout</a>
      </div>
    </div>

    <?php include PROJECT_ROOT . '/app/view/nav.php'; ?>

    <script>
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
    </script>
  </body>
</html>
