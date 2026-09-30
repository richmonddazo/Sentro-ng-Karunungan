<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$user = current_user();


/*
|--------------------------------------------------------------------------
| Get User Reservations
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
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

    WHERE reservations.user_id = :user_id

    ORDER BY reservations.reserved_at DESC
");

$stmt->execute([
    ':user_id' => $user['id']
]);

$reservations = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Reservation Statistics
|--------------------------------------------------------------------------
*/

$totalReservations = count($reservations);

$pendingCount = 0;
$activeCount = 0;
$completedCount = 0;

foreach ($reservations as $reservation) {

    if ($reservation['status'] === 'pending') {
        $pendingCount++;
    }

    if (in_array(
        $reservation['status'],
        ['approved', 'ready', 'borrowed'],
        true
    )) {
        $activeCount++;
    }

    if ($reservation['status'] === 'returned') {
        $completedCount++;
    }
}


/*
|--------------------------------------------------------------------------
| Status Helper
|--------------------------------------------------------------------------
*/

function reservation_status_style(string $status): array
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
        My Reservations | Sentro ng Karunungan
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

        <a href="<?= e(url('user/dashboard.php')) ?>">
            Dashboard
        </a>

        <a href="<?= e(url('user/catalog.php')) ?>">
            Browse Books
        </a>

        <a href="<?= e(url('user/reservations.php')) ?>">
            My Reservations
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
        MY LIBRARY
    </p>

    <h1>
        My Reservations
    </h1>

    <p class="muted">
        Track your book requests, approvals, pickup status,
        borrowed books, and reservation history.
    </p>


    <!-- STATISTICS -->

    <div
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
                    font-size:30px;
                    font-weight:800;
                "
            >
                <?= $totalReservations ?>
            </div>

            <div class="muted">
                Total Reservations
            </div>

        </div>


        <div class="card">

            <div
                style="
                    font-size:30px;
                    font-weight:800;
                "
            >
                <?= $pendingCount ?>
            </div>

            <div class="muted">
                Pending
            </div>

        </div>


        <div class="card">

            <div
                style="
                    font-size:30px;
                    font-weight:800;
                "
            >
                <?= $activeCount ?>
            </div>

            <div class="muted">
                Active
            </div>

        </div>


        <div class="card">

            <div
                style="
                    font-size:30px;
                    font-weight:800;
                "
            >
                <?= $completedCount ?>
            </div>

            <div class="muted">
                Returned
            </div>

        </div>

    </div>


    <?php if (!$reservations): ?>


        <div
            class="card"
            style="
                text-align:center;
                padding:50px 30px;
            "
        >

            <div
                style="
                    font-size:55px;
                    margin-bottom:15px;
                "
            >
                📚
            </div>

            <h2>
                No Reservations Yet
            </h2>

            <p class="muted">
                You haven't reserved any books from the library yet.
            </p>

            <div style="margin-top:22px;">

                <a
                    href="<?= e(url('user/catalog.php')) ?>"
                    class="btn primary"
                >
                    Browse Books
                </a>

            </div>

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
                $statusStyle =
                    reservation_status_style(
                        $reservation['status']
                    );
                ?>


                <article
                    class="card"
                    style="
                        display:grid;
                        grid-template-columns:
                            100px
                            minmax(0, 1fr)
                            auto;
                        gap:22px;
                        align-items:start;
                    "
                >


                    <!-- BOOK COVER -->

                    <div
                        style="
                            width:100px;
                            height:145px;
                            background:#eef1f6;
                            border-radius:10px;
                            overflow:hidden;
                            display:flex;
                            justify-content:center;
                            align-items:center;
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
                                    font-size:13px;
                                    padding:10px;
                                "
                            >
                                📚
                                <br>
                                No Cover
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- DETAILS -->

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
                                    display:inline-block;
                                    padding:6px 10px;
                                    border-radius:999px;
                                    font-size:12px;
                                    font-weight:700;
                                    background:
                                        <?= e($statusStyle['background']) ?>;
                                    color:
                                        <?= e($statusStyle['color']) ?>;
                                "
                            >
                                <?= e($statusStyle['label']) ?>
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
                                margin:0 0 5px;
                                font-size:21px;
                            "
                        >

                            <?= e($reservation['title']) ?>

                        </h2>


                        <p
                            class="muted"
                            style="
                                margin:0 0 15px;
                            "
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
                                font-size:14px;
                            "
                        >


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


                            <?php if (
                                $reservation['processed_at']
                            ): ?>

                                <div>

                                    <strong>
                                        Processed
                                    </strong>

                                    <div class="muted">

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $reservation['processed_at']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                $reservation['pickup_deadline']
                            ): ?>

                                <div>

                                    <strong>
                                        Pickup Deadline
                                    </strong>

                                    <div class="muted">

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $reservation['pickup_deadline']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                $reservation['borrowed_at']
                            ): ?>

                                <div>

                                    <strong>
                                        Borrowed
                                    </strong>

                                    <div class="muted">

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $reservation['borrowed_at']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                $reservation['due_at']
                            ): ?>

                                <div>

                                    <strong>
                                        Due Date
                                    </strong>

                                    <div class="muted">

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $reservation['due_at']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                            <?php if (
                                $reservation['returned_at']
                            ): ?>

                                <div>

                                    <strong>
                                        Returned
                                    </strong>

                                    <div class="muted">

                                        <?= date(
                                            'M d, Y h:i A',
                                            strtotime(
                                                $reservation['returned_at']
                                            )
                                        ) ?>

                                    </div>

                                </div>

                            <?php endif; ?>


                        </div>


                        <?php if (
                            $reservation['admin_note']
                        ): ?>

                            <div
                                style="
                                    background:#f6f8fb;
                                    padding:12px 14px;
                                    border-radius:10px;
                                    margin-top:18px;
                                "
                            >

                                <strong>
                                    Library Note
                                </strong>

                                <div
                                    class="muted"
                                    style="
                                        margin-top:5px;
                                    "
                                >

                                    <?= e(
                                        $reservation['admin_note']
                                    ) ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- STATUS INSTRUCTIONS -->

                        <?php if (
                            $reservation['status'] === 'pending'
                        ): ?>

                            <p
                                class="muted"
                                style="margin-top:17px;"
                            >
                                Waiting for the library administrator
                                to review your reservation.
                            </p>


                        <?php elseif (
                            $reservation['status'] === 'approved'
                        ): ?>

                            <p
                                style="
                                    color:#2454a6;
                                    margin-top:17px;
                                "
                            >
                                Your reservation has been approved.
                                Please wait until the library marks
                                the book as ready for pickup.
                            </p>


                        <?php elseif (
                            $reservation['status'] === 'ready'
                        ): ?>

                            <p
                                style="
                                    color:#176638;
                                    font-weight:700;
                                    margin-top:17px;
                                "
                            >
                                Your book is ready for pickup at the library.
                            </p>


                        <?php elseif (
                            $reservation['status'] === 'borrowed'
                        ): ?>

                            <p
                                style="
                                    color:#5135a5;
                                    font-weight:700;
                                    margin-top:17px;
                                "
                            >
                                You currently have this book borrowed.
                            </p>


                        <?php elseif (
                            $reservation['status'] === 'returned'
                        ): ?>

                            <p
                                style="
                                    color:#176638;
                                    margin-top:17px;
                                "
                            >
                                This borrowing transaction has been completed.
                            </p>

                        <?php endif; ?>


                    </div>


                    <!-- ACTION -->

                    <div>

                        <a
                            href="<?= e(
                                url(
                                    'user/book.php?id=' .
                                    (int)$reservation['book_id']
                                )
                            ) ?>"
                            class="btn"
                            style="
                                display:inline-block;
                                white-space:nowrap;
                            "
                        >
                            View Book
                        </a>


                        <?php if (
                            $reservation['status'] === 'pending'
                        ): ?>

                            <div style="margin-top:10px;">

                                <a
                                    href="<?= e(
                                        url(
                                            'user/cancel-reservation.php?id=' .
                                            (int)$reservation['id']
                                        )
                                    ) ?>"
                                    style="
                                        font-size:14px;
                                        color:#a52a2a;
                                    "
                                >
                                    Cancel Reservation
                                </a>

                            </div>

                        <?php endif; ?>

                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>

</body>

</html>