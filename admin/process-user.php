<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/users.php');
}

verify_csrf();

$admin = current_user();

$userId =
    (int)($_POST['user_id'] ?? 0);

$action =
    trim($_POST['action'] ?? '');


/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/

if ($userId <= 0) {
    redirect(
        'admin/users.php?error=' .
        urlencode('Invalid user account.')
    );
}

if (!in_array(
    $action,
    ['disable', 'enable'],
    true
)) {

    redirect(
        'admin/users.php?error=' .
        urlencode('Invalid account action.')
    );
}


$pdo = db();


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Target User
    |--------------------------------------------------------------------------
    |
    | role = user means this endpoint cannot accidentally modify an admin.
    |--------------------------------------------------------------------------
    */

    $userStmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            role,
            status,
            is_verified

        FROM users

        WHERE id = ?
          AND role = 'user'

        LIMIT 1

        FOR UPDATE
    ");

    $userStmt->execute([
        $userId
    ]);

    $member = $userStmt->fetch();


    if (!$member) {

        throw new Exception(
            'User account was not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DISABLE USER
    |--------------------------------------------------------------------------
    */

    if ($action === 'disable') {


        /*
        |--------------------------------------------------------------------------
        | Already Disabled
        |--------------------------------------------------------------------------
        */

        if ($member['status'] === 'disabled') {

            throw new Exception(
                'This user account is already disabled.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Active Library Transactions
        |--------------------------------------------------------------------------
        |
        | Don't disable someone while they still have:
        |
        | pending
        | approved
        | ready
        | borrowed
        |
        | Admin should finish/reject/cancel those transactions first.
        |--------------------------------------------------------------------------
        */

        $activeStmt = $pdo->prepare("
            SELECT
                COUNT(*)

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

        $activeTransactions =
            (int)$activeStmt->fetchColumn();


        if ($activeTransactions > 0) {

            throw new Exception(
                'This user still has ' .
                $activeTransactions .
                ' active reservation or borrowing transaction' .
                ($activeTransactions === 1 ? '' : 's') .
                '. Resolve them before disabling the account.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Disable Account
        |--------------------------------------------------------------------------
        */

        $updateStmt = $pdo->prepare("
            UPDATE users

            SET status = 'disabled'

            WHERE id = ?
              AND role = 'user'
              AND status = 'active'
        ");

        $updateStmt->execute([
            $userId
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to disable this user.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Invalidate Active OTP Codes
        |--------------------------------------------------------------------------
        */

        $otpStmt = $pdo->prepare("
            UPDATE otp_codes

            SET used_at = NOW()

            WHERE user_id = ?
              AND used_at IS NULL
        ");

        $otpStmt->execute([
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
                :admin_id,
                'user_disabled',
                'user',
                :entity_id,
                :details
            )
        ");

        $auditStmt->execute([
            ':admin_id' => $admin['id'],
            ':entity_id' => $userId,
            ':details' =>
                'Disabled user account: ' .
                $member['name'] .
                ' (' .
                $member['email'] .
                ')'
        ]);


        $pdo->commit();


        redirect(
            'admin/users.php?result=disabled'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ENABLE USER
    |--------------------------------------------------------------------------
    */

    if ($action === 'enable') {


        if ($member['status'] === 'active') {

            throw new Exception(
                'This user account is already active.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Enable Account
        |--------------------------------------------------------------------------
        */

        $updateStmt = $pdo->prepare("
            UPDATE users

            SET status = 'active'

            WHERE id = ?
              AND role = 'user'
              AND status = 'disabled'
        ");

        $updateStmt->execute([
            $userId
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to enable this user.'
            );
        }


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
                :admin_id,
                'user_enabled',
                'user',
                :entity_id,
                :details
            )
        ");

        $auditStmt->execute([
            ':admin_id' => $admin['id'],
            ':entity_id' => $userId,
            ':details' =>
                'Enabled user account: ' .
                $member['name'] .
                ' (' .
                $member['email'] .
                ')'
        ]);


        $pdo->commit();


        redirect(
            'admin/users.php?result=enabled'
        );
    }


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    error_log(
        'User Management Error: ' .
        $e->getMessage()
    );


    redirect(
        'admin/users.php?error=' .
        urlencode(
            $e->getMessage()
        )
    );
}