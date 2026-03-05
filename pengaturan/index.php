<?php
require_once __DIR__ . '/../backend/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

require_once __DIR__ . '/../backend/schedule_store.php';
require_once __DIR__ . '/../backend/db.php';

$messages = [];
$errors = [];
$successRedirect = false;
$tab = $_GET['tab'] ?? 'jadwal';
if (!in_array($tab, ['jadwal', 'akun', 'tampilan', 'aplikasi'], true)) {
    $tab = 'jadwal';
}
$allowedThemes = ['ungu', 'kuning', 'biru', 'hijau', 'magenta'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = $_SESSION['username'];

    if ($action === 'update_username') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if ($newUsername === '' || $currentPassword === '') {
            $errors[] = 'Username baru dan password saat ini wajib diisi.';
        } else if ($newUsername === $username) {
            $errors[] = 'Username baru tidak boleh sama dengan username lama.';
        } else {
            $conn = db_connect();
            if (!$conn) {
                $errors[] = 'Gagal koneksi ke database.';
            } else {
                $check = $conn->prepare("SELECT username FROM user WHERE username = ?");
                $check->bind_param("s", $newUsername);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $errors[] = 'Username baru sudah digunakan.';
                } else {
                    $stmt = $conn->prepare("SELECT password FROM user WHERE username = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->bind_result($hashedPassword);

                    if ($stmt->fetch() && password_verify($currentPassword, $hashedPassword)) {
                        $stmt->close();
                        $update = $conn->prepare("UPDATE user SET username = ? WHERE username = ?");
                        $update->bind_param("ss", $newUsername, $username);
                        if ($update->execute()) {
                            $oldDir = __DIR__ . "/../uploads/$username";
                            $newDir = __DIR__ . "/../uploads/$newUsername";

                            if (is_dir($oldDir)) {
                                if (is_dir($newDir)) {
                                    $errors[] = 'Folder user baru sudah ada. Perubahan dibatalkan.';
                                    $rollback = $conn->prepare("UPDATE user SET username = ? WHERE username = ?");
                                    $rollback->bind_param("ss", $username, $newUsername);
                                    $rollback->execute();
                                } else if (!rename($oldDir, $newDir)) {
                                    $errors[] = 'Gagal memindahkan folder user. Perubahan dibatalkan.';
                                    $rollback = $conn->prepare("UPDATE user SET username = ? WHERE username = ?");
                                    $rollback->bind_param("ss", $username, $newUsername);
                                    $rollback->execute();
                                } else {
                                    $_SESSION['username'] = $newUsername;
                                    unset($_SESSION['active_schedule_id'], $_SESSION['active_schedule_csv'], $_SESSION['active_schedule_json']);
                                    $messages[] = 'Username berhasil diperbarui.';
                                    $successRedirect = true;
                                }
                            } else {
                                $_SESSION['username'] = $newUsername;
                                unset($_SESSION['active_schedule_id'], $_SESSION['active_schedule_csv'], $_SESSION['active_schedule_json']);
                                $messages[] = 'Username berhasil diperbarui.';
                                $successRedirect = true;
                            }
                        } else {
                            $errors[] = 'Gagal memperbarui username.';
                        }
                        $update->close();
                    } else {
                        $errors[] = 'Password saat ini tidak valid.';
                    }
                    $stmt->close();
                }
                $check->close();
                $conn->close();
            }
        }
    }

    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Semua field password wajib diisi.';
        } else if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } else {
            $conn = db_connect();
            if (!$conn) {
                $errors[] = 'Gagal koneksi ke database.';
            } else {
                $stmt = $conn->prepare("SELECT password FROM user WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->bind_result($hashedPassword);

                if ($stmt->fetch() && password_verify($currentPassword, $hashedPassword)) {
                    $stmt->close();
                    $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE user SET password = ? WHERE username = ?");
                    $update->bind_param("ss", $newHashed, $username);
                    if ($update->execute()) {
                        $messages[] = 'Password berhasil diperbarui.';
                        $successRedirect = true;
                    } else {
                        $errors[] = 'Gagal memperbarui password.';
                    }
                    $update->close();
                } else {
                    $errors[] = 'Password saat ini tidak valid.';
                }
                $stmt->close();
                $conn->close();
            }
        }
    }

    if ($action === 'update_theme') {
        $theme = $_POST['theme_color'] ?? '';
        if (!in_array($theme, $allowedThemes, true)) {
            $errors[] = 'Tema tidak valid.';
        } else {
            $conn = db_connect();
            if (!$conn) {
                $errors[] = 'Gagal koneksi ke database.';
            } else {
                $stmt = $conn->prepare("INSERT INTO user_preference (username, theme_color) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme_color=VALUES(theme_color), updated_at=CURRENT_TIMESTAMP");
                $stmt->bind_param('ss', $username, $theme);
                if ($stmt->execute()) {
                    $messages[] = 'Tema berhasil diperbarui.';
                } else {
                    $errors[] = 'Gagal memperbarui tema.';
                }
                $stmt->close();
                $conn->close();
            }
        }
    }
}

