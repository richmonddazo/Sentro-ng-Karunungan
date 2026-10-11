<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$pdo = db();

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to'] ?? '');


function valid_export_date(string $date): bool
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


if (!valid_export_date($fromDate)) {
    $fromDate = '';
}

if (!valid_export_date($toDate)) {
    $toDate = '';
}


$dateCondition = '';
$params = [];


if ($fromDate !== '') {

    $dateCondition .= "
        AND reservations.reserved_at >= ?
    ";

    $params[] =
        $fromDate . ' 00:00:00';
}


if ($toDate !== '') {

    $dateCondition .= "
        AND reservations.reserved_at <= ?
    ";

    $params[] =
        $toDate . ' 23:59:59';
}

/*
|--------------------------------------------------------------------------
| Get Reservation Report
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        reservations.id,
        users.name AS user_name,
        users.email AS user_email,
        books.title AS book_title,
        books.author,
        books.isbn,
        reservations.status,
        reservations.reserved_at,
        reservations.pickup_deadline,
        reservations.borrowed_at,
        reservations.due_at,
        reservations.returned_at

    FROM reservations

    INNER JOIN users
        ON users.id = reservations.user_id

    INNER JOIN books
        ON books.id = reservations.book_id

    WHERE 1 = 1

    $dateCondition

    ORDER BY
        reservations.reserved_at DESC
");

$stmt->execute($params);

$reservations =
    $stmt->fetchAll();

$reservations = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CSV Filename
|--------------------------------------------------------------------------
*/

$filename =
    'sentro_library_report_' .
    date('Y-m-d_H-i-s') .
    '.csv';


/*
|--------------------------------------------------------------------------
| Download Headers
|--------------------------------------------------------------------------
*/

header('Content-Type: text/csv; charset=utf-8');

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);


/*
|--------------------------------------------------------------------------
| Open Output
|--------------------------------------------------------------------------
*/

$output = fopen('php://output', 'w');


/*
|--------------------------------------------------------------------------
| UTF-8 BOM
|--------------------------------------------------------------------------
|
| Helps Microsoft Excel display special characters correctly.
|--------------------------------------------------------------------------
*/

fprintf(
    $output,
    chr(0xEF) .
    chr(0xBB) .
    chr(0xBF)
);


/*
|--------------------------------------------------------------------------
| Column Headings
|--------------------------------------------------------------------------
*/

fputcsv(
    $output,
    [
        'Reservation ID',
        'User Name',
        'Email',
        'Book Title',
        'Author',
        'ISBN',
        'Status',
        'Reserved Date',
        'Pickup Deadline',
        'Borrowed Date',
        'Due Date',
        'Returned Date'
    ]
);


/*
|--------------------------------------------------------------------------
| Reservation Rows
|--------------------------------------------------------------------------
*/

foreach ($reservations as $reservation) {

    /*
    |--------------------------------------------------------------------------
    | Display Overdue Status
    |--------------------------------------------------------------------------
    */

    $displayStatus =
        ucfirst(
            $reservation['status']
        );


    if (
        $reservation['status'] === 'borrowed'
        &&
        !empty($reservation['due_at'])
        &&
        strtotime(
            $reservation['due_at']
        ) < time()
    ) {

        $displayStatus =
            'Overdue';
    }


    fputcsv(
        $output,
        [
            $reservation['id'],
            $reservation['user_name'],
            $reservation['user_email'],
            $reservation['book_title'],
            $reservation['author'],
            $reservation['isbn'],
            $displayStatus,
            $reservation['reserved_at'],
            $reservation['pickup_deadline'],
            $reservation['borrowed_at'],
            $reservation['due_at'],
            $reservation['returned_at']
        ]
    );
}


fclose($output);

exit;