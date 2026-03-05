<?php

require_once PROJECT_ROOT . '/app/model/task_store.php';

function kelas_load_tasks(string $username): array
{
    date_default_timezone_set('Asia/Jakarta');
    $mapHari = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu'
    ];

    $tasksActive = task_list_by_status($username, ['Belum selesai']);
    $tasksHistory = task_list_by_status($username, ['Selesai']);
    $tasksArchive = task_list_by_status($username, ['Arsip']);

    $now = time();
    $augment = function (&$tasks) use ($mapHari, $now) {
        foreach ($tasks as &$task) {
            $task['mataKuliah'] = $task['mata_kuliah'] ?? '';
            $deadlineStr = ($task['tanggal'] ?? '') . ' ' . (($task['jam'] ?? '') !== null ? $task['jam'] : '23:59:59');
            $timestamp = strtotime($deadlineStr);
            $task['timestamp'] = $timestamp ?: 0;
            $dayName = $timestamp ? date('l', $timestamp) : '';
            $task['hari'] = $mapHari[$dayName] ?? $dayName;
            $task['jam_display'] = ($task['jam'] && $task['jam'] !== '00:00:00') ? substr($task['jam'], 0, 5) : '';
            $task['overdue'] = $timestamp && $timestamp < $now;
        }
        unset($task);
    };

    $augment($tasksActive);
    $augment($tasksHistory);
    $augment($tasksArchive);

    $sortFn = function ($a, $b) {
        return $a['timestamp'] <=> $b['timestamp'];
    };

    usort($tasksActive, $sortFn);
    usort($tasksHistory, $sortFn);
    usort($tasksArchive, $sortFn);

    return [
        'tasksActive' => $tasksActive,
        'tasksHistory' => $tasksHistory,
        'tasksArchive' => $tasksArchive,
    ];
}
