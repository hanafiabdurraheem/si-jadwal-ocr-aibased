<?php
require_once __DIR__ . '/../backend/session.php';
app_start_session();

if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/../backend/task_store.php';
require_once __DIR__ . '/../backend/schedule_store.php';
require_once __DIR__ . '/../backend/class_store.php';

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

$tasksActiveAll = task_list_by_status($username, ['Belum selesai']);
$tasksHistoryAll = task_list_by_status($username, ['Selesai']);
$tasksArchiveAll = task_list_by_status($username, ['Arsip']);

$filterTasks = function ($tasks, $isClass) {
    return array_values(array_filter($tasks, function ($task) use ($isClass) {
        $hasClass = !empty($task['source_kelas_id']);
        return $isClass ? $hasClass : !$hasClass;
    }));
};

$tasksActiveClass = $filterTasks($tasksActiveAll, true);
$tasksHistoryClass = $filterTasks($tasksHistoryAll, true);
$tasksArchiveClass = $filterTasks($tasksArchiveAll, true);

$tasksActivePersonal = $filterTasks($tasksActiveAll, false);
$tasksHistoryPersonal = $filterTasks($tasksHistoryAll, false);
$tasksArchivePersonal = $filterTasks($tasksArchiveAll, false);

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

$augment($tasksActiveClass);
$augment($tasksHistoryClass);
$augment($tasksArchiveClass);
$augment($tasksActivePersonal);
$augment($tasksHistoryPersonal);
$augment($tasksArchivePersonal);

$sortFn = function ($a, $b) {
    return $a['timestamp'] <=> $b['timestamp'];
};

usort($tasksActiveClass, $sortFn);
usort($tasksHistoryClass, $sortFn);
usort($tasksArchiveClass, $sortFn);
usort($tasksActivePersonal, $sortFn);
usort($tasksHistoryPersonal, $sortFn);
usort($tasksArchivePersonal, $sortFn);

$notice = $_GET['notice'] ?? '';
$errorMessage = $_GET['error'] ?? '';

$classes = class_list_for_user($username);
$membersByClass = [];
$classScheduleMap = [];
foreach ($classes as $classItem) {
    $classId = (int)($classItem['id'] ?? 0);
    if (($classItem['role'] ?? '') === 'admin') {
        $membersByClass[$classId] = class_list_members($classId);
    }
    $latestSet = $classId > 0 ? class_latest_schedule_set($classId) : null;
    if ($latestSet && !empty($latestSet['set_key'])) {
        $classScheduleMap[$classId] = [
            'set_key' => $latestSet['set_key'],
            'name' => $latestSet['name'] ?? ''
        ];
    }
}

$scheduleIndex = load_schedule_index($username);
$scheduleItems = $scheduleIndex['items'] ?? [];
$activeScheduleId = $scheduleIndex['active_id'] ?? '';

