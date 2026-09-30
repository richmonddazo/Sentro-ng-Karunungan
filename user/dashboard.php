<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$user = current_user();
$pdo = db();


/*
|--------------------------------------------------------------------------
| USER LIBRARY STATISTICS
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->prepare("
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'approved') AS approved,
        SUM(status = 'ready') AS ready,
        SUM(status = 'borrowed') AS borrowed
    FROM reservations
    WHERE user_id = ?
");

$statsStmt->execute([
    $user['id']
]);

$stats = $statsStmt->fetch();

$pendingCount = (int)($stats['pending'] ?? 0);
$approvedCount = (int)($stats['approved'] ?? 0);
$readyCount = (int)($stats['ready'] ?? 0);
$borrowedCount = (int)($stats['borrowed'] ?? 0);


/*
|--------------------------------------------------------------------------
| FIND USER'S MOST USED CATEGORY
|--------------------------------------------------------------------------
|
| If the user has reserved books before, we use the category they
| interact with the most for recommendations.
|
*/

$preferredCategoryStmt = $pdo->prepare("
    SELECT
        books.category_id,
        categories.name AS category_name,
        COUNT(*) AS total
    FROM reservations

    INNER JOIN books
        ON books.id = reservations.book_id

    LEFT JOIN categories
        ON categories.id = books.category_id

    WHERE reservations.user_id = ?
      AND books.category_id IS NOT NULL

    GROUP BY
        books.category_id,
        categories.name

    ORDER BY total DESC

    LIMIT 1
");

$preferredCategoryStmt->execute([
    $user['id']
]);

$preferredCategory = $preferredCategoryStmt->fetch();


/*
|--------------------------------------------------------------------------
| RECOMMENDED BOOKS
|--------------------------------------------------------------------------
*/

$recommendedBooks = [];

if ($preferredCategory) {

    $recommendedStmt = $pdo->prepare("
        SELECT
            books.id,
            books.title,
            books.author,
            books.cover_image,
            books.available_copies,
            categories.name AS category_name

        FROM books

        LEFT JOIN categories
            ON categories.id = books.category_id

        WHERE books.status = 'active'
          AND books.available_copies > 0
          AND books.category_id = ?

        ORDER BY
            books.created_at DESC,
            books.title ASC

        LIMIT 10
    ");

    $recommendedStmt->execute([
        $preferredCategory['category_id']
    ]);

    $recommendedBooks = $recommendedStmt->fetchAll();
}


/*
|--------------------------------------------------------------------------
| FALLBACK RECOMMENDATIONS
|--------------------------------------------------------------------------
|
| New users may not have reservation history yet.
|--------------------------------------------------------------------------
*/

if (count($recommendedBooks) < 6) {

    $fallbackStmt = $pdo->query("
        SELECT
            books.id,
            books.title,
            books.author,
            books.cover_image,
            books.available_copies,
            categories.name AS category_name

        FROM books

        LEFT JOIN categories
            ON categories.id = books.category_id

        WHERE books.status = 'active'
          AND books.available_copies > 0

        ORDER BY
            books.created_at DESC,
            books.title ASC

        LIMIT 12
    ");

    $fallbackBooks = $fallbackStmt->fetchAll();

    $existingIds = [];

    foreach ($recommendedBooks as $book) {
        $existingIds[(int)$book['id']] = true;
    }

    foreach ($fallbackBooks as $book) {

        if (!isset($existingIds[(int)$book['id']])) {

            $recommendedBooks[] = $book;

            $existingIds[(int)$book['id']] = true;
        }

        if (count($recommendedBooks) >= 10) {
            break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| NEW ARRIVALS
|--------------------------------------------------------------------------
*/

$newArrivalsStmt = $pdo->query("
    SELECT
        books.id,
        books.title,
        books.author,
        books.cover_image,
        books.available_copies,
        categories.name AS category_name

    FROM books

    LEFT JOIN categories
        ON categories.id = books.category_id

    WHERE books.status = 'active'

    ORDER BY books.created_at DESC

    LIMIT 10
");

$newArrivals = $newArrivalsStmt->fetchAll();

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
        Dashboard | Sentro ng Karunungan
    </title>

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <style>

        .dashboard-hero {
            background:
                linear-gradient(
                    135deg,
                    #172f63,
                    #2454a6
                );
            color: white;
            border-radius: 22px;
            padding: 38px;
            margin-bottom: 30px;
        }

        .dashboard-hero h1 {
            margin-top: 0;
            margin-bottom: 10px;
            color: white;
        }

        .dashboard-hero p {
            color: rgba(255,255,255,.84);
        }

        .dashboard-search {
            display: flex;
            gap: 10px;
            margin-top: 24px;
            max-width: 700px;
        }

        .dashboard-search input {
            flex: 1;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            font: inherit;
        }

        .dashboard-search button {
            border: none;
            border-radius: 12px;
            padding: 14px 22px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            background: white;
            color: #17366f;
        }


        .library-stats {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            margin-bottom: 38px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 4px;
        }


        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 16px;
        }

        .section-heading h2 {
            margin: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | CAROUSEL
        |--------------------------------------------------------------------------
        */

        .carousel-wrapper {
            position: relative;
            margin-bottom: 42px;
        }

        .book-carousel {
            display: flex;
            gap: 18px;

            overflow-x: auto;

            scroll-behavior: smooth;
            scroll-snap-type: x mandatory;

            padding: 4px 2px 15px;

            scrollbar-width: thin;
        }

        .carousel-card {
            flex: 0 0 220px;

            scroll-snap-align: start;

            background: white;

            border: 1px solid #e3e8f0;

            border-radius: 16px;

            overflow: hidden;

            display: flex;
            flex-direction: column;

            box-shadow:
                0 4px 16px rgba(0,0,0,.04);
        }

        .carousel-cover {
            height: 285px;

            background: #eef1f6;

            display: flex;
            align-items: center;
            justify-content: center;

            overflow: hidden;
        }

        .carousel-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-content {
            padding: 17px;

            display: flex;
            flex-direction: column;

            flex: 1;
        }

        .book-category {
            font-size: 11px;

            text-transform: uppercase;

            font-weight: 800;

            color: #2454a6;

            margin-bottom: 7px;
        }

        .book-title {
            font-size: 17px;

            font-weight: 800;

            line-height: 1.3;

            margin-bottom: 6px;
        }

        .book-author {
            color: #687386;

            font-size: 14px;

            margin-bottom: 14px;
        }

        .availability {
            font-size: 13px;

            color: #176638;

            margin-top: auto;

            margin-bottom: 12px;

            font-weight: 700;
        }

        .carousel-controls {
            display: flex;
            gap: 8px;
        }

        .carousel-button {
            width: 42px;
            height: 42px;

            border: 1px solid #d7deea;

            background: white;

            border-radius: 50%;

            cursor: pointer;

            font-size: 20px;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-button:hover {
            background: #f3f6fa;
        }


        .quick-links {
            display: grid;

            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));

            gap: 16px;

            margin-bottom: 40px;
        }

        .quick-link {
            text-decoration: none;

            color: inherit;

            transition: transform .15s ease;
        }

        .quick-link:hover {
            transform: translateY(-3px);
        }


        @media (max-width: 700px) {

            .dashboard-hero {
                padding: 25px;
            }

            .dashboard-search {
                flex-direction: column;
            }

            .carousel-card {
                flex-basis: 180px;
            }

            .carousel-cover {
                height: 245px;
            }
        }

    </style>

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


    <!-- HERO -->

    <section class="dashboard-hero">

        <p
            style="
                font-size:13px;
                letter-spacing:1px;
                font-weight:700;
                margin-bottom:8px;
            "
        >
            SENTRO NG KARUNUNGAN
        </p>

        <h1>
            Welcome back, <?= e($user['name']) ?>
        </h1>

        <p>
            Find your next book, explore the library,
            and manage your reservations.
        </p>


        <form
            method="GET"
            action="<?= e(url('user/catalog.php')) ?>"
            class="dashboard-search"
        >

            <input
                type="text"
                name="search"
                placeholder="Search books, authors, ISBN..."
            >

            <button type="submit">
                Search Books
            </button>

        </form>

    </section>


    <!-- USER LIBRARY STATUS -->

    <section class="library-stats">

        <div class="card">

            <div class="stat-number">
                <?= $pendingCount ?>
            </div>

            <strong>
                Pending
            </strong>

            <p class="muted">
                Awaiting librarian review
            </p>

        </div>


        <div class="card">

            <div class="stat-number">
                <?= $approvedCount ?>
            </div>

            <strong>
                Approved
            </strong>

            <p class="muted">
                Approved reservations
            </p>

        </div>


        <div class="card">

            <div class="stat-number">
                <?= $readyCount ?>
            </div>

            <strong>
                Ready
            </strong>

            <p class="muted">
                Ready for pickup
            </p>

        </div>


        <div class="card">

            <div class="stat-number">
                <?= $borrowedCount ?>
            </div>

            <strong>
                Borrowed
            </strong>

            <p class="muted">
                Currently borrowed
            </p>

        </div>

    </section>


    <!-- RECOMMENDED BOOKS -->

    <section>

        <div class="section-heading">

            <div>

                <h2>
                    Recommended for You
                </h2>

                <p class="muted">

                    <?php if ($preferredCategory): ?>

                        Based on your interest in

                        <strong>
                            <?= e(
                                $preferredCategory['category_name']
                            ) ?>
                        </strong>.

                    <?php else: ?>

                        Popular and available books
                        from the library.

                    <?php endif; ?>

                </p>

            </div>


            <div class="carousel-controls">

                <button
                    type="button"
                    class="carousel-button"
                    onclick="moveCarousel(
                        'recommendedCarousel',
                        -1
                    )"
                    aria-label="Previous books"
                >
                    ‹
                </button>

                <button
                    type="button"
                    class="carousel-button"
                    onclick="moveCarousel(
                        'recommendedCarousel',
                        1
                    )"
                    aria-label="Next books"
                >
                    ›
                </button>

            </div>

        </div>


        <div class="carousel-wrapper">

            <div
                class="book-carousel"
                id="recommendedCarousel"
            >

                <?php foreach (
                    $recommendedBooks as $book
                ): ?>

                    <article class="carousel-card">


                        <div class="carousel-cover">

                            <?php if ($book['cover_image']): ?>

                                <img
                                    src="<?= e(
                                        url(
                                            'uploads/covers/' .
                                            $book['cover_image']
                                        )
                                    ) ?>"
                                    alt="<?= e($book['title']) ?>"
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
                                            font-size:48px;
                                            margin-bottom:8px;
                                        "
                                    >
                                        📚
                                    </div>

                                    No Cover

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="carousel-content">

                            <div class="book-category">

                                <?= e(
                                    $book['category_name']
                                    ?? 'Uncategorized'
                                ) ?>

                            </div>


                            <div class="book-title">

                                <?= e($book['title']) ?>

                            </div>


                            <div class="book-author">

                                by <?= e($book['author']) ?>

                            </div>


                            <div class="availability">

                                <?= (int)$book['available_copies'] ?>

                                cop<?= (int)$book['available_copies'] === 1
                                    ? 'y'
                                    : 'ies'
                                ?>

                                available

                            </div>


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


                    </article>

                <?php endforeach; ?>


            </div>

        </div>

    </section>


    <!-- NEW ARRIVALS -->

    <section>

        <div class="section-heading">

            <div>

                <h2>
                    New Arrivals
                </h2>

                <p class="muted">
                    Recently added books in the library.
                </p>

            </div>


            <div class="carousel-controls">

                <button
                    type="button"
                    class="carousel-button"
                    onclick="moveCarousel(
                        'newCarousel',
                        -1
                    )"
                >
                    ‹
                </button>

                <button
                    type="button"
                    class="carousel-button"
                    onclick="moveCarousel(
                        'newCarousel',
                        1
                    )"
                >
                    ›
                </button>

            </div>

        </div>


        <div class="carousel-wrapper">

            <div
                class="book-carousel"
                id="newCarousel"
            >


                <?php foreach ($newArrivals as $book): ?>


                    <article class="carousel-card">


                        <div class="carousel-cover">


                            <?php if ($book['cover_image']): ?>

                                <img
                                    src="<?= e(
                                        url(
                                            'uploads/covers/' .
                                            $book['cover_image']
                                        )
                                    ) ?>"
                                    alt="<?= e($book['title']) ?>"
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
                                            font-size:48px;
                                            margin-bottom:8px;
                                        "
                                    >
                                        📚
                                    </div>

                                    No Cover

                                </div>

                            <?php endif; ?>


                        </div>


                        <div class="carousel-content">

                            <div class="book-category">

                                <?= e(
                                    $book['category_name']
                                    ?? 'Uncategorized'
                                ) ?>

                            </div>


                            <div class="book-title">

                                <?= e($book['title']) ?>

                            </div>


                            <div class="book-author">

                                by <?= e($book['author']) ?>

                            </div>


                            <?php if (
                                (int)$book['available_copies'] > 0
                            ): ?>

                                <div class="availability">

                                    <?= (int)$book['available_copies'] ?>

                                    available

                                </div>

                            <?php else: ?>

                                <div
                                    style="
                                        margin-top:auto;
                                        margin-bottom:12px;
                                        color:#a52a2a;
                                        font-size:13px;
                                        font-weight:700;
                                    "
                                >
                                    Currently unavailable
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


                    </article>


                <?php endforeach; ?>


            </div>

        </div>

    </section>


    <!-- QUICK LINKS -->

    <section>

        <div class="section-heading">

            <div>

                <h2>
                    My Library
                </h2>

                <p class="muted">
                    Quick access to your library activities.
                </p>

            </div>

        </div>


        <div class="quick-links">


            <a
                href="<?= e(url('user/catalog.php')) ?>"
                class="card quick-link"
            >

                <h3>
                    Browse Library
                </h3>

                <p class="muted">
                    Search and explore all available books.
                </p>

            </a>


            <a
                href="<?= e(url('user/reservations.php')) ?>"
                class="card quick-link"
            >

                <h3>
                    My Reservations
                </h3>

                <p class="muted">
                    Track pending, approved, ready,
                    borrowed and returned books.
                </p>

            </a>


        </div>

    </section>


</main>


<script>

function moveCarousel(id, direction) {

    const carousel =
        document.getElementById(id);

    if (!carousel) {
        return;
    }

    const amount =
        Math.max(
            250,
            carousel.clientWidth * 0.8
        );

    carousel.scrollBy({
        left: amount * direction,
        behavior: 'smooth'
    });
}

</script>


</body>

</html>