<?php

require_once __DIR__ . '/notifications.php';


function check_inventory_alerts(): int
{
    $pdo = db();

    try {

        $stmt = $pdo->query("
            SELECT
                id,
                title,
                available_copies,
                stock_alert_state

            FROM books

            WHERE status = 'active'
        ");

        $books = $stmt->fetchAll();

        if (!$books) {
            return 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Get Active Admin Accounts
        |--------------------------------------------------------------------------
        */

        $adminStmt = $pdo->query("
            SELECT id
            FROM users
            WHERE role = 'admin'
              AND status = 'active'
        ");

        $adminIds =
            $adminStmt->fetchAll(
                PDO::FETCH_COLUMN
            );


        if (!$adminIds) {
            return 0;
        }


        $updateStmt = $pdo->prepare("
            UPDATE books
            SET stock_alert_state = ?
            WHERE id = ?
        ");


        $notificationCount = 0;


        foreach ($books as $book) {

            $available =
                (int)$book['available_copies'];


            /*
            |--------------------------------------------------------------------------
            | Determine Current Inventory State
            |--------------------------------------------------------------------------
            */

            if ($available <= 0) {

                $newState = 'out';

            } elseif ($available <= 2) {

                $newState = 'low';

            } else {

                $newState = 'normal';
            }


            /*
            |--------------------------------------------------------------------------
            | No Change = No New Alert
            |--------------------------------------------------------------------------
            */

            if (
                $newState ===
                $book['stock_alert_state']
            ) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Save New State
            |--------------------------------------------------------------------------
            */

            $updateStmt->execute([
                $newState,
                (int)$book['id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | Don't Notify When Stock Returns to Normal
            |--------------------------------------------------------------------------
            */

            if ($newState === 'normal') {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Notification Content
            |--------------------------------------------------------------------------
            */

            if ($newState === 'out') {

                $title =
                    'Book Out of Stock';

                $message =
                    '"' .
                    $book['title'] .
                    '" currently has no available copies.';

                $type =
                    'inventory_out';

            } else {

                $title =
                    'Low Book Stock';

                $message =
                    '"' .
                    $book['title'] .
                    '" has only ' .
                    $available .
                    ' available ' .
                    ($available === 1
                        ? 'copy'
                        : 'copies'
                    ) .
                    ' remaining.';

                $type =
                    'inventory_low';
            }


            /*
            |--------------------------------------------------------------------------
            | Notify Every Active Admin
            |--------------------------------------------------------------------------
            */

            foreach ($adminIds as $adminId) {

                $created =
                    create_notification(
                        (int)$adminId,
                        $title,
                        $message,
                        $type,
                        'admin/inventory.php'
                    );


                if ($created) {
                    $notificationCount++;
                }
            }
        }


        return $notificationCount;


    } catch (Throwable $e) {

        error_log(
            'Inventory maintenance error: ' .
            $e->getMessage()
        );

        return 0;
    }
}