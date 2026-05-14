<?php

function jadwalview_load(): array
{
    $username = $_SESSION['username'] ?? 'hana';
    $filePath = PROJECT_ROOT . "/app/uploads/$username/Kartu-Rencana-Studi_Aktif.csv";

    if (!file_exists($filePath)) {
        return ['error' => "File tidak ditemukan: " . $filePath];
    }

    $daysOrder = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
    $jadwal = [];
    $header = [];

    if (($handle = fopen($filePath, "r")) !== false) {
        $header = fgetcsv($handle, 1000, ",");
        $header = array_map(function($h) {
            return trim($h);
        }, $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $row = array_combine($header, $data);
            $hari = trim($row['Hari'] ?? '');
            if (!empty($hari)) {
                $jadwal[$hari][] = $row;
            }
        }
        fclose($handle);
    }

    $currentIndex = 0;
    if (isset($_GET['day']) && in_array($_GET['day'], $daysOrder)) {
        $currentIndex = array_search($_GET['day'], $daysOrder);
    }

    $currentDay = $daysOrder[$currentIndex];
    $prevIndex = ($currentIndex - 1 + count($daysOrder)) % count($daysOrder);
    $nextIndex = ($currentIndex + 1) % count($daysOrder);

    return [
        'error' => null,
        'header' => $header,
        'jadwal' => $jadwal,
        'daysOrder' => $daysOrder,
        'currentDay' => $currentDay,
        'prevDay' => $daysOrder[$prevIndex],
        'nextDay' => $daysOrder[$nextIndex],
    ];
}
