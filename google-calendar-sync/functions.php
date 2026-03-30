<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleServiceException;

function currentUserId(): ?int
{
    if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        $username = $_SESSION['username'] ?? '';
        if ($username === '') {
            return null;
        }

        $resolved = resolveUserIdByUsername((string)$username);
        if ($resolved === null) {
            return null;
        }

        $_SESSION['user_id'] = $resolved;
        return $resolved;
    }

    return (int)$_SESSION['user_id'];
}

function getUserById(int $userId): ?array
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('SELECT id, username AS email FROM `user` WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function resolveUserIdByUsername(string $username): ?int
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('SELECT id FROM `user` WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function resolveUsernameByUserId(int $userId): ?string
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('SELECT username FROM `user` WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    return $row ? (string)$row['username'] : null;
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfOrFail(?string $token): void
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type,
    ];
}

function appRedirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html><head><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></head><body>';
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '</body></html>';
    exit;
}

function pullFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function encryptToken(string $plain): string
{
    if (TOKEN_ENCRYPTION_KEY === '') {
        return $plain;
    }

    $key = hash('sha256', TOKEN_ENCRYPTION_KEY, true);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($cipher === false) {
        throw new RuntimeException('Token encryption failed.');
    }

    return base64_encode($iv . $cipher);
}

function decryptToken(?string $encoded): ?string
{
    if ($encoded === null || $encoded === '') {
        return null;
    }

    if (TOKEN_ENCRYPTION_KEY === '') {
        return $encoded;
    }

    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 17) {
        return null;
    }

    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $key = hash('sha256', TOKEN_ENCRYPTION_KEY, true);

    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

function getGoogleClientBase(): Client
{
    if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '' || GOOGLE_REDIRECT_URI === '') {
        throw new RuntimeException('Google credentials are not configured in config.php.');
    }

    $client = new Client();
    $client->setApplicationName(APP_NAME);
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    $client->setAccessType('offline');
    $client->setIncludeGrantedScopes(true);
    $client->setPrompt('consent');
    $client->setScopes(GOOGLE_SCOPES);

    return $client;
}

function getTokenRow(int $userId): ?array
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('SELECT id, user_id, access_token, refresh_token, expires_at FROM user_tokens WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function upsertUserToken(int $userId, array $tokenPayload): void
{
    ensureGoogleSyncSchema();
    $accessToken = (string)($tokenPayload['access_token'] ?? '');
    $refreshToken = $tokenPayload['refresh_token'] ?? null;
    $expiresIn = (int)($tokenPayload['expires_in'] ?? 3600);

    if ($accessToken === '') {
        throw new RuntimeException('Google access token is missing.');
    }

    $existing = getTokenRow($userId);
    $refreshToStore = $refreshToken;
    if (($refreshToStore === null || $refreshToStore === '') && $existing) {
        $refreshToStore = decryptToken($existing['refresh_token']) ?: null;
    }

    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify(sprintf('+%d seconds', max($expiresIn, 1)))
        ->format('Y-m-d H:i:s');

    $pdo = db();
    if ($existing) {
        $stmt = $pdo->prepare('UPDATE user_tokens SET access_token = :access_token, refresh_token = :refresh_token, expires_at = :expires_at, updated_at = NOW() WHERE user_id = :user_id');
    } else {
        $stmt = $pdo->prepare('INSERT INTO user_tokens (user_id, access_token, refresh_token, expires_at, created_at, updated_at) VALUES (:user_id, :access_token, :refresh_token, :expires_at, NOW(), NOW())');
    }

    $stmt->execute([
        ':user_id' => $userId,
        ':access_token' => encryptToken($accessToken),
        ':refresh_token' => $refreshToStore ? encryptToken((string)$refreshToStore) : null,
        ':expires_at' => $expiresAt,
    ]);
}

function getAuthorizedClientForUser(int $userId): Client
{
    $token = getTokenRow($userId);
    if (!$token) {
        throw new RuntimeException('Google account is not connected.');
    }

    $access = decryptToken($token['access_token']);
    $refresh = decryptToken($token['refresh_token']);

    if (!$access) {
        throw new RuntimeException('Stored Google access token is invalid.');
    }

    $client = getGoogleClientBase();

    $expiresAtUtc = new DateTimeImmutable($token['expires_at'], new DateTimeZone('UTC'));
    $created = $expiresAtUtc->getTimestamp() - 3600;
    $expiresIn = max(1, $expiresAtUtc->getTimestamp() - time());

    $client->setAccessToken([
        'access_token' => $access,
        'refresh_token' => $refresh,
        'created' => $created,
        'expires_in' => $expiresIn,
    ]);

    if ($client->isAccessTokenExpired()) {
        if (!$refresh) {
            throw new RuntimeException('Google refresh token is missing. Reconnect your Google account.');
        }

        $newToken = $client->fetchAccessTokenWithRefreshToken($refresh);
        if (isset($newToken['error'])) {
            throw new RuntimeException('Failed to refresh Google token: ' . $newToken['error']);
        }

        if (!isset($newToken['refresh_token'])) {
            $newToken['refresh_token'] = $refresh;
        }

        upsertUserToken($userId, $newToken);
        $client->setAccessToken($newToken);
    }

    return $client;
}

function removeGoogleConnection(int $userId): void
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('DELETE FROM user_tokens WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}

function fetchSchedulesByUser(int $userId): array
{
    ensureGoogleSyncSchema();
    $username = resolveUsernameByUserId($userId);
    if ($username === null) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, :user_id AS user_id, nama_matakuliah AS course_name, hari AS day, jam_mulai AS start_time, jam_selesai AS end_time, ruang AS location, google_event_id, last_synced_at
         FROM schedule
         WHERE username = :username
           AND is_active = 1
           AND hari IS NOT NULL AND hari <> ""
           AND jam_mulai IS NOT NULL AND jam_mulai <> ""
           AND jam_selesai IS NOT NULL AND jam_selesai <> ""
         ORDER BY id ASC'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':username' => $username,
    ]);
    return $stmt->fetchAll();
}

