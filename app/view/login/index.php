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

  <?php if (!empty($flashMessage)) : ?>
    <div style="color: green; background: #e0ffe0; padding: 10px; margin: 10px 20px; border-radius: 5px;">
      <?= htmlspecialchars($flashMessage) ?>
    </div>
  <?php endif; ?>

  <header class="header">
    <div class="overlap-group">
        <a href="index.php" class="text-wrapper-2">Login</a>
        <a href="index.php?route=signup" class="text-wrapper-3">Sign up</a>
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

          <div class="text-wrapper">Username</div>
          <input type="text" name="username" class="user" placeholder="Username" required>

          <div class="text-wrapper-29">Password</div>
          <input type="password" name="password" class="password" placeholder="Password" required>

          <button type="submit" class="text-wrapper-3">Login</button>
        </div>
      </form>
    <button type="submit" class="text-wrapper-3"></button>
      <?php if (!empty($error)) : ?>
        <div class="error-message" style="color: red; text-align: center; margin-top: 10px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="text-wrapper-4">Selamat Datang</div>
      <div class="text-wrapper-5">Silahkan login!</div>
      <div class="signup-wrapper">
        <span class="text-wrapper-6">Belum punya akun? </span>
        <a href="index.php?route=signup" class="sign-up">Sign Up</a>
      </div>


    </div>


  </div>
</body>
</html>
