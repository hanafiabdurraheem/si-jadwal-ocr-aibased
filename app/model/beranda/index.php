<?php

function beranda_build_view_data(): array
{
    $username = $_SESSION['username'] ?? '';
    $noticeUpdated = isset($_GET['notice']) && $_GET['notice'] === 'updated';

    return [
        'username' => $username,
        'noticeUpdated' => $noticeUpdated,
    ];
}
