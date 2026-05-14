<?php

require_once PROJECT_ROOT . '/app/database/db.php';

function login_attempt(string $username, string $password): array
{
    $conn = db_connect();

    $stmt = $conn->prepare("SELECT password FROM `user` WHERE username = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }

    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($hashedPassword);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            $stmt->close();
            $conn->close();
            return ['ok' => true, 'error' => null];
        }

        $stmt->close();
        $conn->close();
        return ['ok' => false, 'error' => 'Password salah.'];
    }

    $stmt->close();
    $conn->close();
    return ['ok' => false, 'error' => 'Username tidak ditemukan.'];
}
