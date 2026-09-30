<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mailer.php';


/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {

    if (is_admin()) {
        redirect('admin/dashboard.php');
    }

    redirect('user/dashboard.php');
}


$errors = [];

$email = '';


/*
|--------------------------------------------------------------------------
| Login Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $email = strtolower(
        trim($_POST['email'] ?? '')
    );

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($email === '') {

        $errors[] = 'Email address is required.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $errors[] = 'Please enter a valid email address.';
    }


    if ($password === '') {
        $errors[] = 'Password is required.';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Credentials
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $pdo = db();

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                password_hash,
                role,
                status,
                is_verified

            FROM users

            WHERE email = ?

            LIMIT 1
        ");

        $stmt->execute([
            $email
        ]);

        $account = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Invalid Email / Password
        |--------------------------------------------------------------------------
        */

        if (
            !$account ||
            !password_verify(
                $password,
                $account['password_hash']
            )
        ) {

            $errors[] =
                'Incorrect email address or password.';
        }


        /*
        |--------------------------------------------------------------------------
        | Disabled Account
        |--------------------------------------------------------------------------
        */

        elseif ($account['status'] !== 'active') {

            $errors[] =
                'This account is currently disabled.';
        }


        /*
        |--------------------------------------------------------------------------
        | Email Not Verified
        |--------------------------------------------------------------------------
        */

        elseif ((int)$account['is_verified'] !== 1) {

            $errors[] =
                'Your email address has not been verified yet. Please complete registration first.';
        }


        /*
        |--------------------------------------------------------------------------
        | Credentials Correct
        |--------------------------------------------------------------------------
        */

        else {

            try {

                $userId =
                    (int)$account['id'];


                /*
                |--------------------------------------------------------------------------
                | Login OTP Cooldown
                |--------------------------------------------------------------------------
                |
                | Prevent repeatedly sending OTP emails every few seconds.
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

                $latestOtp =
                    $latestStmt->fetch();


                if (
                    $latestOtp &&
                    (int)$latestOtp['seconds_since_created'] < 30
                ) {

                    $remaining =
                        30 -
                        (int)$latestOtp['seconds_since_created'];

                    $errors[] =
                        'Please wait ' .
                        $remaining .
                        ' seconds before requesting another login code.';

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | Generate OTP
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

                    $invalidateStmt =
                        $pdo->prepare("
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
                    | Store New Login OTP
                    |--------------------------------------------------------------------------
                    */

                    $otpStmt =
                        $pdo->prepare("
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
                        ':email' => $account['email'],
                        ':otp_hash' => $otpHash
                    ]);

                    $otpId =
                        (int)$pdo->lastInsertId();


                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | Send OTP Email
                    |--------------------------------------------------------------------------
                    */

                    $sent = send_otp_email(
                        $account['email'],
                        $account['name'],
                        $otp,
                        'login'
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Email Failed
                    |--------------------------------------------------------------------------
                    */

                    if (!$sent) {

                        $failedStmt =
                            $pdo->prepare("
                                UPDATE otp_codes

                                SET used_at = NOW()

                                WHERE id = ?
                            ");

                        $failedStmt->execute([
                            $otpId
                        ]);

                        $errors[] =
                            'We could not send your login verification code. Please try again.';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Email Sent
                    |--------------------------------------------------------------------------
                    */

                    else {

                        /*
                        | Do NOT log the user in yet.
                        |
                        | We only save temporary authentication information.
                        */

                        $_SESSION[
                            'pending_login_user_id'
                        ] = $userId;

                        $_SESSION[
                            'pending_login_email'
                        ] = $account['email'];


                        /*
                        |--------------------------------------------------------------------------
                        | Redirect to Login OTP
                        |--------------------------------------------------------------------------
                        */

                        redirect(
                            'verify-login.php'
                        );
                    }
                }


            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'Login OTP Error: ' .
                    $e->getMessage()
                );

                $errors[] =
                    'Login could not be completed. Please try again.';
            }
        }
    }
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
        Login | Sentro ng Karunungan
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

</head>

<body>


<header class="topbar">

    <strong>
        Sentro ng Karunungan
    </strong>

    <nav>

        <a href="<?= e(url('index.php')) ?>">
            Home
        </a>

        <a href="<?= e(url('register.php')) ?>">
            Create Account
        </a>

    </nav>

</header>


<main class="container">


    <div
        class="card"
        style="
            max-width:520px;
            margin:60px auto;
        "
    >

        <p class="eyebrow">
            WELCOME BACK
        </p>

        <h1>
            Login
        </h1>

        <p class="muted">
            Enter your account credentials.
            A verification code will be sent to
            your registered email address.
        </p>


        <?php if (isset($_GET['verified'])): ?>

            <div
                style="
                    background:#e8f7f0;
                    color:#176638;
                    padding:14px 16px;
                    border-radius:10px;
                    margin:20px 0;
                "
            >
                Your email has been verified successfully.
                You can now log in.
            </div>

        <?php endif; ?>


        <?php if ($errors): ?>

            <div
                style="
                    background:#fff0f0;
                    color:#992929;
                    padding:14px 16px;
                    border-radius:10px;
                    margin:20px 0;
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


            <!-- EMAIL -->

            <div style="margin-bottom:18px;">

                <label for="email">

                    <strong>
                        Email Address
                    </strong>

                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    autocomplete="email"
                    required
                    style="
                        width:100%;
                        padding:12px 13px;
                        margin-top:7px;
                        border:1px solid #d8dfeb;
                        border-radius:10px;
                        font:inherit;
                        box-sizing:border-box;
                    "
                >

            </div>


            <!-- PASSWORD -->

            <div style="margin-bottom:24px;">

                <label for="password">

                    <strong>
                        Password
                    </strong>

                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    style="
                        width:100%;
                        padding:12px 13px;
                        margin-top:7px;
                        border:1px solid #d8dfeb;
                        border-radius:10px;
                        font:inherit;
                        box-sizing:border-box;
                    "
                >

            </div>


            <button
                type="submit"
                class="btn primary"
                style="
                    width:100%;
                    padding:13px;
                "
            >
                Continue & Send OTP
            </button>


        </form>


        <p
            class="muted"
            style="
                text-align:center;
                margin-top:22px;
            "
        >

            Don't have an account?

            <a href="<?= e(url('register.php')) ?>">
                Create Account
            </a>

        </p>


    </div>


</main>

</body>

</html>