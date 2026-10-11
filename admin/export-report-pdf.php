<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_admin();

$pdo = db();

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to'] ?? '');


function valid_pdf_date(string $date): bool
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


if (!valid_pdf_date($fromDate)) {
    $fromDate = '';
}

if (!valid_pdf_date($toDate)) {
    $toDate = '';
}


/*
|--------------------------------------------------------------------------
| Date Filtering
|--------------------------------------------------------------------------
*/

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
| Fetch Report Data
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        reservations.id,
        reservations.status,
        reservations.reserved_at,
        reservations.pickup_deadline,
        reservations.borrowed_at,
        reservations.due_at,
        reservations.returned_at,

        users.name AS user_name,
        users.email AS user_email,

        books.title AS book_title,
        books.author,
        books.isbn

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


/*
|--------------------------------------------------------------------------
| Report Period
|--------------------------------------------------------------------------
*/

if (
    $fromDate !== '' &&
    $toDate !== ''
) {

    $reportPeriod =
        date(
            'F d, Y',
            strtotime($fromDate)
        )
        .
        ' to '
        .
        date(
            'F d, Y',
            strtotime($toDate)
        );

} elseif ($fromDate !== '') {

    $reportPeriod =
        'From ' .
        date(
            'F d, Y',
            strtotime($fromDate)
        );

} elseif ($toDate !== '') {

    $reportPeriod =
        'Up to ' .
        date(
            'F d, Y',
            strtotime($toDate)
        );

} else {

    $reportPeriod =
        'All Reservation Records';
}


/*
|--------------------------------------------------------------------------
| Build HTML
|--------------------------------------------------------------------------
*/

ob_start();
?>

<!doctype html>

<html>

<head>

<meta charset="utf-8">

<style>

    body {
        font-family:
            DejaVu Sans,
            sans-serif;

        font-size: 11px;

        color: #222;
    }

    .header {
        text-align: center;

        margin-bottom: 25px;
    }

    .header h1 {
        margin: 0;

        color: #174c8f;

        font-size: 22px;
    }

    .header p {
        margin: 5px 0;
    }

    .summary {
        margin-bottom: 18px;

        padding: 10px;

        background: #f3f7fc;

        border: 1px solid #d8e3f0;
    }

    table {
        width: 100%;

        border-collapse: collapse;
    }

    th {
        background: #174c8f;

        color: white;

        padding: 7px;

        border: 1px solid #ccc;

        font-size: 9px;
    }

    td {
        padding: 6px;

        border: 1px solid #ddd;

        font-size: 9px;

        vertical-align: top;
    }

    .overdue {
        color: #b42318;

        font-weight: bold;
    }

    .footer {
        margin-top: 25px;

        font-size: 9px;

        color: #666;

        text-align: right;
    }

</style>

</head>


<body>


<div class="header">

    <h1>
        Sentro ng Karunungan
    </h1>

    <p>
        Library Reservation and Borrowing Report
    </p>

    <p>
        <strong>
            <?= htmlspecialchars(
                $reportPeriod
            ) ?>
        </strong>
    </p>

</div>


<div class="summary">

    <strong>
        Total Records:
    </strong>

    <?= count($reservations) ?>

</div>


<table>

<thead>

<tr>

    <th>ID</th>

    <th>User</th>

    <th>Book</th>

    <th>Status</th>

    <th>Reserved</th>

    <th>Due Date</th>

    <th>Returned</th>

</tr>

</thead>


<tbody>


<?php if (!$reservations): ?>

<tr>

    <td colspan="7">
        No reservation records found.
    </td>

</tr>


<?php else: ?>


<?php foreach (
    $reservations as $reservation
): ?>


<?php

$status =
    ucfirst(
        $reservation['status']
    );

$isOverdue =
    $reservation['status'] === 'borrowed'
    &&
    !empty($reservation['due_at'])
    &&
    strtotime(
        $reservation['due_at']
    ) < time();


if ($isOverdue) {
    $status = 'Overdue';
}

?>


<tr>

    <td>
        <?= (int)$reservation['id'] ?>
    </td>


    <td>

        <?= htmlspecialchars(
            $reservation['user_name']
        ) ?>

        <br>

        <small>
            <?= htmlspecialchars(
                $reservation['user_email']
            ) ?>
        </small>

    </td>


    <td>

        <strong>

            <?= htmlspecialchars(
                $reservation['book_title']
            ) ?>

        </strong>

        <br>

        <?= htmlspecialchars(
            $reservation['author']
        ) ?>

    </td>


    <td
        class="<?= $isOverdue
            ? 'overdue'
            : ''
        ?>"
    >

        <?= htmlspecialchars($status) ?>

    </td>


    <td>

        <?= !empty(
            $reservation['reserved_at']
        )
            ? date(
                'M d, Y',
                strtotime(
                    $reservation[
                        'reserved_at'
                    ]
                )
            )
            : '—'
        ?>

    </td>


    <td>

        <?= !empty(
            $reservation['due_at']
        )
            ? date(
                'M d, Y',
                strtotime(
                    $reservation[
                        'due_at'
                    ]
                )
            )
            : '—'
        ?>

    </td>


    <td>

        <?= !empty(
            $reservation['returned_at']
        )
            ? date(
                'M d, Y',
                strtotime(
                    $reservation[
                        'returned_at'
                    ]
                )
            )
            : '—'
        ?>

    </td>

</tr>


<?php endforeach; ?>


<?php endif; ?>


</tbody>

</table>


<div class="footer">

    Generated:
    <?= date('F d, Y h:i A') ?>

</div>


</body>

</html>

<?php

$html = ob_get_clean();


/*
|--------------------------------------------------------------------------
| Generate PDF
|--------------------------------------------------------------------------
*/

$options =
    new Options();

$options->set(
    'isRemoteEnabled',
    true
);

$dompdf =
    new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper(
    'A4',
    'landscape'
);

$dompdf->render();


$filename =
    'sentro_library_report_' .
    date('Y-m-d_H-i-s') .
    '.pdf';


$dompdf->stream(
    $filename,
    [
        'Attachment' => true
    ]
);

exit;