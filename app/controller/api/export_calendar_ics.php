<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/app.php';
require_once PROJECT_ROOT . '/app/model/schedule_store.php';
require_once PROJECT_ROOT . '/app/model/task_store.php';

if (empty($_SESSION['username'])) {
    header("Location: index.php?route=login");
    exit();
}

date_default_timezone_set('Asia/Jakarta');
$username = (string)$_SESSION['username'];

function ics_escape_text(string $text): string
{
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace(",", "\\,", $text);
    $text = str_replace(";", "\\;", $text);
    return str_replace(["\r\n", "\n", "\r"], "\\n", $text);
}

function ics_normalize_time(?string $time): string
{
    $time = trim((string)$time);
    if ($time === '') {
        return '000000';
    }
    $time = str_replace('.', ':', $time);
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        $time .= ':00';
    }
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return '000000';
    }
    return str_replace(':', '', $time);
}

function ics_next_date_for_day(string $indonesianDay): ?DateTimeImmutable
{
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
    $map = [
        'Senin' => 1,
        'Selasa' => 2,
        'Rabu' => 3,
        'Kamis' => 4,
        'Jumat' => 5,
        'Sabtu' => 6,
        'Minggu' => 7,
    ];
    if (!isset($map[$indonesianDay])) {
        return null;
    }

    $todayDow = (int)$today->format('N');
    $targetDow = $map[$indonesianDay];
    $offset = ($targetDow - $todayDow + 7) % 7;

    return $today->modify('+' . $offset . ' day');
}

$active = resolve_active_schedule_item($username);
$scheduleRows = $active ? get_schedule_rows($username, $active['id']) : [];
$pendingTasks = task_list_by_status($username, ['Belum selesai']);

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//SI Jadwal//Calendar Export//ID',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:SI Jadwal - ' . ics_escape_text($username),
    'X-WR-TIMEZONE:Asia/Jakarta',
];

$nowUtc = gmdate('Ymd\THis\Z');
$untilUtc = gmdate('Ymd\THis\Z', strtotime('+6 months'));
$uidSeed = $username . '-' . time();

$dayToByDay = [
    'Senin' => 'MO',
    'Selasa' => 'TU',
    'Rabu' => 'WE',
    'Kamis' => 'TH',
    'Jumat' => 'FR',
    'Sabtu' => 'SA',
    'Minggu' => 'SU',
];

$eventCounter = 0;
foreach ($scheduleRows as $row) {
    $day = trim((string)($row['hari'] ?? ''));
    if ($day === '' || !isset($dayToByDay[$day])) {
        continue;
    }

    $firstDate = ics_next_date_for_day($day);
    if (!$firstDate) {
        continue;
    }

    $startHms = ics_normalize_time($row['jam_mulai'] ?? '');
    $endHms = ics_normalize_time($row['jam_selesai'] ?? '');
    if ($endHms === '000000' || $endHms <= $startHms) {
        $startTime = DateTimeImmutable::createFromFormat('YmdHis', $firstDate->format('Ymd') . $startHms, new DateTimeZone('Asia/Jakarta'));
        $endTime = $startTime ? $startTime->modify('+1 hour') : null;
        $endHms = $endTime ? $endTime->format('His') : '010000';
    }

    $summary = trim((string)($row['nama_matakuliah'] ?? 'Jadwal Kuliah'));
    $location = trim((string)($row['ruang'] ?? ''));
    $description = 'Jadwal kuliah dari SI Jadwal';

    $uid = sprintf('schedule-%s-%d@si-jadwal.local', md5($uidSeed), ++$eventCounter);
    $dtStart = $firstDate->format('Ymd') . 'T' . $startHms;
    $dtEnd = $firstDate->format('Ymd') . 'T' . $endHms;
    $rrule = 'FREQ=WEEKLY;BYDAY=' . $dayToByDay[$day] . ';UNTIL=' . $untilUtc;

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $uid;
    $lines[] = 'DTSTAMP:' . $nowUtc;
    $lines[] = 'DTSTART;TZID=Asia/Jakarta:' . $dtStart;
    $lines[] = 'DTEND;TZID=Asia/Jakarta:' . $dtEnd;
    $lines[] = 'SUMMARY:' . ics_escape_text($summary);
    if ($location !== '') {
        $lines[] = 'LOCATION:' . ics_escape_text($location);
    }
    $lines[] = 'DESCRIPTION:' . ics_escape_text($description);
    $lines[] = 'RRULE:' . $rrule;
    $lines[] = 'END:VEVENT';
}

foreach ($pendingTasks as $task) {
    $tanggal = trim((string)($task['tanggal'] ?? ''));
    if ($tanggal === '') {
        continue;
    }

    $jam = trim((string)($task['jam'] ?? ''));
    $summary = 'Tugas: ' . trim((string)($task['mata_kuliah'] ?? ''));
    $jenis = trim((string)($task['jenis'] ?? ''));
    if ($jenis !== '') {
        $summary .= ' (' . $jenis . ')';
    }
    $uid = sprintf('task-%s-%d@si-jadwal.local', md5($uidSeed), ++$eventCounter);

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $uid;
    $lines[] = 'DTSTAMP:' . $nowUtc;
    if ($jam !== '' && $jam !== '00:00:00') {
        $dtStart = date('Ymd\THis', strtotime($tanggal . ' ' . $jam));
        $dtEnd = date('Ymd\THis', strtotime($tanggal . ' ' . $jam . ' +30 minutes'));
        $lines[] = 'DTSTART;TZID=Asia/Jakarta:' . $dtStart;
        $lines[] = 'DTEND;TZID=Asia/Jakarta:' . $dtEnd;
    } else {
        $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($tanggal));
    }
    $lines[] = 'SUMMARY:' . ics_escape_text($summary);
    $lines[] = 'DESCRIPTION:' . ics_escape_text('Pengingat tugas dari SI Jadwal');
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';
$icsContent = implode("\r\n", $lines) . "\r\n";

$filename = 'si-jadwal-' . $username . '.ics';
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($icsContent));
echo $icsContent;
exit;

