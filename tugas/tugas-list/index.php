<?php
session_start();

// Check if user is logged in
if (empty($_SESSION['username'])) {
    header("Location: ../../login/index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
  
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style.css" />


  </head>

  <body>
    <div class="table-wrapper">
    <?php include '../../backend/bacacsv-tugas.php'; 
    ?>
    </div>

        <a href="../../beranda/index.php" class="kembali-button">← Kembali</a>
        <a href="../../tugas/index.php" class="tambah-button">+ Tambah</a>
    
    
  </body>
</html>
