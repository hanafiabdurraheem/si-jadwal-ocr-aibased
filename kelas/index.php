<?php
session_start();

// Check if user is logged in
if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <link rel="stylesheet" href="global.css" />
    <link rel="stylesheet" href="styleguide.css" />
    <link rel="stylesheet" href="style.css?v=<?= time() ?>" />
  </head>
  <body>
    <div class="kelas">
      <div class="div">
        <div class="text-wrapper">Kelas</div>
        <div class="overlap-group"><div class="ellipse"></div></div>
        <div class="overlap">
          <div class="text-wrapper-2">Ketua Kelas (upcoming update)</div>
        
        <div class="text-wrapper-3">Mengingatkan tugas pengajian</div>
        </div>
        <img class="line" src="../img/line.png" />
      </div>
    </div>
      <?php include '../nav.php'; ?>
    </div>
  </body>
</html>
