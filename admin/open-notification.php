<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';

require_admin();

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    redirect(
        'admin/notifications.php'
    );
}


verify_csrf();


$notificationId =
    (int)($_POST['notification_id'] ?? 0);


if ($notificationId <= 0) {

    redirect(
        'admin/notifications.php'
    );
}


/*
|--------------------------------------------------------------------------
| Find Admin Notification
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        id,
        link

    FROM notifications

    WHERE id = ?
      AND user_id = ?

    LIMIT 1
");

$stmt->execute([
    $notificationId,
    (int)$admin['id']
]);

$notification =
    $stmt->fetch();


if (!$notification) {

    redirect(
        'admin/notifications.php'
    );
}


/*
|--------------------------------------------------------------------------
| Mark Read
|--------------------------------------------------------------------------
*/

mark_notification_read(
    $notificationId,
    (int)$admin['id']
);


/*
|--------------------------------------------------------------------------
| Safe Redirect
|--------------------------------------------------------------------------
*/

$link =
    trim(
        $notification['link'] ?? ''
    );


if (
    $link === ''
    ||
    str_contains(
        $link,
        '://'
    )
    ||
    str_starts_with(
        $link,
        '//'
    )
) {

    redirect(
        'admin/notifications.php'
    );
}


redirect($link);