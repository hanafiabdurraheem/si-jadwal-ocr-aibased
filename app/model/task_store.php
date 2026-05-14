<?php
require_once __DIR__ . '/../database/db.php';

function task_ensure_schema() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn = db_connect();
    if (!$conn) {
        return;
    }

    $res = $conn->query("SHOW COLUMNS FROM task LIKE 'link_pelaksanaan'");
    if (!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE task ADD COLUMN link_pelaksanaan VARCHAR(500) NULL AFTER jam");
    }
    if ($res) {
        $res->close();
    }
    $conn->close();
}

function task_add($username, $mataKuliah, $jenis, $tanggal, $jam = null, $linkPelaksanaan = null) {
    task_ensure_schema();
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO task (username, mata_kuliah, jenis, tanggal, jam, link_pelaksanaan, status) VALUES (?,?,?,?,?,?, 'Belum selesai')");
    $stmt->bind_param('ssssss', $username, $mataKuliah, $jenis, $tanggal, $jam, $linkPelaksanaan);
    $stmt->execute();
    $insertedId = (int)$conn->insert_id;
    $stmt->close();
    $conn->close();
    return $insertedId;
}

function task_list_by_status($username, $statusArray) {
    task_ensure_schema();
    $placeholders = implode(',', array_fill(0, count($statusArray), '?'));
    $types = str_repeat('s', count($statusArray) + 1);
    $conn = db_connect();
    $sql = "SELECT * FROM task WHERE username=? AND status IN ($placeholders) ORDER BY tanggal ASC, COALESCE(jam,'23:59:59') ASC";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$username], $statusArray);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = db_stmt_fetch_all_assoc($stmt);
    $stmt->close();
    $conn->close();
    return $rows;
}

function task_update_status($username, $id, $status) {
    task_ensure_schema();
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE task SET status=? WHERE id=? AND username=?");
    $stmt->bind_param('sis', $status, $id, $username);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function task_delete($username, $id) {
    task_ensure_schema();
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM task WHERE id=? AND username=?");
    $stmt->bind_param('is', $id, $username);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
