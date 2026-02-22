<?php
session_start();             // mulai session
session_unset();             // hapus semua variabel session
session_destroy();           // hancurkan session
header("Location: ../login/index.php"); // redirect ke halaman login
exit;
