<?php
session_start();
require_once __DIR__ . '/../backend/db.php';

$adminUsername = 'hanafi';
if (empty($_SESSION['username'])) {
    header('Location: ../login/index.php');
    exit();
}

if ($_SESSION['username'] !== $adminUsername) {
    header('Location: ../beranda/index.php');
    exit();
}

function set_flash($type, $message) {
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash() {
    if (!empty($_SESSION['admin_flash'])) {
        $flash = $_SESSION['admin_flash'];
        unset($_SESSION['admin_flash']);
        return $flash;
    }
    return null;
}

$conn = db_connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentUser = $_SESSION['username'];

    if ($action === 'delete_user') {
        $username = trim($_POST['username'] ?? '');
        if ($username === '') {
            set_flash('error', 'Username tidak valid.');
        } elseif ($username === $currentUser) {
            set_flash('error', 'Tidak dapat menghapus akun yang sedang login.');
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("DELETE FROM schedule WHERE username=?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM task WHERE username=?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM `user` WHERE username=?");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $deleted = $stmt->affected_rows > 0;
                $stmt->close();

                $conn->commit();

                if ($deleted) {
                    set_flash('success', 'User berhasil dihapus.');
                } else {
                    set_flash('error', 'User tidak ditemukan.');
                }
            } catch (Throwable $e) {
                $conn->rollback();
                set_flash('error', 'Gagal menghapus user.');
            }
        }
    } elseif ($action === 'delete_schedule') {
        $username = trim($_POST['username'] ?? '');
        $setId = trim($_POST['set_id'] ?? '');
        if ($username === '' || $setId === '') {
            set_flash('error', 'Data jadwal tidak valid.');
        } else {
            $stmt = $conn->prepare("DELETE FROM schedule WHERE username=? AND set_id=?");
            $stmt->bind_param('ss', $username, $setId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                set_flash('success', 'Jadwal berhasil dihapus.');
            } else {
                set_flash('error', 'Jadwal tidak ditemukan.');
            }
        }
    } elseif ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            set_flash('error', 'ID tugas tidak valid.');
        } else {
            $stmt = $conn->prepare("DELETE FROM task WHERE id=?");
            $stmt->bind_param('i', $taskId);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                set_flash('success', 'Tugas berhasil dihapus.');
            } else {
                set_flash('error', 'Tugas tidak ditemukan.');
            }
        }
    }

    header('Location: index.php');
    exit();
}

$flash = get_flash();

$totalUsers = 0;
$totalScheduleSets = 0;
$totalScheduleRows = 0;
$activeScheduleSets = 0;
$totalTasks = 0;
$tasksByStatus = [];

$res = $conn->query("SELECT COUNT(*) AS c FROM `user`");
if ($res) {
    $row = $res->fetch_assoc();
    $totalUsers = (int)$row['c'];
}

$res = $conn->query("SELECT COUNT(*) AS c FROM schedule");
if ($res) {
    $row = $res->fetch_assoc();
    $totalScheduleRows = (int)$row['c'];
}

$res = $conn->query("SELECT COUNT(*) AS c FROM (SELECT DISTINCT username, set_id FROM schedule) AS t");
if ($res) {
    $row = $res->fetch_assoc();
    $totalScheduleSets = (int)$row['c'];
}

$res = $conn->query("SELECT COUNT(*) AS c FROM (SELECT DISTINCT CONCAT(username, '::', set_id) AS k FROM schedule WHERE is_active=1) AS t");
if ($res) {
    $row = $res->fetch_assoc();
    $activeScheduleSets = (int)$row['c'];
}

$res = $conn->query("SELECT COUNT(*) AS c FROM task");
if ($res) {
    $row = $res->fetch_assoc();
    $totalTasks = (int)$row['c'];
}

$res = $conn->query("SELECT status, COUNT(*) AS c FROM task GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tasksByStatus[$row['status']] = (int)$row['c'];
    }
}

$users = [];
$res = $conn->query("SELECT u.id, u.username, u.created_at,
    (SELECT COUNT(DISTINCT s.set_id) FROM schedule s WHERE s.username=u.username) AS schedule_sets,
    (SELECT COUNT(*) FROM task t WHERE t.username=u.username) AS task_count
    FROM `user` u ORDER BY u.created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $users[] = $row;
    }
}

$schedules = [];
$res = $conn->query("SELECT username, set_id, name, MAX(is_active) AS is_active, COUNT(*) AS row_count,
    MAX(created_at) AS created_at, MAX(updated_at) AS updated_at
    FROM schedule
    GROUP BY username, set_id, name
    ORDER BY updated_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $schedules[] = $row;
    }
}

