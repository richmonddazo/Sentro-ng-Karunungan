<?php

require_once __DIR__ . '/includes/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Require Pending Registration Session
|--------------------------------------------------------------------------
*/

$userId =
    (int)($_SESSION['pending_registration_user_id'] ?? 0);

$email =
    $_SESSION['pending_registration_email'] ?? '';

if ($userId <= 0 || $email === '') {
    redirect('register.php');
}


$errors = [];


/*
|--------------------------------------------------------------------------
| Load Pending User
|--------------------------------------------------------------------------
*/

$userStmt = db()->prepare("
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

$pendingUser = $userStmt->fetch();


if (!$pendingUser) {

    unset(
        $_SESSION['pending_registration_user_id'],
        $_SESSION['pending_registration_email']
    );

    redirect('register.php');
}


/*
|--------------------------------------------------------------------------
| Already Verified
|--------------------------------------------------------------------------
*/

if ((int)$pendingUser['is_verified'] === 1) {

    unset(
        $_SESSION['pending_registration_user_id'],
        $_SESSION['pending_registration_email']
    );

    redirect('login.php?verified=1');
}


/*
|--------------------------------------------------------------------------
| Verify Submitted OTP
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $otp = trim($_POST['otp'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Basic OTP Validation
    |--------------------------------------------------------------------------
    */

    if (!preg_match('/^\d{6}$/', $otp)) {

        $errors[] =
            'Please enter the 6-digit verification code.';

    } else {

        $pdo = db();

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Get Latest Active Registration OTP
            |--------------------------------------------------------------------------
            */

            $otpStmt = $pdo->prepare("
                SELECT
                    id,
                    otp_hash,
                    expires_at,
                    attempts,
                    max_attempts,

                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        expires_at
                    ) AS seconds_remaining

                FROM otp_codes

                WHERE user_id = ?
                  AND email = ?
                  AND purpose = 'registration'
                  AND used_at IS NULL

                ORDER BY id DESC

                LIMIT 1

                FOR UPDATE
            ");

            $otpStmt->execute([
                $userId,
                $email
            ]);

            $otpRecord = $otpStmt->fetch();


            /*
            |--------------------------------------------------------------------------
            | OTP Does Not Exist
            |--------------------------------------------------------------------------
            */

            if (!$otpRecord) {

                throw new Exception(
                    'No active verification code was found. Please register again.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Maximum Attempts
            |--------------------------------------------------------------------------
            */

            if (
                (int)$otpRecord['attempts'] >=
                (int)$otpRecord['max_attempts']
            ) {

                /*
                | Disable this OTP.
                */

                $expireStmt = $pdo->prepare("
                    UPDATE otp_codes
                    SET used_at = NOW()
                    WHERE id = ?
                ");

                $expireStmt->execute([
                    $otpRecord['id']
                ]);

                $pdo->commit();

                $errors[] =
                    'This verification code has reached the maximum number of attempts. Please request a new code.';
            }


            /*
            |--------------------------------------------------------------------------
            | OTP Expired
            |--------------------------------------------------------------------------
            */

            elseif (
                (int)$otpRecord['seconds_remaining'] <= 0
            ) {

                $expireStmt = $pdo->prepare("
                    UPDATE otp_codes
                    SET used_at = NOW()
                    WHERE id = ?
                ");

                $expireStmt->execute([
                    $otpRecord['id']
                ]);

                $pdo->commit();

                $errors[] =
                    'This verification code has expired. Please request a new code.';
            }


            /*
            |--------------------------------------------------------------------------
            | Verify OTP
            |--------------------------------------------------------------------------
            */

            elseif (
                !password_verify(
                    $otp,
                    $otpRecord['otp_hash']
                )
            ) {

                /*
                |--------------------------------------------------------------------------
                | Wrong Code
                |--------------------------------------------------------------------------
                */

                $attemptStmt = $pdo->prepare("
                    UPDATE otp_codes

                    SET attempts = attempts + 1

                    WHERE id = ?
                ");

                $attemptStmt->execute([
                    $otpRecord['id']
                ]);


                $newAttempts =
                    (int)$otpRecord['attempts'] + 1;

                $remainingAttempts =
                    max(
                        0,
                        (int)$otpRecord['max_attempts']
                        - $newAttempts
                    );


                /*
                | If that was the final attempt,
                | invalidate the OTP.
                */

                if ($remainingAttempts <= 0) {

                    $invalidateStmt = $pdo->prepare("
                        UPDATE otp_codes
                        SET used_at = NOW()
                        WHERE id = ?
                    ");

                    $invalidateStmt->execute([
                        $otpRecord['id']
                    ]);
                }


                $pdo->commit();


                if ($remainingAttempts > 0) {

                    $errors[] =
                        'Incorrect verification code. ' .
                        $remainingAttempts .
                        ' attempt' .
                        ($remainingAttempts === 1 ? '' : 's') .
                        ' remaining.';

                } else {

                    $errors[] =
                        'Incorrect verification code. You have reached the maximum number of attempts.';
                }

            }


            /*
            |--------------------------------------------------------------------------
            | Correct OTP
            |--------------------------------------------------------------------------
            */

            else {


                /*
                |--------------------------------------------------------------------------
                | Mark User Verified
                |--------------------------------------------------------------------------
                */

                $verifyUserStmt = $pdo->prepare("
                    UPDATE users

                    SET is_verified = 1

                    WHERE id = ?
                      AND email = ?
                      AND is_verified = 0
                ");

                $verifyUserStmt->execute([
                    $userId,
                    $email
                ]);


                /*
                |--------------------------------------------------------------------------
                | Mark OTP Used
                |--------------------------------------------------------------------------
                */

                $usedOtpStmt = $pdo->prepare("
                    UPDATE otp_codes

                    SET used_at = NOW()

                    WHERE id = ?
                ");

                $usedOtpStmt->execute([
                    $otpRecord['id']
                ]);


                /*
                |--------------------------------------------------------------------------
                | Invalidate Any Other Registration OTP
                |--------------------------------------------------------------------------
                */

                $invalidateOtherStmt = $pdo->prepare("
                    UPDATE otp_codes

                    SET used_at = NOW()

                    WHERE user_id = ?
                      AND purpose = 'registration'
                      AND used_at IS NULL
                ");

                $invalidateOtherStmt->execute([
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
                        'account_verified',
                        'user',
                        :entity_id,
                        :details
                    )
                ");

                $auditStmt->execute([
                    ':user_id' => $userId,
                    ':entity_id' => $userId,
                    ':details' =>
                        'User email verified during registration.'
                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | Clear Temporary Registration Session
                |--------------------------------------------------------------------------
                */

                unset(
                    $_SESSION['pending_registration_user_id'],
                    $_SESSION['pending_registration_email']
                );


                /*
                |--------------------------------------------------------------------------
                | Send User to Login
                |--------------------------------------------------------------------------
                */

                redirect(
                    'login.php?verified=1'
                );
            }


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Registration OTP Error: ' .
                $e->getMessage()
            );

            $errors[] =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Mask Email
|--------------------------------------------------------------------------
*/

function mask_email(string $email): string
{
    [$local, $domain] =
        array_pad(
            explode('@', $email, 2),
            2,
            ''
        );

    if (strlen($local) <= 2) {

        $maskedLocal =
            substr($local, 0, 1) . '*';

    } else {

        $maskedLocal =
            substr($local, 0, 2) .
            str_repeat(
                '*',
                max(3, strlen($local) - 2)
            );
    }

    return $maskedLocal . '@' . $domain;
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Verify Email | Sentro ng Karunungan
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <style>

        .otp-input {

            width: 100%;

            padding: 18px;

            border: 2px solid #d8dfeb;

            border-radius: 12px;

            font-size: 30px;

            font-weight: 800;

            letter-spacing: 10px;

            text-align: center;

            box-sizing: border-box;
        }

        .otp-input:focus {

            outline: none;

            border-color: #2454a6;

            box-shadow:
                0 0 0 4px rgba(36,84,166,.1);
        }

    </style>

</head>

<body>


<header class="topbar">

    <strong>
        Sentro ng Karunungan
    </strong>

</header>


<main class="container">


    <div
        class="card"
        style="
            max-width:520px;
            margin:60px auto;
            text-align:center;
        "
    >


        <div
            style="
                width:70px;
                height:70px;
                margin:0 auto 20px;
                border-radius:50%;
                background:#eef3ff;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:32px;
            "
        >
            ✉
        </div>


        <p class="eyebrow">
            EMAIL VERIFICATION
        </p>


        <h1>
            Check Your Email
        </h1>


        <p class="muted">

            We sent a six-digit verification code to

            <strong>
                <?= e(mask_email($email)) ?>
            </strong>.

        </p>


        <p class="muted">
            The code expires after 5 minutes.
        </p>


        <?php if ($errors): ?>

            <div
                style="
                    background:#fff0f0;
                    color:#992929;
                    padding:14px 16px;
                    border-radius:10px;
                    margin:22px 0;
                    text-align:left;
                "
            >

                <?php foreach ($errors as $error): ?>

                    <div>
                        <?= e($error) ?>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            style="margin-top:25px;"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >


            <label
                for="otp"
                style="
                    display:block;
                    text-align:left;
                    margin-bottom:8px;
                "
            >

                <strong>
                    Verification Code
                </strong>

            </label>


            <input
                type="text"
                id="otp"
                name="otp"
                class="otp-input"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                placeholder="000000"
                required
                autofocus
            >


            <button
                type="submit"
                class="btn primary"
                style="
                    width:100%;
                    padding:14px;
                    margin-top:20px;
                "
            >
                Verify Email
            </button>


        </form>


        <div
            style="
                margin-top:25px;
                padding-top:20px;
                border-top:1px solid #e3e8f0;
            "
        >
<p
    class="muted"
    style="margin-bottom:12px;"
>
    Didn't receive the code?
</p>


<?php if (isset($_GET['resent'])): ?>

    <div
        style="
            background:#e8f7f0;
            color:#176638;
            padding:10px;
            border-radius:9px;
            margin-bottom:15px;
        "
    >
        A new verification code has been sent.
    </div>

<?php endif; ?>


<?php if (isset($_GET['resend_error'])): ?>

    <div
        style="
            background:#fff0f0;
            color:#992929;
            padding:10px;
            border-radius:9px;
            margin-bottom:15px;
        "
    >
        We could not resend the verification code.
        Please try again.
    </div>

<?php endif; ?>


<?php if (isset($_GET['resend_wait'])): ?>

    <div
        style="
            background:#fff7df;
            color:#8a6500;
            padding:10px;
            border-radius:9px;
            margin-bottom:15px;
        "
    >
        Please wait
        <?= (int)$_GET['resend_wait'] ?>
        seconds before requesting another code.
    </div>

<?php endif; ?>


<form
    method="POST"
    action="<?= e(
        url('resend-registration-otp.php')
    ) ?>"
>

    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(csrf_token()) ?>"
    >

    <button
        type="submit"
        style="
            border:none;
            background:none;
            color:#2454a6;
            font-weight:700;
            cursor:pointer;
            font:inherit;
        "
    >
        Resend Verification Code
    </button>

</form>
        </div>


    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| OTP Input Cleanup
|--------------------------------------------------------------------------
|
| Allows only numeric characters.
|--------------------------------------------------------------------------
*/

const otpInput =
    document.getElementById('otp');

otpInput.addEventListener(
    'input',
    function () {

        this.value =
            this.value
                .replace(/\D/g, '')
                .slice(0, 6);
    }
);

</script>


</body>

</html>