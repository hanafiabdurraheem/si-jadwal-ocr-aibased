<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';

function pengaturan_load_schedule_items(string $username): array
{
    $scheduleIndex = load_schedule_index($username);
    $scheduleItems = $scheduleIndex['items'] ?? [];

    usort($scheduleItems, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    return $scheduleItems;
}

function pengaturan_user_dir(string $username): string
{
    return PROJECT_ROOT . '/app/uploads/' . $username;
}
