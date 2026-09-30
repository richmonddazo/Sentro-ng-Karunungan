<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$user = current_user();

$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);

$categoryStmt = db()->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $categoryStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Build Book Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        books.id,
        books.title,
        books.author,
        books.isbn,
        books.publisher,
        books.publication_year,
        books.description,
        books.cover_image,
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
            OR books.publisher LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/

if ($category_id > 0) {

    $sql .= "
        AND books.category_id = ?
    ";

    $params[] = $category_id;
}

$sql .= "
    ORDER BY books.title ASC
";

$stmt = db()->prepare($sql);
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
        Browse Books | Sentro ng Karunungan
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
        LIBRARY CATALOG
    </p>

    <h1>
        Browse Books
    </h1>

    <p class="muted">
        Search the library collection and check which books are currently available.
    </p>


    <!-- SEARCH AND FILTER -->

    <form
        method="GET"
        style="
            display:grid;
            grid-template-columns:2fr 1fr auto;
            gap:12px;
            margin-top:28px;
            margin-bottom:30px;
        "
    >

        <input
            type="text"
            name="search"
            placeholder="Search title, author, ISBN, or publisher..."
            value="<?= e($search) ?>"
            style="
                width:100%;
                padding:13px;
                border:1px solid #d8dfeb;
                border-radius:12px;
                font:inherit;
            "
        >


        <select
            name="category"
            style="
                width:100%;
                padding:13px;
                border:1px solid #d8dfeb;
                border-radius:12px;
                font:inherit;
                background:white;
            "
        >

            <option value="0">
                All Categories
            </option>

            <?php foreach ($categories as $category): ?>

                <option
                    value="<?= (int)$category['id'] ?>"
                    <?= $category_id === (int)$category['id']
                        ? 'selected'
                        : ''
                    ?>
                >
                    <?= e($category['name']) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="submit"
            class="btn primary"
        >
            Search
        </button>

    </form>


    <?php if ($search !== '' || $category_id > 0): ?>

        <div style="margin-bottom:20px;">

            <a href="<?= e(url('user/catalog.php')) ?>">
                Clear Search / Filters
            </a>

        </div>

    <?php endif; ?>


    <!-- RESULTS COUNT -->

    <div style="margin-bottom:20px;">

        <strong>
            <?= count($books) ?>
        </strong>

        <?= count($books) === 1 ? 'book found' : 'books found' ?>

    </div>


    <?php if (!$books): ?>

        <div class="card">

            <h3>
                No books found
            </h3>

            <p class="muted">
                Try changing your search keyword or category.
            </p>

        </div>

    <?php else: ?>


        <div
            style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fill, minmax(220px, 1fr));
                gap:22px;
            "
        >

            <?php foreach ($books as $book): ?>

                <article
                    class="card"
                    style="
                        display:flex;
                        flex-direction:column;
                        padding:0;
                        overflow:hidden;
                    "
                >

                    <!-- COVER -->

                    <div
                        style="
                            height:300px;
                            background:#eef1f6;
                            display:flex;
                            justify-content:center;
                            align-items:center;
                            overflow:hidden;
                        "
                    >

                        <?php if ($book['cover_image']): ?>

                            <img
                                src="<?= e(
                                    url(
                                        'uploads/covers/' .
                                        $book['cover_image']
                                    )
                                ) ?>"
                                alt="<?= e($book['title']) ?>"
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
                                    padding:20px;
                                "
                            >

                                <div
                                    style="
                                        font-size:42px;
                                        margin-bottom:10px;
                                    "
                                >
                                    📚
                                </div>

                                No Cover Available

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- BOOK INFORMATION -->

                    <div
                        style="
                            padding:20px;
                            display:flex;
                            flex-direction:column;
                            flex:1;
                        "
                    >

                        <div
                            style="
                                font-size:12px;
                                font-weight:700;
                                color:#2357d9;
                                text-transform:uppercase;
                                margin-bottom:8px;
                            "
                        >

                            <?= e(
                                $book['category_name']
                                ?? 'Uncategorized'
                            ) ?>

                        </div>


                        <h3
                            style="
                                margin:0 0 7px;
                                line-height:1.3;
                            "
                        >

                            <?= e($book['title']) ?>

                        </h3>


                        <p
                            style="
                                margin:0 0 12px;
                                color:#687386;
                            "
                        >

                            by <?= e($book['author']) ?>

                        </p>


                        <?php if ($book['publication_year']): ?>

                            <p
                                style="
                                    margin:0 0 12px;
                                    font-size:14px;
                                "
                            >

                                Published:
                                <?= (int)$book['publication_year'] ?>

                            </p>

                        <?php endif; ?>


                        <div
                            style="
                                margin-top:auto;
                                padding-top:18px;
                            "
                        >

                            <?php if (
                                (int)$book['available_copies'] > 0
                            ): ?>

                                <div
                                    style="
                                        background:#ecf9f0;
                                        color:#176638;
                                        padding:9px 11px;
                                        border-radius:10px;
                                        margin-bottom:14px;
                                        font-size:14px;
                                        font-weight:700;
                                    "
                                >

                                    Available:
                                    <?= (int)$book['available_copies'] ?>

                                    of

                                    <?= (int)$book['total_copies'] ?>

                                </div>

                            <?php else: ?>

                                <div
                                    style="
                                        background:#fff1f1;
                                        color:#9a1c1c;
                                        padding:9px 11px;
                                        border-radius:10px;
                                        margin-bottom:14px;
                                        font-size:14px;
                                        font-weight:700;
                                    "
                                >

                                    Currently Unavailable

                                </div>

                            <?php endif; ?>


                            <a
                                href="<?= e(
                                    url(
                                        'user/book.php?id=' .
                                        (int)$book['id']
                                    )
                                ) ?>"
                                class="btn primary"
                                style="
                                    display:block;
                                    text-align:center;
                                "
                            >

                                View Book

                            </a>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

</body>

</html>