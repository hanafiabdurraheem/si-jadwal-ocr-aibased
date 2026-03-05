<?php

require_once PROJECT_ROOT . '/app/database/db.php';

function signup_handle_submit(): array
{
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        return ['error' => ''];
    }

    $username = $_POST["username"];
    $passwordPlain = $_POST["password"];
    $agreeTerms = isset($_POST["agree_terms"]);

    if (!$agreeTerms) {
        return ['error' => 'Anda harus menyetujui syarat dan ketentuan.'];
    }

    $conn = db_connect();

    $check = $conn->prepare("SELECT username FROM `user` WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['message'] = "⚠️ Username sudah terdaftar. Silakan login.";
        header("Location: index.php?route=login");
        exit;
    }

    $passwordHashed = password_hash($passwordPlain, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO `user` (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $passwordHashed);

    if ($stmt->execute()) {
        $userFolder = PROJECT_ROOT . "/app/uploads/$username";
        if (!is_dir($userFolder)) {
            mkdir($userFolder, 0777, true);
        }
        $_SESSION['message'] = "✅ Registrasi berhasil. Silakan login.";
        header("Location: index.php?route=login");
        exit;
    }

    $_SESSION['message'] = "❌ Gagal menyimpan data.";
    header("Location: index.php?route=signup");
    exit;
}
