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
    <div class="pengaturan">
      <div class="div">
        <div class="overlap-group">
          <div class="text-wrapper">Tampilan (upcoming update)</div>
          <div class="interface-weather"><img class="group" src="../img/interface-weather-sun--photos-light-camera-mode-brightness-sun-photo-full--Streamline-Core.png" /></div>
        </div>
        <div class="overlap">
          <div class="interface-file"><img class="img" src="../img/interface-file-multiple--double-common-file--Streamline-Core.png" /></div>
          <div class="text-wrapper-2">Jadwal (upcoming update)</div>
        </div>
        <div class="overlap-2">
          <div class="group-wrapper"><img class="img" src="../img/interface-file-clipboard-add--edit-task-edition-add-clipboard-form--Streamline-Core.png" /></div>
          <div class="text-wrapper-3">Tugas (upcoming update)</div>
        </div>
        <div class="overlap-3">
          <div class="interface-time-alarm"><img class="group-2" src="../img/interface-time-alarm--notification-alert-bell-wake-clock-alarm--Streamline-Core.png" /></div>
          <div class="text-wrapper-4">Alarm (upcoming update)</div>
        </div>
        
        </div>
        <div class="text-wrapper-6">Pengaturan</div>
        <img class="line" src="../img/line.png" />
        
        <a href="../backend/logout.php" class="tambah">
  <div class="tambah-2">Logout</div>
        </div>
      <?php include '../nav.php'; ?>
    </div>


  </body>
</html>
