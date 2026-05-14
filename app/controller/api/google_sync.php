<?php
require_once __DIR__ . '/google_bootstrap.php';

if (empty($_SESSION['username'])) {
    google_redirect('index.php?route=login');
}

if (!google_require_dependency()) {
    google_flash_message('Integrasi Google Calendar belum tersedia di server hosting ini.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    google_redirect('index.php?route=pengaturan&tab=akun');
}

google_verify_csrf_or_fail($_POST['csrf_token'] ?? null);

$userId = currentUserId();
if (!$userId || !getUserById($userId)) {
    google_flash_message('Akun login tidak valid.', 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

try {
    $client = getAuthorizedClientForUser($userId);
} catch (Throwable $e) {
    google_flash_message('Sinkronisasi diblokir: ' . $e->getMessage(), 'error');
    google_redirect('index.php?route=pengaturan&tab=akun');
}

$schedules = fetchSchedulesByUser($userId);
$stats = [
    'created' => 0,
    'updated' => 0,
    'deleted' => 0,
    'unchanged' => 0,
    'errors' => 0,
];
$localIds = [];

foreach ($schedules as $schedule) {
    $scheduleId = (int)$schedule['id'];
    $localIds[(string)$scheduleId] = true;

    try {
        $eventId = trim((string)($schedule['google_event_id'] ?? ''));

        if ($eventId === '') {
            $existingEvent = findGoogleEventByScheduleId($client, $userId, $scheduleId);

            if ($existingEvent) {
                $eventId = (string)$existingEvent->getId();
                saveScheduleEventId($scheduleId, $eventId);

                if (eventNeedsUpdate($existingEvent, $schedule)) {
                    updateEvent($client, $eventId, [
                        'user_id' => $userId,
                        'schedule' => $schedule,
                    ]);
                    $stats['updated']++;
                } else {
                    $stats['unchanged']++;
                }
            } else {
                $created = createEvent($client, [
                    'user_id' => $userId,
                    'schedule' => $schedule,
                ]);
                saveScheduleEventId($scheduleId, (string)$created->getId());
                $stats['created']++;
            }

            markScheduleSynced($scheduleId);
            continue;
        }

        try {
            $service = new Google\Service\Calendar($client);
            $event = $service->events->get('primary', $eventId);
        } catch (Throwable $e) {
            if (!isNotFoundGoogleError($e)) {
                throw $e;
            }

            $created = createEvent($client, [
                'user_id' => $userId,
                'schedule' => $schedule,
            ]);
            saveScheduleEventId($scheduleId, (string)$created->getId());
            $stats['created']++;
            markScheduleSynced($scheduleId);
            continue;
        }

        if (eventNeedsUpdate($event, $schedule)) {
            updateEvent($client, $eventId, [
                'user_id' => $userId,
                'schedule' => $schedule,
            ]);
            $stats['updated']++;
        } else {
            $stats['unchanged']++;
        }

        markScheduleSynced($scheduleId);
    } catch (Throwable $e) {
        $stats['errors']++;
    }
}

try {
    $remoteEvents = listAllAppEvents($client, $userId);

    foreach ($remoteEvents as $event) {
        $private = $event->getExtendedProperties() ? $event->getExtendedProperties()->getPrivate() : [];
        $remoteScheduleId = (string)($private['schedule_id'] ?? '');

        if ($remoteScheduleId === '') {
            continue;
        }

        if (!isset($localIds[$remoteScheduleId])) {
            deleteEvent($client, (string)$event->getId());
            $stats['deleted']++;
        }
    }
} catch (Throwable $e) {
    $stats['errors']++;
}

google_flash_message(
    sprintf(
        'Sinkronisasi selesai. Buat: %d, Update: %d, Hapus: %d, Tidak berubah: %d, Error: %d',
        $stats['created'],
        $stats['updated'],
        $stats['deleted'],
        $stats['unchanged'],
        $stats['errors']
    ),
    $stats['errors'] > 0 ? 'warning' : 'success'
);

google_redirect('index.php?route=pengaturan&tab=akun');