if ($successRedirect && empty($errors)) {
    if (empty($_SESSION['username'])) {
        header("Location: ../login/index.php");
        exit();
    }
    header("Location: index.php?tab=akun&notice=success");
    exit();
}

$username = $_SESSION['username'];

$currentTheme = 'ungu';
$conn = db_connect();
if ($conn) {
    $stmt = $conn->prepare("SELECT theme_color FROM user_preference WHERE username=? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($themeValue);
    if ($stmt->fetch() && $themeValue) {
        $currentTheme = $themeValue;
    }
    $stmt->close();
    $conn->close();
}

if (isset($_GET['notice']) && $_GET['notice'] === 'success') {
    $messages[] = 'Perubahan berhasil disimpan.';
}
$scheduleIndex = load_schedule_index($username);
$scheduleItems = $scheduleIndex['items'] ?? [];

usort($scheduleItems, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});

$userDir = __DIR__ . '/../uploads/' . $username;
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
    <link rel="stylesheet" href="style.css?v=<?= time() ?>" />
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
          <a class="tab <?php echo $tab === 'jadwal' ? 'active' : ''; ?>" href="?tab=jadwal">Jadwal</a>
          <a class="tab <?php echo $tab === 'akun' ? 'active' : ''; ?>" href="?tab=akun">Akun</a>
          <a class="tab <?php echo $tab === 'tampilan' ? 'active' : ''; ?>" href="?tab=tampilan">Tampilan</a>
          <a class="tab <?php echo $tab === 'aplikasi' ? 'active' : ''; ?>" href="?tab=aplikasi">Aplikasi</a>
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
                        <a class="btn-link" href="../backend/export_schedule.php?schedule_id=<?php echo urlencode($itemId); ?>">Download</a>
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
                        <a href="../uploads/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($relative); ?>" target="_blank">
                          <img src="../uploads/<?php echo htmlspecialchars($username); ?>/<?php echo htmlspecialchars($relative); ?>" alt="Foto Jadwal">
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
              <a class="btn-primary" href="../admin/index.php">Login sebagai admin</a>
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

        <?php if ($tab === 'tampilan'): ?>
        <section class="section">
          <div class="section-title">Tampilan</div>
          <div class="account-grid">
            <form class="account-card" method="POST">
              <input type="hidden" name="action" value="update_theme">
              <div class="card-title">Tema Warna</div>
              <p style="font-size: 12px; color: #b0b0b8;">Pilih warna tema sesuai selera.</p>
              <div class="theme-picker">
                <label class="theme-option">
                  <input type="radio" name="theme_color" value="ungu" <?= $currentTheme === 'ungu' ? 'checked' : '' ?>>
                  <span class="swatch" style="--swatch:#6552fe;"></span>
                  <span>Ungu</span>
                </label>
                <label class="theme-option">
                  <input type="radio" name="theme_color" value="kuning" <?= $currentTheme === 'kuning' ? 'checked' : '' ?>>
                  <span class="swatch" style="--swatch:#fbbf24;"></span>
                  <span>Kuning</span>
                </label>
                <label class="theme-option">
                  <input type="radio" name="theme_color" value="biru" <?= $currentTheme === 'biru' ? 'checked' : '' ?>>
                  <span class="swatch" style="--swatch:#3b82f6;"></span>
                  <span>Biru</span>
                </label>
                <label class="theme-option">
                  <input type="radio" name="theme_color" value="hijau" <?= $currentTheme === 'hijau' ? 'checked' : '' ?>>
                  <span class="swatch" style="--swatch:#22c55e;"></span>
                  <span>Hijau</span>
                </label>
                <label class="theme-option">
                  <input type="radio" name="theme_color" value="magenta" <?= $currentTheme === 'magenta' ? 'checked' : '' ?>>
                  <span class="swatch" style="--swatch:#d946ef;"></span>
                  <span>Magenta</span>
                </label>
              </div>
              <button type="submit" class="btn-primary">Simpan Tema</button>
            </form>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($tab === 'aplikasi'): ?>
        <section class="section">
          <div class="section-title">Aplikasi</div>
          <div class="account-grid">
            <div class="account-card">
              <div class="card-title">Notifikasi Mobile</div>
              <p style="font-size: 12px; color: #b0b0b8;">
                Aktifkan notifikasi pengingat kelas dan deadline tugas. Notifikasi hanya tampil jika izin diberikan.
              </p>
              <button type="button" id="enableNotificationsBtn" class="btn-primary">Aktifkan Notifikasi</button>
              <div id="notificationStatus" style="font-size:12px; color:#b0b0b8; margin-top:8px;"></div>
            </div>

            <div class="account-card">
              <div class="card-title">Install Android App</div>
              <p style="font-size: 12px; color: #b0b0b8;">
                Pasang Si Jadwal ke layar utama Android agar berjalan seperti aplikasi native.
              </p>
              <button type="button" id="installAppBtn" class="btn-primary">Install App</button>
              <div id="installStatus" style="font-size:12px; color:#b0b0b8; margin-top:8px;"></div>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <a href="../backend/logout.php" class="logout">Logout</a>
      </div>
    </div>

    <?php include '../nav.php'; ?>

    <script>
      document.querySelectorAll('.delete-schedule').forEach(button => {
        button.addEventListener('click', async () => {
          const scheduleId = button.dataset.id;
          if (!confirm('Hapus jadwal ini?')) {
            return;
          }
          try {
            const response = await fetch('../backend/delete_schedule.php', {
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

      const notifBtn = document.getElementById('enableNotificationsBtn');
      const notifStatus = document.getElementById('notificationStatus');
      const installBtn = document.getElementById('installAppBtn');
      const installStatus = document.getElementById('installStatus');

      function updateNotifStatus(message) {
        if (notifStatus) {
          notifStatus.textContent = message;
        }
      }

      async function getDiag() {
        if (!window.SiJadwalPWA || !window.SiJadwalPWA.getRuntimeDiagnostics) return null;
        return window.SiJadwalPWA.getRuntimeDiagnostics();
      }

      if (notifBtn) {
        notifBtn.addEventListener('click', async () => {
          if (!window.SiJadwalPWA) {
            updateNotifStatus('Modul PWA belum siap. Muat ulang halaman.');
            return;
          }

          const diag = await getDiag();
          if (diag && !diag.isSecureContext) {
            if (diag.isLanIp || (!diag.isLocalhost && diag.protocol !== 'https:')) {
              updateNotifStatus('Notifikasi tidak bisa di HTTP/LAN IP. Untuk testing tanpa hosting gunakan HTTPS tunnel (Cloudflare Tunnel / ngrok).');
            } else {
              updateNotifStatus('Notifikasi butuh secure context (HTTPS/localhost).');
            }
            return;
          }

          const result = await window.SiJadwalPWA.requestNotificationPermission();
          if (result.ok) {
            updateNotifStatus('Izin notifikasi aktif. Notifikasi uji sudah dikirim.');
          } else if (result.permission === 'denied') {
            updateNotifStatus('Izin ditolak. Buka Site settings di Chrome Android, aktifkan Notifications untuk situs ini.');
          } else if (result.permission === 'unsupported') {
            updateNotifStatus('Browser tidak mendukung notifikasi.');
          } else if (result.reason === 'insecure-context') {
            updateNotifStatus('Notifikasi butuh HTTPS. Buka aplikasi dari URL HTTPS, bukan HTTP.');
          } else if (result.reason === 'service-worker-unsupported') {
            updateNotifStatus('Browser ini tidak mendukung Service Worker.');
          } else {
            updateNotifStatus('Izin notifikasi belum diberikan. Coba lagi lalu pilih Allow.');
          }
        });
      }

      if ('Notification' in window) {
        if (Notification.permission === 'granted') {
          updateNotifStatus('Izin notifikasi aktif.');
        } else if (Notification.permission === 'denied') {
          updateNotifStatus('Izin notifikasi ditolak.');
        } else {
          updateNotifStatus('Izin notifikasi belum diberikan.');
        }
      }

      function setInstallStatus(message) {
        if (installStatus) {
          installStatus.textContent = message;
        }
      }

      function markInstallAvailability() {
        if (!installBtn) return;
        installBtn.disabled = false;
        setInstallStatus('Siap di-install.');
      }

      if (installBtn) {
        installBtn.disabled = false;
        setInstallStatus('Siap install. Jika prompt belum muncul, gunakan menu Chrome.');

        installBtn.addEventListener('click', async () => {
          if (!window.SiJadwalPWA) {
            setInstallStatus('Modul PWA belum siap. Muat ulang halaman.');
            return;
          }

          const diag = await getDiag();
          if (diag && !diag.isSecureContext) {
            if (diag.isLanIp || (!diag.isLocalhost && diag.protocol !== 'https:')) {
              setInstallStatus('Install app tidak muncul di HTTP/LAN IP. Gunakan HTTPS tunnel (Cloudflare Tunnel/ngrok) lalu buka URL HTTPS di Android.');
            } else {
              setInstallStatus('Install app butuh secure context (HTTPS/localhost).');
            }
            return;
          }

          if (window.SiJadwalPWA.isStandaloneMode && window.SiJadwalPWA.isStandaloneMode()) {
            setInstallStatus('Aplikasi sudah ter-install.');
            return;
          }

          const accepted = await window.SiJadwalPWA.promptInstall();
          if (accepted) {
            setInstallStatus('Prompt install ditampilkan. Lanjutkan dari browser.');
          } else {
            setInstallStatus('Prompt belum tersedia. Buka Chrome menu (⋮) > Add to Home screen / Install app. Coba lagi setelah membuka app 1-2 kali.');
          }
        });

        window.addEventListener('si-jadwal:install-available', markInstallAvailability);

        if (window.SiJadwalPWA && window.SiJadwalPWA.isStandaloneMode && window.SiJadwalPWA.isStandaloneMode()) {
          installBtn.disabled = true;
          setInstallStatus('Aplikasi sudah ter-install.');
        } else if (window.SiJadwalPWA && window.SiJadwalPWA.canPromptInstall && window.SiJadwalPWA.canPromptInstall()) {
          markInstallAvailability();
        } else {
          setInstallStatus('Jika prompt belum muncul, gunakan menu Chrome (⋮) > Add to Home screen / Install app. Untuk localhost via IP, pakai HTTPS tunnel.');
        }
      }
    </script>
  </body>
</html>
