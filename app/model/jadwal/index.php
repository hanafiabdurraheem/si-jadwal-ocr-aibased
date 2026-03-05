<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';

function jadwal_load_view_data(string $username): array
{
    $scheduleIndex = load_schedule_index($username);
    $scheduleItems = $scheduleIndex['items'] ?? [];
    $activeScheduleId = $scheduleIndex['active_id'] ?? null;

    usort($scheduleItems, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return [
        'scheduleItems' => $scheduleItems,
        'activeScheduleId' => $activeScheduleId,
    ];
}
