<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$user = current_user();

$book_id = (int)($_GET['id'] ?? $_POST['book_id'] ?? 0);

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
    LIMIT 1
");

$stmt->execute([
    ':id' => $book_id
]);

$book = $stmt->fetch();

if (!$book || $book['status'] !== 'active') {
    exit('Book not found or unavailable.');
}


/*
|--------------------------------------------------------------------------
| Check Existing Reservation
|--------------------------------------------------------------------------
*/

$existingStmt = db()->prepare("
    SELECT id, status
    FROM reservations
    WHERE user_id = :user_id
      AND book_id = :book_id
      AND status IN (
          'pending',
          'approved',
          'ready',
          'borrowed'
      )
    LIMIT 1
");

$existingStmt->execute([
    ':user_id' => $user['id'],
    ':book_id' => $book_id
]);

$existingReservation = $existingStmt->fetch();

$error = null;


/*
|--------------------------------------------------------------------------
| Process Reservation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    if ($existingReservation) {

        $error =
            'You already have an active reservation for this book.';

    } else {

        try {

            db()->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock Book Row
            |--------------------------------------------------------------------------
            */

            $bookLockStmt = db()->prepare("
                SELECT
                    id,
                    title,
                    available_copies,
                    status
                FROM books
                WHERE id = ?
                FOR UPDATE
            ");

            $bookLockStmt->execute([
                $book_id
            ]);

            $lockedBook = $bookLockStmt->fetch();


            if (
                !$lockedBook ||
                $lockedBook['status'] !== 'active'
            ) {
                throw new Exception(
                    'This book is no longer available.'
                );
            }


            if ((int)$lockedBook['available_copies'] <= 0) {
                throw new Exception(
                    'There are currently no available copies of this book.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Again
            |--------------------------------------------------------------------------
            */

            $duplicateStmt = db()->prepare("
                SELECT id
                FROM reservations
                WHERE user_id = ?
                  AND book_id = ?
                  AND status IN (
                      'pending',
                      'approved',
                      'ready',
                      'borrowed'
                  )
                LIMIT 1
                FOR UPDATE
            ");

            $duplicateStmt->execute([
                $user['id'],
                $book_id
            ]);

            if ($duplicateStmt->fetch()) {

                throw new Exception(
                    'You already have an active reservation for this book.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Create Reservation
            |--------------------------------------------------------------------------
            */

            $reservationStmt = db()->prepare("
                INSERT INTO reservations (
                    user_id,
                    book_id,
                    status
                )
                VALUES (
                    :user_id,
                    :book_id,
                    'pending'
                )
            ");

            $reservationStmt->execute([
                ':user_id' => $user['id'],
                ':book_id' => $book_id
            ]);

            $reservation_id =
                (int)db()->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Add Audit Log
            |--------------------------------------------------------------------------
            */

            $auditStmt = db()->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    action,
                    entity_type,
                    entity_id,
                    details
                )
                VALUES (
                    :user_id,
                    :action,
                    :entity_type,
                    :entity_id,
                    :details
                )
            ");

            $auditStmt->execute([
                ':user_id' => $user['id'],
                ':action' => 'reservation_created',
                ':entity_type' => 'reservation',
                ':entity_id' => $reservation_id,
                ':details' =>
                    'Reservation requested for book: ' .
                    $lockedBook['title']
            ]);


            db()->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect After Success
            |--------------------------------------------------------------------------
            */

            header(
                'Location: ' .
                url(
                    'user/reserve-book.php?id=' .
                    $book_id .
                    '&success=1'
                )
            );

            exit;

        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            $error = $e->getMessage();
        }
    }
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
        Reserve Book | Sentro ng Karunungan
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


    <?php if (isset($_GET['success'])): ?>


        <div
            class="card"
            style="
                max-width:700px;
                margin:40px auto;
                text-align:center;
            "
        >

            <div
                style="
                    font-size:55px;
                    margin-bottom:15px;
                "
            >
                ✓
            </div>


            <p class="eyebrow">
                RESERVATION SUBMITTED
            </p>


            <h1>
                Reservation Pending
            </h1>


            <p class="muted">

                Your reservation request for

                <strong>
                    <?= e($book['title']) ?>
                </strong>

                has been submitted successfully.

            </p>


            <p class="muted">

                The library administrator will review your request.

            </p>


            <div
                style="
                    margin-top:25px;
                    display:flex;
                    gap:12px;
                    justify-content:center;
                    flex-wrap:wrap;
                "
            >

                <a
                    href="<?= e(url('user/catalog.php')) ?>"
                    class="btn primary"
                >
                    Browse More Books
                </a>

            </div>

        </div>


    <?php else: ?>


        <div style="margin-bottom:24px;">

            <a
                href="<?= e(
                    url(
                        'user/book.php?id=' .
                        $book['id']
                    )
                ) ?>"
            >
                ← Back to Book
            </a>

        </div>


        <div
            class="card"
            style="
                max-width:750px;
                margin:auto;
            "
        >


            <p class="eyebrow">
                BOOK RESERVATION
            </p>


            <h1>
                Confirm Reservation
            </h1>


            <p class="muted">
                Please review the book before submitting your request.
            </p>


            <?php if ($error): ?>

                <div class="alert error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <?php if ($existingReservation): ?>

                <div class="alert error">

                    You already have a

                    <strong>
                        <?= e(
                            ucfirst(
                                $existingReservation['status']
                            )
                        ) ?>
                    </strong>

                    reservation for this book.

                </div>

            <?php endif; ?>


            <div
                style="
                    display:flex;
                    gap:22px;
                    align-items:flex-start;
                    margin-top:30px;
                "
            >


                <!-- COVER -->

                <div
                    style="
                        width:130px;
                        min-width:130px;
                        height:190px;
                        background:#eef1f6;
                        border-radius:12px;
                        overflow:hidden;
                        display:flex;
                        align-items:center;
                        justify-content:center;
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
                                padding:12px;
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

                    <h2
                        style="
                            margin-top:0;
                            margin-bottom:8px;
                        "
                    >
                        <?= e($book['title']) ?>
                    </h2>


                    <p class="muted">
                        by <?= e($book['author']) ?>
                    </p>


                    <p>

                        <strong>
                            Category:
                        </strong>

                        <?= e(
                            $book['category_name']
                            ?? 'Uncategorized'
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Available Copies:
                        </strong>

                        <?= (int)$book['available_copies'] ?>

                    </p>

                </div>

            </div>


            <?php if (
                !$existingReservation &&
                (int)$book['available_copies'] > 0
            ): ?>


                <form
                    method="POST"
                    style="
                        margin-top:30px;
                        padding-top:25px;
                        border-top:1px solid #e3e8f0;
                    "
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="book_id"
                        value="<?= (int)$book['id'] ?>"
                    >


                    <p class="muted">

                        After submitting, your request will have a

                        <strong>
                            Pending
                        </strong>

                        status until the administrator approves or rejects it.

                    </p>


                    <div
                        style="
                            display:flex;
                            gap:12px;
                            flex-wrap:wrap;
                            margin-top:20px;
                        "
                    >

                        <button
                            type="submit"
                            class="btn primary"
                        >
                            Confirm Reservation
                        </button>


                        <a
                            href="<?= e(
                                url(
                                    'user/book.php?id=' .
                                    $book['id']
                                )
                            ) ?>"
                            style="
                                padding:12px 16px;
                            "
                        >
                            Cancel
                        </a>

                    </div>

                </form>


            <?php elseif (
                (int)$book['available_copies'] <= 0
            ): ?>


                <div
                    class="alert error"
                    style="margin-top:25px;"
                >

                    This book currently has no available copies.

                </div>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</main>

</body>

</html>