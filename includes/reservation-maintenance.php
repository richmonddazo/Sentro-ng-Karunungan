<?php

require_once __DIR__ . '/notifications.php';


/*
|--------------------------------------------------------------------------
| Expire Unclaimed Ready Reservations
|--------------------------------------------------------------------------
|
| Any reservation with:
|
| status = ready
| pickup_deadline < current time
|
| will automatically:
|
| 1. Become expired
| 2. Return one copy to available inventory
| 3. Notify the user
|
|--------------------------------------------------------------------------
*/

function expire_ready_reservations(): int
{
    $pdo = db();

    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Find Expired Ready Reservations
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT
                reservations.id,
                reservations.user_id,
                reservations.book_id,
                reservations.pickup_deadline,

                books.title

            FROM reservations

            INNER JOIN books
                ON books.id = reservations.book_id

            WHERE reservations.status = 'ready'

              AND reservations.pickup_deadline
                  IS NOT NULL

              AND reservations.pickup_deadline
                  < NOW()

            FOR UPDATE
        ");

        $expiredReservations =
            $stmt->fetchAll();


        /*
        |--------------------------------------------------------------------------
        | Nothing To Expire
        |--------------------------------------------------------------------------
        */

        if (!$expiredReservations) {

            $pdo->commit();

            return 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Prepared Statements
        |--------------------------------------------------------------------------
        */

        $expireStmt = $pdo->prepare("
            UPDATE reservations

            SET status = 'expired'

            WHERE id = ?
              AND status = 'ready'
        ");


        $restoreBookStmt = $pdo->prepare("
            UPDATE books

            SET available_copies =
                LEAST(
                    available_copies + 1,
                    total_copies
                )

            WHERE id = ?
        ");


        $expiredCount = 0;


        /*
        |--------------------------------------------------------------------------
        | Process Each Expired Reservation
        |--------------------------------------------------------------------------
        */

        foreach (
            $expiredReservations
            as $reservation
        ) {

            /*
            | Change reservation to expired.
            */

            $expireStmt->execute([
                (int)$reservation['id']
            ]);


            /*
            | If another process already changed it,
            | don't restore another copy.
            */

            if (
                $expireStmt->rowCount() !== 1
            ) {
                continue;
            }


            /*
            | Return reserved copy to inventory.
            */

            $restoreBookStmt->execute([
                (int)$reservation['book_id']
            ]);

            /*
|--------------------------------------------------------------------------
| Record No-Show
|--------------------------------------------------------------------------
*/

$userStmt = $pdo->prepare("
    SELECT no_show_count
    FROM users
    WHERE id = ?
    FOR UPDATE
");

$userStmt->execute([
    (int)$reservation['user_id']
]);

$userRecord =
    $userStmt->fetch();


if ($userRecord) {

    $newNoShowCount =
        (int)$userRecord[
            'no_show_count'
        ] + 1;


    /*
    |--------------------------------------------------------------------------
    | 3 No-Shows = 7 Day Cooldown
    |--------------------------------------------------------------------------
    */

    if ($newNoShowCount >= 3) {

        $blockStmt = $pdo->prepare("
            UPDATE users

            SET
                no_show_count = 0,

                reservation_blocked_until =
                    DATE_ADD(
                        NOW(),
                        INTERVAL 7 DAY
                    )

            WHERE id = ?
        ");

        $blockStmt->execute([
            (int)$reservation['user_id']
        ]);


        create_notification(
            (int)$reservation['user_id'],

            'Reservation Privileges Temporarily Suspended',

            'You have missed 3 reserved-book pickups. New reservations are temporarily disabled for 7 days.',

            'reservation_blocked',

            'user/reservations.php'
        );

    } else {

        $updateNoShowStmt =
            $pdo->prepare("
                UPDATE users
                SET no_show_count = ?
                WHERE id = ?
            ");

        $updateNoShowStmt->execute([
            $newNoShowCount,
            (int)$reservation['user_id']
        ]);
    }
}

            /*
            | Notify User
            */

            $notificationCreated =
                create_notification(
                    (int)$reservation['user_id'],

                    'Reservation Expired',

                    'Your pickup period for "' .
                    $reservation['title'] .
                    '" has expired. The reserved copy has been returned to the library inventory.',

                    'reservation_expired',

                    'user/reservations.php'
                );


            if (!$notificationCreated) {

                throw new RuntimeException(
                    'Unable to create expiration notification.'
                );
            }


            $expiredCount++;
        }


        /*
        |--------------------------------------------------------------------------
        | Finish
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        return $expiredCount;


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        error_log(
            'Reservation expiration error: ' .
            $e->getMessage()
        );


        /*
        | Don't crash the whole website
        | if automatic maintenance fails.
        */

        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Notify Overdue Borrowed Books
|--------------------------------------------------------------------------
|
| A reservation is considered overdue when:
|
| status = borrowed
| due_at < current time
|
| We keep its status as "borrowed" so the admin can still
| mark it as returned normally.
|--------------------------------------------------------------------------
*/

function notify_overdue_reservations(): int
{
    $pdo = db();

    try {

        /*
        |--------------------------------------------------------------------------
        | Find Overdue Borrowings
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->query("
            SELECT
                reservations.id,
                reservations.user_id,
                reservations.book_id,
                reservations.due_at,

                books.title

            FROM reservations

            INNER JOIN books
                ON books.id = reservations.book_id

            WHERE reservations.status = 'borrowed'

              AND reservations.due_at IS NOT NULL

              AND reservations.due_at < NOW()
        ");

        $overdueReservations =
            $stmt->fetchAll();


        if (!$overdueReservations) {
            return 0;
        }


        $createdCount = 0;


        foreach (
            $overdueReservations
            as $reservation
        ) {

            /*
            |--------------------------------------------------------------------------
            | Unique Notification Link
            |--------------------------------------------------------------------------
            |
            | The reservation ID makes the notification unique.
            |--------------------------------------------------------------------------
            */

            $notificationLink =
                'user/reservations.php?reservation=' .
                (int)$reservation['id'];


            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Overdue Notifications
            |--------------------------------------------------------------------------
            */

            $existingStmt = $pdo->prepare("
                SELECT id

                FROM notifications

                WHERE user_id = ?
                  AND type = 'overdue'
                  AND link = ?

                LIMIT 1
            ");

            $existingStmt->execute([
                (int)$reservation['user_id'],
                $notificationLink
            ]);


            if ($existingStmt->fetch()) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Create Overdue Notification
            |--------------------------------------------------------------------------
            */

            $created =
                create_notification(

                    (int)$reservation['user_id'],

                    'Book Overdue',

                    '"' .
                    $reservation['title'] .
                    '" is already overdue. Please return the book to the library as soon as possible.',

                    'overdue',

                    $notificationLink
                );


            if ($created) {
                $createdCount++;
            }
        }


        return $createdCount;


    } catch (Throwable $e) {

        error_log(
            'Overdue notification error: ' .
            $e->getMessage()
        );

        return 0;
    }
}

/*
|--------------------------------------------------------------------------
| Notify Books Due Soon
|--------------------------------------------------------------------------
|
| Notify the user once when a borrowed book is due
| within the next 24 hours.
|--------------------------------------------------------------------------
*/

function notify_due_soon_reservations(): int
{
    $pdo = db();

    try {

        $stmt = $pdo->query("
            SELECT
                reservations.id,
                reservations.user_id,
                reservations.book_id,
                reservations.due_at,

                books.title

            FROM reservations

            INNER JOIN books
                ON books.id = reservations.book_id

            WHERE reservations.status = 'borrowed'

              AND reservations.due_at IS NOT NULL

              AND reservations.due_at > NOW()

              AND reservations.due_at <=
                  DATE_ADD(
                      NOW(),
                      INTERVAL 1 DAY
                  )
        ");

        $reservations =
            $stmt->fetchAll();


        if (!$reservations) {
            return 0;
        }


        $createdCount = 0;


        foreach (
            $reservations
            as $reservation
        ) {

            $notificationLink =
                'user/reservations.php?reservation=' .
                (int)$reservation['id'];


            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Reminder
            |--------------------------------------------------------------------------
            */

            $existingStmt = $pdo->prepare("
                SELECT id

                FROM notifications

                WHERE user_id = ?
                  AND type = 'due_reminder'
                  AND link = ?

                LIMIT 1
            ");

            $existingStmt->execute([
                (int)$reservation['user_id'],
                $notificationLink
            ]);


            if ($existingStmt->fetch()) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Friendly Due Date
            |--------------------------------------------------------------------------
            */

            $dueDate =
                date(
                    'F d, Y h:i A',
                    strtotime(
                        $reservation['due_at']
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | Create Notification
            |--------------------------------------------------------------------------
            */

            $created =
                create_notification(

                    (int)$reservation['user_id'],

                    'Book Due Soon',

                    '"' .
                    $reservation['title'] .
                    '" is due on ' .
                    $dueDate .
                    '. Please return the book on or before the due date.',

                    'due_reminder',

                    $notificationLink
                );


            if ($created) {
                $createdCount++;
            }
        }


        return $createdCount;


    } catch (Throwable $e) {

        error_log(
            'Due soon notification error: ' .
            $e->getMessage()
        );

        return 0;
    }
}