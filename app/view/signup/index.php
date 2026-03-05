<!DOCTYPE html>
<html lang="id">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <title>Login</title>
  <link rel="stylesheet" href="app/view/login/global.css" />
  <link rel="stylesheet" href="app/view/login/styleguide.css" />
  <link rel="stylesheet" href="app/view/login/style.css?v=<?= time() ?>" />

</head>
<body>

  <header class="header">
    <div class="overlap-group">
        <a href="index.php?route=login" class="text-wrapper-2">Login</a>
        <div class="text-wrapper-3" onclick="document.getElementById('signup-section').scrollIntoView({ behavior: 'smooth' });">
          Sign up
        </div>
        <a href="index.php?route=login">
  <img class="sijadwal-logo" src="app/view/assets/img-ldg/sijadwal-logo.png" />
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
        <a href="index.php?route=login" class="sign-up">Login</a>
      </div>
    </div>

  </div>
</body>

</html>
