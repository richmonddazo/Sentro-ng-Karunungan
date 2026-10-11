<?php

/*
|--------------------------------------------------------------------------
| Create Notification
|--------------------------------------------------------------------------
*/

function create_notification(
    int $userId,
    string $title,
    string $message,
    string $type = 'general',
    ?string $link = null
): bool {

    $title = trim($title);
    $message = trim($message);
    $type = trim($type);

    if (
        $userId <= 0 ||
        $title === '' ||
        $message === ''
    ) {
        return false;
    }

    if ($type === '') {
        $type = 'general';
    }

    $stmt = db()->prepare("
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            link,
            is_read
        )

        VALUES (
            :user_id,
            :title,
            :message,
            :type,
            :link,
            0
        )
    ");

    return $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':message' => $message,
        ':type' => $type,
        ':link' => $link
    ]);
}


/*
|--------------------------------------------------------------------------
| Get Unread Notification Count
|--------------------------------------------------------------------------
*/

function unread_notification_count(
    int $userId
): int {

    if ($userId <= 0) {
        return 0;
    }

    $stmt = db()->prepare("
        SELECT COUNT(*)

        FROM notifications

        WHERE user_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        $userId
    ]);

    return (int)$stmt->fetchColumn();
}


/*
|--------------------------------------------------------------------------
| Get User Notifications
|--------------------------------------------------------------------------
*/

function get_user_notifications(
    int $userId,
    int $limit = 20
): array {

    if ($userId <= 0) {
        return [];
    }

    /*
    | Keep LIMIT within a safe range.
    */

    $limit = max(
        1,
        min($limit, 100)
    );

    $stmt = db()->prepare("
        SELECT
            id,
            user_id,
            title,
            message,
            type,
            link,
            is_read,
            created_at

        FROM notifications

        WHERE user_id = ?

        ORDER BY created_at DESC, id DESC

        LIMIT {$limit}
    ");

    $stmt->execute([
        $userId
    ]);

    return $stmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| Mark One Notification as Read
|--------------------------------------------------------------------------
*/

function mark_notification_read(
    int $notificationId,
    int $userId
): bool {

    if (
        $notificationId <= 0 ||
        $userId <= 0
    ) {
        return false;
    }

    $stmt = db()->prepare("
        UPDATE notifications

        SET is_read = 1

        WHERE id = ?
          AND user_id = ?
    ");

    $stmt->execute([
        $notificationId,
        $userId
    ]);

    return true;
}


/*
|--------------------------------------------------------------------------
| Mark All Notifications as Read
|--------------------------------------------------------------------------
*/

function mark_all_notifications_read(
    int $userId
): int {

    if ($userId <= 0) {
        return 0;
    }

    $stmt = db()->prepare("
        UPDATE notifications

        SET is_read = 1

        WHERE user_id = ?
          AND is_read = 0
    ");

    $stmt->execute([
        $userId
    ]);

    return $stmt->rowCount();
}