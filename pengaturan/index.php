<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
if (!in_array($tab, ['jadwal', 'akun'], true)) {
    $tab = 'jadwal';
}

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
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="styleguide.css" />
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
    </script>
  </body>
</html>
