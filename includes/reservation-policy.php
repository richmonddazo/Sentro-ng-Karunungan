<?php

/*
|--------------------------------------------------------------------------
| Reservation Policy
|--------------------------------------------------------------------------
|
| Current rules:
|
| 1. Maximum 3 active reservations per user.
| 2. User cannot reserve the same book twice while an active reservation exists.
| 3. User with an overdue borrowed book cannot create a new reservation.
|
|--------------------------------------------------------------------------
*/

const MAX_ACTIVE_RESERVATIONS = 3;


/*
|--------------------------------------------------------------------------
| Check Reservation Policy
|--------------------------------------------------------------------------
*/

function check_reservation_policy(
    int $userId,
    int $bookId
): array {

    $pdo = db();

    /*
|--------------------------------------------------------------------------
| Reservation Cooldown
|--------------------------------------------------------------------------
*/

$blockedStmt = $pdo->prepare("
    SELECT reservation_blocked_until
    FROM users
    WHERE id = ?
      AND reservation_blocked_until IS NOT NULL
      AND reservation_blocked_until > NOW()
    LIMIT 1
");

$blockedStmt->execute([
    $userId
]);

$blockedUser =
    $blockedStmt->fetch();


if ($blockedUser) {

    $blockedUntil =
        date(
            'F d, Y h:i A',
            strtotime(
                $blockedUser[
                    'reservation_blocked_until'
                ]
            )
        );

    return [
        'allowed' => false,

        'code' =>
            'reservation_cooldown',

        'message' =>
            'Your reservation privileges are temporarily suspended due to repeated missed pickups. You may reserve again after ' .
            $blockedUntil .
            '.'
    ];
}


/*
|--------------------------------------------------------------------------
| Clean Expired Cooldown
|--------------------------------------------------------------------------
*/

$clearBlockStmt = $pdo->prepare("
    UPDATE users
    SET reservation_blocked_until = NULL
    WHERE id = ?
      AND reservation_blocked_until IS NOT NULL
      AND reservation_blocked_until <= NOW()
");

$clearBlockStmt->execute([
    $userId
]);

    /*
    |--------------------------------------------------------------------------
    | 1. Check Overdue Books
    |--------------------------------------------------------------------------
    */

    $overdueStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM reservations

        WHERE user_id = ?
          AND status = 'borrowed'
          AND due_at IS NOT NULL
          AND due_at < NOW()
    ");

    $overdueStmt->execute([
        $userId
    ]);

    $overdueCount =
        (int)$overdueStmt->fetchColumn();


    if ($overdueCount > 0) {

        return [
            'allowed' => false,

            'code' =>
                'overdue_book',

            'message' =>
                'You currently have an overdue book. Please return it before making another reservation.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 2. Prevent Duplicate Active Reservation
    |--------------------------------------------------------------------------
    */

    $duplicateStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM reservations

        WHERE user_id = ?
          AND book_id = ?

          AND status IN (
              'pending',
              'approved',
              'ready',
              'borrowed'
          )
    ");

    $duplicateStmt->execute([
        $userId,
        $bookId
    ]);

    $duplicateCount =
        (int)$duplicateStmt->fetchColumn();


    if ($duplicateCount > 0) {

        return [
            'allowed' => false,

            'code' =>
                'duplicate_reservation',

            'message' =>
                'You already have an active reservation or borrowing record for this book.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | 3. Maximum Active Reservations
    |--------------------------------------------------------------------------
    */

    $activeStmt = $pdo->prepare("
        SELECT COUNT(*)

        FROM reservations

        WHERE user_id = ?

          AND status IN (
              'pending',
              'approved',
              'ready',
              'borrowed'
          )
    ");

    $activeStmt->execute([
        $userId
    ]);

    $activeCount =
        (int)$activeStmt->fetchColumn();


    if (
        $activeCount >=
        MAX_ACTIVE_RESERVATIONS
    ) {

        return [
            'allowed' => false,

            'code' =>
                'reservation_limit',

            'message' =>
                'You have reached the maximum of ' .
                MAX_ACTIVE_RESERVATIONS .
                ' active reservations. Complete or cancel an existing reservation before reserving another book.'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Reservation Allowed
    |--------------------------------------------------------------------------
    */

    return [
        'allowed' => true,

        'code' =>
            'allowed',

        'message' =>
            'Reservation allowed.'
    ];
}