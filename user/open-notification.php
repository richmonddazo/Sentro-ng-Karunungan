<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';

require_login();

$user = current_user();

if ($user['role'] === 'admin') {
    redirect('admin/dashboard.php');
}


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('user/notifications.php');
}

verify_csrf();


/*
|--------------------------------------------------------------------------
| Notification ID
|--------------------------------------------------------------------------
*/

$notificationId =
    (int)($_POST['notification_id'] ?? 0);

if ($notificationId <= 0) {
    redirect('user/notifications.php');
}


/*
|--------------------------------------------------------------------------
| Get Notification
|--------------------------------------------------------------------------
|
| Important:
| user_id is included so a user cannot open another user's notification.
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        id,
        user_id,
        link,
        is_read

    FROM notifications

    WHERE id = ?
      AND user_id = ?

    LIMIT 1
");

$stmt->execute([
    $notificationId,
    (int)$user['id']
]);

$notification = $stmt->fetch();


if (!$notification) {
    redirect('user/notifications.php');
}


/*
|--------------------------------------------------------------------------
| Mark As Read
|--------------------------------------------------------------------------
*/

mark_notification_read(
    $notificationId,
    (int)$user['id']
);


/*
|--------------------------------------------------------------------------
| Safe Redirect
|--------------------------------------------------------------------------
*/

$target =
    trim(
        (string)($notification['link'] ?? '')
    );


/*
| Only allow internal project paths.
| Prevent external redirects such as:
| https://malicious-site.com
*/

if (
    $target === '' ||
    str_contains($target, '://') ||
    str_starts_with($target, '//')
) {

    $target =
        'user/notifications.php';
}


redirect($target);