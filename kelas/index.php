<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/../backend/task_store.php';

date_default_timezone_set('Asia/Jakarta');
$mapHari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action'])) {
    $action = $_POST['task_action'];
    $taskId = (int)($_POST['task_id'] ?? 0);

    if ($taskId > 0) {
        if ($action === 'done') {
            task_update_status($username, $taskId, 'Selesai');
        } elseif ($action === 'pending') {
            task_update_status($username, $taskId, 'Belum selesai');
        } elseif ($action === 'archive') {
            task_update_status($username, $taskId, 'Arsip');
        } elseif ($action === 'delete') {
            task_delete($username, $taskId);
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$tasksActive = task_list_by_status($username, ['Belum selesai']);
$tasksHistory = task_list_by_status($username, ['Selesai']);
$tasksArchive = task_list_by_status($username, ['Arsip']);

$now = time();
$augment = function (&$tasks) use ($mapHari, $now) {
    foreach ($tasks as &$task) {
        $task['mataKuliah'] = $task['mata_kuliah'] ?? '';
        $deadlineStr = ($task['tanggal'] ?? '') . ' ' . (($task['jam'] ?? '') !== null ? $task['jam'] : '23:59:59');
        $timestamp = strtotime($deadlineStr);
        $task['timestamp'] = $timestamp ?: 0;
        $dayName = $timestamp ? date('l', $timestamp) : '';
        $task['hari'] = $mapHari[$dayName] ?? $dayName;
        $task['jam_display'] = ($task['jam'] && $task['jam'] !== '00:00:00') ? substr($task['jam'], 0, 5) : '';
        $task['overdue'] = $timestamp && $timestamp < $now;
    }
    unset($task);
};

$augment($tasksActive);
$augment($tasksHistory);
$augment($tasksArchive);

$sortFn = function ($a, $b) {
    return $a['timestamp'] <=> $b['timestamp'];
};

usort($tasksActive, $sortFn);
usort($tasksHistory, $sortFn);
usort($tasksArchive, $sortFn);
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
  <body data-chat-context="pengingat">
    <div class="kelas">
      <div class="container">
        <div class="header">
          <h1>Pengingat Tugas</h1>
          <p>Urutan berdasarkan hari dan jam terdekat.</p>
        </div>

        <section class="section">
          <div class="section-title">Pengingat Aktif</div>
          <?php if (empty($tasksActive)): ?>
            <div class="empty-state">Belum ada tugas aktif.</div>
          <?php else: ?>
            <div class="task-list">
              <?php foreach ($tasksActive as $index => $task): ?>
                <div class="task-card <?= $task['overdue'] ? 'late' : '' ?>">
                  <div class="task-header" data-target="detail-active-<?= $index ?>">
                    <div>
                      <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                      <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                    </div>
                    <div class="task-time">
                      <div><?= htmlspecialchars($task['hari']) ?></div>
                      <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                    </div>
                  </div>
                  <div class="task-detail" id="detail-active-<?= $index ?>">
                    <div class="detail-row"><span>Status</span><strong><?= htmlspecialchars($task['status'] ?: 'Belum selesai') ?></strong></div>
                    <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' ' . htmlspecialchars($task['jam_display']) : '' ?></strong></div>
                    <div class="detail-row"><span>Mata Kuliah</span><strong><?= htmlspecialchars($task['mataKuliah']) ?></strong></div>
                    <div class="detail-row"><span>Jenis Tugas</span><strong><?= htmlspecialchars($task['jenis']) ?></strong></div>
                  </div>
                  <div class="task-actions">
                    <form method="POST">
                      <input type="hidden" name="task_action" value="done">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <button type="submit" class="btn-primary">Selesai</button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="task_action" value="pending">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <button type="submit" class="btn-secondary">Belum</button>
                    </form>
                    <?php if ($task['overdue']): ?>
                      <form method="POST">
                        <input type="hidden" name="task_action" value="archive">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="btn-warning">Arsipkan</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST">
                      <input type="hidden" name="task_action" value="delete">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <div class="section-separator"></div>

        <section class="section">
          <div class="section-title">Histori Tugas</div>
          <?php if (empty($tasksHistory)): ?>
            <div class="empty-state">Belum ada tugas selesai.</div>
          <?php else: ?>
            <div class="task-list">
              <?php foreach ($tasksHistory as $index => $task): ?>
                <div class="task-card done">
                  <div class="task-header" data-target="detail-history-<?= $index ?>">
                    <div>
                      <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                      <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                    </div>
                    <div class="task-time">
                      <div><?= htmlspecialchars($task['hari']) ?></div>
                      <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                    </div>
                  </div>
                  <div class="task-detail" id="detail-history-<?= $index ?>">
                    <div class="detail-row"><span>Status</span><strong>Selesai</strong></div>
                    <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' ' . htmlspecialchars($task['jam_display']) : '' ?></strong></div>
                  </div>
                  <div class="task-actions">
                    <form method="POST">
                      <input type="hidden" name="task_action" value="delete">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <div class="section-separator"></div>

        <section class="section">
          <div class="section-title">Arsip Tugas</div>
          <?php if (empty($tasksArchive)): ?>
            <div class="empty-state">Belum ada tugas diarsipkan.</div>
          <?php else: ?>
            <div class="task-list">
              <?php foreach ($tasksArchive as $index => $task): ?>
                <div class="task-card archived">
                  <div class="task-header" data-target="detail-archive-<?= $index ?>">
                    <div>
                      <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                      <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                    </div>
                    <div class="task-time">
                      <div><?= htmlspecialchars($task['hari']) ?></div>
                      <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                    </div>
                  </div>
                  <div class="task-detail" id="detail-archive-<?= $index ?>">
                    <div class="detail-row"><span>Status</span><strong>Arsip</strong></div>
                    <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' ' . htmlspecialchars($task['jam_display']) : '' ?></strong></div>
                  </div>
                  <div class="task-actions">
                    <form method="POST">
                      <input type="hidden" name="task_action" value="delete">
                      <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                      <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>
    </div>
    <?php include '../nav.php'; ?>
    <?php include __DIR__ . '/../chat/widget.php'; ?>

    <script>
      document.querySelectorAll('.task-header').forEach(header => {
        header.addEventListener('click', () => {
          const card = header.closest('.task-card');
          if (card) {
            card.classList.toggle('open');
          }
        });
      });
    </script>
  </body>
</html>
