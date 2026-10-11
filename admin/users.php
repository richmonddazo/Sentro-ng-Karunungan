<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$admin = current_user();

$search =
    trim($_GET['search'] ?? '');

$statusFilter =
    trim($_GET['status'] ?? 'all');

$allowedStatuses = [
    'all',
    'active',
    'disabled'
];

if (!in_array(
    $statusFilter,
    $allowedStatuses,
    true
)) {
    $statusFilter = 'all';
}


/*
|--------------------------------------------------------------------------
| USER STATISTICS
|--------------------------------------------------------------------------
*/

$totalUsers =
    (int)db()->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
    ")->fetchColumn();


$activeUsers =
    (int)db()->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
          AND status = 'active'
    ")->fetchColumn();


$disabledUsers =
    (int)db()->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
          AND status = 'disabled'
    ")->fetchColumn();


$verifiedUsers =
    (int)db()->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
          AND is_verified = 1
    ")->fetchColumn();
    
$suspendedUsers =
    (int)db()->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
          AND reservation_blocked_until IS NOT NULL
          AND reservation_blocked_until > NOW()
    ")->fetchColumn();

/*
|--------------------------------------------------------------------------
| BUILD USER QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        users.id,
        users.name,
        users.email,
        users.phone,
        users.status,
        users.is_verified,
        users.no_show_count,
        users.reservation_blocked_until,
        users.created_at,

        COUNT(
            DISTINCT reservations.id
        ) AS total_reservations,

        SUM(
            reservations.status = 'pending'
        ) AS pending_reservations,

        SUM(
            reservations.status = 'borrowed'
        ) AS borrowed_books

    FROM users

    LEFT JOIN reservations
        ON reservations.user_id = users.id

    WHERE users.role = 'user'
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
            users.name LIKE ?
            OR users.email LIKE ?
            OR users.phone LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($statusFilter !== 'all') {

    $sql .= "
        AND users.status = ?
    ";

    $params[] = $statusFilter;
}


$sql .= "
    GROUP BY
        users.id,
        users.name,
        users.email,
        users.phone,
        users.status,
        users.is_verified,
        users.no_show_count,
        users.reservation_blocked_until,
        users.created_at

    ORDER BY users.created_at DESC
";

$stmt = db()->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll();

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
        Manage Users | Sentro ng Karunungan
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
        USER MANAGEMENT
    </p>

    <h1>
        Manage Users
    </h1>

    <p class="muted">
        View registered library users and monitor
        their account and borrowing activity.
    </p>

    <?php if (isset($_GET['result'])): ?>

    <div
        style="
            background:#e8f7f0;
            color:#176638;
            padding:14px 16px;
            border-radius:10px;
            margin-top:20px;
        "
    >

        <?php if ($_GET['result'] === 'disabled'): ?>

            User account disabled successfully.

        <?php elseif ($_GET['result'] === 'enabled'): ?>

            User account enabled successfully.

        <?php elseif (
            $_GET['result'] ===
            'suspension_lifted'
        ): ?>

            Reservation suspension lifted successfully.

        <?php endif; ?>

    </div>

<?php endif; ?>


<?php if (!empty($_GET['error'])): ?>

    <div
        style="
            background:#fff0f0;
            color:#992929;
            padding:14px 16px;
            border-radius:10px;
            margin-top:20px;
        "
    >

        <?= e($_GET['error']) ?>

    </div>

<?php endif; ?>


    <!-- USER STATS -->

    <section
        style="
            display:grid;
            grid-template-columns:
                repeat(auto-fit, minmax(170px, 1fr));
            gap:16px;
            margin:30px 0;
        "
    >


        <div class="card">

            <div
                style="
                    font-size:32px;
                    font-weight:800;
                "
            >
                <?= $totalUsers ?>
            </div>

            <div class="muted">
                Total Users
            </div>

        </div>


        <div class="card">

            <div
                style="
                    font-size:32px;
                    font-weight:800;
                "
            >
                <?= $activeUsers ?>
            </div>

            <div class="muted">
                Active
            </div>

        </div>


        <div class="card">

            <div
                style="
                    font-size:32px;
                    font-weight:800;
                "
            >
                <?= $verifiedUsers ?>
            </div>

            <div class="muted">
                Verified
            </div>

        </div>


        <div class="card">

            <div
                style="
                    font-size:32px;
                    font-weight:800;
                "
            >
                <?= $disabledUsers ?>
            </div>

            <div class="muted">
                Disabled
            </div>

    <div class="card">

    <div
        style="
            font-size:32px;
            font-weight:800;
        "
    >
            <?= $suspendedUsers ?>
        </div>

            <div class="muted">
                Reservation Suspensions
            </div>

</div>
        </div>


    </section>


    <!-- SEARCH -->

    <form
        method="GET"
        style="
            display:grid;
            grid-template-columns:
                minmax(250px, 2fr)
                minmax(150px, 1fr)
                auto;
            gap:12px;
            margin-bottom:25px;
        "
    >

        <input
            type="text"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search name, email or phone..."
            style="
                width:100%;
                padding:12px;
                border:1px solid #d8dfeb;
                border-radius:10px;
                box-sizing:border-box;
            "
        >


        <select
            name="status"
            style="
                padding:12px;
                border:1px solid #d8dfeb;
                border-radius:10px;
                background:white;
            "
        >

            <option
                value="all"
                <?= $statusFilter === 'all'
                    ? 'selected'
                    : ''
                ?>
            >
                All Status
            </option>

            <option
                value="active"
                <?= $statusFilter === 'active'
                    ? 'selected'
                    : ''
                ?>
            >
                Active
            </option>

            <option
                value="disabled"
                <?= $statusFilter === 'disabled'
                    ? 'selected'
                    : ''
                ?>
            >
                Disabled
            </option>

        </select>


        <button
            type="submit"
            class="btn primary"
        >
            Search
        </button>

    </form>


    <?php if (
        $search !== '' ||
        $statusFilter !== 'all'
    ): ?>

        <div style="margin-bottom:20px;">

            <a href="<?= e(url('admin/users.php')) ?>">
                Clear Filters
            </a>

        </div>

    <?php endif; ?>


    <!-- USER TABLE -->

    <div class="card">


        <?php if (!$users): ?>

            <div
                style="
                    padding:30px;
                    text-align:center;
                "
            >

                <h3>
                    No Users Found
                </h3>

                <p class="muted">
                    No registered users match your search.
                </p>

            </div>


        <?php else: ?>


            <div style="overflow-x:auto;">

                <table
                    style="
                        width:100%;
                        border-collapse:collapse;
                    "
                >

                    <thead>

                        <tr>

                            <th style="text-align:left;padding:13px;">
                                User
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Contact
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Verification
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Status
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Reservation Policy
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Reservations
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Borrowed
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Joined
                            </th>

                            <th style="text-align:left;padding:13px;">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($users as $member): ?>


                        <tr
                            style="
                                border-top:
                                    1px solid #e3e8f0;
                            "
                        >


                            <!-- USER -->

                            <td style="padding:13px;">

                                <strong>
                                    <?= e($member['name']) ?>
                                </strong>

                                <div
                                    class="muted"
                                    style="
                                        font-size:12px;
                                        margin-top:3px;
                                    "
                                >
                                    User #<?= (int)$member['id'] ?>
                                </div>

                            </td>


                            <!-- CONTACT -->

                            <td style="padding:13px;">

                                <div>
                                    <?= e($member['email']) ?>
                                </div>

                                <div
                                    class="muted"
                                    style="
                                        font-size:13px;
                                        margin-top:4px;
                                    "
                                >
                                    <?= e(
                                        $member['phone']
                                        ?: 'No phone'
                                    ) ?>
                                </div>

                            </td>


                            <!-- VERIFICATION -->

                            <td style="padding:13px;">

                                <?php if (
                                    (int)$member['is_verified'] === 1
                                ): ?>

                                    <span
                                        style="
                                            display:inline-block;
                                            padding:6px 10px;
                                            border-radius:999px;
                                            background:#e8f7f0;
                                            color:#176638;
                                            font-size:12px;
                                            font-weight:700;
                                        "
                                    >
                                        Verified
                                    </span>

                                <?php else: ?>

                                    <span
                                        style="
                                            display:inline-block;
                                            padding:6px 10px;
                                            border-radius:999px;
                                            background:#fff7df;
                                            color:#8a6500;
                                            font-size:12px;
                                            font-weight:700;
                                        "
                                    >
                                        Unverified
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- STATUS -->

                            <td style="padding:13px;">

                                <?php if (
                                    $member['status'] === 'active'
                                ): ?>

                                    <span
                                        style="
                                            display:inline-block;
                                            padding:6px 10px;
                                            border-radius:999px;
                                            background:#e8f7f0;
                                            color:#176638;
                                            font-size:12px;
                                            font-weight:700;
                                        "
                                    >
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span
                                        style="
                                            display:inline-block;
                                            padding:6px 10px;
                                            border-radius:999px;
                                            background:#fff0f0;
                                            color:#a52a2a;
                                            font-size:12px;
                                            font-weight:700;
                                        "
                                    >
                                        Disabled
                                    </span>

                                <?php endif; ?>

                            </td>
                            
                            <!-- RESERVATION POLICY -->

<td style="padding:13px;">

    <?php

    $isReservationBlocked =
        !empty(
            $member['reservation_blocked_until']
        )
        &&
        strtotime(
            $member['reservation_blocked_until']
        ) > time();

    ?>


    <?php if ($isReservationBlocked): ?>

        <span
            style="
                display:inline-block;
                padding:6px 10px;
                border-radius:999px;
                background:#fff0f0;
                color:#b42318;
                font-size:12px;
                font-weight:700;
            "
        >
            Suspended
        </span>

        <div
            class="muted"
            style="
                font-size:11px;
                margin-top:6px;
            "
        >
            Until:
            <?= date(
                'M d, Y h:i A',
                strtotime(
                    $member[
                        'reservation_blocked_until'
                    ]
                )
            ) ?>
        </div>


    <?php else: ?>

        <span
            style="
                display:inline-block;
                padding:6px 10px;
                border-radius:999px;
                background:#e8f7f0;
                color:#176638;
                font-size:12px;
                font-weight:700;
            "
        >
            Allowed
        </span>

    <?php endif; ?>


    <div
        class="muted"
        style="
            font-size:11px;
            margin-top:6px;
        "
    >
        No-shows:
        <?= (int)$member['no_show_count'] ?>
        / 3
    </div>

</td>

                            <!-- RESERVATIONS -->

                            <td style="padding:13px;">

                                <strong>
                                    <?= (int)$member['total_reservations'] ?>
                                </strong>

                                <?php if (
                                    (int)$member['pending_reservations'] > 0
                                ): ?>

                                    <div
                                        class="muted"
                                        style="font-size:12px;"
                                    >

                                        <?= (int)$member['pending_reservations'] ?>

                                        pending

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- BORROWED -->

                            <td style="padding:13px;">

                                <?= (int)$member['borrowed_books'] ?>

                            </td>


                            <!-- JOINED -->

                            <td style="padding:13px;">

                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $member['created_at']
                                    )
                                ) ?>

                            </td>


<!-- ACTION -->

<td style="padding:13px;">

<?php

$isReservationBlocked =
    !empty(
        $member['reservation_blocked_until']
    )
    &&
    strtotime(
        $member['reservation_blocked_until']
    ) > time();

?>


<?php if ($isReservationBlocked): ?>

    <form
        method="POST"
        action="<?= e(
            url(
                'admin/lift-reservation-suspension.php'
            )
        ) ?>"
        style="margin-bottom:8px;"
        onsubmit="
            return confirm(
                'Lift this user\'s reservation suspension?'
            );
        "
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="user_id"
            value="<?= (int)$member['id'] ?>"
        >

        <button
            type="submit"
            class="btn primary"
        >
            Lift Suspension
        </button>

    </form>

<?php endif; ?>

    <a
        href="<?= e(
            url(
                'admin/user-details.php?id=' .
                (int)$member['id']
            )
        ) ?>"
        style="
            display:inline-block;
            margin-bottom:8px;
            font-weight:700;
        "
    >
        View Details
    </a>

    <?php if (
        $member['status'] === 'active'
    ): ?>

                                    <form
                                        method="POST"
                                        action="<?= e(
                                            url(
                                                'admin/process-user.php'
                                            )
                                        ) ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int)$member['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="disable"
                                        >

                                        <button
                                            type="submit"
                                            style="
                                                border:1px solid #b52b2b;
                                                background:white;
                                                color:#a52a2a;
                                                padding:8px 11px;
                                                border-radius:8px;
                                                font-weight:700;
                                                cursor:pointer;
                                            "
                                        >
                                            Disable
                                        </button>

                                    </form>


                                <?php else: ?>


                                    <form
                                        method="POST"
                                        action="<?= e(
                                            url(
                                                'admin/process-user.php'
                                            )
                                        ) ?>"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e(csrf_token()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="user_id"
                                            value="<?= (int)$member['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="enable"
                                        >

                                        <button
                                            type="submit"
                                            class="btn primary"
                                        >
                                            Enable
                                        </button>

                                    </form>


                                <?php endif; ?>

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