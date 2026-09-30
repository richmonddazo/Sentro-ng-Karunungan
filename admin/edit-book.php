<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$user = current_user();

$book_id = (int)($_GET['id'] ?? 0);

if ($book_id <= 0) {
    exit('Invalid book ID.');
}

$stmt = db()->prepare("
    SELECT *
    FROM books
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $book_id
]);

$book = $stmt->fetch();

if (!$book) {
    exit('Book not found.');
}

$categoryStmt = db()->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $categoryStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $publisher = trim($_POST['publisher'] ?? '');
    $publication_year = trim($_POST['publication_year'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $total_copies = (int)($_POST['total_copies'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($title === '') {
        $errors[] = 'Book title is required.';
    }

    if ($author === '') {
        $errors[] = 'Author is required.';
    }

    if ($category_id <= 0) {
        $errors[] = 'Please select a category.';
    }

    if ($total_copies <= 0) {
        $errors[] = 'Total copies must be at least 1.';
    }

    if (!in_array($status, ['active', 'archived'], true)) {
        $errors[] = 'Invalid book status.';
    }

    $cover_filename = $book['cover_image'];

    if (
        isset($_FILES['cover_image']) &&
        $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was an error uploading the cover image.';
        } else {

            $allowed_types = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            $tmp_name = $_FILES['cover_image']['tmp_name'];
            $file_size = $_FILES['cover_image']['size'];

            if ($file_size > 5 * 1024 * 1024) {
                $errors[] = 'Cover image must be 5MB or smaller.';
            }

            $mime = mime_content_type($tmp_name);

            if (!in_array($mime, $allowed_types, true)) {
                $errors[] = 'Cover image must be JPG, PNG, or WEBP.';
            }

            if (!$errors) {

                $extension = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg'
                };

                $new_cover =
                    'book_' .
                    time() .
                    '_' .
                    bin2hex(random_bytes(4)) .
                    '.' .
                    $extension;

                $upload_dir =
                    __DIR__ .
                    '/../uploads/covers/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $destination =
                    $upload_dir .
                    $new_cover;

                if (
                    move_uploaded_file(
                        $tmp_name,
                        $destination
                    )
                ) {

                    if (
                        $book['cover_image'] &&
                        file_exists(
                            $upload_dir .
                            $book['cover_image']
                        )
                    ) {
                        unlink(
                            $upload_dir .
                            $book['cover_image']
                        );
                    }

                    $cover_filename = $new_cover;

                } else {

                    $errors[] = 'Failed to save the uploaded cover image.';
                }
            }
        }
    }

    if (!$errors) {

        $old_total = (int)$book['total_copies'];
        $old_available = (int)$book['available_copies'];

        $currently_unavailable =
            $old_total - $old_available;

        $new_available =
            $total_copies - $currently_unavailable;

        if ($new_available < 0) {
            $errors[] =
                'Total copies cannot be lower than the number of copies currently unavailable.';
        }
    }

    if (!$errors) {

        $stmt = db()->prepare("
            UPDATE books
            SET
                title = :title,
                author = :author,
                isbn = :isbn,
                category_id = :category_id,
                publisher = :publisher,
                publication_year = :publication_year,
                description = :description,
                cover_image = :cover_image,
                total_copies = :total_copies,
                available_copies = :available_copies,
                status = :status
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $title,
            ':author' => $author,
            ':isbn' => $isbn !== '' ? $isbn : null,
            ':category_id' => $category_id,
            ':publisher' =>
                $publisher !== ''
                    ? $publisher
                    : null,
            ':publication_year' =>
                $publication_year !== ''
                    ? (int)$publication_year
                    : null,
            ':description' =>
                $description !== ''
                    ? $description
                    : null,
            ':cover_image' => $cover_filename,
            ':total_copies' => $total_copies,
            ':available_copies' => $new_available,
            ':status' => $status,
            ':id' => $book_id
        ]);

        header(
            'Location: ' .
            url('admin/books.php')
        );

        exit;
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
        Edit Book | Sentro ng Karunungan
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

    <h1>
        Edit Book
    </h1>

    <p class="muted">
        Update book information and availability.
    </p>


    <?php if ($errors): ?>

        <div
            class="card"
            style="
                margin-bottom:20px;
                border:1px solid #d9534f;
            "
        >

            <strong>
                Please fix the following:
            </strong>

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data"
        class="card"
    >

        <div style="margin-bottom:16px;">

            <label>
                Book Title
            </label>

            <input
                type="text"
                name="title"
                value="<?= e($_POST['title'] ?? $book['title']) ?>"
                required
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Author
            </label>

            <input
                type="text"
                name="author"
                value="<?= e($_POST['author'] ?? $book['author']) ?>"
                required
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

        </div>


        <div style="margin-bottom:16px;">

            <label>
                ISBN
            </label>

            <input
                type="text"
                name="isbn"
                value="<?= e($_POST['isbn'] ?? $book['isbn']) ?>"
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Category
            </label>

            <select
                name="category_id"
                required
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

                <option value="">
                    Select category
                </option>

                <?php foreach ($categories as $category): ?>

                    <?php

                    $selected_category =
                        $_POST['category_id']
                        ?? $book['category_id'];

                    ?>

                    <option
                        value="<?= (int)$category['id'] ?>"
                        <?= (
                            $selected_category ==
                            $category['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($category['name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Publisher
            </label>

            <input
                type="text"
                name="publisher"
                value="<?= e($_POST['publisher'] ?? $book['publisher']) ?>"
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Publication Year
            </label>

            <input
                type="number"
                name="publication_year"
                min="1000"
                max="<?= date('Y') ?>"
                value="<?= e($_POST['publication_year'] ?? $book['publication_year']) ?>"
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Description
            </label>

            <textarea
                name="description"
                rows="6"
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            ><?= e($_POST['description'] ?? $book['description']) ?></textarea>

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Total Copies
            </label>

            <input
                type="number"
                name="total_copies"
                min="1"
                value="<?= e($_POST['total_copies'] ?? $book['total_copies']) ?>"
                required
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

            <small class="muted">
                Currently available:
                <?= (int)$book['available_copies'] ?>
            </small>

        </div>


        <div style="margin-bottom:16px;">

            <label>
                Status
            </label>

            <?php

            $selected_status =
                $_POST['status']
                ?? $book['status'];

            ?>

            <select
                name="status"
                style="
                    width:100%;
                    padding:10px;
                    margin-top:6px;
                "
            >

                <option
                    value="active"
                    <?= $selected_status === 'active'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Active
                </option>

                <option
                    value="archived"
                    <?= $selected_status === 'archived'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Archived
                </option>

            </select>

        </div>


        <div style="margin-bottom:20px;">

            <label>
                Current Cover
            </label>

            <div style="margin-top:10px;">

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
                            width:100px;
                            height:145px;
                            object-fit:cover;
                            border-radius:8px;
                        "
                    >

                <?php else: ?>

                    <p class="muted">
                        No cover image.
                    </p>

                <?php endif; ?>

            </div>

        </div>


        <div style="margin-bottom:20px;">

            <label>
                Replace Cover
            </label>

            <input
                type="file"
                name="cover_image"
                accept=".jpg,.jpeg,.png,.webp"
                style="
                    display:block;
                    margin-top:8px;
                "
            >

            <small class="muted">
                Leave empty to keep the current cover.
            </small>

        </div>


        <div
            style="
                display:flex;
                gap:12px;
                flex-wrap:wrap;
            "
        >

            <button
                type="submit"
                class="button"
            >
                Save Changes
            </button>

            <a
                href="<?= e(url('admin/books.php')) ?>"
                class="button secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</main>

</body>
</html>
