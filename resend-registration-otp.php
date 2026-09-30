<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('verify-registration.php');
}

verify_csrf();

$userId =
    (int)($_SESSION['pending_registration_user_id'] ?? 0);

$email =
    $_SESSION['pending_registration_email'] ?? '';

if ($userId <= 0 || $email === '') {
    redirect('register.php');
}

$pdo = db();

try {

    /*
    |--------------------------------------------------------------------------
    | Get Pending User
    |--------------------------------------------------------------------------
    */

    $userStmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
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

    $user = $userStmt->fetch();

    if (!$user) {
        throw new Exception(
            'Account could not be found.'
        );
    }

    if ((int)$user['is_verified'] === 1) {

        unset(
            $_SESSION['pending_registration_user_id'],
            $_SESSION['pending_registration_email']
        );

        redirect('login.php?verified=1');
    }


    /*
    |--------------------------------------------------------------------------
    | RESEND COOLDOWN
    |--------------------------------------------------------------------------
    |
    | User must wait at least 60 seconds between OTP emails.
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
          AND purpose = 'registration'
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
            60 - (int)$latestOtp['seconds_since_created'];

        redirect(
            'verify-registration.php?resend_wait=' .
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
    | Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Invalidate Old Registration OTPs
    |--------------------------------------------------------------------------
    */

    $invalidateStmt = $pdo->prepare("
        UPDATE otp_codes
        SET used_at = NOW()
        WHERE user_id = ?
          AND purpose = 'registration'
          AND used_at IS NULL
    ");

    $invalidateStmt->execute([
        $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | Save New OTP
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
            'registration',
            :otp_hash,
            DATE_ADD(NOW(), INTERVAL 5 MINUTE),
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
    | Send OTP
    |--------------------------------------------------------------------------
    */

    $sent = send_otp_email(
        $email,
        $user['name'],
        $otp,
        'registration'
    );

    if (!$sent) {

        /*
        | Invalidate code if email sending failed.
        */

        $failedStmt = $pdo->prepare("
            UPDATE otp_codes
            SET used_at = NOW()
            WHERE id = ?
        ");

        $failedStmt->execute([
            $otpId
        ]);

        redirect(
            'verify-registration.php?resend_error=1'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    redirect(
        'verify-registration.php?resent=1'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Registration OTP Resend Error: ' .
        $e->getMessage()
    );

    redirect(
        'verify-registration.php?resend_error=1'
    );
}