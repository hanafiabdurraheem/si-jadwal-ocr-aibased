<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accent = '#6552fe';

if (!empty($_SESSION['username'])) {
    require_once PROJECT_ROOT . '/app/model/pengaturan/index.php';
    $accent = pengaturan_get_user_preference((string)$_SESSION['username'], 'accent_color', $accent);
}
?>
<script>
  (function applyThemeAccent() {
    const color = <?php echo json_encode($accent); ?>;
    if (color) {
      document.documentElement.style.setProperty('--theme-accent', color);
    }
  })();
</script>
