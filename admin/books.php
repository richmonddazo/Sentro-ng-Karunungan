<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$user = current_user();

$stmt = db()->query("
    SELECT
        books.id,
        books.title,
        books.author,
        books.isbn,
        books.cover_image,
        books.total_copies,
        books.available_copies,
        books.status,
        books.created_at,
        categories.name AS category_name
    FROM books
    LEFT JOIN categories
        ON categories.id = books.category_id
    ORDER BY books.created_at DESC
");

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
        Manage Books | Sentro ng Karunungan
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
        ADMIN BOOK MANAGEMENT
    </p>

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:20px;
            margin-bottom:25px;
        "
    >

        <div>

            <h1>
                Books
            </h1>

            <p class="muted">
                Manage the books available in the library.
            </p>

        </div>

        <a
            href="<?= e(url('admin/add-book.php')) ?>"
            class="button"
        >
            + Add Book
        </a>

    </div>


    <div class="card">

        <?php if (!$books): ?>

            <h3>
                No books yet
            </h3>

            <p class="muted">
                Your library currently has no books.
                Click Add Book to create the first one.
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

                            <th style="text-align:left;padding:12px;">
                                Cover
                            </th>

                            <th style="text-align:left;padding:12px;">
                                Title
                            </th>

                            <th style="text-align:left;padding:12px;">
                                Author
                            </th>

                            <th style="text-align:left;padding:12px;">
                                Category
                            </th>

                            <th style="text-align:left;padding:12px;">
                                ISBN
                            </th>

                            <th style="text-align:left;padding:12px;">
                                Copies
                            </th>

                            <th style="text-align:left;padding:12px;">
                                Status
                            </th>

                            <th style="text-align:left;padding:12px;">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($books as $book): ?>

                        <tr
                            style="
                                border-top:1px solid #ddd;
                            "
                        >

                            <td style="padding:12px;">

                                <?php if ($book['cover_image']): ?>

                                    <img
                                        src="<?= e(url('uploads/covers/' . $book['cover_image'])) ?>"
                                        alt="<?= e($book['title']) ?>"
                                        style="
                                            width:55px;
                                            height:80px;
                                            object-fit:cover;
                                            border-radius:6px;
                                        "
                                    >

                                <?php else: ?>

                                    <div
                                        style="
                                            width:55px;
                                            height:80px;
                                            background:#eee;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            text-align:center;
                                            font-size:11px;
                                            border-radius:6px;
                                        "
                                    >
                                        No Cover
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td style="padding:12px;">

                                <strong>
                                    <?= e($book['title']) ?>
                                </strong>

                            </td>


                            <td style="padding:12px;">

                                <?= e($book['author']) ?>

                            </td>


                            <td style="padding:12px;">

                                <?= e($book['category_name'] ?? 'Uncategorized') ?>

                            </td>


                            <td style="padding:12px;">

                                <?= e($book['isbn'] ?? '—') ?>

                            </td>


                            <td style="padding:12px;">

                                <?= (int)$book['available_copies'] ?>
                                /
                                <?= (int)$book['total_copies'] ?>

                            </td>


                            <td style="padding:12px;">

                                <?= e(ucfirst($book['status'])) ?>

                            </td>


                            <td style="padding:12px;">

                                <a
                                    href="<?= e(url(
                                        'admin/edit-book.php?id='
                                        . (int)$book['id']
                                    )) ?>"
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

    </div>

</main>

</body>
</html>