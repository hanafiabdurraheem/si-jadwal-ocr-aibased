<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

app_start_session();

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$theme = 'ungu';
if (!empty($_SESSION['username'])) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT theme_color FROM user_preference WHERE username=? LIMIT 1");
    $stmt->bind_param('s', $_SESSION['username']);
    $stmt->execute();
    $stmt->bind_result($dbTheme);
    if ($stmt->fetch() && $dbTheme) {
        $theme = $dbTheme;
    }
    $stmt->close();
    $conn->close();
}

$palettes = [
    'ungu' => [
        'accent' => '#6552fe',
        'accent_dark' => '#5255fe',
        'accent_soft' => '#9283ff',
        'accent_700' => '#4f46e5',
        'accent_rgb' => '101, 82, 254'
    ],
    'kuning' => [
        'accent' => '#fbbf24',
        'accent_dark' => '#f59e0b',
        'accent_soft' => '#fde68a',
        'accent_700' => '#d97706',
        'accent_rgb' => '251, 191, 36'
    ],
    'biru' => [
        'accent' => '#3b82f6',
        'accent_dark' => '#2563eb',
        'accent_soft' => '#93c5fd',
        'accent_700' => '#1d4ed8',
        'accent_rgb' => '59, 130, 246'
    ],
    'hijau' => [
        'accent' => '#22c55e',
        'accent_dark' => '#16a34a',
        'accent_soft' => '#86efac',
        'accent_700' => '#15803d',
        'accent_rgb' => '34, 197, 94'
    ],
    'magenta' => [
        'accent' => '#d946ef',
        'accent_dark' => '#c026d3',
        'accent_soft' => '#f0abfc',
        'accent_700' => '#a21caf',
        'accent_rgb' => '217, 70, 239'
    ]
];

if (!isset($palettes[$theme])) {
    $theme = 'ungu';
}

$palette = $palettes[$theme];

echo ":root{";
echo "--accent:{$palette['accent']};";
echo "--accent-dark:{$palette['accent_dark']};";
echo "--accent-soft:{$palette['accent_soft']};";
echo "--accent-700:{$palette['accent_700']};";
echo "--accent-rgb:{$palette['accent_rgb']};";
echo "}";
?>
