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
    <?php
      $alertType = in_array($flashType ?? 'success', ['success', 'warning', 'error'], true) ? $flashType : 'success';
      $alertTitle = 'Informasi';
      if ($alertType === 'success') {
          $alertTitle = 'Berhasil';
      } elseif ($alertType === 'warning') {
          $alertTitle = 'Perhatian';
      } elseif ($alertType === 'error') {
          $alertTitle = 'Terjadi Kendala';
      }

      $alertIcon = '✓';
      if ($alertType === 'warning') {
          $alertIcon = '!';
      } elseif ($alertType === 'error') {
          $alertIcon = '×';
      }

      $alertBody = preg_replace('/^[^\p{L}\p{N}]+/u', '', (string)$flashMessage);
    ?>
    <div class="login-popup-backdrop" id="loginPopupBackdrop" role="dialog" aria-modal="true" aria-label="Notifikasi">
      <div class="login-popup login-alert-<?= htmlspecialchars($alertType) ?>" id="loginPopupCard">
        <button type="button" class="login-popup-close" id="loginPopupClose" aria-label="Tutup notifikasi">×</button>
        <div class="login-alert-icon"><?= htmlspecialchars($alertIcon) ?></div>
        <div class="login-alert-content">
          <div class="login-alert-title"><?= htmlspecialchars($alertTitle) ?></div>
          <p><?= htmlspecialchars($alertBody) ?></p>
        </div>
      </div>
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
          <input type="password" name="password" id="login-password" class="password" placeholder="Password" required>
          <button type="button" class="password-toggle" data-target="login-password" aria-label="Tampilkan password" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M1.5 12s3.8-6 10.5-6 10.5 6 10.5 6-3.8 6-10.5 6S1.5 12 1.5 12z"></path>
              <circle cx="12" cy="12" r="3.2"></circle>
            </svg>
          </button>

          <button type="submit" class="text-wrapper-3">Login</button>
        </div>
      </form>
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
  <script>
    (function initFlashPopup() {
      const backdrop = document.getElementById('loginPopupBackdrop');
      const card = document.getElementById('loginPopupCard');
      const closeButton = document.getElementById('loginPopupClose');
      if (!backdrop || !card || !closeButton) return;

      function closePopup() {
        backdrop.classList.add('is-hidden');
      }

      closeButton.addEventListener('click', closePopup);
      backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) closePopup();
      });
    })();

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
