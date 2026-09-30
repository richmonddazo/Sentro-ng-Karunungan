<?php

require_once __DIR__ . '/includes/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/

if (is_logged_in()) {

    redirect(
        is_admin()
            ? 'admin/dashboard.php'
            : 'user/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| Require Pending Login
|--------------------------------------------------------------------------
*/

$userId =
    (int)($_SESSION['pending_login_user_id'] ?? 0);

$email =
    $_SESSION['pending_login_email'] ?? '';

if ($userId <= 0 || $email === '') {
    redirect('login.php');
}


$errors = [];


/*
|--------------------------------------------------------------------------
| Load Account
|--------------------------------------------------------------------------
*/

$userStmt = db()->prepare("
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


if ($account['status'] !== 'active') {

    unset(
        $_SESSION['pending_login_user_id'],
        $_SESSION['pending_login_email']
    );

    exit('This account is currently disabled.');
}


if ((int)$account['is_verified'] !== 1) {

    unset(
        $_SESSION['pending_login_user_id'],
        $_SESSION['pending_login_email']
    );

    redirect('login.php');
}


/*
|--------------------------------------------------------------------------
| Verify Login OTP
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $otp = trim($_POST['otp'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | Validate Format
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
            | Get Latest Active Login OTP
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
                  AND purpose = 'login'
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
            | No OTP
            |--------------------------------------------------------------------------
            */

            if (!$otpRecord) {

                $pdo->rollBack();

                $errors[] =
                    'No active login verification code was found. Please log in again.';
            }


            /*
            |--------------------------------------------------------------------------
            | Maximum Attempts Reached
            |--------------------------------------------------------------------------
            */

            elseif (
                (int)$otpRecord['attempts'] >=
                (int)$otpRecord['max_attempts']
            ) {

                $invalidateStmt =
                    $pdo->prepare("
                        UPDATE otp_codes

                        SET used_at = NOW()

                        WHERE id = ?
                    ");

                $invalidateStmt->execute([
                    $otpRecord['id']
                ]);

                $pdo->commit();

                $errors[] =
                    'This verification code has reached the maximum number of attempts. Please log in again.';
            }


            /*
            |--------------------------------------------------------------------------
            | OTP Expired
            |--------------------------------------------------------------------------
            */

            elseif (
                (int)$otpRecord['seconds_remaining'] <= 0
            ) {

                $expireStmt =
                    $pdo->prepare("
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
            | Incorrect OTP
            |--------------------------------------------------------------------------
            */

            elseif (
                !password_verify(
                    $otp,
                    $otpRecord['otp_hash']
                )
            ) {

                $attemptStmt =
                    $pdo->prepare("
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
                |--------------------------------------------------------------------------
                | Invalidate After Final Attempt
                |--------------------------------------------------------------------------
                */

                if ($remainingAttempts <= 0) {

                    $invalidateStmt =
                        $pdo->prepare("
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
                        'Incorrect verification code. Maximum attempts reached. Please log in again.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | OTP Correct
            |--------------------------------------------------------------------------
            */

            else {


                /*
                |--------------------------------------------------------------------------
                | Mark OTP Used
                |--------------------------------------------------------------------------
                */

                $usedStmt =
                    $pdo->prepare("
                        UPDATE otp_codes

                        SET used_at = NOW()

                        WHERE id = ?
                          AND used_at IS NULL
                    ");

                $usedStmt->execute([
                    $otpRecord['id']
                ]);


                /*
                |--------------------------------------------------------------------------
                | Invalidate Other Login OTPs
                |--------------------------------------------------------------------------
                */

                $invalidateOtherStmt =
                    $pdo->prepare("
                        UPDATE otp_codes

                        SET used_at = NOW()

                        WHERE user_id = ?
                          AND purpose = 'login'
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

                $auditStmt =
                    $pdo->prepare("
                        INSERT INTO audit_logs (
                            user_id,
                            action,
                            entity_type,
                            entity_id,
                            details
                        )

                        VALUES (
                            :user_id,
                            'login_verified',
                            'user',
                            :entity_id,
                            :details
                        )
                    ");

                $auditStmt->execute([
                    ':user_id' => $userId,
                    ':entity_id' => $userId,
                    ':details' =>
                        'Login completed using email OTP verification.'
                ]);


                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | Prevent Session Fixation
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);


                /*
                |--------------------------------------------------------------------------
                | Complete Login
                |--------------------------------------------------------------------------
                */

                $_SESSION['user_id'] =
                    (int)$account['id'];

                $_SESSION['role'] =
                    $account['role'];

                $_SESSION['name'] =
                    $account['name'];


                /*
                |--------------------------------------------------------------------------
                | Remove Temporary Login Session
                |--------------------------------------------------------------------------
                */

                unset(
                    $_SESSION['pending_login_user_id'],
                    $_SESSION['pending_login_email']
                );


                /*
                |--------------------------------------------------------------------------
                | Redirect Based on Role
                |--------------------------------------------------------------------------
                */

                if ($account['role'] === 'admin') {

                    redirect(
                        'admin/dashboard.php'
                    );
                }

                redirect(
                    'user/dashboard.php'
                );
            }


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Login Verification Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Login verification could not be completed. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Mask Email
|--------------------------------------------------------------------------
*/

function mask_login_email(string $email): string
{
    [$local, $domain] =
        array_pad(
            explode('@', $email, 2),
            2,
            ''
        );

    if (strlen($local) <= 2) {

        $masked =
            substr($local, 0, 1) . '*';

    } else {

        $masked =
            substr($local, 0, 2) .
            str_repeat(
                '*',
                max(
                    3,
                    strlen($local) - 2
                )
            );
    }

    return $masked . '@' . $domain;
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
        Login Verification | Sentro ng Karunungan
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <style>

        .otp-input {

            width:100%;

            padding:18px;

            border:2px solid #d8dfeb;

            border-radius:12px;

            font-size:30px;

            font-weight:800;

            letter-spacing:10px;

            text-align:center;

            box-sizing:border-box;
        }

        .otp-input:focus {

            outline:none;

            border-color:#2454a6;

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
            🔐
        </div>


        <p class="eyebrow">
            LOGIN VERIFICATION
        </p>


        <h1>
            Verify Your Login
        </h1>


        <p class="muted">

            We sent a six-digit verification code to

            <strong>
                <?= e(
                    mask_login_email($email)
                ) ?>
            </strong>.

        </p>


        <p class="muted">
            The code will expire in 5 minutes.
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
                Verify & Login
            </button>

        </form>


        <div
            style="
                margin-top:25px;

                padding-top:20px;

                border-top:
                    1px solid #e3e8f0;
            "
        >

            <p
                class="muted"
                style="
                    margin-bottom:8px;
                "
            >
                Didn't receive the code?
            </p>


            <span
                class="muted"
                style="
                    font-size:14px;
                "
            >
                We'll add Resend Login OTP next.
            </span>


            <div style="margin-top:16px;">

                <a
                    href="<?= e(url('login.php')) ?>"
                >
                    ← Back to Login
                </a>

            </div>

        </div>


    </div>


</main>


<script>

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