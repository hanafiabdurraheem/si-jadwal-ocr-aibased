<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accent = '#6552fe';

if (!empty($_SESSION['username'])) {
    require_once PROJECT_ROOT . '/app/database/db.php';
    $conn = db_connect();
    if ($conn) {
        $stmt = $conn->prepare("SELECT theme_color FROM user_preference WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $_SESSION['username']);
            if ($stmt->execute()) {
                $stmt->bind_result($value);
                if ($stmt->fetch()) {
                    $value = (string)$value;
                    $legacyMap = [
                        'ungu' => '#6552fe',
                        'kuning' => '#f59e0b',
                        'biru' => '#0ea5e9',
                        'hijau' => '#10b981',
                        'magenta' => '#f43f5e'
                    ];
                    $lower = strtolower(trim($value));
                    if ($lower !== '' && $lower[0] !== '#' && isset($legacyMap[$lower])) {
                        $accent = $legacyMap[$lower];
                    } elseif ($value !== '') {
                        $accent = $value;
                    }
                }
            }
            $stmt->close();
        }
        $conn->close();
    }
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
