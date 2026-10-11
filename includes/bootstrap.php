<?php

/*
|--------------------------------------------------------------------------
| Session Security
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    /*
    | Determine whether the site is using HTTPS.
    | On localhost/XAMPP this will normally be false.
    */

    $isHttps =
        (
            !empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        )
        ||
        (
            isset($_SERVER['SERVER_PORT']) &&
            (int)$_SERVER['SERVER_PORT'] === 443
        );


    /*
    | Reject unknown/uninitialized session IDs.
    */

    ini_set(
        'session.use_strict_mode',
        '1'
    );


    /*
    | Only use cookies for sessions.
    */

    ini_set(
        'session.use_only_cookies',
        '1'
    );


    /*
    | Configure session cookie.
    */

    session_set_cookie_params([

        'lifetime' => 0,

        'path' => '/',

        'domain' => '',

        /*
        | Secure cookies require HTTPS.
        | Keep false automatically on localhost.
        */

        'secure' => $isHttps,

        /*
        | JavaScript cannot access the PHP session cookie.
        */

        'httponly' => true,

        /*
        | Helps protect against cross-site requests.
        */

        'samesite' => 'Lax'
    ]);


    session_start();
}


/*
|--------------------------------------------------------------------------
| Project Dependencies
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


/*
|--------------------------------------------------------------------------
| Logged-In Session Timeout
|--------------------------------------------------------------------------
|
| Logged-in users are automatically signed out after
| 30 minutes of inactivity.
|
| OTP verification itself still uses its own 5-minute expiration.
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {

    $sessionTimeout = 30 * 60;

    $lastActivity =
        (int)($_SESSION['last_activity'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Session Expired
    |--------------------------------------------------------------------------
    */

    if (
        $lastActivity > 0 &&
        (time() - $lastActivity) > $sessionTimeout
    ) {

        /*
        | Clear session contents.
        */

        $_SESSION = [];


        /*
        | Delete session cookie.
        */

        if (ini_get('session.use_cookies')) {

            $params =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax'
                ]
            );
        }


        /*
        | Destroy old session.
        */

        session_destroy();


        /*
        | Start fresh session.
        */

        session_start();


        /*
        | Send user back to login.
        */

        header(
            'Location: ' .
            url('login.php?session_expired=1')
        );

        exit;
    }


    /*
    | Update activity timestamp.
    */

    $_SESSION['last_activity'] =
        time();
}

/*
|--------------------------------------------------------------------------
| Automatic Reservation Maintenance
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/reservation-maintenance.php';

expire_ready_reservations();
notify_overdue_reservations();
notify_due_soon_reservations();

require_once __DIR__ . '/inventory-maintenance.php';

check_inventory_alerts();