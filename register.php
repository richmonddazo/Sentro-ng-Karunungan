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

$name = '';
$email = '';
$phone = '';


/*
|--------------------------------------------------------------------------
| Registration Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(
        trim($_POST['email'] ?? '')
    );
    $phone = trim($_POST['phone'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {
        $errors[] = 'Full name is required.';
    }

    if ($email === '') {

        $errors[] = 'Email address is required.';

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $errors[] = 'Please enter a valid email address.';
    }


    if ($phone === '') {

        $errors[] = 'Phone number is required.';

    } elseif (
        !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)
    ) {

        $errors[] = 'Please enter a valid phone number.';
    }


    if (strlen($password) < 8) {

        $errors[] =
            'Password must contain at least 8 characters.';
    }


    if ($password !== $confirmPassword) {

        $errors[] =
            'Password and confirmation do not match.';
    }


    /*
    |--------------------------------------------------------------------------
    | Continue Registration
    |--------------------------------------------------------------------------
    */

    if (!$errors) {

        $pdo = db();

        /*
        |--------------------------------------------------------------------------
        | Check Existing Email
        |--------------------------------------------------------------------------
        */

        $existingStmt = $pdo->prepare("
            SELECT
                id,
                email,
                is_verified
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $existingStmt->execute([
            $email
        ]);

        $existingUser = $existingStmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Email Already Has Verified Account
        |--------------------------------------------------------------------------
        */

        if (
            $existingUser &&
            (int)$existingUser['is_verified'] === 1
        ) {

            $errors[] =
                'An account with this email already exists.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Password Hash
                |--------------------------------------------------------------------------
                */

                $passwordHash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | Existing Unverified Account
                |--------------------------------------------------------------------------
                |
                | If somebody started registration earlier but never verified
                | the OTP, update the existing unverified account instead of
                | creating duplicate users.
                |--------------------------------------------------------------------------
                */

                if ($existingUser) {

                    $userId =
                        (int)$existingUser['id'];
$updateStmt = $pdo->prepare("
    UPDATE users

    SET
        name = :name,
        phone = :phone,
        password_hash = :password_hash,
        role = 'user',
        status = 'active',
        is_verified = 0

    WHERE id = :id
");

$updateStmt->execute([
    ':name' => $name,
    ':phone' => $phone,
    ':password_hash' => $passwordHash,
    ':id' => $userId
]);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Create Unverified Account
                    |--------------------------------------------------------------------------
                    */
$insertStmt = $pdo->prepare("
    INSERT INTO users (
        name,
        email,
        phone,
        password_hash,
        role,
        status,
        is_verified
    )

    VALUES (
        :name,
        :email,
        :phone,
        :password_hash,
        'user',
        'active',
        0
    )
");

$insertStmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':phone' => $phone,
    ':password_hash' => $passwordHash
]);

                    $userId =
                        (int)$pdo->lastInsertId();
                }


                /*
                |--------------------------------------------------------------------------
                | Invalidate Previous Registration OTPs
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
                | Generate OTP
                |--------------------------------------------------------------------------
                */

                $otp =
                    (string)random_int(
                        100000,
                        999999
                    );


                /*
                |--------------------------------------------------------------------------
                | Hash OTP Before Saving
                |--------------------------------------------------------------------------
                |
                | The actual six-digit OTP is only sent by email.
                | The database stores the hash instead.
                |--------------------------------------------------------------------------
                */

                $otpHash =
                    password_hash(
                        $otp,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | Save OTP
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


                /*
                |--------------------------------------------------------------------------
                | Send OTP Email
                |--------------------------------------------------------------------------
                */

                $sent = send_otp_email(
                    $email,
                    $name,
                    $otp,
                    'registration'
                );


                /*
                |--------------------------------------------------------------------------
                | Email Failed
                |--------------------------------------------------------------------------
                */

                if (!$sent) {

                    /*
                    | Invalidate the OTP because the user never received it.
                    */

                    $failedStmt = $pdo->prepare("
                        UPDATE otp_codes
                        SET used_at = NOW()
                        WHERE id = ?
                    ");

                    $failedStmt->execute([
                        $otpId
                    ]);

                    $errors[] =
                        'We could not send the verification email. Please try again.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Store Temporary Registration State
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION[
                        'pending_registration_user_id'
                    ] = $userId;

                    $_SESSION[
                        'pending_registration_email'
                    ] = $email;


                    /*
                    |--------------------------------------------------------------------------
                    | Redirect to OTP Verification
                    |--------------------------------------------------------------------------
                    */

                    redirect(
                        'verify-registration.php'
                    );
                }


            } catch (Throwable $e) {

                error_log(
                    'Registration Error: ' .
                    $e->getMessage()
                );

                $errors[] =
                    'Registration could not be completed. Please try again.';
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
        Create Account | Sentro ng Karunungan
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

        <a href="<?= e(url('login.php')) ?>">
            Login
        </a>

    </nav>

</header>


<main class="container">


    <div
        class="card"
        style="
            max-width:620px;
            margin:45px auto;
        "
    >

        <p class="eyebrow">
            CREATE ACCOUNT
        </p>

        <h1>
            Join Sentro ng Karunungan
        </h1>

        <p class="muted">
            Create your library account.
            We'll verify your email using a
            six-digit verification code.
        </p>


        <?php if ($errors): ?>

            <div
                style="
                    background:#fff0f0;
                    color:#992929;
                    padding:15px 18px;
                    border-radius:10px;
                    margin:20px 0;
                "
            >

                <strong>
                    Please check the following:
                </strong>

                <ul
                    style="
                        margin-bottom:0;
                    "
                >

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

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


            <!-- NAME -->

            <div style="margin-bottom:18px;">

                <label for="name">

                    <strong>
                        Full Name
                    </strong>

                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($name) ?>"
                    autocomplete="name"
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


            <!-- PHONE -->

            <div style="margin-bottom:18px;">

                <label for="phone">

                    <strong>
                        Phone Number
                    </strong>

                </label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    value="<?= e($phone) ?>"
                    autocomplete="tel"
                    placeholder="09XXXXXXXXX"
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

            <div style="margin-bottom:18px;">

                <label for="password">

                    <strong>
                        Password
                    </strong>

                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
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

                <small class="muted">
                    Minimum of 8 characters.
                </small>

            </div>


            <!-- CONFIRM PASSWORD -->

            <div style="margin-bottom:24px;">

                <label for="confirm_password">

                    <strong>
                        Confirm Password
                    </strong>

                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
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
                Create Account & Send OTP
            </button>

        </form>


        <p
            class="muted"
            style="
                text-align:center;
                margin-top:22px;
            "
        >

            Already have an account?

            <a href="<?= e(url('login.php')) ?>">
                Login
            </a>

        </p>


    </div>


</main>

</body>

</html>