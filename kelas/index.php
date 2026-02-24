<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];
$tugasPath = __DIR__ . '/../uploads/' . $username . '/tugas.csv';

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

function normalize_task_row($row) {
    $mataKuliah = trim($row[0] ?? '');
    $jenis = trim($row[1] ?? '');
    $tanggal = trim($row[2] ?? '');
    $jam = '';
    $status = '';

    $candidate1 = trim($row[3] ?? '');
    $candidate2 = trim($row[4] ?? '');

    if ($candidate1 !== '' && preg_match('/^\d{1,2}[.:]\d{2}$/', $candidate1)) {
        $jam = str_replace('.', ':', $candidate1);
        $status = $candidate2;
    } else {
        $status = $candidate1;
        if ($candidate2 !== '') {
            $status = $candidate2;
        }
    }

    return [
        'mataKuliah' => $mataKuliah,
        'jenis' => $jenis,
        'tanggal' => $tanggal,
        'jam' => $jam,
        'status' => $status
    ];
}

function build_task_row($task) {
    return [
        $task['mataKuliah'] ?? '',
        $task['jenis'] ?? '',
        $task['tanggal'] ?? '',
        $task['jam'] ?? '',
        $task['status'] ?? ''
    ];
}

$rawRows = [];
if (file_exists($tugasPath)) {
    $rawRows = array_map('str_getcsv', file($tugasPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_action'], $_POST['task_index'])) {
    $action = $_POST['task_action'];
    $index = (int)$_POST['task_index'];

    if (isset($rawRows[$index])) {
        $task = normalize_task_row($rawRows[$index]);
        if ($action === 'done') {
            $task['status'] = 'Selesai';
            $rawRows[$index] = build_task_row($task);
        } elseif ($action === 'pending') {
            $task['status'] = 'Belum selesai';
            $rawRows[$index] = build_task_row($task);
        } elseif ($action === 'archive') {
            $task['status'] = 'Arsip';
            $rawRows[$index] = build_task_row($task);
        } elseif ($action === 'delete') {
            unset($rawRows[$index]);
        }

        $fp = fopen($tugasPath, 'w');
        foreach ($rawRows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$tasksActive = [];
$tasksHistory = [];
$tasksArchive = [];
$now = time();

foreach ($rawRows as $index => $row) {
    $task = normalize_task_row($row);
    if ($task['tanggal'] === '') {
        continue;
    }

    $deadlineStr = $task['tanggal'] . ' ' . ($task['jam'] !== '' ? $task['jam'] : '23:59');
    $timestamp = strtotime($deadlineStr);
    if (!$timestamp) {
        continue;
    }

    $dayName = date('l', $timestamp);
    $hari = $mapHari[$dayName] ?? $dayName;

    $status = $task['status'];
    $overdue = $timestamp < $now && $status !== 'Selesai' && $status !== 'Arsip';

    $payload = [
        'index' => $index,
        'mataKuliah' => $task['mataKuliah'],
        'jenis' => $task['jenis'],
        'tanggal' => $task['tanggal'],
        'jam' => $task['jam'],
        'status' => $status,
        'timestamp' => $timestamp,
        'hari' => $hari,
        'overdue' => $overdue
    ];

    if ($status === 'Selesai') {
        $tasksHistory[] = $payload;
    } elseif ($status === 'Arsip') {
        $tasksArchive[] = $payload;
    } else {
        $tasksActive[] = $payload;
    }
}

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
  <body>
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
                      <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam'] ? ' • ' . htmlspecialchars($task['jam']) : '' ?></div>
                    </div>
                  </div>
                  <div class="task-detail" id="detail-active-<?= $index ?>">
                    <div class="detail-row"><span>Status</span><strong><?= htmlspecialchars($task['status'] ?: 'Belum selesai') ?></strong></div>
                    <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam'] ? ' ' . htmlspecialchars($task['jam']) : '' ?></strong></div>
                    <div class="detail-row"><span>Mata Kuliah</span><strong><?= htmlspecialchars($task['mataKuliah']) ?></strong></div>
                    <div class="detail-row"><span>Jenis Tugas</span><strong><?= htmlspecialchars($task['jenis']) ?></strong></div>
                  </div>
                  <div class="task-actions">
                    <form method="POST">
                      <input type="hidden" name="task_action" value="done">
                      <input type="hidden" name="task_index" value="<?= $task['index'] ?>">
                      <button type="submit" class="btn-primary">Selesai</button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="task_action" value="pending">
                      <input type="hidden" name="task_index" value="<?= $task['index'] ?>">
                      <button type="submit" class="btn-secondary">Belum</button>
                    </form>
                    <?php if ($task['overdue']): ?>
                      <form method="POST">
                        <input type="hidden" name="task_action" value="archive">
                        <input type="hidden" name="task_index" value="<?= $task['index'] ?>">
                        <button type="submit" class="btn-warning">Arsipkan</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST">
                      <input type="hidden" name="task_action" value="delete">
                      <input type="hidden" name="task_index" value="<?= $task['index'] ?>">
                      <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

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
                      <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam'] ? ' • ' . htmlspecialchars($task['jam']) : '' ?></div>
                    </div>
                  </div>
                  <div class="task-detail" id="detail-history-<?= $index ?>">
                    <div class="detail-row"><span>Status</span><strong>Selesai</strong></div>
                    <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam'] ? ' ' . htmlspecialchars($task['jam']) : '' ?></strong></div>
                  </div>
                  <div class="task-actions">
                    <form method="POST">
                      <input type="hidden" name="task_action" value="delete">
                      <input type="hidden" name="task_index" value="<?= $task['index'] ?>">
                      <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

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
                      <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam'] ? ' • ' . htmlspecialchars($task['jam']) : '' ?></div>
                    </div>
                  </div>
                  <div class="task-detail" id="detail-archive-<?= $index ?>">
                    <div class="detail-row"><span>Status</span><strong>Arsip</strong></div>
                    <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam'] ? ' ' . htmlspecialchars($task['jam']) : '' ?></strong></div>
                  </div>
                  <div class="task-actions">
                    <form method="POST">
                      <input type="hidden" name="task_action" value="delete">
                      <input type="hidden" name="task_index" value="<?= $task['index'] ?>">
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
