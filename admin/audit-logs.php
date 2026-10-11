<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$admin = current_user();

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? 'all');


/*
|--------------------------------------------------------------------------
| Get Available Actions
|--------------------------------------------------------------------------
*/

$actionStmt = db()->query("
    SELECT DISTINCT action
    FROM audit_logs
    WHERE action IS NOT NULL
      AND action <> ''
    ORDER BY action ASC
");

$availableActions =
    $actionStmt->fetchAll(
        PDO::FETCH_COLUMN
    );


/*
|--------------------------------------------------------------------------
| Build Audit Log Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        audit_logs.id,
        audit_logs.user_id,
        audit_logs.action,
        audit_logs.entity_type,
        audit_logs.entity_id,
        audit_logs.details,
        audit_logs.created_at,

        users.name AS actor_name,
        users.email AS actor_email,
        users.role AS actor_role

    FROM audit_logs

    LEFT JOIN users
        ON users.id = audit_logs.user_id

    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            audit_logs.action LIKE ?
            OR audit_logs.entity_type LIKE ?
            OR audit_logs.details LIKE ?
            OR users.name LIKE ?
            OR users.email LIKE ?
        )
    ";

    $value =
        '%' . $search . '%';

    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
}


/*
|--------------------------------------------------------------------------
| Action Filter
|--------------------------------------------------------------------------
*/

if (
    $actionFilter !== 'all' &&
    in_array(
        $actionFilter,
        $availableActions,
        true
    )
) {

    $sql .= "
        AND audit_logs.action = ?
    ";

    $params[] =
        $actionFilter;
}


$sql .= "
    ORDER BY
        audit_logs.created_at DESC,
        audit_logs.id DESC

    LIMIT 300
";


$stmt = db()->prepare($sql);

$stmt->execute($params);

$logs = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Human-Friendly Action Label
|--------------------------------------------------------------------------
*/

function audit_action_label(
    string $action
): string {

    $labels = [

        'account_verified'
            => 'Account Verified',

        'login_verified'
            => 'Login Verified',

        'user_disabled'
            => 'User Disabled',

        'user_enabled'
            => 'User Enabled',

        'reservation_created'
            => 'Reservation Created',

        'reservation_approved'
            => 'Reservation Approved',

        'reservation_rejected'
            => 'Reservation Rejected',

        'reservation_ready'
            => 'Ready for Pickup',

        'reservation_borrowed'
            => 'Book Borrowed',

        'reservation_returned'
            => 'Book Returned',

        'reservation_cancelled'
            => 'Reservation Cancelled',

        'book_added'
            => 'Book Added',

        'book_updated'
            => 'Book Updated'
    ];

    return $labels[$action]
        ?? ucwords(
            str_replace(
                '_',
                ' ',
                $action
            )
        );
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
        Audit Logs | Sentro ng Karunungan
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

</head>


<body>


<header class="topbar">

    <strong>
        Sentro ng Karunungan — Admin
    </strong>

    <nav>

        <a href="<?= e(url('admin/dashboard.php')) ?>">
            Dashboard
        </a>

        <a href="<?= e(url('admin/books.php')) ?>">
            Books
        </a>

        <a href="<?= e(url('admin/reservations.php')) ?>">
            Reservations
        </a>

        <a href="<?= e(url('admin/users.php')) ?>">
            Users
        </a>

        <a href="<?= e(url('admin/audit-logs.php')) ?>">
            Activity Logs
        </a>

        <span>
            <?= e($admin['name']) ?>
        </span>

        <a href="<?= e(url('logout.php')) ?>">
            Logout
        </a>

    </nav>

</header>


<main class="container">


    <p class="eyebrow">
        SYSTEM MONITORING
    </p>

    <h1>
        Activity Logs
    </h1>

    <p class="muted">
        Review important actions performed
        inside the library system.
    </p>


    <!-- FILTERS -->

    <form
        method="GET"
        style="
            display:grid;
            grid-template-columns:
                minmax(250px,2fr)
                minmax(180px,1fr)
                auto;
            gap:12px;
            margin:28px 0;
        "
    >

        <input
            type="search"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search user, action or details..."
        >


        <select name="action">

            <option value="all">
                All Actions
            </option>


            <?php foreach (
                $availableActions
                as $action
            ): ?>

                <option
                    value="<?= e($action) ?>"
                    <?= $actionFilter === $action
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= e(
                        audit_action_label(
                            $action
                        )
                    ) ?>

                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="submit"
            class="btn primary"
        >
            Filter
        </button>

    </form>


    <?php if (
        $search !== '' ||
        $actionFilter !== 'all'
    ): ?>

        <div style="margin-bottom:20px;">

            <a
                href="<?= e(
                    url(
                        'admin/audit-logs.php'
                    )
                ) ?>"
            >
                Clear Filters
            </a>

        </div>

    <?php endif; ?>


    <!-- LOGS -->

    <div class="card">


        <?php if (!$logs): ?>

            <div
                style="
                    text-align:center;
                    padding:40px;
                "
            >

                <h3>
                    No Activity Found
                </h3>

                <p class="muted">
                    No audit records match
                    your current filters.
                </p>

            </div>


        <?php else: ?>


            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Date & Time
                            </th>

                            <th>
                                User
                            </th>

                            <th>
                                Action
                            </th>

                            <th>
                                Entity
                            </th>

                            <th>
                                Details
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $logs as $log
                    ): ?>


                        <tr>


                            <td
                                style="
                                    white-space:nowrap;
                                "
                            >

                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $log['created_at']
                                    )
                                ) ?>

                                <div
                                    class="muted"
                                    style="
                                        font-size:12px;
                                        margin-top:3px;
                                    "
                                >

                                    <?= date(
                                        'h:i A',
                                        strtotime(
                                            $log[
                                                'created_at'
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $log['actor_name']
                                        ?? 'System'
                                    ) ?>

                                </strong>


                                <?php if (
                                    $log['actor_email']
                                ): ?>

                                    <div
                                        class="muted"
                                        style="
                                            font-size:12px;
                                        "
                                    >

                                        <?= e(
                                            $log[
                                                'actor_email'
                                            ]
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span
                                    style="
                                        display:inline-block;
                                        padding:6px 10px;
                                        border-radius:999px;
                                        background:#eaf4ff;
                                        color:#2454a6;
                                        font-size:12px;
                                        font-weight:700;
                                    "
                                >

                                    <?= e(
                                        audit_action_label(
                                            $log['action']
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $log['entity_type']
                                    ?: '—'
                                ) ?>

                                <?php if (
                                    $log['entity_id']
                                ): ?>

                                    <div
                                        class="muted"
                                        style="
                                            font-size:12px;
                                        "
                                    >
                                        ID #
                                        <?= (int)$log[
                                            'entity_id'
                                        ] ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    $log['details']
                                    ?: 'No additional details.'
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </div>


</main>


</body>

</html>
