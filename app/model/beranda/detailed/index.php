<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/database/db.php';

function beranda_detailed_load(string $username): array
{
    $active = resolve_active_schedule_item($username);
    if (!$active) {
        return ['error' => '⚠️ Jadwal aktif tidak ditemukan.'];
    }

    $rowsDb = get_schedule_rows($username, $active['id']);
    if (empty($rowsDb)) {
        return ['error' => '⚠️ Jadwal kosong.'];
    }

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

    $fields = [
        ['label' => 'Nama Matakuliah', 'key' => 'nama_matakuliah', 'icon' => 'daring2.png'],
        ['label' => 'Hari', 'key' => 'hari', 'icon' => 'hari2.png'],
        ['label' => 'Jam Mulai', 'key' => 'jam_mulai', 'icon' => 'jam2.png'],
        ['label' => 'Jam Selesai', 'key' => 'jam_selesai', 'icon' => 'kosong2.png'],
        ['label' => 'Ruang', 'key' => 'ruang', 'icon' => 'kelas3.png'],
        ['label' => 'Pengampu', 'key' => 'pengampu', 'icon' => 'dosen3.png'],
    ];

    $filtered = array_filter($rowsDb, function ($row) use ($hariIndonesia) {
        return isset($row['hari']) && $row['hari'] === $hariIndonesia;
    });

    usort($filtered, function ($a, $b) {
        $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
        $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
        return $timeA <=> $timeB;
    });

    $now = strtotime(date('H:i'));
    $jadwalTerdekat = null;
    $jadwalSedangBerlangsung = null;

    foreach ($filtered as $row) {
        $jamMulai = strtotime(str_replace('.', ':', $row['jam_mulai'] ?? ''));
        $jamSelesai = strtotime(str_replace('.', ':', $row['jam_selesai'] ?? ''));

        if ($jamMulai !== false && $jamMulai >= $now && !$jadwalTerdekat) {
            $jadwalTerdekat = $row;
        }

        if ($jamMulai !== false && $jamSelesai !== false && $now >= $jamMulai && $now <= $jamSelesai) {
            $jadwalSedangBerlangsung = $row;
        }
    }

    $jadwalYangDitampilkan = $jadwalTerdekat ?? $jadwalSedangBerlangsung;

    return [
        'error' => null,
        'jadwalYangDitampilkan' => $jadwalYangDitampilkan,
        'fields' => $fields,
    ];
}
