<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$user = current_user();

$statusFilter = trim($_GET['status'] ?? 'all');

$allowedFilters = [
    'all',
    'pending',
    'approved',
    'ready',
    'borrowed',
    'returned',
    'rejected',
    'cancelled',
    'expired'
];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}


/*
|--------------------------------------------------------------------------
| Reservation Statistics
|--------------------------------------------------------------------------
*/

$statsStmt = db()->query("
    SELECT
        COUNT(*) AS total,

        SUM(status = 'pending') AS pending,

        SUM(status = 'approved') AS approved,

        SUM(status = 'ready') AS ready,

        SUM(status = 'borrowed') AS borrowed,

        SUM(status = 'returned') AS returned

    FROM reservations
");

$stats = $statsStmt->fetch();


/*
|--------------------------------------------------------------------------
| Get Reservations
|--------------------------------------------------------------------------
*/

$sql = "
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

        users.id AS user_id,
        users.name AS user_name,
        users.email AS user_email,
        users.phone AS user_phone,

        books.id AS book_id,
        books.title,
        books.author,
        books.cover_image,
        books.total_copies,
        books.available_copies,

        categories.name AS category_name

    FROM reservations

    INNER JOIN users
        ON users.id = reservations.user_id

    INNER JOIN books
        ON books.id = reservations.book_id

    LEFT JOIN categories
        ON categories.id = books.category_id
";

$params = [];

if ($statusFilter !== 'all') {

    $sql .= "
        WHERE reservations.status = ?
    ";

    $params[] = $statusFilter;
}

$sql .= "
    ORDER BY
        CASE reservations.status

            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'ready' THEN 3
            WHEN 'borrowed' THEN 4

            ELSE 5

        END,

        reservations.reserved_at DESC
";

$stmt = db()->prepare($sql);

$stmt->execute($params);

$reservations = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Status Appearance
|--------------------------------------------------------------------------
*/

function admin_reservation_status(string $status): array
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
        Reservations | Sentro ng Karunungan
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

        <span>
            <?= e($user['name']) ?>
        </span>

        <a href="<?= e(url('logout.php')) ?>">
            Logout
        </a>

    </nav>

</header>


<main class="container">


    <p class="eyebrow">
        LIBRARY MANAGEMENT
    </p>

    <h1>
        Reservations
    </h1>

    <p class="muted">
        Review and manage book reservation requests.
    </p>


    <!-- STATISTICS -->

    <div
        style="
            display:grid;
            grid-template-columns:
                repeat(auto-fit, minmax(150px, 1fr));
            gap:15px;
            margin:28px 0;
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
                Total
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
                <?= (int)$stats['approved'] ?>
            </div>

            <div class="muted">
                Approved
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
                <?= (int)$stats['returned'] ?>
            </div>

            <div class="muted">
                Returned
            </div>

        </div>

    </div>


    <!-- FILTER -->

    <form
        method="GET"
        style="
            display:flex;
            gap:12px;
            align-items:center;
            flex-wrap:wrap;
            margin-bottom:25px;
        "
    >

        <label>
            <strong>
                Filter:
            </strong>
        </label>

        <select
            name="status"
            onchange="this.form.submit()"
            style="
                padding:10px 13px;
                border:1px solid #d8dfeb;
                border-radius:10px;
                background:white;
            "
        >

            <?php foreach ($allowedFilters as $filter): ?>

                <option
                    value="<?= e($filter) ?>"
                    <?= $statusFilter === $filter
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= $filter === 'all'
                        ? 'All Reservations'
                        : ucfirst($filter)
                    ?>

                </option>

            <?php endforeach; ?>

        </select>

    </form>


    <?php if (!$reservations): ?>


        <div
            class="card"
            style="
                text-align:center;
                padding:45px;
            "
        >

            <h2>
                No Reservations Found
            </h2>

            <p class="muted">
                There are currently no reservations matching this filter.
            </p>

        </div>


    <?php else: ?>


        <div
            style="
                display:flex;
                flex-direction:column;
                gap:18px;
            "
        >


        <?php foreach ($reservations as $reservation): ?>


            <?php

            $status =
                admin_reservation_status(
                    $reservation['status']
                );

            ?>


            <article class="card">


                <div
                    style="
                        display:grid;
                        grid-template-columns:
                            90px
                            minmax(0, 1fr)
                            minmax(220px, 280px);
                        gap:24px;
                        align-items:start;
                    "
                >


                    <!-- COVER -->

                    <div
                        style="
                            width:90px;
                            height:130px;
                            background:#eef1f6;
                            border-radius:10px;
                            overflow:hidden;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        "
                    >

                        <?php if ($reservation['cover_image']): ?>

                            <img
                                src="<?= e(
                                    url(
                                        'uploads/covers/' .
                                        $reservation['cover_image']
                                    )
                                ) ?>"
                                alt="<?= e($reservation['title']) ?>"
                                style="
                                    width:100%;
                                    height:100%;
                                    object-fit:cover;
                                "
                            >

                        <?php else: ?>

                            <div
                                style="
                                    text-align:center;
                                    color:#687386;
                                    font-size:12px;
                                "
                            >
                                📚
                                <br>
                                No Cover
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- RESERVATION INFORMATION -->

                    <div>


                        <div
                            style="
                                display:flex;
                                gap:10px;
                                align-items:center;
                                flex-wrap:wrap;
                                margin-bottom:8px;
                            "
                        >

                            <span
                                style="
                                    padding:6px 10px;
                                    border-radius:999px;
                                    font-size:12px;
                                    font-weight:700;
                                    background:
                                        <?= e($status['background']) ?>;
                                    color:
                                        <?= e($status['color']) ?>;
                                "
                            >
                                <?= e($status['label']) ?>
                            </span>


                            <span
                                class="muted"
                                style="font-size:13px;"
                            >
                                Reservation #
                                <?= (int)$reservation['id'] ?>
                            </span>

                        </div>


                        <h2
                            style="
                                margin:0 0 4px;
                                font-size:21px;
                            "
                        >
                            <?= e($reservation['title']) ?>
                        </h2>


                        <p
                            class="muted"
                            style="margin-top:0;"
                        >
                            by <?= e($reservation['author']) ?>
                        </p>


                        <div
                            style="
                                display:grid;
                                grid-template-columns:
                                    repeat(
                                        auto-fit,
                                        minmax(160px, 1fr)
                                    );
                                gap:14px;
                                margin-top:18px;
                                font-size:14px;
                            "
                        >


                            <div>

                                <strong>
                                    Requested By
                                </strong>

                                <div class="muted">
                                    <?= e($reservation['user_name']) ?>
                                </div>

                            </div>


                            <div>

                                <strong>
                                    Email
                                </strong>

                                <div class="muted">
                                    <?= e($reservation['user_email']) ?>
                                </div>

                            </div>


                            <div>

                                <strong>
                                    Phone
                                </strong>

                                <div class="muted">
                                    <?= e(
                                        $reservation['user_phone']
                                        ?: 'Not provided'
                                    ) ?>
                                </div>

                            </div>


                            <div>

                                <strong>
                                    Category
                                </strong>

                                <div class="muted">
                                    <?= e(
                                        $reservation['category_name']
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
                                            $reservation['reserved_at']
                                        )
                                    ) ?>

                                </div>

                            </div>


                            <div>

                                <strong>
                                    Book Availability
                                </strong>

                                <div class="muted">

                                    <?= (int)$reservation['available_copies'] ?>

                                    /

                                    <?= (int)$reservation['total_copies'] ?>

                                    available

                                </div>

                            </div>


                        </div>


                        <?php if ($reservation['admin_note']): ?>

                            <div
                                style="
                                    background:#f6f8fb;
                                    padding:12px;
                                    border-radius:10px;
                                    margin-top:18px;
                                "
                            >

                                <strong>
                                    Admin Note
                                </strong>

                                <div
                                    class="muted"
                                    style="margin-top:4px;"
                                >
                                    <?= e($reservation['admin_note']) ?>
                                </div>

                            </div>

                        <?php endif; ?>


                    </div>


                    <!-- ACTION AREA -->

