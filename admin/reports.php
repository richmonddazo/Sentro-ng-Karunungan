<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$admin = current_user();

$pdo = db();

/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to'] ?? '');


function valid_report_date(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $d = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    return $d
        && $d->format('Y-m-d') === $date;
}


if (!valid_report_date($fromDate)) {
    $fromDate = '';
}

if (!valid_report_date($toDate)) {
    $toDate = '';
}


/*
|--------------------------------------------------------------------------
| Reservation Date Condition
|--------------------------------------------------------------------------
*/

$dateCondition = '';
$dateParams = [];


if ($fromDate !== '') {

    $dateCondition .= "
        AND reservations.reserved_at >= ?
    ";

    $dateParams[] =
        $fromDate . ' 00:00:00';
}


if ($toDate !== '') {

    $dateCondition .= "
        AND reservations.reserved_at <= ?
    ";

    $dateParams[] =
        $toDate . ' 23:59:59';
}

/*
|--------------------------------------------------------------------------
| SUMMARY STATISTICS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE 1 = 1
    $dateCondition
");

$stmt->execute($dateParams);

$totalReservations =
    (int)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'borrowed'
    $dateCondition
");

$stmt->execute($dateParams);

$totalBorrowed =
    (int)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'returned'
    $dateCondition
");

$stmt->execute($dateParams);

$totalReturned =
    (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'pending'
    $dateCondition
");

$stmt->execute($dateParams);

$totalPending =
    (int)$stmt->fetchColumn();
    
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'ready'
    $dateCondition
");

$stmt->execute($dateParams);

$totalReady =
    (int)$stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reservations
    WHERE status = 'borrowed'
      AND due_at IS NOT NULL
      AND due_at < NOW()
    $dateCondition
");

$stmt->execute($dateParams);

$totalOverdue =
    (int)$stmt->fetchColumn();


$totalUsers =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'user'
    ")->fetchColumn();


$totalBooks =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM books
        WHERE status = 'active'
    ")->fetchColumn();


/*
|--------------------------------------------------------------------------
| MOST RESERVED BOOKS
|--------------------------------------------------------------------------
*/