$tasks = [];
$res = $conn->query("SELECT id, username, mata_kuliah, jenis, tanggal, jam, status, created_at, updated_at
    FROM task ORDER BY updated_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $tasks[] = $row;
    }
}

$conn->close();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_date($value) {
    if (!$value) return '-';
    return h($value);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin - SI Jadwal</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <div class="page">
        <header class="hero">
            <div class="hero-content">
                <p class="eyebrow">Dashboard Admin</p>
                <h1>Kontrol Data SI Jadwal</h1>
                <p class="lead">Pantau akun, jadwal, tugas, dan statistik secara cepat.</p>
            </div>
            <div class="hero-meta">
                <div class="chip">Login sebagai: <strong><?php echo h($_SESSION['username']); ?></strong></div>
                <a class="chip" href="../backend/logout.php">Logout</a>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="flash <?php echo h($flash['type']); ?>">
                <?php echo h($flash['message']); ?>
            </div>
        <?php endif; ?>

        <section class="stats">
            <div class="stat-card">
                <p>Total User</p>
                <h2><?php echo h($totalUsers); ?></h2>
            </div>
            <div class="stat-card">
                <p>Jadwal (Set)</p>
                <h2><?php echo h($totalScheduleSets); ?></h2>
                <span><?php echo h($activeScheduleSets); ?> aktif</span>
            </div>
            <div class="stat-card">
                <p>Jadwal (Baris)</p>
                <h2><?php echo h($totalScheduleRows); ?></h2>
            </div>
            <div class="stat-card">
                <p>Total Tugas</p>
                <h2><?php echo h($totalTasks); ?></h2>
                <span>
                    <?php
                    $parts = [];
                    foreach ($tasksByStatus as $status => $count) {
                        $parts[] = h($status) . ': ' . h($count);
                    }
                    echo implode(' | ', $parts) ?: '-';
                    ?>
                </span>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h3>Data User</h3>
                <p>Melihat user terdaftar dan jumlah jadwal/tugas.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Jadwal</th>
                            <th>Tugas</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="empty">Belum ada user.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo h($user['id']); ?></td>
                                    <td><?php echo h($user['username']); ?></td>
                                    <td><?php echo h($user['schedule_sets']); ?> set</td>
                                    <td><?php echo h($user['task_count']); ?></td>
                                    <td><?php echo format_date($user['created_at']); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Hapus user ini beserta jadwal dan tugasnya?');">
                                            <input type="hidden" name="action" value="delete_user" />
                                            <input type="hidden" name="username" value="<?php echo h($user['username']); ?>" />
                                            <button class="btn danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h3>Data Jadwal</h3>
                <p>Daftar set jadwal berdasarkan user.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Set ID</th>
                            <th>Nama</th>
                            <th>Status</th>
                            <th>Jumlah Baris</th>
                            <th>Update</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schedules)): ?>
                            <tr><td colspan="7" class="empty">Belum ada jadwal.</td></tr>
                        <?php else: ?>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo h($schedule['username']); ?></td>
                                    <td><?php echo h($schedule['set_id']); ?></td>
                                    <td><?php echo h($schedule['name']); ?></td>
                                    <td>
                                        <?php if ((int)$schedule['is_active'] === 1): ?>
                                            <span class="badge active">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($schedule['row_count']); ?></td>
                                    <td><?php echo format_date($schedule['updated_at']); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Hapus set jadwal ini?');">
                                            <input type="hidden" name="action" value="delete_schedule" />
                                            <input type="hidden" name="username" value="<?php echo h($schedule['username']); ?>" />
                                            <input type="hidden" name="set_id" value="<?php echo h($schedule['set_id']); ?>" />
                                            <button class="btn danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <h3>Data Tugas</h3>
                <p>Daftar tugas dari seluruh user.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Mata Kuliah</th>
                            <th>Jenis</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Status</th>
                            <th>Update</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="9" class="empty">Belum ada tugas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><?php echo h($task['id']); ?></td>
                                    <td><?php echo h($task['username']); ?></td>
                                    <td><?php echo h($task['mata_kuliah']); ?></td>
                                    <td><?php echo h($task['jenis']); ?></td>
                                    <td><?php echo format_date($task['tanggal']); ?></td>
                                    <td><?php echo h($task['jam'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo h(strtolower(str_replace(' ', '-', $task['status']))); ?>">
                                            <?php echo h($task['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_date($task['updated_at']); ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Hapus tugas ini?');">
                                            <input type="hidden" name="action" value="delete_task" />
                                            <input type="hidden" name="task_id" value="<?php echo h($task['id']); ?>" />
                                            <button class="btn danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</body>
</html>
