<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$admin = current_user();
$pdo = db();

$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? 'all');


/*
|--------------------------------------------------------------------------
| Inventory Summary
|--------------------------------------------------------------------------
*/

$totalCopies =
    (int)$pdo->query("
        SELECT COALESCE(SUM(total_copies), 0)
        FROM books
        WHERE status = 'active'
    ")->fetchColumn();


$availableCopies =
    (int)$pdo->query("
        SELECT COALESCE(SUM(available_copies), 0)
        FROM books
        WHERE status = 'active'
    ")->fetchColumn();


$borrowedCopies =
    max(
        0,
        $totalCopies - $availableCopies
    );


$lowStockBooks =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM books
        WHERE status = 'active'
          AND available_copies BETWEEN 1 AND 2
    ")->fetchColumn();


$unavailableBooks =
    (int)$pdo->query("
        SELECT COUNT(*)
        FROM books
        WHERE status = 'active'
          AND available_copies = 0
    ")->fetchColumn();


/*
|--------------------------------------------------------------------------
| Inventory Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        books.id,
        books.title,
        books.author,
        books.isbn,
        books.total_copies,
        books.available_copies,
        books.status,

        categories.name AS category_name

    FROM books

    LEFT JOIN categories
        ON categories.id = books.category_id

    WHERE books.status = 'active'
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
            books.title LIKE ?
            OR books.author LIKE ?
            OR books.isbn LIKE ?
        )
    ";

    $value = '%' . $search . '%';

    $params[] = $value;
    $params[] = $value;
    $params[] = $value;
}


/*
|--------------------------------------------------------------------------
| Availability Filter
|--------------------------------------------------------------------------
*/

if ($filter === 'available') {

    $sql .= "
        AND books.available_copies >= 3
    ";

} elseif ($filter === 'low') {

    $sql .= "
        AND books.available_copies BETWEEN 1 AND 2
    ";

} elseif ($filter === 'unavailable') {

    $sql .= "
        AND books.available_copies = 0
    ";
}


$sql .= "
    ORDER BY
        books.available_copies ASC,
        books.title ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$books = $stmt->fetchAll();

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
        Inventory | Sentro ng Karunungan
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
        BOOK MONITORING
    </p>

    <h1>
        Book Inventory
    </h1>

    <p class="muted">
        Monitor available, borrowed,
        low-stock, and unavailable books.
    </p>


    <!-- SUMMARY CARDS -->

    <section
        style="
            display:grid;
            grid-template-columns:
                repeat(auto-fit,minmax(170px,1fr));
            gap:16px;
            margin:28px 0;
        "
    >

        <div class="card">
            <h2><?= $totalCopies ?></h2>
            <div class="muted">
                Total Copies
            </div>
        </div>


        <div class="card">
            <h2><?= $availableCopies ?></h2>
            <div class="muted">
                Available Copies
            </div>
        </div>


        <div class="card">
            <h2><?= $borrowedCopies ?></h2>
            <div class="muted">
                Currently Unavailable
            </div>
        </div>


        <div class="card">
            <h2><?= $lowStockBooks ?></h2>
            <div class="muted">
                Low Stock Titles
            </div>
        </div>


        <div class="card">

            <h2
                style="
                    color:
                        <?= $unavailableBooks > 0
                            ? '#b42318'
                            : 'inherit'
                        ?>;
                "
            >
                <?= $unavailableBooks ?>
            </h2>

            <div class="muted">
                Unavailable Titles
            </div>

        </div>

    </section>


    <!-- FILTERS -->

    <form
        method="GET"
        style="
            display:flex;
            flex-wrap:wrap;
            gap:12px;
            margin-bottom:25px;
        "
    >

        <input
            type="search"
            name="search"
            value="<?= e($search) ?>"
            placeholder="Search title, author, or ISBN..."
            style="min-width:280px;"
        >


        <select name="filter">

            <option
                value="all"
                <?= $filter === 'all'
                    ? 'selected'
                    : ''
                ?>
            >
                All Books
            </option>

            <option
                value="available"
                <?= $filter === 'available'
                    ? 'selected'
                    : ''
                ?>
            >
                Available
            </option>

            <option
                value="low"
                <?= $filter === 'low'
                    ? 'selected'
                    : ''
                ?>
            >
                Low Stock
            </option>

            <option
                value="unavailable"
                <?= $filter === 'unavailable'
                    ? 'selected'
                    : ''
                ?>
            >
                Unavailable
            </option>

        </select>


        <button
            type="submit"
            class="btn primary"
        >
            Filter
        </button>


        <a
            href="<?= e(url('admin/inventory.php')) ?>"
            class="btn"
        >
            Clear
        </a>

    </form>


    <!-- INVENTORY TABLE -->

    <section class="card">

        <?php if (!$books): ?>

            <p class="muted">
                No books found.
            </p>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table>

                    <thead>

                    <tr>

                        <th>Book</th>

                        <th>Category</th>

                        <th>Total</th>

                        <th>Available</th>

                        <th>Unavailable</th>

                        <th>Inventory Status</th>

                        <th>Action</th>

                    </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($books as $book): ?>

                        <?php

                        $total =
                            (int)$book['total_copies'];

                        $available =
                            (int)$book['available_copies'];

                        $unavailable =
                            max(
                                0,
                                $total - $available
                            );


                        if ($available === 0) {

                            $inventoryLabel =
                                'Unavailable';

                            $inventoryColor =
                                '#b42318';

                            $inventoryBackground =
                                '#fee4e2';

                        } elseif ($available <= 2) {

                            $inventoryLabel =
                                'Low Stock';

                            $inventoryColor =
                                '#8a5700';

                            $inventoryBackground =
                                '#fff4cc';

                        } else {

                            $inventoryLabel =
                                'Available';

                            $inventoryColor =
                                '#176638';

                            $inventoryBackground =
                                '#e8f7f0';
                        }

                        ?>


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

                                <?php if (
                                    !empty($book['isbn'])
                                ): ?>

                                    <div
                                        class="muted"
                                        style="font-size:11px;"
                                    >
                                        ISBN:
                                        <?= e($book['isbn']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    $book['category_name']
                                    ?? 'Uncategorized'
                                ) ?>

                            </td>


                            <td>
                                <?= $total ?>
                            </td>


                            <td>

                                <strong>
                                    <?= $available ?>
                                </strong>

                            </td>


                            <td>
                                <?= $unavailable ?>
                            </td>


                            <td>

                                <span
                                    style="
                                        display:inline-block;
                                        padding:6px 10px;
                                        border-radius:999px;
                                        font-size:12px;
                                        font-weight:700;
                                        color:
                                            <?= $inventoryColor ?>;
                                        background:
                                            <?= $inventoryBackground ?>;
                                    "
                                >

                                    <?= e($inventoryLabel) ?>

                                </span>

                            </td>


                            <td>

                                <a
                                    href="<?= e(
                                        url(
                                            'admin/edit-book.php?id='
                                            .
                                            (int)$book['id']
                                        )
                                    ) ?>"
                                    class="btn"
                                >
                                    Edit
                                </a>

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