$topBooksStmt =
    $pdo->query("
        SELECT
            books.id,
            books.title,
            books.author,

            COUNT(
                reservations.id
            ) AS reservation_count

        FROM books

        LEFT JOIN reservations
            ON reservations.book_id = books.id

        GROUP BY
            books.id,
            books.title,
            books.author

        ORDER BY
            reservation_count DESC,
            books.title ASC

        LIMIT 10
    ");

$topBooks =
    $topBooksStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RESERVATION STATUS BREAKDOWN
|--------------------------------------------------------------------------
*/

$statusStmt =
    $pdo->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM reservations

        GROUP BY status

        ORDER BY total DESC
    ");

$statusBreakdown =
    $statusStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| MONTHLY RESERVATION ACTIVITY
|--------------------------------------------------------------------------
|
| Last 6 months including current month.
|--------------------------------------------------------------------------
*/

$monthlyStmt =
    $pdo->query("
        SELECT
            DATE_FORMAT(
                reserved_at,
                '%Y-%m'
            ) AS month_key,

            DATE_FORMAT(
                reserved_at,
                '%b %Y'
            ) AS month_label,

            COUNT(*) AS total

        FROM reservations

        WHERE reserved_at >=
            DATE_SUB(
                DATE_FORMAT(
                    CURDATE(),
                    '%Y-%m-01'
                ),
                INTERVAL 5 MONTH
            )

        GROUP BY
            month_key,
            month_label

        ORDER BY
            month_key ASC
    ");

$monthlyActivity =
    $monthlyStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| RECENT BORROWING ACTIVITY
|--------------------------------------------------------------------------
*/

$recentStmt =
    $pdo->query("
        SELECT
            reservations.id,
            reservations.status,
            reservations.reserved_at,
            reservations.borrowed_at,
            reservations.due_at,
            reservations.returned_at,

            users.name AS user_name,

            books.title AS book_title

        FROM reservations

        INNER JOIN users
            ON users.id = reservations.user_id

        INNER JOIN books
            ON books.id = reservations.book_id

        ORDER BY
            reservations.reserved_at DESC

        LIMIT 15
    ");

$recentActivity =
    $recentStmt->fetchAll();

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
        Reports | Sentro ng Karunungan
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

        <a href="<?= e(url('admin/inventory.php')) ?>">
    Inventory
        </a>

        <a href="<?= e(url('admin/reports.php')) ?>">
            Reports
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
        LIBRARY ANALYTICS
    </p>

    <h1>
        Reports & Analytics
    </h1>

    <p class="muted">
        Overview of book reservations,
        borrowing activity, and library usage.
    </p>

    <form
    method="GET"
    style="
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:end;
        margin-top:24px;
        margin-bottom:20px;
    "
>

    <div>

        <label for="from">
            From Date
        </label>

        <input
            type="date"
            id="from"
            name="from"
            value="<?= e($fromDate) ?>"
        >

    </div>


    <div>

        <label for="to">
            To Date
        </label>

        <input
            type="date"
            id="to"
            name="to"
            value="<?= e($toDate) ?>"
        >

    </div>


    <button
        type="submit"
        class="btn primary"
    >
        Apply Filter
    </button>


    <a
        href="<?= e(url('admin/reports.php')) ?>"
        class="btn"
    >
        Clear
    </a>

</form>

    <div style="margin-top:20px;">

  <?php

$exportUrl =
    'admin/export-report.php?' .
    http_build_query([
        'from' => $fromDate,
        'to'   => $toDate
    ]);

$pdfExportUrl =
    'admin/export-report-pdf.php?' .
    http_build_query([
        'from' => $fromDate,
        'to'   => $toDate
    ]);
?>

<a
    href="<?= e(url($exportUrl)) ?>"
    class="btn primary"
>
    Export Filtered Report
</a>

<a
    href="<?= e(url($pdfExportUrl)) ?>"
    class="btn"
>
    Export PDF
</a>

</div>

    <!-- SUMMARY -->

    <section
        style="
            display:grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(170px, 1fr)
                );
            gap:16px;
            margin:30px 0;
        "
    >

        <div class="card">
            <h2><?= $totalUsers ?></h2>
            <div class="muted">
                Registered Users
            </div>
        </div>


        <div class="card">
            <h2><?= $totalBooks ?></h2>
            <div class="muted">
                Book Titles
            </div>
        </div>


        <div class="card">
            <h2><?= $totalReservations ?></h2>
            <div class="muted">
                Total Reservations
            </div>
        </div>


        <div class="card">
            <h2><?= $totalPending ?></h2>
            <div class="muted">
                Pending
            </div>
        </div>


        <div class="card">
            <h2><?= $totalReady ?></h2>
            <div class="muted">
                Ready for Pickup
            </div>
        </div>


        <div class="card">
            <h2><?= $totalBorrowed ?></h2>
            <div class="muted">
                Borrowed
            </div>
        </div>


        <div class="card">

            <h2
                style="
                    color:
                        <?= $totalOverdue > 0
                            ? '#b42318'
                            : 'inherit'
                        ?>;
                "
            >
                <?= $totalOverdue ?>
            </h2>

            <div class="muted">
                Overdue
            </div>

        </div>


        <div class="card">
            <h2><?= $totalReturned ?></h2>
            <div class="muted">
                Returned
            </div>
        </div>

    </section>


    <!-- MONTHLY ACTIVITY -->

    <section
        class="card"
        style="margin-bottom:25px;"
    >

        <h2>
            Monthly Reservation Activity
        </h2>

        <p class="muted">
            Reservations recorded during the
            last six months.
        </p>


        <?php if (!$monthlyActivity): ?>

            <p class="muted">
                No reservation activity yet.
            </p>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>
                            <th>Month</th>
                            <th>Total Reservations</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $monthlyActivity
                        as $month
                    ): ?>

                        <tr>

                            <td>
                                <?= e(
                                    $month['month_label']
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= (int)$month['total'] ?>
                                </strong>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


    <!-- TWO COLUMN REPORT -->

    <div
        style="
            display:grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(340px,1fr)
                );
            gap:25px;
            margin-bottom:25px;
        "
    >


        <!-- TOP BOOKS -->

        <section class="card">

            <h2>
                Most Reserved Books
            </h2>

            <p class="muted">
                Books with the highest number
                of reservations.
            </p>


            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>
                            <th>Book</th>
                            <th>Reservations</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $topBooks as $book
                    ): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e($book['title']) ?>
                                </strong>

                                <div
                                    class="muted"
                                    style="font-size:12px;"
                                >
                                    <?= e($book['author']) ?>
                                </div>

                            </td>

                            <td>
                                <?= (int)$book[
                                    'reservation_count'
                                ] ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- STATUS BREAKDOWN -->

        <section class="card">

            <h2>
                Reservation Status
            </h2>

            <p class="muted">
                Current distribution of all
                reservation records.
            </p>


            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $statusBreakdown
                        as $row
                    ): ?>

                        <tr>

                            <td>
                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $row['status']
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <strong>
                                    <?= (int)$row['total'] ?>
                                </strong>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </div>


    <!-- RECENT ACTIVITY -->

    <section class="card">

        <h2>
            Recent Library Activity
        </h2>

        <p class="muted">
            Latest reservation and borrowing
            transactions.
        </p>


        <?php if (!$recentActivity): ?>

            <p class="muted">
                No transactions available.
            </p>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Book
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Reserved
                            </th>

                            <th>
                                Due Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $recentActivity
                        as $activity
                    ): ?>


                        <tr>

                            <td>
                                <?= e(
                                    $activity['user_name']
                                ) ?>
                            </td>

                            <td>

                                <strong>
                                    <?= e(
                                        $activity[
                                            'book_title'
                                        ]
                                    ) ?>
                                </strong>

                            </td>

                            <td>

                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $activity[
                                                'status'
                                            ]
                                        )
                                    )
                                ) ?>

                                <?php if (
                                    $activity['status']
                                        === 'borrowed'
                                    &&
                                    !empty(
                                        $activity['due_at']
                                    )
                                    &&
                                    strtotime(
                                        $activity['due_at']
                                    ) < time()
                                ): ?>

                                    <div
                                        style="
                                            color:#b42318;
                                            font-size:12px;
                                            font-weight:700;
                                            margin-top:3px;
                                        "
                                    >
                                        OVERDUE
                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $activity[
                                            'reserved_at'
                                        ]
                                    )
                                ) ?>

                            </td>

                            <td>

                                <?php if (
                                    $activity['due_at']
                                ): ?>

                                    <?= date(
                                        'M d, Y',
                                        strtotime(
                                            $activity[
                                                'due_at'
                                            ]
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


</main>

</body>

</html>