<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';

function beranda_render_jadwal_hari_ini(string $username): string
{
    ob_start();

    date_default_timezone_set('Asia/Jakarta');
    $hariIni = date('l');

    $mapHari = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];

    $hariIndonesia = $mapHari[$hariIni] ?? '';

    $active = resolve_active_schedule_item($username);
    if (!$active) {
        echo "⚠️ Jadwal aktif tidak ditemukan.";
        return ob_get_clean();
    }

    $rows = get_schedule_rows($username, $active['id']);
    if (empty($rows)) {
        echo "⚠️ Jadwal kosong.";
        return ob_get_clean();
    }

    $kolomYangDitampilkan = [
        "Nama Matakuliah" => "nama_matakuliah",
        "Jam Mulai" => "jam_mulai",
        "Jam Selesai" => "jam_selesai",
        "Ruang" => "ruang"
    ];

    $filtered = array_filter($rows, function ($row) use ($hariIndonesia) {
        return isset($row['hari']) && trim($row['hari']) === $hariIndonesia;
    });

    usort($filtered, function ($a, $b) {
        $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
        $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
        return $timeA <=> $timeB;
    });

    echo "<h2></h2>";
    if (empty($filtered)) {
        echo "<p class='teks-putih'>Selamat, tidak ada jadwal hari ini, anda bisa turu seharian.</p>";
    } else {
        echo "<table border='1' cellpadding='6' cellspacing='0'>";
        echo "<tr>";
        foreach ($kolomYangDitampilkan as $namaKolom => $key) {
            echo "<th>" . htmlspecialchars($namaKolom) . "</th>";
        }
        echo "</tr>";

        foreach ($filtered as $row) {
            echo "<tr>";
            foreach ($kolomYangDitampilkan as $key) {
                echo "<td>" . htmlspecialchars($row[$key] ?? '-') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "\n\n<style> .teks-putih {\n  color: white;\n  padding-left: 12px;\n}\n</style>\n";

    return ob_get_clean();
}
