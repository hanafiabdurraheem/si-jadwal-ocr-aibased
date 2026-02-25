<?php
require_once __DIR__ . '/db.php';

function task_add($username, $mataKuliah, $jenis, $tanggal, $jam = null) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO task (username, mata_kuliah, jenis, tanggal, jam, status) VALUES (?,?,?,?,?, 'Belum selesai')");
    $stmt->bind_param('sssss', $username, $mataKuliah, $jenis, $tanggal, $jam);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function task_list_by_status($username, $statusArray) {
    $placeholders = implode(',', array_fill(0, count($statusArray), '?'));
    $types = str_repeat('s', count($statusArray) + 1);
    $conn = db_connect();
    $sql = "SELECT * FROM task WHERE username=? AND status IN ($placeholders) ORDER BY tanggal ASC, COALESCE(jam,'23:59:59') ASC";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$username], $statusArray);
    $stmt->bind_param($types, ...$params);
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

function task_update_status($username, $id, $status) {
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE task SET status=? WHERE id=? AND username=?");
    $stmt->bind_param('sis', $status, $id, $username);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function task_delete($username, $id) {
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM task WHERE id=? AND username=?");
    $stmt->bind_param('is', $id, $username);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
