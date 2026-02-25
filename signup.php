<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$error = '';
require_once __DIR__ . '/backend/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $passwordPlain = $_POST["password"];
    $agreeTerms = isset($_POST["agree_terms"]);

    if (!$agreeTerms) {
        $error = "Anda harus menyetujui syarat dan ketentuan.";
    }

    if (empty($error)) {
        $conn = db_connect();

        $check = $conn->prepare("SELECT username FROM `user` WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $_SESSION['message'] = "⚠️ Username sudah terdaftar. Silakan login.";
            header("Location: login/index.php");
            exit;
        }

        $passwordHashed = password_hash($passwordPlain, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO `user` (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $passwordHashed);

        if ($stmt->execute()) {
            $userFolder = __DIR__ . "/uploads/$username";
            if (!is_dir($userFolder)) {
                mkdir($userFolder, 0777, true);
            }
            $_SESSION['message'] = "✅ Registrasi berhasil. Silakan login.";
            header("Location: login/index.php");
            exit;
        } else {
            $_SESSION['message'] = "❌ Gagal menyimpan data.";
            header("Location: register.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <title>Login</title>
  <link rel="stylesheet" href="login/global.css" />
  <link rel="stylesheet" href="login/styleguide.css" />
  <link rel="stylesheet" href="style.css?v=<?= time() ?>" />

</head>
<body>

  <header class="header">
    <div class="overlap-group">
        <a href="login/index.php" class="text-wrapper-2">Login</a>
        <div class="text-wrapper-3" onclick="document.getElementById('signup-section').scrollIntoView({ behavior: 'smooth' });">
          Sign up
        </div>
        <a href="index.php">
  <img class="sijadwal-logo" src="img-ldg/sijadwal-logo.png" />
</a>

      </div>
    </div>
  </header>

  <div class="login">
    <div class="div">

      <form method="POST" action="">
        <div class="overlap-group">
          <div class="kotak-utama"></div>
          <div class="kotak-usernam"></div>
          <div class="kotak-pass"></div>
          <div class="button-login"></div>

          <div class="text-wrapper">Username Baru</div>
          <input type="text" name="username" class="user" placeholder="Username" required>

          <div class="text-wrapper-29">Password Baru</div>
          <input type="password" name="password" class="password" placeholder="Password" required>

          <label class="terms">
            <input type="checkbox" name="agree_terms" required>
            Saya menyetujui syarat dan ketentuan
          </label>

          <button type="submit" class="text-wrapper-3">Sign Up</button>
        </div>
      </form>

      <?php if (!empty($error)) : ?>
        <div class="error-message" style="color: red; text-align: center; margin-top: 10px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div id="signup-section" class="text-wrapper-4">Form Registrasi</div>
      <div class="text-wrapper-5">Silahkan Daftar!</div>
      <div class="text-wrapper-6">Sudah punya akun?</div>
      <div class="signup">
        <a href="login/index.php" class="sign-up">Login</a>
      </div>
    </div>

  </div>
</body>

</html>
