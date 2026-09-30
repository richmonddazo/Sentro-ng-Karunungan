<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();


/*
|--------------------------------------------------------------------------
| POST Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/reservations.php');
}

verify_csrf();

$admin = current_user();

$reservation_id = (int)($_POST['reservation_id'] ?? 0);
$action = trim($_POST['action'] ?? '');

$allowed_actions = [
    'approve',
    'reject',
    'ready',
    'borrow',
    'return'
];

if ($reservation_id <= 0) {
    exit('Invalid reservation ID.');
}

if (!in_array($action, $allowed_actions, true)) {
    exit('Invalid reservation action.');
}

$pdo = db();


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Lock Reservation + Book
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            reservations.id,
            reservations.user_id,
            reservations.book_id,
            reservations.status,
            reservations.pickup_deadline,
            reservations.borrowed_at,
            reservations.due_at,

            books.title,
            books.total_copies,
            books.available_copies,
            books.status AS book_status,

            users.name AS user_name

        FROM reservations

        INNER JOIN books
            ON books.id = reservations.book_id

        INNER JOIN users
            ON users.id = reservations.user_id

        WHERE reservations.id = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $reservation_id
    ]);

    $reservation = $stmt->fetch();


    if (!$reservation) {
        throw new Exception(
            'Reservation not found.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    if ($action === 'approve') {

        if ($reservation['status'] !== 'pending') {

            throw new Exception(
                'Only pending reservations can be approved.'
            );
        }


        if ($reservation['book_status'] !== 'active') {

            throw new Exception(
                'This book is currently unavailable.'
            );
        }


        if ((int)$reservation['available_copies'] <= 0) {

            throw new Exception(
                'There are no available copies remaining.'
            );
        }


        /*
        | Reserve one physical copy
        */

        $bookStmt = $pdo->prepare("
            UPDATE books

            SET available_copies =
                available_copies - 1

            WHERE id = ?
              AND available_copies > 0
        ");

        $bookStmt->execute([
            $reservation['book_id']
        ]);


        if ($bookStmt->rowCount() !== 1) {

            throw new Exception(
                'The book is no longer available.'
            );
        }


        $updateStmt = $pdo->prepare("
            UPDATE reservations

            SET
                status = 'approved',
                processed_at = NOW()

            WHERE id = ?
              AND status = 'pending'
        ");

        $updateStmt->execute([
            $reservation_id
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to approve reservation.'
            );
        }


        add_audit_log(
            $pdo,
            $admin['id'],
            'reservation_approved',
            $reservation_id,
            'Approved reservation for "' .
            $reservation['title'] .
            '" requested by ' .
            $reservation['user_name']
        );


        $pdo->commit();

        redirect(
            'admin/reservations.php?result=approved'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    if ($action === 'reject') {

        if ($reservation['status'] !== 'pending') {

            throw new Exception(
                'Only pending reservations can be rejected.'
            );
        }


        $updateStmt = $pdo->prepare("
            UPDATE reservations

            SET
                status = 'rejected',
                processed_at = NOW()

            WHERE id = ?
              AND status = 'pending'
        ");

        $updateStmt->execute([
            $reservation_id
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to reject reservation.'
            );
        }


        add_audit_log(
            $pdo,
            $admin['id'],
            'reservation_rejected',
            $reservation_id,
            'Rejected reservation for "' .
            $reservation['title'] .
            '" requested by ' .
            $reservation['user_name']
        );


        $pdo->commit();

        redirect(
            'admin/reservations.php?result=rejected'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | READY FOR PICKUP
    |--------------------------------------------------------------------------
    |
    | Approved → Ready
    |
    | The user gets 2 days to collect the reserved book.
    |
    */

    if ($action === 'ready') {

        if ($reservation['status'] !== 'approved') {

            throw new Exception(
                'Only approved reservations can be marked ready for pickup.'
            );
        }


        $updateStmt = $pdo->prepare("
            UPDATE reservations

            SET
                status = 'ready',
                pickup_deadline =
                    DATE_ADD(NOW(), INTERVAL 2 DAY)

            WHERE id = ?
              AND status = 'approved'
        ");

        $updateStmt->execute([
            $reservation_id
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to mark reservation as ready.'
            );
        }


        add_audit_log(
            $pdo,
            $admin['id'],
            'reservation_ready',
            $reservation_id,
            'Marked "' .
            $reservation['title'] .
            '" as ready for pickup for ' .
            $reservation['user_name']
        );


        $pdo->commit();

        redirect(
            'admin/reservations.php?result=ready'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS BORROWED
    |--------------------------------------------------------------------------
    |
    | Ready → Borrowed
    |
    | For now:
    | Borrowing period = 7 days
    |
    */

    if ($action === 'borrow') {

        if ($reservation['status'] !== 'ready') {

            throw new Exception(
                'Only reservations ready for pickup can be marked as borrowed.'
            );
        }


        $updateStmt = $pdo->prepare("
            UPDATE reservations

            SET
                status = 'borrowed',
                borrowed_at = NOW(),
                due_at =
                    DATE_ADD(NOW(), INTERVAL 7 DAY)

            WHERE id = ?
              AND status = 'ready'
        ");

        $updateStmt->execute([
            $reservation_id
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to mark book as borrowed.'
            );
        }


        add_audit_log(
            $pdo,
            $admin['id'],
            'book_borrowed',
            $reservation_id,
            '"' .
            $reservation['title'] .
            '" was released to ' .
            $reservation['user_name']
        );


        $pdo->commit();

        redirect(
            'admin/reservations.php?result=borrowed'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK AS RETURNED
    |--------------------------------------------------------------------------
    |
    | Borrowed → Returned
    |
    | The physical copy becomes available again.
    |
    */

    if ($action === 'return') {

        if ($reservation['status'] !== 'borrowed') {

            throw new Exception(
                'Only borrowed books can be marked as returned.'
            );
        }


        /*
        | Mark reservation returned
        */

        $updateStmt = $pdo->prepare("
            UPDATE reservations

            SET
                status = 'returned',
                returned_at = NOW()

            WHERE id = ?
              AND status = 'borrowed'
        ");

        $updateStmt->execute([
            $reservation_id
        ]);


        if ($updateStmt->rowCount() !== 1) {

            throw new Exception(
                'Unable to mark book as returned.'
            );
        }


        /*
        | Restore available copy
        |
        | Prevent available_copies from becoming
        | greater than total_copies.
        */

        $bookStmt = $pdo->prepare("
            UPDATE books

            SET available_copies =
                LEAST(
                    available_copies + 1,
                    total_copies
                )

            WHERE id = ?
        ");

        $bookStmt->execute([
            $reservation['book_id']
        ]);


        add_audit_log(
            $pdo,
            $admin['id'],
            'book_returned',
            $reservation_id,
            '"' .
            $reservation['title'] .
            '" was returned by ' .
            $reservation['user_name']
        );


        $pdo->commit();

        redirect(
            'admin/reservations.php?result=returned'
        );
    }


    throw new Exception(
        'Unknown reservation action.'
    );


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = urlencode(
        $e->getMessage()
    );

    header(
        'Location: ' .
        url(
            'admin/reservations.php?error=' .
            $message
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Audit Log Helper
|--------------------------------------------------------------------------
*/

function add_audit_log(
    PDO $pdo,
    int $admin_id,
    string $action,
    int $reservation_id,
    string $details
): void {

    $auditStmt = $pdo->prepare("
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
            'reservation',
            :entity_id,
            :details
        )
    ");

    $auditStmt->execute([
        ':user_id' => $admin_id,
        ':action' => $action,
        ':entity_id' => $reservation_id,
        ':details' => $details
    ]);
}