<?php
require_once __DIR__ . '/db.php';

function schedule_map_row($row) {
    return [
        'id' => $row['set_id'],
        'name' => $row['name'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'is_active' => (int)$row['is_active']
    ];
}

function load_schedule_index($username) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT set_id, name, MAX(created_at) as created_at, MAX(updated_at) as updated_at, MAX(is_active) as is_active FROM schedule WHERE username=? GROUP BY set_id, name ORDER BY is_active DESC, created_at DESC");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = schedule_map_row($row);
    }
    $stmt->close();
    $conn->close();

    $activeId = null;
    foreach ($items as $item) {
        if ($item['is_active']) {
            $activeId = $item['id'];
            break;
        }
    }
    if (!$activeId && !empty($items)) {
        $activeId = $items[0]['id'];
    }

    return [
        'active_id' => $activeId,
        'items' => $items
    ];
}

function find_schedule_item($index, $scheduleId) {
    foreach ($index['items'] as $item) {
        if ($item['id'] === $scheduleId) return $item;
    }
    return null;
}

function set_active_schedule_id($username, $scheduleId) {
    $conn = db_connect();
    $conn->begin_transaction();
    $stmt = $conn->prepare("UPDATE schedule SET is_active=0 WHERE username=?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE schedule SET is_active=1 WHERE username=? AND set_id=?");
    $stmt->bind_param('ss', $username, $scheduleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();
    $conn->close();

    if ($affected <= 0) return null;
    return ['id' => $scheduleId];
}

function resolve_active_schedule_item($username) {
    $index = load_schedule_index($username);
    if ($index['active_id']) {
        return find_schedule_item($index, $index['active_id']);
    }
    return null;
}

function set_active_schedule_session($username, $item) {
    if (!$item) return;
    $_SESSION['active_schedule_id'] = $item['id'];
}

function add_schedule_set($username, $setId, $name, $rows, $makeActive = true) {
    $conn = db_connect();
    $conn->begin_transaction();

    if ($makeActive) {
        $stmt = $conn->prepare("UPDATE schedule SET is_active=0 WHERE username=?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("INSERT INTO schedule (username, set_id, name, is_active, no_col, kode, nama_matakuliah, sks, kelas, pengampu, jenis, ruang, hari, jam_mulai, jam_selesai) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($rows as $row) {
        $no = $row['No'] ?? $row['no'] ?? '';
        $kode = $row['Kode'] ?? '';
        $nama = $row['Nama Matakuliah'] ?? '';
        $sks = $row['SKS'] ?? '';
        $kelas = $row['Kelas/Rombel'] ?? $row['Kelas'] ?? '';
        $pengampu = $row['Pengampu'] ?? '';
        $jenis = $row['Jenis'] ?? '';
        $ruang = $row['Ruang'] ?? '';
        $hari = $row['Hari'] ?? '';
        $jamMulai = $row['Jam Mulai'] ?? '';
        $jamSelesai = $row['Jam Selesai'] ?? '';
        $active = $makeActive ? 1 : 0;
        $stmt->bind_param('sssssssssssssss', $username, $setId, $name, $active, $no, $kode, $nama, $sks, $kelas, $pengampu, $jenis, $ruang, $hari, $jamMulai, $jamSelesai);
        $stmt->execute();
    }
    $stmt->close();
    $conn->commit();
    $conn->close();
}

function replace_schedule_set($username, $setId, $name, $rows, $makeActive = false) {
    $conn = db_connect();
    $conn->begin_transaction();
    $del = $conn->prepare("DELETE FROM schedule WHERE username=? AND set_id=?");
    $del->bind_param('ss', $username, $setId);
    $del->execute();
    $del->close();
    $conn->commit();
    $conn->close();
    add_schedule_set($username, $setId, $name, $rows, $makeActive);
}

function get_schedule_rows($username, $setId) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id, set_id, name, is_active, no_col, kode, nama_matakuliah, sks, kelas, pengampu, jenis, ruang, hari, jam_mulai, jam_selesai FROM schedule WHERE username=? AND set_id=? ORDER BY id ASC");
    $stmt->bind_param('ss', $username, $setId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
    $conn->close();
    return $rows;
}

function delete_schedule_set($username, $setId) {
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM schedule WHERE username=? AND set_id=?");
    $stmt->bind_param('ss', $username, $setId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    return $affected > 0;
}
?>
