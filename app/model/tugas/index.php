<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/model/task_store.php';

function tugas_get_mata_kuliah_list(string $username): array
{
    $scheduleActive = resolve_active_schedule_item($username);
    $rowsMatkul = $scheduleActive ? get_schedule_rows($username, $scheduleActive['id']) : [];
    $mataKuliahList = [];
    foreach ($rowsMatkul as $row) {
        if (!empty($row['nama_matakuliah'])) {
            $mataKuliahList[] = trim($row['nama_matakuliah']);
        }
    }
    $mataKuliahList = array_values(array_unique($mataKuliahList));
    sort($mataKuliahList);

    return $mataKuliahList;
}

function tugas_handle_submit(string $username): ?string
{
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return null;
    }

    $mataKuliah = $_POST['mata_kuliah'] ?? '';
    $jenisKegiatan = $_POST['jenis_kegiatan'] ?? '';
    $deadline = $_POST['status_tugas'] ?? '';

    if ($mataKuliah && $jenisKegiatan && $deadline) {
        task_add($username, $mataKuliah, $jenisKegiatan, $deadline, null);
        header("Location: index.php?route=pengingat&notice=task_added");
        exit;
    }

    return "⚠️ Semua field harus diisi.";
}
