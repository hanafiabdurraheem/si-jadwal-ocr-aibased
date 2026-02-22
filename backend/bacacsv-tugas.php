<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'];
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'];
$filePath = __DIR__ . "/../uploads/$username/tugas.csv";

// Fungsi menghapus baris berdasarkan index
if (isset($_GET['hapus'])) {
    $hapusIndex = (int)$_GET['hapus'];
    if (file_exists($filePath)) {
        $rows = file($filePath, FILE_IGNORE_NEW_LINES);
        if (isset($rows[$hapusIndex])) {
            unset($rows[$hapusIndex]);
            file_put_contents($filePath, implode(PHP_EOL, $rows));
            header("Location: " . $_SERVER['PHP_SELF']); // refresh tanpa query
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Daftar Tugas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 30px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        th, td {
            text-align: left;
            padding: 12px 15px;
        }

        th {
            background-color: #4f46e5;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        tr:hover {
            background-color: #eef2ff;
        }

        .hapus-button {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
        }

        .hapus-button:hover {
            background-color: #dc2626;
        }


    </style>
</head>
<body>
    <h1>Daftar Tugas</h1>

    <?php
    if (file_exists($filePath)) {
        $file = fopen($filePath, 'r');
        echo "<table>";
        echo "<tr><th>Nama Tugas</th><th>Keterangan</th><th>Deadline</th><th>Selesai</th></tr>";
        $index = 0;
        while (($row = fgetcsv($file)) !== false) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "<td><a href='?hapus=$index'><button class='hapus-button'>Selesai</button></a></td>";
            echo "</tr>";
            $index++;
        }
        fclose($file);
        echo "</table>";
    } else {
        echo "<p>⚠️ File <code>tugas.csv</code> belum ada atau tidak ditemukan.</p>";
    }
    ?>

    
</body>
</html>