<div
    style="
        border-left:1px solid #e3e8f0;
        padding-left:22px;
    "
>

    <?php if (
        $reservation['status'] === 'pending'
    ): ?>

        <h4 style="margin-top:0;">
            Review Request
        </h4>

        <?php if (
            (int)$reservation['available_copies'] > 0
        ): ?>

            <form
                method="POST"
                action="<?= e(
                    url('admin/process-reservation.php')
                ) ?>"
                style="margin-bottom:10px;"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="reservation_id"
                    value="<?= (int)$reservation['id'] ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="approve"
                >

                <button
                    type="submit"
                    class="btn primary"
                    style="width:100%;"
                >
                    Approve Reservation
                </button>

            </form>

        <?php else: ?>

            <div
                style="
                    background:#fff4e5;
                    color:#8a5300;
                    padding:10px;
                    border-radius:8px;
                    margin-bottom:10px;
                    font-size:14px;
                "
            >
                No copies currently available.
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="<?= e(
                url('admin/process-reservation.php')
            ) ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="reservation_id"
                value="<?= (int)$reservation['id'] ?>"
            >

            <input
                type="hidden"
                name="action"
                value="reject"
            >

            <button
                type="submit"
                style="
                    width:100%;
                    padding:11px;
                    border:1px solid #c33;
                    border-radius:9px;
                    background:white;
                    color:#a52a2a;
                    cursor:pointer;
                    font-weight:700;
                "
            >
                Reject Reservation
            </button>

        </form>


    <?php elseif (
        $reservation['status'] === 'approved'
    ): ?>

        <h4 style="margin-top:0;">
            Reservation Approved
        </h4>

        <p class="muted">
            The book copy has been reserved for this user.
        </p>

        <form
            method="POST"
            action="<?= e(
                url('admin/process-reservation.php')
            ) ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="reservation_id"
                value="<?= (int)$reservation['id'] ?>"
            >

            <input
                type="hidden"
                name="action"
                value="ready"
            >

            <button
                type="submit"
                class="btn primary"
                style="width:100%;"
            >
                Mark Ready for Pickup
            </button>

        </form>


    <?php elseif (
        $reservation['status'] === 'ready'
    ): ?>

        <h4 style="margin-top:0;">
            Ready for Pickup
        </h4>

        <?php if ($reservation['pickup_deadline']): ?>

            <p class="muted">
                Pickup deadline:
                <br>

                <strong>
                    <?= date(
                        'M d, Y h:i A',
                        strtotime(
                            $reservation['pickup_deadline']
                        )
                    ) ?>
                </strong>
            </p>

        <?php endif; ?>

        <form
            method="POST"
            action="<?= e(
                url('admin/process-reservation.php')
            ) ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="reservation_id"
                value="<?= (int)$reservation['id'] ?>"
            >

            <input
                type="hidden"
                name="action"
                value="borrow"
            >

            <button
                type="submit"
                class="btn primary"
                style="width:100%;"
            >
                Mark as Borrowed
            </button>

        </form>


    <?php elseif (
        $reservation['status'] === 'borrowed'
    ): ?>

        <h4 style="margin-top:0;">
            Currently Borrowed
        </h4>

        <?php if ($reservation['borrowed_at']): ?>

            <p class="muted">
                Borrowed:
                <br>

                <?= date(
                    'M d, Y h:i A',
                    strtotime(
                        $reservation['borrowed_at']
                    )
                ) ?>
            </p>

        <?php endif; ?>


        <?php if ($reservation['due_at']): ?>

            <?php

            $isOverdue =
                strtotime($reservation['due_at']) <
                time();

            ?>

            <p
                style="
                    color:
                        <?= $isOverdue
                            ? '#a52a2a'
                            : '#5135a5'
                        ?>;
                "
            >

                Due:
                <br>

                <strong>

                    <?= date(
                        'M d, Y h:i A',
                        strtotime(
                            $reservation['due_at']
                        )
                    ) ?>

                </strong>

                <?php if ($isOverdue): ?>

                    <br>
                    OVERDUE

                <?php endif; ?>

            </p>

        <?php endif; ?>


        <form
            method="POST"
            action="<?= e(
                url('admin/process-reservation.php')
            ) ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <input
                type="hidden"
                name="reservation_id"
                value="<?= (int)$reservation['id'] ?>"
            >

            <input
                type="hidden"
                name="action"
                value="return"
            >

            <button
                type="submit"
                class="btn primary"
                style="width:100%;"
            >
                Mark as Returned
            </button>

        </form>


    <?php elseif (
        $reservation['status'] === 'returned'
    ): ?>

        <h4 style="margin-top:0;">
            Transaction Completed
        </h4>

        <?php if ($reservation['returned_at']): ?>

            <p class="muted">
                Returned:
                <br>

                <?= date(
                    'M d, Y h:i A',
                    strtotime(
                        $reservation['returned_at']
                    )
                ) ?>
            </p>

        <?php endif; ?>

        <div
            style="
                padding:10px;
                border-radius:9px;
                background:#e8f7f0;
                color:#16714b;
                font-weight:700;
            "
        >
            Book Returned
        </div>


    <?php elseif (
        $reservation['status'] === 'rejected'
    ): ?>

        <h4 style="margin-top:0;">
            Reservation Rejected
        </h4>

        <p class="muted">
            No book copy was allocated.
        </p>


    <?php elseif (
        $reservation['status'] === 'cancelled'
    ): ?>

        <h4 style="margin-top:0;">
            Reservation Cancelled
        </h4>

        <p class="muted">
            Cancelled by the user.
        </p>


    <?php elseif (
        $reservation['status'] === 'expired'
    ): ?>

        <h4 style="margin-top:0;">
            Reservation Expired
        </h4>

        <p class="muted">
            The pickup period has expired.
        </p>

    <?php endif; ?>

</div>


                </div>


            </article>


        <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>

</body>

</html>