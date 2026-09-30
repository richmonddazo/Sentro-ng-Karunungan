<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| Send OTP Email
|--------------------------------------------------------------------------
*/

function send_otp_email(
    string $recipientEmail,
    string $recipientName,
    string $otp,
    string $purpose = 'login'
): bool {

    $config = require __DIR__ . '/../config/mail.php';

    $mail = new PHPMailer(true);

    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP Configuration
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = $config['host'];

        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];

        $mail->Password = $config['password'];

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = $config['port'];


        /*
        |--------------------------------------------------------------------------
        | Sender
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );


        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );


        /*
        |--------------------------------------------------------------------------
        | Email Content
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        if ($purpose === 'registration') {

            $title = 'Verify Your Sentro ng Karunungan Account';

            $message =
                'Use the verification code below to complete your registration.';

        } else {

            $title = 'Sentro ng Karunungan Login Verification';

            $message =
                'Use the verification code below to complete your login.';
        }


        $safeName = htmlspecialchars(
            $recipientName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeOtp = htmlspecialchars(
            $otp,
            ENT_QUOTES,
            'UTF-8'
        );


        $mail->Subject = $title;


        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
        </head>

        <body
            style="
                margin:0;
                padding:0;
                background:#f4f6f9;
                font-family:Arial, Helvetica, sans-serif;
            "
        >

            <div
                style="
                    max-width:600px;
                    margin:40px auto;
                    background:#ffffff;
                    border-radius:14px;
                    overflow:hidden;
                    box-shadow:0 5px 20px rgba(0,0,0,.08);
                "
            >

                <div
                    style="
                        background:#17366f;
                        color:white;
                        padding:28px;
                        text-align:center;
                    "
                >

                    <h2
                        style="
                            margin:0;
                        "
                    >
                        Sentro ng Karunungan
                    </h2>

                    <p
                        style="
                            margin:8px 0 0;
                            opacity:.85;
                        "
                    >
                        Online Library Reservation System
                    </p>

                </div>


                <div
                    style="
                        padding:35px;
                    "
                >

                    <p>
                        Hello <strong>' . $safeName . '</strong>,
                    </p>

                    <p>
                        ' . $message . '
                    </p>


                    <div
                        style="
                            text-align:center;
                            margin:35px 0;
                        "
                    >

                        <div
                            style="
                                display:inline-block;
                                background:#eef3ff;
                                color:#17366f;
                                font-size:34px;
                                font-weight:bold;
                                letter-spacing:8px;
                                padding:18px 25px;
                                border-radius:12px;
                            "
                        >
                            ' . $safeOtp . '
                        </div>

                    </div>


                    <p>
                        This code will expire in
                        <strong>5 minutes</strong>.
                    </p>

                    <p>
                        Do not share this code with anyone.
                    </p>

                    <p
                        style="
                            color:#687386;
                            font-size:13px;
                            margin-top:30px;
                        "
                    >
                        If you did not request this code,
                        you can ignore this email.
                    </p>

                </div>


                <div
                    style="
                        background:#f7f8fb;
                        padding:18px;
                        text-align:center;
                        color:#7a8492;
                        font-size:12px;
                    "
                >
                    Sentro ng Karunungan
                </div>

            </div>

        </body>
        </html>
        ';


        /*
        |--------------------------------------------------------------------------
        | Plain Text Fallback
        |--------------------------------------------------------------------------
        */

        $mail->AltBody =
            "Hello {$recipientName},\n\n" .
            "{$message}\n\n" .
            "Your verification code is: {$otp}\n\n" .
            "This code expires in 5 minutes.\n" .
            "Do not share this code with anyone.\n\n" .
            "Sentro ng Karunungan";


        /*
        |--------------------------------------------------------------------------
        | Send
        |--------------------------------------------------------------------------
        */

        $mail->send();

        return true;


    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | Development Logging
        |--------------------------------------------------------------------------
        |
        | We log the technical error instead of exposing SMTP information
        | directly to the user.
        |
        */

        error_log(
            'Sentro Mail Error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}