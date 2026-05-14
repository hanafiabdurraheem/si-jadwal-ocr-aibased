<?php
function db_connect() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'si-jadwal_db';

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

function db_stmt_fetch_all_assoc($stmt) {
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}
