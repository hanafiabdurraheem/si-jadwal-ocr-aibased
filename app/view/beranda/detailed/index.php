<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <link rel="stylesheet" href="app/view/beranda/detailed/global.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="app/view/beranda/detailed/styleguide.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="app/view/beranda/detailed/style.css?v=<?= time() ?>" />
  <script>
    (function applyThemeAccent() {
      const key = 'si-jadwal-accent-color';
      const saved = localStorage.getItem(key);
      if (saved) {
        document.documentElement.style.setProperty('--theme-accent', saved);
      }
    })();
  </script>
</head>

<style>
.jadwal-container {
    background-image: url(app/view/assets/img/mesh-gradient-1.png);
    background-color: var(--theme-accent, #6552fe);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-blend-mode: overlay; 

    color: white;
    padding: 10px;
    border-radius: 12px;
    max-width: 500px;
    margin: 0px auto;
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    gap: 10px;

    box-shadow: 0 0 12px rgba(0, 0, 0, 0.2);
}

.jadwal-container,
.jadwal-container * {
    text-shadow: 0 2px 6px rgba(0, 0, 0, 0.45);
}

.jadwal-value-only {
    background-color: transparent;
    padding: 10px;
    border-radius: 8px;
    text-align: left;
    padding-left: 65px;
    font-size: 16px;
    backdrop-filter: blur(4px);
}

.jadwal-foto {
    position: absolute;
    top: 100px;
    left: 40px;
    width: 30px;
    height: 30px;
    object-fit: cover;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
    z-index: 2;
}

.jadwal-value-only {
    background-color: transparent;
    padding: 10px;
    border-radius: 8px;
    text-align: left;
    padding-left: 20px;
    font-size: 16px;
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    gap: 12px;
}

.jadwal-icon {
    width: 24px;
    height: 24px;
    object-fit: contain;
}
</style>

<body style="margin: 0; padding: 0; background: transparent;">
  <a href="index.php?route=beranda" style="display: block; width: 100%; height: 250px; text-decoration: none; color: inherit;">
    <div class="detailed-upcoming">
      <div class="overlap-wrapper">
        
        <?php
        echo "<h2></h2>";
        if (!$jadwalYangDitampilkan) {
            echo "
            <div class='jadwal-container no-jadwal'>
              <p class='no-jadwal-text'>🎉 Horeee, tidak ada jadwal lagi hari ini!</p>
            </div>";
        } else {
            echo "<div class='jadwal-container'>";
            foreach ($fields as $field) {
                $iconPath = 'app/view/beranda/detailed/img/' . $field['icon'];
                $value = htmlspecialchars($jadwalYangDitampilkan[$field['key']] ?? '-');
                $label = htmlspecialchars($field['label']);
                echo "<div class='jadwal-value-only'>
                        <img src='$iconPath' class='jadwal-icon' alt='{$label} icon' />
                        $value
                      </div>";
            }

            echo "</div>";
        }
        ?>
      </div>
    </div>
  </a>
</body>
</html>
