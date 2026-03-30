<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

require_once PROJECT_ROOT . '/app/database/db.php';
require_once APP_ROOT . '/model/pengaturan/index.php';
require_once PROJECT_ROOT . '/google-calendar-sync/functions.php';

$messages = [];
$errors = [];
$successRedirect = false;
$tab = $_GET['tab'] ?? 'jadwal';
if (!in_array($tab, ['jadwal', 'akun', 'tampilan'], true)) {
    $tab = 'jadwal';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = $_SESSION['username'];

    if ($action === 'update_username') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';

        if ($newUsername === '' || $currentPassword === '') {
            $errors[] = 'Username baru dan password saat ini wajib diisi.';
        } else if ($newUsername === $username) {
            $errors[] = 'Username baru tidak boleh sama dengan username lama.';
        } else {
            $conn = db_connect();
            if (!$conn) {
                $errors[] = 'Gagal koneksi ke database.';
            } else {
                $check = $conn->prepare("SELECT username FROM user WHERE username = ?");
                $check->bind_param("s", $newUsername);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $errors[] = 'Username baru sudah digunakan.';
                } else {
                    $stmt = $conn->prepare("SELECT password FROM user WHERE username = ?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->bind_result($hashedPassword);

                    if ($stmt->fetch() && password_verify($currentPassword, $hashedPassword)) {
                        $stmt->close();
                        $update = $conn->prepare("UPDATE user SET username = ? WHERE username = ?");
                        $update->bind_param("ss", $newUsername, $username);
                        if ($update->execute()) {
                            $oldDir = PROJECT_ROOT . "/app/uploads/$username";
                            $newDir = PROJECT_ROOT . "/app/uploads/$newUsername";

                            if (is_dir($oldDir)) {
                                if (is_dir($newDir)) {
                                    $errors[] = 'Folder user baru sudah ada. Perubahan dibatalkan.';
                                    $rollback = $conn->prepare("UPDATE user SET username = ? WHERE username = ?");
                                    $rollback->bind_param("ss", $username, $newUsername);
                                    $rollback->execute();
                                } else if (!rename($oldDir, $newDir)) {
                                    $errors[] = 'Gagal memindahkan folder user. Perubahan dibatalkan.';
                                    $rollback = $conn->prepare("UPDATE user SET username = ? WHERE username = ?");
                                    $rollback->bind_param("ss", $username, $newUsername);
                                    $rollback->execute();
                                } else {
                                    $_SESSION['username'] = $newUsername;
                                    unset($_SESSION['active_schedule_id'], $_SESSION['active_schedule_csv'], $_SESSION['active_schedule_json']);
                                    $messages[] = 'Username berhasil diperbarui.';
                                    $successRedirect = true;
                                }
                            } else {
                                $_SESSION['username'] = $newUsername;
                                unset($_SESSION['active_schedule_id'], $_SESSION['active_schedule_csv'], $_SESSION['active_schedule_json']);
                                $messages[] = 'Username berhasil diperbarui.';
                                $successRedirect = true;
                            }
                        } else {
                            $errors[] = 'Gagal memperbarui username.';
                        }
                        $update->close();
                    } else {
                        $errors[] = 'Password saat ini tidak valid.';
                    }
                    $stmt->close();
                }
                $check->close();
                $conn->close();
            }
        }
    }

    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'Semua field password wajib diisi.';
        } else if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } else {
            $conn = db_connect();
            if (!$conn) {
                $errors[] = 'Gagal koneksi ke database.';
            } else {
                $stmt = $conn->prepare("SELECT password FROM user WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->bind_result($hashedPassword);

                if ($stmt->fetch() && password_verify($currentPassword, $hashedPassword)) {
                    $stmt->close();
                    $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE user SET password = ? WHERE username = ?");
                    $update->bind_param("ss", $newHashed, $username);
                    if ($update->execute()) {
                        $messages[] = 'Password berhasil diperbarui.';
                        $successRedirect = true;
                    } else {
                        $errors[] = 'Gagal memperbarui password.';
                    }
                    $update->close();
                } else {
                    $errors[] = 'Password saat ini tidak valid.';
                }
                $stmt->close();
                $conn->close();
            }
        }
    }

    if ($action === 'update_preference') {
        $value = $_POST['preference_value'] ?? '';

        if ($value === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Value preferensi wajib diisi.']);
            exit();
        } else {
            $result = pengaturan_set_user_preference($username, 'accent_color', $value);
            if ($result['success']) {
                echo json_encode(['ok' => true]);
                exit();
            } else {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => $result['error']]);
                exit();
            }
        }
    }
}

if ($successRedirect && empty($errors)) {
    if (empty($_SESSION['username'])) {
        header("Location: index.php?route=login");
        exit();
    }
    header("Location: index.php?route=pengaturan&tab=akun&notice=success");
    exit();
}

$username = $_SESSION['username'];

if (isset($_GET['notice']) && $_GET['notice'] === 'success') {
    $messages[] = 'Perubahan berhasil disimpan.';
}

$googleFlash = pullFlash();
if ($googleFlash) {
    $flashType = $googleFlash['type'] ?? 'info';
    $flashMessage = $googleFlash['message'] ?? '';
    if (in_array($flashType, ['error'], true)) {
        $errors[] = $flashMessage;
    } else {
        $messages[] = $flashMessage;
    }
}

$scheduleItems = pengaturan_load_schedule_items($username);
$userDir = pengaturan_user_dir($username);
$userPreferences = [
    'accent_color' => pengaturan_get_user_preference($username, 'accent_color', '#6552fe')
];
$googleCsrfToken = ensureCsrfToken();
$googleUserId = currentUserId();
$googleCalendarConnected = false;
if ($googleUserId) {
    $googleCalendarConnected = (bool)getTokenRow($googleUserId);
}

require APP_ROOT . '/view/pengaturan/index.php';
