<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$user = current_user();

$book_id = (int)($_GET['id'] ?? 0);

if ($book_id <= 0) {
    exit('Invalid book ID.');
}


/*
|--------------------------------------------------------------------------
| Get Book
|--------------------------------------------------------------------------
*/

$stmt = db()->prepare("
    SELECT
        books.*,
        categories.name AS category_name
    FROM books

    LEFT JOIN categories
        ON categories.id = books.category_id

    WHERE books.id = :id
      AND books.status = 'active'

    LIMIT 1
");

$stmt->execute([
    ':id' => $book_id
]);

$book = $stmt->fetch();

if (!$book) {
    exit('Book not found or unavailable.');
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
        <?= e($book['title']) ?>
        | Sentro ng Karunungan
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


    <div style="margin-bottom:24px;">

        <a href="<?= e(url('user/catalog.php')) ?>">
            ← Back to Books
        </a>

    </div>


    <section
        class="card"
        style="
            display:grid;
            grid-template-columns:
                minmax(220px, 320px)
                1fr;
            gap:40px;
            align-items:start;
        "
    >


        <!-- BOOK COVER -->

        <div>

            <div
                style="
                    width:100%;
                    aspect-ratio:2 / 3;
                    background:#eef1f6;
                    border-radius:14px;
                    overflow:hidden;
                    display:flex;
                    justify-content:center;
                    align-items:center;
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
                            padding:30px;
                            color:#687386;
                        "
                    >

                        <div
                            style="
                                font-size:64px;
                                margin-bottom:12px;
                            "
                        >
                            📚
                        </div>

                        No Cover Available

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- BOOK DETAILS -->

        <div>

            <p class="eyebrow">
                <?= e(
                    $book['category_name']
                    ?? 'Uncategorized'
                ) ?>
            </p>


            <h1
                style="
                    margin-bottom:8px;
                "
            >
                <?= e($book['title']) ?>
            </h1>


            <p
                style="
                    font-size:20px;
                    color:#687386;
                    margin-top:0;
                "
            >
                by <?= e($book['author']) ?>
            </p>


            <!-- AVAILABILITY -->

            <div style="margin:24px 0;">

                <?php if (
                    (int)$book['available_copies'] > 0
                ): ?>

                    <div
                        style="
                            display:inline-block;
                            background:#ecf9f0;
                            color:#176638;
                            padding:10px 15px;
                            border-radius:12px;
                            font-weight:700;
                        "
                    >

                        Available

                        —

                        <?= (int)$book['available_copies'] ?>

                        of

                        <?= (int)$book['total_copies'] ?>

                        copies

                    </div>

                <?php else: ?>

                    <div
                        style="
                            display:inline-block;
                            background:#fff1f1;
                            color:#9a1c1c;
                            padding:10px 15px;
                            border-radius:12px;
                            font-weight:700;
                        "
                    >

                        Currently Unavailable

                    </div>

                <?php endif; ?>

            </div>


            <!-- BOOK INFORMATION -->

            <div
                style="
                    display:grid;
                    grid-template-columns:
                        repeat(2, minmax(180px, 1fr));
                    gap:18px;
                    margin:25px 0;
                "
            >


                <div>

                    <strong>
                        Category
                    </strong>

                    <p class="muted">

                        <?= e(
                            $book['category_name']
                            ?? 'Uncategorized'
                        ) ?>

                    </p>

                </div>


                <div>

                    <strong>
                        ISBN
                    </strong>

                    <p class="muted">

                        <?= e(
                            $book['isbn']
                            ?: 'Not specified'
                        ) ?>

                    </p>

                </div>


                <div>

                    <strong>
                        Publisher
                    </strong>

                    <p class="muted">

                        <?= e(
                            $book['publisher']
                            ?: 'Not specified'
                        ) ?>

                    </p>

                </div>


                <div>

                    <strong>
                        Publication Year
                    </strong>

                    <p class="muted">

                        <?= $book['publication_year']
                            ? (int)$book['publication_year']
                            : 'Not specified'
                        ?>

                    </p>

                </div>


            </div>


            <!-- DESCRIPTION -->

            <div
                style="
                    border-top:1px solid #e1e5ec;
                    padding-top:24px;
                "
            >

                <h3>
                    About this Book
                </h3>

                <?php if ($book['description']): ?>

                    <p
                        style="
                            line-height:1.7;
                            color:#4f5b6d;
                        "
                    >

                        <?= nl2br(
                            e($book['description'])
                        ) ?>

                    </p>

                <?php else: ?>

                    <p class="muted">
                        No description available.
                    </p>

                <?php endif; ?>

            </div>


            <!-- RESERVE AREA -->

            <div
                style="
                    margin-top:30px;
                    padding-top:25px;
                    border-top:1px solid #e1e5ec;
                "
            >

                <?php if (
                    (int)$book['available_copies'] > 0
                ): ?>

                    <a
                        href="<?= e(
                            url(
                                'user/reserve-book.php?id=' .
                                (int)$book['id']
                            )
                        ) ?>"
                        class="btn primary"
                        style="
                            display:inline-block;
                            padding:13px 24px;
                        "
                    >
                        Reserve This Book
                    </a>

                    <p
                        class="muted"
                        style="
                            margin-top:10px;
                            font-size:14px;
                        "
                    >
                        Your reservation will be sent to the
                        library administrator for approval.
                    </p>

                <?php else: ?>

                    <button
                        type="button"
                        disabled
                        style="
                            padding:13px 24px;
                            border:0;
                            border-radius:10px;
                            background:#d8dde6;
                            color:#788393;
                            font-weight:700;
                            cursor:not-allowed;
                        "
                    >
                        No Copies Available
                    </button>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>


</body>

</html>