<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$admin = current_user();

$userId = (int)($_GET['id'] ?? 0);

if ($userId <= 0) {
    redirect('admin/users.php');
}


/*
|--------------------------------------------------------------------------
| GET USER
|--------------------------------------------------------------------------
*/

$userStmt = db()->prepare("
    SELECT
    id,
    name,
    email,
    phone,
    role,
    status,
    is_verified,
    no_show_count,
    reservation_blocked_until,
    created_at
    FROM users
    WHERE id = ?
      AND role = 'user'
    LIMIT 1
");

$userStmt->execute([
    $userId
]);

$member = $userStmt->fetch();

if (!$member) {
    http_response_code(404);
    exit('User not found.');
}

$isReservationBlocked =
    !empty($member['reservation_blocked_until'])
    &&
    strtotime(
        $member['reservation_blocked_until']
    ) > time();

/*
|--------------------------------------------------------------------------
| USER STATISTICS
|--------------------------------------------------------------------------
*/

$statsStmt = db()->prepare("
    SELECT
        COUNT(*) AS total,

        COALESCE(
            SUM(status = 'pending'),
            0
        ) AS pending,

        COALESCE(
            SUM(status = 'approved'),
            0
        ) AS approved,

        COALESCE(
            SUM(status = 'ready'),
            0
        ) AS ready,

        COALESCE(
            SUM(status = 'borrowed'),
            0
        ) AS borrowed,

        COALESCE(
            SUM(status = 'returned'),
            0
        ) AS returned,

        COALESCE(
            SUM(status = 'rejected'),
            0
        ) AS rejected,

        COALESCE(
    SUM(status = 'cancelled'),
    0
        ) AS cancelled,

        COALESCE(
            SUM(status = 'expired'),
            0
        ) AS expired,

        COALESCE(
            SUM(
                status = 'borrowed'
                AND due_at IS NOT NULL
                AND due_at < NOW()
            ),
            0
        ) AS overdue

    FROM reservations

    WHERE user_id = ?
");

$statsStmt->execute([
    $userId
]);

$stats = $statsStmt->fetch();


/*
|--------------------------------------------------------------------------
| COMPLETE TRANSACTION HISTORY
|--------------------------------------------------------------------------
*/

$historyStmt = db()->prepare("
    SELECT
        reservations.id,
        reservations.status,
        reservations.reserved_at,
        reservations.processed_at,
        reservations.pickup_deadline,
        reservations.borrowed_at,
        reservations.due_at,
        reservations.returned_at,
        reservations.admin_note,

        books.id AS book_id,
        books.title,
        books.author,
        books.cover_image,

        categories.name AS category_name

    FROM reservations

    INNER JOIN books
        ON books.id = reservations.book_id

    LEFT JOIN categories
        ON categories.id = books.category_id

    WHERE reservations.user_id = ?

    ORDER BY reservations.reserved_at DESC
");

$historyStmt->execute([
    $userId
]);

$history = $historyStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| STATUS STYLE
|--------------------------------------------------------------------------
*/

function user_history_status(string $status): array
{
    return match ($status) {

        'pending' => [
            'label' => 'Pending',
            'background' => '#fff7df',
            'color' => '#8a6500'
        ],

        'approved' => [
            'label' => 'Approved',
            'background' => '#e8f1ff',
            'color' => '#2454a6'
        ],

        'ready' => [
            'label' => 'Ready for Pickup',
            'background' => '#e7f8ee',
            'color' => '#176638'
        ],

        'borrowed' => [
            'label' => 'Borrowed',
            'background' => '#eee9ff',
            'color' => '#5135a5'
        ],

        'returned' => [
            'label' => 'Returned',
            'background' => '#e8f7f0',
            'color' => '#16714b'
        ],

        'rejected' => [
            'label' => 'Rejected',
            'background' => '#fff0f0',
            'color' => '#a52a2a'
        ],

        'cancelled' => [
            'label' => 'Cancelled',
            'background' => '#f1f2f4',
            'color' => '#5d6673'
        ],

        'expired' => [
            'label' => 'Expired',
            'background' => '#f4eeee',
            'color' => '#725454'
        ],

        default => [
            'label' => ucfirst($status),
            'background' => '#eeeeee',
            'color' => '#444444'
        ]
    };
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
        User Details | Sentro ng Karunungan
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


    <div style="margin-bottom:24px;">

        <a href="<?= e(url('admin/users.php')) ?>">
            ← Back to Users
        </a>

    </div>


    <p class="eyebrow">
        USER PROFILE
    </p>


    <div
        style="
            display:grid;
            grid-template-columns:
                minmax(280px, 1fr)
                minmax(0, 2fr);
            gap:25px;
            align-items:start;
        "
    >


        <!-- PROFILE -->

        <section class="card">

            <div
                style="
                    width:80px;
                    height:80px;
                    border-radius:50%;
                    background:#eef3ff;
                    color:#2454a6;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    font-size:30px;
                    font-weight:800;
                    margin-bottom:20px;
                "
            >

                <?= e(
                    strtoupper(
                        substr(
                            $member['name'],
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <h1
                style="
                    margin-bottom:5px;
                "
            >
                <?= e($member['name']) ?>
            </h1>


            <p class="muted">
                Reader Account
            </p>


            <div
                style="
                    display:flex;
                    gap:8px;
                    flex-wrap:wrap;
                    margin:18px 0 25px;
                "
            >

                <?php if (
                    $member['status'] === 'active'
                ): ?>

                    <span
                        style="
                            background:#e8f7f0;
                            color:#176638;
                            padding:6px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:700;
                        "
                    >
                        Active
                    </span>

                <?php else: ?>

                    <span
                        style="
                            background:#fff0f0;
                            color:#a52a2a;
                            padding:6px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:700;
                        "
                    >
                        Disabled
                    </span>

                <?php endif; ?>


                <?php if (
                    (int)$member['is_verified'] === 1
                ): ?>

                    <span
                        style="
                            background:#e8f1ff;
                            color:#2454a6;
                            padding:6px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:700;
                        "
                    >
                        Email Verified
                    </span>

                <?php else: ?>

                    <span
                        style="
                            background:#fff7df;
                            color:#8a6500;
                            padding:6px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:700;
                        "
                    >
                        Unverified
                    </span>

                <?php endif; ?>

            </div>


            <div
                style="
                    border-top:1px solid #e3e8f0;
                    padding-top:20px;
                "
            >

                <p>

                    <strong>
                        Email
                    </strong>

                    <br>

                    <span class="muted">
                        <?= e($member['email']) ?>
                    </span>

                </p>


                <p>

                    <strong>
                        Phone
                    </strong>

                    <br>

                    <span class="muted">
                        <?= e(
                            $member['phone']
                            ?: 'Not provided'
                        ) ?>
                    </span>

                </p>


                <p>

                    <strong>
                        User ID
                    </strong>

                    <br>

                    <span class="muted">
                        #<?= (int)$member['id'] ?>
                    </span>

                </p>


                <p>

                    <strong>
                        Joined
                    </strong>

                    <br>

                    <span class="muted">

                        <?= date(
                            'F d, Y',
                            strtotime(
                                $member['created_at']
                            )
                        ) ?>

                    </span>

                </p>

                <p>

    <strong>
        Reservation Policy
    </strong>

    <br>

    <?php if ($isReservationBlocked): ?>

        <span
            style="
                color:#b42318;
                font-weight:700;
            "
        >
            Suspended
        </span>

    <?php else: ?>

        <span
            style="
                color:#176638;
                font-weight:700;
            "
        >
            Allowed
        </span>

    <?php endif; ?>

</p>


<p>

    <strong>
        No-Shows
    </strong>

    <br>

    <span class="muted">
        <?= (int)$member['no_show_count'] ?> / 3
    </span>

</p>


<?php if ($isReservationBlocked): ?>

    <p>

        <strong>
            Suspended Until
        </strong>

        <br>

        <span
            style="
                color:#b42318;
                font-weight:700;
            "
        >
            <?= date(
                'F d, Y h:i A',
                strtotime(
                    $member[
                        'reservation_blocked_until'
                    ]
                )
            ) ?>
        </span>

    </p>

<?php endif; ?>

            </div>


            <!-- ACCOUNT ACTION -->

            <div
                style="
                    border-top:1px solid #e3e8f0;
                    padding-top:20px;
                    margin-top:20px;
                "
            >
                <?php if ($isReservationBlocked): ?>

    <form
        method="POST"
        action="<?= e(
            url(
                'admin/lift-reservation-suspension.php'
            )
        ) ?>"
        style="margin-bottom:10px;"
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
            style="width:100%;"
        >
            Lift Reservation Suspension
        </button>

    </form>

<?php endif; ?>

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
                                width:100%;
                                padding:11px;
                                border:1px solid #b52b2b;
                                background:white;
                                color:#a52a2a;
                                border-radius:9px;
                                font-weight:700;
                                cursor:pointer;
                            "
                        >
                            Disable Account
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
                            style="width:100%;"
                        >
                            Enable Account
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>


        <!-- ACTIVITY -->

        <div>


            <!-- STATISTICS -->

            <section
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(
                            auto-fit,
                            minmax(130px, 1fr)
                        );
                    gap:14px;
                    margin-bottom:25px;
                "
            >


                <div class="card">

                    <div
                        style="
                            font-size:28px;
                            font-weight:800;
                        "
                    >
                        <?= (int)$stats['total'] ?>
                    </div>

                    <div class="muted">
                        Reservations
                    </div>

                </div>


                <div class="card">

                    <div
                        style="
                            font-size:28px;
                            font-weight:800;
                        "
                    >
                        <?= (int)$stats['pending'] ?>
                    </div>

                    <div class="muted">
                        Pending
                    </div>

                </div>


                <div class="card">

                    <div
                        style="
                            font-size:28px;
                            font-weight:800;
                        "
                    >
                        <?= (int)$stats['ready'] ?>
                    </div>

                    <div class="muted">
                        Ready
                    </div>

                </div>


                <div class="card">

                    <div
                        style="
                            font-size:28px;
                            font-weight:800;
                        "
                    >
                        <?= (int)$stats['borrowed'] ?>
                    </div>

                    <div class="muted">
                        Borrowed
                    </div>

                </div>

                <div class="card">

    <div
        style="
            font-size:28px;
            font-weight:800;
        "
    >
        <?= (int)$stats['overdue'] ?>
    </div>

    <div class="muted">
        Overdue
    </div>

</div>

<div class="card">

    <div
        style="
            font-size:28px;
            font-weight:800;
        "
    >
        <?= (int)$stats['expired'] ?>
    </div>

    <div class="muted">
        Expired / Missed Pickup
    </div>

</div>

<div class="card">

    <div
        style="
            font-size:28px;
            font-weight:800;
        "
    >
        <?= (int)$member['no_show_count'] ?> / 3
    </div>

    <div class="muted">
        Current No-Show Strikes
    </div>

</div>

                <div class="card">

                    <div
                        style="
                            font-size:28px;
                            font-weight:800;
                        "
                    >
                        <?= (int)$stats['returned'] ?>
                    </div>

                    <div class="muted">
                        Returned
                    </div>

                </div>


            </section>


            <!-- HISTORY -->

            <section>

                <div style="margin-bottom:15px;">

                    <h2 style="margin-bottom:5px;">
                        Library Activity
                    </h2>

                    <p
                        class="muted"
                        style="margin:0;"
                    >
                        Complete reservation and borrowing history.
                    </p>

                </div>


                <?php if (!$history): ?>

                    <div class="card">

                        <h3>
                            No Library Activity
                        </h3>

                        <p class="muted">
                            This user has not reserved any books yet.
                        </p>

                    </div>


                <?php else: ?>


                    <div
                        style="
                            display:flex;
                            flex-direction:column;
                            gap:15px;
                        "
                    >


                        <?php foreach ($history as $item): ?>


                            <?php

                            $status =
                                user_history_status(
                                    $item['status']
                                );

                            $isOverdue =
    $item['status'] === 'borrowed'
    &&
    !empty($item['due_at'])
    &&
    strtotime($item['due_at']) < time();

if ($isOverdue) {

    $status = [
        'label' => 'Overdue',
        'background' => '#fff0f0',
        'color' => '#b42318'
    ];
}

                            ?>


                            <article class="card">

                                <div
                                    style="
                                        display:grid;
                                        grid-template-columns:
                                            70px
                                            minmax(0,1fr);
                                        gap:18px;
                                    "
                                >


                                    <!-- COVER -->

                                    <div
                                        style="
                                            width:70px;
                                            height:105px;
                                            border-radius:8px;
                                            overflow:hidden;
                                            background:#eef1f6;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                        "
                                    >

                                        <?php if (
                                            $item['cover_image']
                                        ): ?>

                                            <img
                                                src="<?= e(
                                                    url(
                                                        'uploads/covers/' .
                                                        $item['cover_image']
                                                    )
                                                ) ?>"
                                                alt="<?= e($item['title']) ?>"
                                                style="
                                                    width:100%;
                                                    height:100%;
                                                    object-fit:cover;
                                                "
                                            >

                                        <?php else: ?>

                                            <span>
                                                📚
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <!-- DETAILS -->

                                    <div>


                                        <div
                                            style="
                                                display:flex;
                                                justify-content:space-between;
                                                gap:15px;
                                                flex-wrap:wrap;
                                            "
                                        >

                                            <div>

                                                <h3
                                                    style="
                                                        margin:0 0 5px;
                                                    "
                                                >
                                                    <?= e($item['title']) ?>
                                                </h3>

                                                <p
                                                    class="muted"
                                                    style="
                                                        margin:0;
                                                    "
                                                >
                                                    by
                                                    <?= e($item['author']) ?>
                                                </p>

                                            </div>


                                            <span
                                                style="
                                                    height:max-content;
                                                    padding:6px 10px;
                                                    border-radius:999px;
                                                    font-size:12px;
                                                    font-weight:700;

                                                    background:
                                                        <?= e(
                                                            $status['background']
                                                        ) ?>;

                                                    color:
                                                        <?= e(
                                                            $status['color']
                                                        ) ?>;
                                                "
                                            >
                                                <?= e(
                                                    $status['label']
                                                ) ?>
                                            </span>

                                        </div>


                                        <div
                                            style="
                                                display:grid;
                                                grid-template-columns:
                                                    repeat(
                                                        auto-fit,
                                                        minmax(
                                                            150px,
                                                            1fr
                                                        )
                                                    );
                                                gap:12px;
                                                margin-top:17px;
                                                font-size:13px;
                                            "
                                        >


                                            <div>

                                                <strong>
                                                    Category
                                                </strong>

                                                <div class="muted">

                                                    <?= e(
                                                        $item['category_name']
                                                        ?? 'Uncategorized'
                                                    ) ?>

                                                </div>

                                            </div>


                                            <div>

                                                <strong>
                                                    Requested
                                                </strong>

                                                <div class="muted">

                                                    <?= date(
                                                        'M d, Y h:i A',
                                                        strtotime(
                                                            $item['reserved_at']
                                                        )
                                                    ) ?>

                                                </div>

                                            </div>


                                            <?php if (
                                                $item['borrowed_at']
                                            ): ?>

                                                <div>

                                                    <strong>
                                                        Borrowed
                                                    </strong>

                                                    <div class="muted">

                                                        <?= date(
                                                            'M d, Y',
                                                            strtotime(
                                                                $item['borrowed_at']
                                                            )
                                                        ) ?>

                                                    </div>

                                                </div>

                                            <?php if (
    $item['due_at']
): ?>

    <div>

        <strong>
            Due
        </strong>

        <div
            style="
                color:
                    <?= $isOverdue
                        ? '#b42318'
                        : '#687386'
                    ?>;

                font-weight:
                    <?= $isOverdue
                        ? '700'
                        : '400'
                    ?>;
            "
        >

            <?= date(
                'M d, Y',
                strtotime(
                    $item['due_at']
                )
            ) ?>

            <?php if ($isOverdue): ?>

                <div
                    style="
                        font-size:11px;
                        margin-top:3px;
                    "
                >
                    Overdue
                </div>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

                                                    </div>

                                                </div>

                                            <?php endif; ?>


                                            <?php if (
                                                $item['returned_at']
                                            ): ?>

                                                <div>

                                                    <strong>
                                                        Returned
                                                    </strong>

                                                    <div class="muted">

                                                        <?= date(
                                                            'M d, Y',
                                                            strtotime(
                                                                $item['returned_at']
                                                            )
                                                        ) ?>

                                                    </div>

                                                </div>

                                            <?php endif; ?>


                                        </div>

                                        <?php if ($isOverdue): ?>

    <?php

    $daysOverdue =
        max(
            1,
            (int)floor(
                (
                    time() -
                    strtotime($item['due_at'])
                ) / 86400
            )
        );

    ?>

    <div
        style="
            background:#fff0f0;
            border:1px solid #f0c7c7;
            color:#a52a2a;
            padding:10px 12px;
            border-radius:8px;
            margin-top:15px;
            font-size:13px;
        "
    >

        <strong>
            Overdue Book
        </strong>

        <br>

        This book is currently

        <?= $daysOverdue ?>

        day<?= $daysOverdue === 1 ? '' : 's' ?>

        overdue.

    </div>

<?php endif; ?>

                                        <?php if (
                                            $item['admin_note']
                                        ): ?>

                                            <div
                                                style="
                                                    background:#f6f8fb;
                                                    padding:10px 12px;
                                                    border-radius:8px;
                                                    margin-top:15px;
                                                    font-size:13px;
                                                "
                                            >

                                                <strong>
                                                    Admin Note:
                                                </strong>

                                                <?= e(
                                                    $item['admin_note']
                                                ) ?>

                                            </div>

                                        <?php endif; ?>


                                    </div>


                                </div>

                            </article>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </section>


        </div>


    </div>


</main>

</body>

</html>