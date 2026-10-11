<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mailer.php';


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('verify-login.php');
}

verify_csrf();


/*
|--------------------------------------------------------------------------
| Pending Login Session
|--------------------------------------------------------------------------
*/

$userId =
    (int)($_SESSION['pending_login_user_id'] ?? 0);

$email =
    $_SESSION['pending_login_email'] ?? '';

if ($userId <= 0 || $email === '') {
    redirect('login.php');
}


$pdo = db();

try {

    /*
    |--------------------------------------------------------------------------
    | Get Account
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
          AND email = ?

        LIMIT 1
    ");

    $userStmt->execute([
        $userId,
        $email
    ]);

    $account = $userStmt->fetch();


    if (!$account) {

        unset(
            $_SESSION['pending_login_user_id'],
            $_SESSION['pending_login_email']
        );

        redirect('login.php');
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Account
    |--------------------------------------------------------------------------
    */

    if (
        $account['status'] !== 'active' ||
        (int)$account['is_verified'] !== 1
    ) {

        unset(
            $_SESSION['pending_login_user_id'],
            $_SESSION['pending_login_email']
        );

        redirect('login.php');
    }


    /*
    |--------------------------------------------------------------------------
    | 60-Second Cooldown
    |--------------------------------------------------------------------------
    */

    $latestStmt = $pdo->prepare("
        SELECT
            created_at,

            TIMESTAMPDIFF(
                SECOND,
                created_at,
                NOW()
            ) AS seconds_since_created

        FROM otp_codes

        WHERE user_id = ?
          AND purpose = 'login'

        ORDER BY id DESC

        LIMIT 1
    ");

    $latestStmt->execute([
        $userId
    ]);

    $latestOtp = $latestStmt->fetch();


    if (
        $latestOtp &&
        (int)$latestOtp['seconds_since_created'] < 60
    ) {

        $remaining =
            60 -
            (int)$latestOtp['seconds_since_created'];

        redirect(
            'verify-login.php?resend_wait=' .
            $remaining
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate New OTP
    |--------------------------------------------------------------------------
    */

    $otp =
        (string)random_int(
            100000,
            999999
        );

    $otpHash =
        password_hash(
            $otp,
            PASSWORD_DEFAULT
        );


    /*
    |--------------------------------------------------------------------------
    | Start Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Invalidate Previous Login OTPs
    |--------------------------------------------------------------------------
    */

    $invalidateStmt = $pdo->prepare("
        UPDATE otp_codes

        SET used_at = NOW()

        WHERE user_id = ?
          AND purpose = 'login'
          AND used_at IS NULL
    ");

    $invalidateStmt->execute([
        $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Save New Login OTP
    |--------------------------------------------------------------------------
    */

    $otpStmt = $pdo->prepare("
        INSERT INTO otp_codes (
            user_id,
            email,
            purpose,
            otp_hash,
            expires_at,
            attempts,
            max_attempts
        )

        VALUES (
            :user_id,
            :email,
            'login',
            :otp_hash,
            DATE_ADD(
                NOW(),
                INTERVAL 5 MINUTE
            ),
            0,
            5
        )
    ");

    $otpStmt->execute([
        ':user_id' => $userId,
        ':email' => $email,
        ':otp_hash' => $otpHash
    ]);

    $otpId =
        (int)$pdo->lastInsertId();

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Send Email
    |--------------------------------------------------------------------------
    */

    $sent = send_otp_email(
        $email,
        $account['name'],
        $otp,
        'login'
    );


    /*
    |--------------------------------------------------------------------------
    | Sending Failed
    |--------------------------------------------------------------------------
    */

    if (!$sent) {

        $failedStmt = $pdo->prepare("
            UPDATE otp_codes

            SET used_at = NOW()

            WHERE id = ?
        ");

        $failedStmt->execute([
            $otpId
        ]);

        redirect(
            'verify-login.php?resend_error=1'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    redirect(
        'verify-login.php?resent=1'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Login OTP Resend Error: ' .
        $e->getMessage()
    );

    redirect(
        'verify-login.php?resend_error=1'
    );
}