$activeSchedule = resolve_active_schedule_item($username);
$rowsMatkul = $activeSchedule ? get_schedule_rows($username, $activeSchedule['id']) : [];
$mataKuliahList = [];
foreach ($rowsMatkul as $row) {
    if (!empty($row['nama_matakuliah'])) {
        $mataKuliahList[] = trim($row['nama_matakuliah']);
    }
}
$mataKuliahList = array_values(array_unique($mataKuliahList));
sort($mataKuliahList);
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
  <body data-chat-context="pengingat">
    <div class="kelas">
      <div class="container">
        <div class="header">
          <h1>Pengingat Tugas</h1>
          <p>Urutan berdasarkan hari dan jam terdekat.</p>
        </div>

        <?php if (!empty($notice) || !empty($errorMessage)): ?>
          <div class="alert <?= !empty($errorMessage) ? 'error' : 'success' ?>">
            <?php
              if (!empty($errorMessage)) {
                  echo htmlspecialchars($errorMessage);
              } else if ($notice === 'kelas_created') {
                  echo 'Kelas berhasil dibuat.';
              } else if ($notice === 'kelas_joined') {
                  echo 'Berhasil bergabung ke kelas.';
              } else if ($notice === 'jadwal_shared') {
                  echo 'Jadwal berhasil dibagikan ke kelas.';
              } else if ($notice === 'tugas_shared') {
                  echo 'Tugas kelas berhasil ditambahkan.';
              } else if ($notice === 'kelas_activated') {
                  echo 'Kelas berhasil diaktifkan.';
              } else {
                  echo 'Perubahan berhasil.';
              }
            ?>
          </div>
        <?php endif; ?>

        <section class="section">
          <div class="section-title">Kelas</div>
          <div class="kelas-actions">
            <button class="fab-add" type="button" id="fabClass" aria-label="Tambah kelas">+</button>
            <div class="fab-menu" id="fabMenu" aria-hidden="true">
              <form class="kelas-form" method="POST" action="../backend/class_create.php">
                <label>Nama kelas baru</label>
                <input type="text" name="class_name" placeholder="Contoh: Kelas A Sistem Informasi" required>
                <button type="submit" class="btn-primary">Buat Kelas</button>
              </form>

              <form class="kelas-form" method="POST" action="../backend/class_join.php">
                <label>Kode kelas</label>
                <input type="text" name="class_code" placeholder="Masukkan kode" required>
                <button type="submit" class="btn-secondary">Gabung Kelas</button>
              </form>
            </div>
          </div>

          <?php if (empty($classes)): ?>
            <div class="empty-state">Belum ada kelas. Buat atau gabung kelas terlebih dahulu.</div>
          <?php else: ?>
            <div class="kelas-list">
              <?php foreach ($classes as $classItem): ?>
                <?php
                  $classId = (int)($classItem['id'] ?? 0);
                  $className = $classItem['nama'] ?? 'Kelas';
                  $classRole = $classItem['role'] ?? 'member';
                  $memberCount = (int)($classItem['member_count'] ?? 0);
                  $classCode = $classItem['kode_join'] ?? '';
                  $isAdmin = $classRole === 'admin';
                  $classSchedule = $classScheduleMap[$classId]['set_key'] ?? '';
                  $classScheduleName = $classScheduleMap[$classId]['name'] ?? '';
                  $isActiveClass = ($classSchedule !== '' && $classSchedule === $activeScheduleId);
                ?>
                <div class="kelas-card">
                  <button class="kelas-toggle" type="button" data-target="kelas-body-<?= $classId ?>">
                    <div>
                      <div class="kelas-name"><?= htmlspecialchars($className) ?></div>
                      <div class="kelas-meta">Peran: <?= htmlspecialchars($classRole) ?> • Anggota: <?= $memberCount ?></div>
                    </div>
                    <span class="toggle-icon">▼</span>
                  </button>

                  <div class="kelas-body" id="kelas-body-<?= $classId ?>">
                    <div class="kelas-actions-row">
                      <?php if ($classSchedule !== ''): ?>
                        <form class="kelas-activate" method="POST" action="../backend/activate_class.php">
                          <input type="hidden" name="kelas_id" value="<?= $classId ?>">
                          <button type="submit" class="btn-primary" <?= $isActiveClass ? 'disabled' : '' ?>>
                            <?= $isActiveClass ? 'Aktif' : 'Aktifkan Kelas' ?>
                          </button>
                          <?php if ($classScheduleName): ?>
                            <div class="kelas-meta">Jadwal kelas: <?= htmlspecialchars($classScheduleName) ?></div>
                          <?php endif; ?>
                        </form>
                      <?php else: ?>
                        <div class="kelas-meta">Belum ada jadwal kelas.</div>
                      <?php endif; ?>

                      <?php if ($isAdmin): ?>
                        <div class="kelas-code">
                          Kode: <strong><?= htmlspecialchars($classCode) ?></strong>
                          <button type="button" class="btn-secondary btn-qr" data-code="<?= htmlspecialchars($classCode) ?>">QR</button>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php if ($isAdmin): ?>
                      <div class="kelas-tools">
                        <form class="kelas-share" method="POST" action="../backend/class_share_schedule.php">
                          <input type="hidden" name="kelas_id" value="<?= $classId ?>">
                          <label>Bagikan jadwal ke kelas</label>
                          <select name="schedule_id" required>
                            <option value="">Pilih jadwal</option>
                            <?php foreach ($scheduleItems as $scheduleItem): ?>
                              <?php $scheduleId = $scheduleItem['id'] ?? ''; ?>
                              <?php if ($scheduleId): ?>
                                <option value="<?= htmlspecialchars($scheduleId) ?>">
                                  <?= htmlspecialchars($scheduleItem['name'] ?? 'Jadwal') ?>
                                </option>
                              <?php endif; ?>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="btn-primary">Bagikan Jadwal</button>
                        </form>
                      </div>

                      <div class="kelas-members">
                        <div class="members-title">Anggota Kelas</div>
                        <div class="members-list">
                          <?php foreach (($membersByClass[$classId] ?? []) as $member): ?>
                            <div class="member-item">
                              <span><?= htmlspecialchars($member['username'] ?? '') ?></span>
                              <small><?= htmlspecialchars($member['role'] ?? '') ?></small>
                            </div>
                          <?php endforeach; ?>
                        </div>
                        <img class="kelas-qr" data-code="<?= htmlspecialchars($classCode) ?>" alt="QR Kode Kelas" style="display:none;">
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="section">
          <button class="reminder-toggle" type="button" data-target="reminder-class">
            <span>Pengingat Kelas</span>
            <span class="toggle-icon">▼</span>
          </button>
          <div class="reminder-body" id="reminder-class">
            <div class="section-title">Aktif</div>
            <?php if (empty($tasksActiveClass)): ?>
              <div class="empty-state">Belum ada tugas kelas aktif.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksActiveClass as $index => $task): ?>
                  <div class="task-card <?= $task['overdue'] ? 'late' : '' ?>">
                    <div class="task-header" data-target="detail-class-active-<?= $index ?>">
                      <div>
                        <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                        <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                      </div>
                      <div class="task-time">
                        <div><?= htmlspecialchars($task['hari']) ?></div>
                        <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                      </div>
                    </div>
                    <div class="task-detail" id="detail-class-active-<?= $index ?>">
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

            <div class="section-separator"></div>

            <div class="section-title">Histori</div>
            <?php if (empty($tasksHistoryClass)): ?>
              <div class="empty-state">Belum ada tugas kelas selesai.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksHistoryClass as $index => $task): ?>
                  <div class="task-card done">
                    <div class="task-header" data-target="detail-class-history-<?= $index ?>">
                      <div>
                        <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                        <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                      </div>
                      <div class="task-time">
                        <div><?= htmlspecialchars($task['hari']) ?></div>
                        <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                      </div>
                    </div>
                    <div class="task-detail" id="detail-class-history-<?= $index ?>">
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

            <div class="section-separator"></div>

            <div class="section-title">Arsip</div>
            <?php if (empty($tasksArchiveClass)): ?>
              <div class="empty-state">Belum ada tugas kelas diarsipkan.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksArchiveClass as $index => $task): ?>
                  <div class="task-card archived">
                    <div class="task-header" data-target="detail-class-archive-<?= $index ?>">
                      <div>
                        <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                        <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                      </div>
                      <div class="task-time">
                        <div><?= htmlspecialchars($task['hari']) ?></div>
                        <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                      </div>
                    </div>
                    <div class="task-detail" id="detail-class-archive-<?= $index ?>">
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
          </div>
        </section>

        <div class="section-separator"></div>

        <section class="section">
          <button class="reminder-toggle" type="button" data-target="reminder-personal">
            <span>Pengingat Pribadi</span>
            <span class="toggle-icon">▼</span>
          </button>
          <div class="reminder-body" id="reminder-personal">
            <div class="section-title">Pengingat Aktif</div>
            <?php if (empty($tasksActivePersonal)): ?>
              <div class="empty-state">Belum ada tugas aktif.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksActivePersonal as $index => $task): ?>
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

            <div class="section-separator"></div>

            <div class="section-title">Histori Tugas</div>
            <?php if (empty($tasksHistoryPersonal)): ?>
              <div class="empty-state">Belum ada tugas selesai.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksHistoryPersonal as $index => $task): ?>
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

            <div class="section-separator"></div>

            <div class="section-title">Arsip Tugas</div>
            <?php if (empty($tasksArchivePersonal)): ?>
              <div class="empty-state">Belum ada tugas diarsipkan.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksArchivePersonal as $index => $task): ?>
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
          </div>
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

      document.querySelectorAll('.reminder-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
          const targetId = toggle.getAttribute('data-target');
          const body = targetId ? document.getElementById(targetId) : null;
          if (!body) return;
          const isOpen = body.classList.toggle('open');
          toggle.classList.toggle('open', isOpen);
        });
      });

      const fabClass = document.getElementById('fabClass');
      const fabMenu = document.getElementById('fabMenu');
      if (fabClass && fabMenu) {
        fabClass.addEventListener('click', () => {
          const isOpen = fabMenu.classList.toggle('open');
          fabMenu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        });
      }

      document.querySelectorAll('.kelas-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
          const targetId = toggle.getAttribute('data-target');
          const body = targetId ? document.getElementById(targetId) : null;
          if (!body) return;
          const isOpen = body.classList.toggle('open');
          toggle.classList.toggle('open', isOpen);
        });
      });

      document.querySelectorAll('.btn-qr').forEach(btn => {
        btn.addEventListener('click', () => {
          const code = btn.dataset.code || '';
          const card = btn.closest('.kelas-card');
          const img = card ? card.querySelector('.kelas-qr') : null;
          if (!img || !code) return;
          if (!img.src) {
            const joinUrl = `${window.location.origin}/si-jadwal/backend/class_join.php?code=${encodeURIComponent(code)}`;
            img.src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(joinUrl)}`;
          }
          img.style.display = img.style.display === 'none' || img.style.display === '' ? 'block' : 'none';
        });
      });
    </script>
  </body>
</html>
