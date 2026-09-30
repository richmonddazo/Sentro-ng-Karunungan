<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$user = current_user();


/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$totalUsers = (int) db()->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'user'
      AND status = 'active'
")->fetchColumn();


$totalBooks = (int) db()->query("
    SELECT COUNT(*)
    FROM books
    WHERE status = 'active'
")->fetchColumn();


$totalCopies = (int) db()->query("
    SELECT COALESCE(SUM(total_copies), 0)
    FROM books
    WHERE status = 'active'
")->fetchColumn();


$availableCopies = (int) db()->query("
    SELECT COALESCE(SUM(available_copies), 0)
    FROM books
    WHERE status = 'active'
")->fetchColumn();


$pendingReservations = (int) db()->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'pending'
")->fetchColumn();


$readyReservations = (int) db()->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'ready'
")->fetchColumn();


$borrowedBooks = (int) db()->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'borrowed'
")->fetchColumn();


$overdueBooks = (int) db()->query("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'borrowed'
      AND due_at IS NOT NULL
      AND due_at < NOW()
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| RECENT RESERVATIONS
|--------------------------------------------------------------------------
*/

$recentStmt = db()->query("
    SELECT
        reservations.id,
        reservations.status,
        reservations.reserved_at,

        users.name AS user_name,

        books.title AS book_title,
        books.author AS book_author

    FROM reservations

    INNER JOIN users
        ON users.id = reservations.user_id

    INNER JOIN books
        ON books.id = reservations.book_id

    ORDER BY reservations.reserved_at DESC

    LIMIT 5
");

$recentReservations = $recentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| STATUS STYLE
|--------------------------------------------------------------------------
*/

function dashboard_status(string $status): array
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
            'label' => 'Ready',
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
        Admin Dashboard | Sentro ng Karunungan
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


    <!-- WELCOME -->

    <section style="margin-bottom:30px;">

        <p class="eyebrow">
            LIBRARY ADMINISTRATION
        </p>

        <h1 style="margin-bottom:8px;">
            Welcome, <?= e($user['name']) ?>
        </h1>

        <p class="muted">
            Here's an overview of the Sentro ng Karunungan
            library system.
        </p>

    </section>


    <!-- MAIN STATISTICS -->

    <section
        style="
            display:grid;
            grid-template-columns:
                repeat(auto-fit, minmax(190px, 1fr));
            gap:18px;
            margin-bottom:32px;
        "
    >


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Registered Users
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                "
            >
                <?= $totalUsers ?>
            </div>

            <p class="muted">
                Active reader accounts
            </p>

        </div>


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Book Titles
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                "
            >
                <?= $totalBooks ?>
            </div>

            <p class="muted">
                Active books in catalog
            </p>

        </div>


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Available Copies
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                "
            >
                <?= $availableCopies ?>
            </div>

            <p class="muted">
                Out of <?= $totalCopies ?> total copies
            </p>

        </div>


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Pending Requests
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                "
            >
                <?= $pendingReservations ?>
            </div>

            <p class="muted">
                Waiting for review
            </p>

        </div>


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Ready for Pickup
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                "
            >
                <?= $readyReservations ?>
            </div>

            <p class="muted">
                Waiting for collection
            </p>

        </div>


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Borrowed
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                "
            >
                <?= $borrowedBooks ?>
            </div>

            <p class="muted">
                Books currently borrowed
            </p>

        </div>


        <div class="card">

            <div
                style="
                    font-size:13px;
                    font-weight:700;
                    color:#687386;
                    text-transform:uppercase;
                "
            >
                Overdue
            </div>

            <div
                style="
                    font-size:36px;
                    font-weight:800;
                    margin-top:8px;
                    color:
                        <?= $overdueBooks > 0
                            ? '#a52a2a'
                            : 'inherit'
                        ?>;
                "
            >
                <?= $overdueBooks ?>
            </div>

            <p class="muted">
                Past their due dates
            </p>

        </div>


    </section>


    <!-- QUICK ACTIONS -->

    <section style="margin-bottom:35px;">

        <h2>
            Quick Actions
        </h2>

        <div
            style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fit, minmax(210px, 1fr));
                gap:16px;
            "
        >


            <a
                href="<?= e(url('admin/add-book.php')) ?>"
                class="card"
                style="
                    text-decoration:none;
                    color:inherit;
                "
            >

                <h3>
                    + Add New Book
                </h3>

                <p class="muted">
                    Add a title, author, cover,
                    category and number of copies.
                </p>

            </a>


            <a
                href="<?= e(url('admin/books.php')) ?>"
                class="card"
                style="
                    text-decoration:none;
                    color:inherit;
                "
            >

                <h3>
                    Manage Books
                </h3>

                <p class="muted">
                    Edit book information,
                    availability and status.
                </p>

            </a>


            <a
                href="<?= e(
                    url(
                        'admin/reservations.php?status=pending'
                    )
                ) ?>"
                class="card"
                style="
                    text-decoration:none;
                    color:inherit;
                "
            >

                <h3>
                    Review Reservations
                </h3>

                <p class="muted">

                    <?= $pendingReservations ?>

                    pending request<?= $pendingReservations === 1 ? '' : 's' ?>

                    waiting.

                </p>

            </a>


            <a
                href="<?= e(
                    url(
                        'admin/reservations.php?status=borrowed'
                    )
                ) ?>"
                class="card"
                style="
                    text-decoration:none;
                    color:inherit;
                "
            >

                <h3>
                    Borrowed Books
                </h3>

                <p class="muted">
                    Track currently borrowed
                    books and their due dates.
                </p>

            </a>


        </div>

    </section>


    <!-- RECENT RESERVATIONS -->

    <section>

        <div
            style="
                display:flex;
                justify-content:space-between;
                gap:20px;
                align-items:center;
                margin-bottom:15px;
            "
        >

            <div>

                <h2 style="margin-bottom:5px;">
                    Recent Reservations
                </h2>

                <p
                    class="muted"
                    style="margin:0;"
                >
                    Latest book requests made by users.
                </p>

            </div>


            <a
                href="<?= e(url('admin/reservations.php')) ?>"
            >
                View All
            </a>

        </div>


        <div class="card">


            <?php if (!$recentReservations): ?>


                <p class="muted">
                    No reservations have been created yet.
                </p>


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

                                <th
                                    style="
                                        text-align:left;
                                        padding:12px;
                                    "
                                >
                                    User
                                </th>

                                <th
                                    style="
                                        text-align:left;
                                        padding:12px;
                                    "
                                >
                                    Book
                                </th>

                                <th
                                    style="
                                        text-align:left;
                                        padding:12px;
                                    "
                                >
                                    Status
                                </th>

                                <th
                                    style="
                                        text-align:left;
                                        padding:12px;
                                    "
                                >
                                    Requested
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $recentReservations as $reservation
                        ): ?>


                            <?php

                            $status =
                                dashboard_status(
                                    $reservation['status']
                                );

                            ?>


                            <tr
                                style="
                                    border-top:
                                        1px solid #e7ebf0;
                                "
                            >

                                <td style="padding:12px;">

                                    <strong>
                                        <?= e(
                                            $reservation['user_name']
                                        ) ?>
                                    </strong>

                                </td>


                                <td style="padding:12px;">

                                    <strong>
                                        <?= e(
                                            $reservation['book_title']
                                        ) ?>
                                    </strong>

                                    <div
                                        class="muted"
                                        style="
                                            font-size:13px;
                                            margin-top:3px;
                                        "
                                    >

                                        <?= e(
                                            $reservation['book_author']
                                        ) ?>

                                    </div>

                                </td>


                                <td style="padding:12px;">

                                    <span
                                        style="
                                            display:inline-block;
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
                                        <?= e($status['label']) ?>
                                    </span>

                                </td>


                                <td style="padding:12px;">

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $reservation['reserved_at']
                                        )
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </div>

    </section>


</main>

</body>

</html>