function saveScheduleEventId(int $scheduleId, string $eventId): void
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('UPDATE schedule SET google_event_id = :event_id, last_synced_at = NOW() WHERE id = :id');
    $stmt->execute([
        ':event_id' => $eventId,
        ':id' => $scheduleId,
    ]);
}

function markScheduleSynced(int $scheduleId): void
{
    ensureGoogleSyncSchema();
    $stmt = db()->prepare('UPDATE schedule SET last_synced_at = NOW() WHERE id = :id');
    $stmt->execute([':id' => $scheduleId]);
}

function clearAllScheduleEventIds(int $userId): void
{
    ensureGoogleSyncSchema();
    $username = resolveUsernameByUserId($userId);
    if ($username === null) {
        return;
    }
    $stmt = db()->prepare('UPDATE schedule SET google_event_id = NULL, last_synced_at = NULL WHERE username = :username');
    $stmt->execute([':username' => $username]);
}

function weekdayToGoogleCode(string $day): string
{
    $value = strtoupper(trim($day));
    $map = [
        'MONDAY' => 'MO',
        'MON' => 'MO',
        'SENIN' => 'MO',
        'TUESDAY' => 'TU',
        'TUE' => 'TU',
        'SELASA' => 'TU',
        'WEDNESDAY' => 'WE',
        'WED' => 'WE',
        'RABU' => 'WE',
        'THURSDAY' => 'TH',
        'THU' => 'TH',
        'KAMIS' => 'TH',
        'FRIDAY' => 'FR',
        'FRI' => 'FR',
        'JUMAT' => 'FR',
        'JUM\'AT' => 'FR',
        'SABTU' => 'SA',
        'SATURDAY' => 'SA',
        'SAT' => 'SA',
        'MINGGU' => 'SU',
        'AHAD' => 'SU',
        'SUNDAY' => 'SU',
        'SUN' => 'SU',
    ];

    return $map[$value] ?? 'MO';
}

function canonicalWeekDate(string $day): string
{
    $baseMonday = new DateTimeImmutable('2001-01-01', new DateTimeZone(APP_TIMEZONE)); // Monday
    $order = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
    $target = weekdayToGoogleCode($day);
    $targetIdx = array_search($target, $order, true);
    if ($targetIdx === false) {
        $targetIdx = 0;
    }
    $date = $baseMonday->modify(sprintf('+%d days', (int)$targetIdx));
    return $date->format('Y-m-d');
}

function normalizeTimeValue(string $time): string
{
    $time = str_replace('.', ':', trim($time));
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }

    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }

    $dt = DateTimeImmutable::createFromFormat('H:i:s', $time)
        ?: DateTimeImmutable::createFromFormat('H:i', $time);

    return $dt ? $dt->format('H:i:s') : '00:00:00';
}

