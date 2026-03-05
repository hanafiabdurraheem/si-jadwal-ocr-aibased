<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/database/db.php';

function jadwal_confirm_edit_load(string $username): array
{
    $daysOrder = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
    $extraDay = "Tanpa Hari";

    $activeItem = resolve_active_schedule_item($username);
    if (!$activeItem) {
        return ['error' => 'File jadwal tidak ditemukan.'];
    }

    $header = ["No","Kode","Nama Matakuliah","SKS","Kelas/Rombel","Pengampu","Jenis","Ruang","Hari","Jam Mulai","Jam Selesai"];
    $rowsAssoc = [];
    $jadwal = [];
    foreach ($daysOrder as $day) {
        $jadwal[$day] = [];
    }
    $jadwal[$extraDay] = [];

    $dbRows = get_schedule_rows($username, $activeItem['id']);
    $idx = 0;
    foreach ($dbRows as $r) {
        $rowAssoc = [
            "No" => $r['no_col'] ?? '',
            "Kode" => $r['kode'] ?? '',
            "Nama Matakuliah" => $r['nama_matakuliah'] ?? '',
            "SKS" => $r['sks'] ?? '',
            "Kelas/Rombel" => $r['kelas'] ?? '',
            "Pengampu" => $r['pengampu'] ?? '',
            "Jenis" => $r['jenis'] ?? '',
            "Ruang" => $r['ruang'] ?? '',
            "Hari" => $r['hari'] ?? '',
            "Jam Mulai" => $r['jam_mulai'] ?? '',
            "Jam Selesai" => $r['jam_selesai'] ?? '',
            "_index" => $idx++
        ];
        $dayValue = trim($rowAssoc['Hari']);
        if (!in_array($dayValue, $daysOrder, true)) {
            $dayValue = $extraDay;
        }
        $rowsAssoc[] = $rowAssoc;
        $jadwal[$dayValue][] = $rowAssoc;
    }

    return [
        'error' => null,
        'activeItem' => $activeItem,
        'header' => $header,
        'rowsAssoc' => $rowsAssoc,
        'jadwal' => $jadwal,
        'daysOrder' => $daysOrder,
        'extraDay' => $extraDay,
    ];
}
