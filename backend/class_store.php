<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schedule_store.php';
require_once __DIR__ . '/task_store.php';

function class_generate_code($length = 6) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}

function class_get_by_code($code) {
    $conn = db_connect();
    $code = strtoupper(trim((string)$code));
    $stmt = $conn->prepare("SELECT id, owner_username, nama, kode_join FROM kelas WHERE kode_join=? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function class_get_by_id($kelasId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id, owner_username, nama, kode_join FROM kelas WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $kelasId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function class_create($ownerUsername, $name) {
    $ownerUsername = trim((string)$ownerUsername);
    $name = trim((string)$name);
    if ($ownerUsername === '' || $name === '') {
        return null;
    }

    $conn = db_connect();
    $code = null;
    for ($i = 0; $i < 8; $i++) {
        $candidate = class_generate_code(6);
        $check = $conn->prepare("SELECT id FROM kelas WHERE kode_join=?");
        $check->bind_param('s', $candidate);
        $check->execute();
        $check->store_result();
        if ($check->num_rows === 0) {
            $code = $candidate;
            $check->close();
            break;
        }
        $check->close();
    }

    if ($code === null) {
        $conn->close();
        return null;
    }

    $stmt = $conn->prepare("INSERT INTO kelas (owner_username, nama, kode_join) VALUES (?,?,?)");
    $stmt->bind_param('sss', $ownerUsername, $name, $code);
    $ok = $stmt->execute();
    $kelasId = $conn->insert_id;
    $stmt->close();

    if ($ok && $kelasId) {
        $member = $conn->prepare("INSERT INTO kelas_member (kelas_id, username, role) VALUES (?, ?, 'admin')");
        $member->bind_param('is', $kelasId, $ownerUsername);
        $member->execute();
        $member->close();
    }

    $conn->close();
    if (!$ok || !$kelasId) {
        return null;
    }
    return ['id' => $kelasId, 'code' => $code];
}

function class_join($username, $kelasId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id FROM kelas_member WHERE kelas_id=? AND username=? LIMIT 1");
    $stmt->bind_param('is', $kelasId, $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return true;
    }
    $stmt->close();

    $insert = $conn->prepare("INSERT INTO kelas_member (kelas_id, username, role) VALUES (?, ?, 'member')");
    $insert->bind_param('is', $kelasId, $username);
    $ok = $insert->execute();
    $insert->close();
    $conn->close();
    return $ok;
}

function class_list_for_user($username) {
    $conn = db_connect();
    $stmt = $conn->prepare("
        SELECT k.id, k.nama, k.kode_join, k.owner_username,
               km.role, km.joined_at,
               (SELECT COUNT(*) FROM kelas_member WHERE kelas_id = k.id) AS member_count
        FROM kelas_member km
        JOIN kelas k ON k.id = km.kelas_id
        WHERE km.username=?
        ORDER BY km.joined_at DESC
    ");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

function class_latest_schedule_set($kelasId) {
    $conn = db_connect();
    $hasSetKey = class_has_set_key_column($conn);
    if (!$hasSetKey) {
        $conn->close();
        return null;
    }
    $stmt = $conn->prepare("SELECT id, set_key, name FROM kelas_jadwal_set WHERE kelas_id=? ORDER BY created_at DESC, id DESC LIMIT 1");
    if (!$stmt) {
        $conn->close();
        return null;
    }
    $stmt->bind_param('i', $kelasId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function class_list_members($kelasId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT username, role, joined_at FROM kelas_member WHERE kelas_id=? ORDER BY role DESC, joined_at ASC");
    $stmt->bind_param('i', $kelasId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

function class_is_admin($username, $kelasId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT role FROM kelas_member WHERE kelas_id=? AND username=? LIMIT 1");
    $stmt->bind_param('is', $kelasId, $username);
    $stmt->execute();
    $stmt->bind_result($role);
    $isAdmin = false;
    if ($stmt->fetch()) {
        $isAdmin = ($role === 'admin');
    }
    $stmt->close();
    $conn->close();
    return $isAdmin;
}

function class_has_set_key_column($conn) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $result = $conn->query("SHOW COLUMNS FROM kelas_jadwal_set LIKE 'set_key'");
    if ($result && $result->num_rows > 0) {
        $cache = true;
        return true;
    }
    $cache = false;
    return false;
}

function class_get_schedule_set_keys($kelasId, $conn = null) {
    $ownsConn = false;
    if ($conn === null) {
        $conn = db_connect();
        $ownsConn = true;
    }

    if (!class_has_set_key_column($conn)) {
        if ($ownsConn) {
            $conn->close();
        }
        return [];
    }

    $stmt = $conn->prepare("SELECT set_key FROM kelas_jadwal_set WHERE kelas_id=?");
    $stmt->bind_param('i', $kelasId);
    $stmt->execute();
    $res = $stmt->get_result();
    $keys = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['set_key'])) {
            $keys[] = $row['set_key'];
        }
    }
    $stmt->close();

    if ($ownsConn) {
        $conn->close();
    }
    return $keys;
}

function class_find_by_schedule_id($scheduleId) {
    $conn = db_connect();
    if (!class_has_set_key_column($conn)) {
        $conn->close();
        return null;
    }
    $stmt = $conn->prepare("
        SELECT k.id, k.nama, k.owner_username, k.kode_join, s.set_key
        FROM kelas_jadwal_set s
        JOIN kelas k ON k.id = s.kelas_id
        WHERE s.set_key=?
        LIMIT 1
    ");
    $stmt->bind_param('s', $scheduleId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $conn->close();
    return $row ?: null;
}

function class_member_usernames($kelasId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT username FROM kelas_member WHERE kelas_id=?");
    $stmt->bind_param('i', $kelasId);
    $stmt->execute();
    $res = $stmt->get_result();
    $usernames = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['username'])) {
            $usernames[] = $row['username'];
        }
    }
    $stmt->close();
    $conn->close();
    return $usernames;
}

function class_sync_schedule_set_to_member($username, $setKey, $setName, $items) {
    if ($setKey === '' || empty($items)) {
        return;
    }

    $conn = db_connect();
    $check = $conn->prepare("SELECT 1 FROM schedule WHERE username=? AND set_id=? LIMIT 1");
    $check->bind_param('ss', $username, $setKey);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0;
    $check->close();
    $conn->close();

    if ($exists) {
        return;
    }

    $rows = [];
    foreach ($items as $item) {
        $rows[] = [
            'No' => $item['no_col'] ?? '',
            'Kode' => $item['kode'] ?? '',
            'Nama Matakuliah' => $item['nama_matakuliah'] ?? '',
            'SKS' => $item['sks'] ?? '',
            'Kelas/Rombel' => $item['kelas'] ?? '',
            'Pengampu' => $item['pengampu'] ?? '',
            'Jenis' => $item['jenis'] ?? '',
            'Ruang' => $item['ruang'] ?? '',
            'Hari' => $item['hari'] ?? '',
            'Jam Mulai' => $item['jam_mulai'] ?? '',
            'Jam Selesai' => $item['jam_selesai'] ?? '',
            'Mode' => 'luring'
        ];
    }

    add_schedule_set($username, $setKey, $setName, $rows, false);
}

function class_sync_member($username, $kelasId) {
    $classInfo = class_get_by_id($kelasId);
    if (!$classInfo) {
        return false;
    }

    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id, set_key, name FROM kelas_jadwal_set WHERE kelas_id=?");
    $stmt->bind_param('i', $kelasId);
    $stmt->execute();
    $res = $stmt->get_result();
    $sets = [];
    while ($row = $res->fetch_assoc()) {
        $sets[] = $row;
    }
    $stmt->close();

    foreach ($sets as $set) {
        $items = [];
        $itemsStmt = $conn->prepare("SELECT no_col, kode, nama_matakuliah, sks, kelas, pengampu, jenis, ruang, hari, jam_mulai, jam_selesai FROM kelas_jadwal_item WHERE kelas_jadwal_set_id=? ORDER BY id ASC");
        $itemsStmt->bind_param('i', $set['id']);
        $itemsStmt->execute();
        $itemsRes = $itemsStmt->get_result();
        while ($row = $itemsRes->fetch_assoc()) {
            $items[] = $row;
        }
        $itemsStmt->close();

        $setKey = $set['set_key'] ?? '';
        $setName = $set['name'] ?? ('Kelas: ' . ($classInfo['nama'] ?? 'Kelas'));
        class_sync_schedule_set_to_member($username, $setKey, $setName, $items);
    }
    $conn->close();
    return true;
}

function class_sync_tasks_for_member($username, $kelasId) {
    $conn = db_connect();
    $taskStmt = $conn->prepare("SELECT id, mata_kuliah, jenis, tanggal, jam FROM kelas_tugas WHERE kelas_id=? ORDER BY id ASC");
    $taskStmt->bind_param('i', $kelasId);
    $taskStmt->execute();
    $taskRes = $taskStmt->get_result();
    while ($task = $taskRes->fetch_assoc()) {
        $taskId = (int)($task['id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }
        $check = $conn->prepare("SELECT 1 FROM task WHERE username=? AND source_kelas_tugas_id=? LIMIT 1");
        $check->bind_param('si', $username, $taskId);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();

        if ($exists) {
            continue;
        }

        task_add(
            $username,
            $task['mata_kuliah'] ?? '',
            $task['jenis'] ?? '',
            $task['tanggal'] ?? '',
            $task['jam'] ?? null,
            $kelasId,
            $taskId
        );
    }
    $taskStmt->close();
    $conn->close();
    return true;
}

function class_share_schedule_set($kelasId, $createdBy, $sourceScheduleId) {
    if (!class_is_admin($createdBy, $kelasId)) {
        return ['ok' => false, 'message' => 'Hanya admin kelas yang bisa membagikan jadwal.'];
    }

    $classInfo = class_get_by_id($kelasId);
    if (!$classInfo) {
        return ['ok' => false, 'message' => 'Kelas tidak ditemukan.'];
    }

    $rows = get_schedule_rows($createdBy, $sourceScheduleId);
    if (empty($rows)) {
        return ['ok' => false, 'message' => 'Jadwal sumber kosong.'];
    }

    $scheduleIndex = load_schedule_index($createdBy);
    $item = find_schedule_item($scheduleIndex, $sourceScheduleId);
    $scheduleName = $item['name'] ?? 'Jadwal';

    $setKey = 'kelas' . $kelasId . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $setName = 'Kelas: ' . ($classInfo['nama'] ?? 'Kelas') . ' - ' . $scheduleName;

    $conn = db_connect();
    if (!class_has_set_key_column($conn)) {
        $alter = $conn->query("ALTER TABLE kelas_jadwal_set ADD COLUMN set_key VARCHAR(64) NOT NULL UNIQUE AFTER kelas_id");
        if (!$alter) {
            $message = $conn->error ? ('DB error: ' . $conn->error) : 'Kolom set_key belum ada di kelas_jadwal_set.';
            $conn->close();
            return ['ok' => false, 'message' => $message];
        }
    }
    $stmt = $conn->prepare("INSERT INTO kelas_jadwal_set (kelas_id, set_key, name, created_by) VALUES (?,?,?,?)");
    if (!$stmt) {
        $message = $conn->error ? ('DB error: ' . $conn->error) : 'Gagal menyiapkan jadwal kelas.';
        $conn->close();
        return ['ok' => false, 'message' => $message];
    }
    $stmt->bind_param('isss', $kelasId, $setKey, $setName, $createdBy);
    $ok = $stmt->execute();
    $setId = $conn->insert_id;
    $stmt->close();

    if (!$ok || !$setId) {
        $conn->close();
        return ['ok' => false, 'message' => 'Gagal membuat jadwal kelas.'];
    }

    $insertItem = $conn->prepare("
        INSERT INTO kelas_jadwal_item
        (kelas_jadwal_set_id, no_col, kode, nama_matakuliah, sks, kelas, pengampu, jenis, ruang, hari, jam_mulai, jam_selesai)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$insertItem) {
        $message = $conn->error ? ('DB error: ' . $conn->error) : 'Gagal menyimpan item jadwal kelas.';
        $conn->close();
        return ['ok' => false, 'message' => $message];
    }

    foreach ($rows as $row) {
        $no = $row['no_col'] ?? '';
        $kode = $row['kode'] ?? '';
        $nama = $row['nama_matakuliah'] ?? '';
        $sks = $row['sks'] ?? '';
        $kelas = $row['kelas'] ?? '';
        $pengampu = $row['pengampu'] ?? '';
        $jenis = $row['jenis'] ?? '';
        $ruang = $row['ruang'] ?? '';
        $hari = $row['hari'] ?? '';
        $jamMulai = $row['jam_mulai'] ?? '';
        $jamSelesai = $row['jam_selesai'] ?? '';
        $insertItem->bind_param('isssssssssss', $setId, $no, $kode, $nama, $sks, $kelas, $pengampu, $jenis, $ruang, $hari, $jamMulai, $jamSelesai);
        $insertItem->execute();
    }
    $insertItem->close();

    $members = class_member_usernames($kelasId);
    $items = $rows;
    foreach ($members as $member) {
        class_sync_schedule_set_to_member($member, $setKey, $setName, $items);
    }

    $conn->close();
    return ['ok' => true, 'set_key' => $setKey];
}

function class_add_task($kelasId, $createdBy, $mataKuliah, $jenis, $tanggal, $jam = null) {
    if (!class_is_admin($createdBy, $kelasId)) {
        return ['ok' => false, 'message' => 'Hanya admin kelas yang bisa menambah tugas.'];
    }

    $mataKuliah = trim((string)$mataKuliah);
    $jenis = trim((string)$jenis);
    $tanggal = trim((string)$tanggal);
    $jam = $jam !== null ? trim((string)$jam) : null;

    if ($mataKuliah === '' || $jenis === '' || $tanggal === '') {
        return ['ok' => false, 'message' => 'Data tugas tidak lengkap.'];
    }

    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO kelas_tugas (kelas_id, created_by, mata_kuliah, jenis, tanggal, jam) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('isssss', $kelasId, $createdBy, $mataKuliah, $jenis, $tanggal, $jam);
    $ok = $stmt->execute();
    $taskId = $conn->insert_id;
    $stmt->close();

    if (!$ok || !$taskId) {
        $conn->close();
        return ['ok' => false, 'message' => 'Gagal menambah tugas kelas.'];
    }
    $conn->close();

    $setKeys = class_get_schedule_set_keys($kelasId);
    if (!empty($setKeys)) {
        $placeholders = implode(',', array_fill(0, count($setKeys), '?'));
        $types = str_repeat('s', count($setKeys));
        $conn = db_connect();
        $sql = "SELECT DISTINCT username FROM schedule WHERE set_id IN ($placeholders) AND is_active=1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$setKeys);
        $stmt->execute();
        $res = $stmt->get_result();
        $activeMembers = [];
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['username'])) {
                $activeMembers[] = $row['username'];
            }
        }
        $stmt->close();
        $conn->close();

        foreach ($activeMembers as $member) {
            task_add($member, $mataKuliah, $jenis, $tanggal, $jam, $kelasId, $taskId);
        }
    }

    return ['ok' => true, 'task_id' => $taskId];
}
?>