function buildEventObject(array $schedule, int $userId): Event
{
    $date = canonicalWeekDate((string)$schedule['day']);
    $startAt = sprintf('%sT%s', $date, normalizeTimeValue((string)$schedule['start_time']));
    $endAt = sprintf('%sT%s', $date, normalizeTimeValue((string)$schedule['end_time']));

    $event = new Event([
        'summary' => (string)$schedule['course_name'],
        'location' => (string)($schedule['location'] ?? ''),
        'description' => sprintf('Synced from SI Jadwal (schedule_id=%d).', (int)$schedule['id']),
        'start' => new EventDateTime([
            'dateTime' => $startAt,
            'timeZone' => APP_TIMEZONE,
        ]),
        'end' => new EventDateTime([
            'dateTime' => $endAt,
            'timeZone' => APP_TIMEZONE,
        ]),
        'recurrence' => [
            sprintf('RRULE:FREQ=WEEKLY;BYDAY=%s', weekdayToGoogleCode((string)$schedule['day'])),
        ],
        'extendedProperties' => [
            'private' => [
                'app' => APP_EVENT_TAG,
                'user_id' => (string)$userId,
                'schedule_id' => (string)$schedule['id'],
            ],
        ],
    ]);

    return $event;
}

function createEvent(Client $client, array $data): Event
{
    $service = new Calendar($client);
    $event = buildEventObject($data['schedule'], (int)$data['user_id']);
    return $service->events->insert('primary', $event);
}

function updateEvent(Client $client, string $eventId, array $data): Event
{
    $service = new Calendar($client);
    $event = buildEventObject($data['schedule'], (int)$data['user_id']);
    return $service->events->update('primary', $eventId, $event);
}

function deleteEvent(Client $client, string $eventId): void
{
    $service = new Calendar($client);
    $service->events->delete('primary', $eventId);
}

function findGoogleEventByScheduleId(Client $client, int $userId, int $scheduleId): ?Event
{
    $service = new Calendar($client);
    $events = $service->events->listEvents('primary', [
        'privateExtendedProperty' => [
            'app=' . APP_EVENT_TAG,
            'user_id=' . $userId,
            'schedule_id=' . $scheduleId,
        ],
        'singleEvents' => true,
        'maxResults' => 1,
    ]);

    $items = $events->getItems();
    return $items[0] ?? null;
}

function listAllAppEvents(Client $client, int $userId): array
{
    $service = new Calendar($client);
    $events = [];
    $pageToken = null;

    do {
        $response = $service->events->listEvents('primary', [
            'privateExtendedProperty' => [
                'app=' . APP_EVENT_TAG,
                'user_id=' . $userId,
            ],
            'singleEvents' => true,
            'showDeleted' => false,
            'maxResults' => 250,
            'pageToken' => $pageToken,
        ]);

        foreach ($response->getItems() as $event) {
            $events[] = $event;
        }

        $pageToken = $response->getNextPageToken();
    } while ($pageToken);

    return $events;
}

function eventNeedsUpdate(Event $event, array $schedule): bool
{
    $expectedDate = canonicalWeekDate((string)$schedule['day']);
    $expectedStart = sprintf('%sT%s', $expectedDate, normalizeTimeValue((string)$schedule['start_time']));
    $expectedEnd = sprintf('%sT%s', $expectedDate, normalizeTimeValue((string)$schedule['end_time']));

    $startDateTime = $event->getStart() ? $event->getStart()->getDateTime() : null;
    $endDateTime = $event->getEnd() ? $event->getEnd()->getDateTime() : null;

    $summaryDiff = trim((string)$event->getSummary()) !== trim((string)$schedule['course_name']);
    $locationDiff = trim((string)$event->getLocation()) !== trim((string)($schedule['location'] ?? ''));

    $startDiff = !$startDateTime || (new DateTimeImmutable($startDateTime))->format('Y-m-d\TH:i:s') !== $expectedStart;
    $endDiff = !$endDateTime || (new DateTimeImmutable($endDateTime))->format('Y-m-d\TH:i:s') !== $expectedEnd;

    return $summaryDiff || $locationDiff || $startDiff || $endDiff;
}

function isNotFoundGoogleError(Throwable $e): bool
{
    if (!$e instanceof GoogleServiceException) {
        return false;
    }

    return $e->getCode() === 404;
}

function ensureGoogleSyncSchema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_tokens_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $colGoogle = $pdo->query("SHOW COLUMNS FROM schedule LIKE 'google_event_id'");
    if ($colGoogle && !$colGoogle->fetch()) {
        $pdo->exec("ALTER TABLE schedule ADD COLUMN google_event_id VARCHAR(255) NULL");
    }

    $colSynced = $pdo->query("SHOW COLUMNS FROM schedule LIKE 'last_synced_at'");
    if ($colSynced && !$colSynced->fetch()) {
        $pdo->exec("ALTER TABLE schedule ADD COLUMN last_synced_at DATETIME NULL");
    }

    $checked = true;
}
