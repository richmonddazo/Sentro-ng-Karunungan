<?php


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| URL Helper
|--------------------------------------------------------------------------
*/

function url(string $path = ''): string
{
    return
        rtrim(BASE_URL, '/') .
        '/' .
        ltrim($path, '/');
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

function redirect(string $path): never
{
    header(
        'Location: ' . url($path)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| Verify CSRF
|--------------------------------------------------------------------------
*/

function verify_csrf(): void
{
    $token =
        $_POST['csrf_token'] ?? '';

    if (
        !$token ||
        !hash_equals(
            $_SESSION['csrf_token'] ?? '',
            $token
        )
    ) {

        http_response_code(419);

        exit(
            'Invalid or expired form request. ' .
            'Please go back and try again.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Session Has User
|--------------------------------------------------------------------------
*/

function is_logged_in(): bool
{
    return !empty(
        $_SESSION['user_id']
    );
}


/*
|--------------------------------------------------------------------------
| Get Authenticated User from Database
|--------------------------------------------------------------------------
|
| Do not trust role/status only from the PHP session.
| The database remains the source of truth.
|--------------------------------------------------------------------------
*/

function authenticated_user_record(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            status,
            is_verified,
            created_at

        FROM users

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([
        (int)$_SESSION['user_id']
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    return $user;
}


/*
|--------------------------------------------------------------------------
| Clear Authentication Session
|--------------------------------------------------------------------------
*/

function clear_auth_session(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['role'],
        $_SESSION['name'],
        $_SESSION['last_activity'],
        $_SESSION['pending_login_user_id'],
        $_SESSION['pending_login_email']
    );

    /*
    | Generate another session ID after removing
    | authenticated state.
    */

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}


/*
|--------------------------------------------------------------------------
| Require Valid Login
|--------------------------------------------------------------------------
|
| A session alone is no longer enough.
|
| User must:
| - still exist
| - still be active
| - still be verified
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $user =
        authenticated_user_record();


    /*
    |--------------------------------------------------------------------------
    | Account Missing
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        clear_auth_session();

        redirect(
            'login.php?account_invalid=1'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Disabled Account
    |--------------------------------------------------------------------------
    */

    if ($user['status'] !== 'active') {

        clear_auth_session();

        redirect(
            'login.php?account_disabled=1'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Unverified Account
    |--------------------------------------------------------------------------
    */

    if ((int)$user['is_verified'] !== 1) {

        clear_auth_session();

        redirect(
            'login.php?verification_required=1'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Keep Session Role/Name Synchronized
    |--------------------------------------------------------------------------
    |
    | If an administrator changes somebody's role or name,
    | the active session will use the newest database value.
    |--------------------------------------------------------------------------
    */

    $_SESSION['role'] =
        $user['role'];

    $_SESSION['name'] =
        $user['name'];
}


/*
|--------------------------------------------------------------------------
| Is Admin
|--------------------------------------------------------------------------
*/

function is_admin(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $user =
        authenticated_user_record();

    if (!$user) {
        return false;
    }

    return (
        $user['status'] === 'active' &&
        (int)$user['is_verified'] === 1 &&
        $user['role'] === 'admin'
    );
}


/*
|--------------------------------------------------------------------------
| Require Admin
|--------------------------------------------------------------------------
*/

function require_admin(): void
{
    require_login();

    $user =
        authenticated_user_record();

    if (
        !$user ||
        $user['role'] !== 'admin'
    ) {

        http_response_code(403);

        exit(
            'Access denied.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $user =
        authenticated_user_record();

    if (!$user) {
        return null;
    }

    return $user;
}