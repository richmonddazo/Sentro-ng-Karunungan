<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';

require_admin();

$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/users.php');
}

verify_csrf();

$userId =
    (int)($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    redirect(
        'admin/users.php?error=Invalid user.'
    );
}

$pdo = db();

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Find User
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            role,
            reservation_blocked_until
        FROM users
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $userId
    ]);

    $member = $stmt->fetch();


    if (
        !$member ||
        $member['role'] !== 'user'
    ) {

        throw new Exception(
            'User account not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check Active Suspension
    |--------------------------------------------------------------------------
    */

    if (
        empty(
            $member['reservation_blocked_until']
        )
        ||
        strtotime(
            $member['reservation_blocked_until']
        ) <= time()
    ) {

        throw new Exception(
            'This user does not have an active reservation suspension.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Lift Suspension
    |--------------------------------------------------------------------------
    */

    $updateStmt = $pdo->prepare("
        UPDATE users
        SET reservation_blocked_until = NULL
        WHERE id = ?
    ");

    $updateStmt->execute([
        $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs (
            user_id,
            action,
            entity_type,
            entity_id,
            details
        )
        VALUES (
            :user_id,
            :action,
            :entity_type,
            :entity_id,
            :details
        )
    ");

    $auditStmt->execute([
        ':user_id' =>
            (int)$admin['id'],

        ':action' =>
            'reservation_suspension_lifted',

        ':entity_type' =>
            'user',

        ':entity_id' =>
            $userId,

        ':details' =>
            'Reservation suspension manually lifted for user: ' .
            $member['name']
    ]);


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Notify User
    |--------------------------------------------------------------------------
    */

    create_notification(
        $userId,
        'Reservation Suspension Lifted',
        'Your reservation privileges have been restored by the library administrator. You may now reserve books again.',
        'reservation_suspension_lifted',
        'user/catalog.php'
    );


    redirect(
        'admin/users.php?result=suspension_lifted'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirect(
        'admin/users.php?error=' .
        urlencode(
            $e->getMessage()
        )
    );
}