<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function schedule_user_dir($username) {
    return __DIR__ . '/../uploads/' . $username;
}

function schedule_index_path($username) {
    return schedule_user_dir($username) . '/schedules.json';
}

function read_json_file($path) {
    if (!file_exists($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function write_json_file($path, $data) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

function format_timestamp_from_id($id, $fallbackTime) {
    if (preg_match('/^(\\d{8})_(\\d{6})/', $id, $matches)) {
        $dt = DateTime::createFromFormat('Ymd_His', $matches[1] . '_' . $matches[2]);
        if ($dt) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return date('Y-m-d H:i:s', $fallbackTime);
}

function build_schedule_index($username) {
    $userDir = schedule_user_dir($username);
    $items = [];

    if (is_dir($userDir)) {
        $csvFiles = glob($userDir . '/*/result.csv');
        foreach ($csvFiles as $csvPath) {
            $folder = basename(dirname($csvPath));
            $jsonPath = $userDir . '/' . $folder . '/result.json';
            $createdAt = format_timestamp_from_id($folder, filemtime($csvPath));
            $items[] = [
                'id' => $folder,
                'name' => 'Jadwal ' . $folder,
                'csv' => $folder . '/result.csv',
                'json' => file_exists($jsonPath) ? $folder . '/result.json' : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'source' => null
            ];
        }

        if (empty($items)) {
            $legacyCsv = $userDir . '/Kartu-Rencana-Studi_Aktif.csv';
            if (file_exists($legacyCsv)) {
                $createdAt = date('Y-m-d H:i:s', filemtime($legacyCsv));
                $items[] = [
                    'id' => 'legacy',
                    'name' => 'Jadwal Aktif',
                    'csv' => 'Kartu-Rencana-Studi_Aktif.csv',
                    'json' => file_exists($userDir . '/Kartu-Rencana-Studi_Aktif.json') ? 'Kartu-Rencana-Studi_Aktif.json' : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'source' => null
                ];
            }
        }
    }

    usort($items, function ($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    $activeId = $items[0]['id'] ?? null;

    $index = [
        'active_id' => $activeId,
        'items' => $items
    ];

    if (is_dir($userDir)) {
        write_json_file(schedule_index_path($username), $index);
    }

    return $index;
}

function load_schedule_index($username) {
    $indexPath = schedule_index_path($username);
    $index = read_json_file($indexPath);

    if (is_array($index) && isset($index['items']) && is_array($index['items'])) {
        return $index;
    }

    return build_schedule_index($username);
}

function save_schedule_index($username, $index) {
    write_json_file(schedule_index_path($username), $index);
}

function find_schedule_item($index, $scheduleId) {
    foreach ($index['items'] as $item) {
        if (($item['id'] ?? null) === $scheduleId) {
            return $item;
        }
    }
    return null;
}

function resolve_active_schedule_item($username) {
    $index = load_schedule_index($username);
    $activeId = $index['active_id'] ?? null;

    if ($activeId) {
        $item = find_schedule_item($index, $activeId);
        if ($item) {
            return $item;
        }
    }

    return $index['items'][0] ?? null;
}

function schedule_item_paths($username, $item) {
    $userDir = schedule_user_dir($username);
    $csv = null;
    $json = null;

    if (!empty($item['csv'])) {
        $csv = $userDir . '/' . ltrim($item['csv'], '/');
    }

    if (!empty($item['json'])) {
        $json = $userDir . '/' . ltrim($item['json'], '/');
    }

    return [
        'csv' => $csv,
        'json' => $json
    ];
}

function set_active_schedule_session($username, $item) {
    if (!$item || empty($item['id'])) {
        return;
    }

    $_SESSION['active_schedule_id'] = $item['id'];

    $paths = schedule_item_paths($username, $item);
    if (!empty($paths['csv'])) {
        $_SESSION['active_schedule_csv'] = $paths['csv'];
    }
    if (!empty($paths['json'])) {
        $_SESSION['active_schedule_json'] = $paths['json'];
    }
}

function sync_active_schedule_copy($username, $item) {
    $userDir = schedule_user_dir($username);
    $paths = schedule_item_paths($username, $item);

    if (!empty($paths['csv']) && file_exists($paths['csv'])) {
        $legacyCsv = $userDir . '/Kartu-Rencana-Studi_Aktif.csv';
        if (realpath($paths['csv']) !== realpath($legacyCsv)) {
            copy($paths['csv'], $legacyCsv);
        }
    }

    if (!empty($paths['json']) && file_exists($paths['json'])) {
        $legacyJson = $userDir . '/Kartu-Rencana-Studi_Aktif.json';
        if (realpath($paths['json']) !== realpath($legacyJson)) {
            copy($paths['json'], $legacyJson);
        }
    }
}

function set_active_schedule_id($username, $scheduleId) {
    $index = load_schedule_index($username);
    $item = find_schedule_item($index, $scheduleId);
    if (!$item) {
        return null;
    }

    $index['active_id'] = $scheduleId;
    save_schedule_index($username, $index);

    set_active_schedule_session($username, $item);
    sync_active_schedule_copy($username, $item);

    return $item;
}

function add_schedule_item($username, $item) {
    $index = load_schedule_index($username);
    $exists = false;
    foreach ($index['items'] as $existing) {
        if (($existing['id'] ?? null) === ($item['id'] ?? null)) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $index['items'][] = $item;
    }
    $index['active_id'] = $item['id'];
    save_schedule_index($username, $index);

    set_active_schedule_session($username, $item);
    sync_active_schedule_copy($username, $item);

    return $item;
}

function resolve_active_schedule_csv($username) {
    $item = resolve_active_schedule_item($username);
    if ($item) {
        $paths = schedule_item_paths($username, $item);
        if (!empty($paths['csv']) && file_exists($paths['csv'])) {
            return $paths['csv'];
        }
    }

    $legacy = schedule_user_dir($username) . '/Kartu-Rencana-Studi_Aktif.csv';
    if (file_exists($legacy)) {
        return $legacy;
    }

    return null;
}

function resolve_active_schedule_json($username) {
    $item = resolve_active_schedule_item($username);
    if ($item) {
        $paths = schedule_item_paths($username, $item);
        if (!empty($paths['json']) && file_exists($paths['json'])) {
            return $paths['json'];
        }
    }

    $legacy = schedule_user_dir($username) . '/Kartu-Rencana-Studi_Aktif.json';
    if (file_exists($legacy)) {
        return $legacy;
    }

    return null;
}
