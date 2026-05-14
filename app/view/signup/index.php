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
        <div class="overlap-group signup-card">
          <div class="kotak-utama"></div>
          <div class="kotak-usernam"></div>
          <div class="kotak-pass"></div>
          <div class="button-login"></div>

          <div class="text-wrapper">Username Baru</div>
          <input type="text" name="username" class="user" placeholder="Username" required>

          <div class="text-wrapper-29">Password Baru</div>
          <input type="password" name="password" id="signup-password" class="password" placeholder="Password" required>
          <button type="button" class="password-toggle" data-target="signup-password" aria-label="Tampilkan password" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M1.5 12s3.8-6 10.5-6 10.5 6 10.5 6-3.8 6-10.5 6S1.5 12 1.5 12z"></path>
              <circle cx="12" cy="12" r="3.2"></circle>
            </svg>
          </button>

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
  <script>
    (function initPasswordToggle() {
      const toggles = document.querySelectorAll('.password-toggle');
      toggles.forEach((toggle) => {
        const targetId = toggle.getAttribute('data-target');
        const input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;

        toggle.addEventListener('click', () => {
          const visible = input.type === 'text';
          input.type = visible ? 'password' : 'text';
          toggle.classList.toggle('is-visible', !visible);
          toggle.setAttribute('aria-pressed', String(!visible));
          toggle.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
        });
      });
    })();
  </script>
</body>

</html>
