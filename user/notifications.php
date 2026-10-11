<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';

require_login();

$user = current_user();


/*
|--------------------------------------------------------------------------
| User Only
|--------------------------------------------------------------------------
*/

if ($user['role'] === 'admin') {
    redirect('admin/dashboard.php');
}


/*
|--------------------------------------------------------------------------
| Mark All As Read
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'mark_all_read'
) {

    verify_csrf();

    mark_all_notifications_read(
        (int)$user['id']
    );

    redirect(
        'user/notifications.php?marked=1'
    );
}


/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications =
    get_user_notifications(
        (int)$user['id'],
        100
    );


$unreadCount =
    unread_notification_count(
        (int)$user['id']
    );


/*
|--------------------------------------------------------------------------
| Notification Type Label
|--------------------------------------------------------------------------
*/

function notification_type_label(
    string $type
): string {

    return match ($type) {

        'reservation_approved' =>
            'Reservation Approved',

        'reservation_rejected' =>
            'Reservation Rejected',

        'ready_for_pickup' =>
            'Ready for Pickup',

        'book_borrowed' =>
            'Borrowed',

        'book_returned' =>
            'Returned',

        'reservation_expired' =>
            'Reservation Expired',

        'due_reminder' =>
            'Due Reminder',

        'overdue' =>
            'Overdue',

        default =>
            'Library Update'
    };
}


/*
|--------------------------------------------------------------------------
| Notification Type Color
|--------------------------------------------------------------------------
*/

function notification_type_style(
    string $type
): array {

    return match ($type) {

        'reservation_approved',
        'ready_for_pickup',
        'book_returned' => [
            'background' => '#e8f7f0',
            'color' => '#176638'
        ],

        'reservation_rejected',
        'reservation_expired',
        'overdue' => [
            'background' => '#fff0f0',
            'color' => '#a52a2a'
        ],

        'book_borrowed',
        'due_reminder' => [
            'background' => '#eaf4ff',
            'color' => '#2454a6'
        ],

        default => [
            'background' => '#f1f3f5',
            'color' => '#4b5563'
        ]
    };
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
        Notifications | Sentro ng Karunungan
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
            Books
        </a>

        <a href="<?= e(url('user/reservations.php')) ?>">
            My Reservations
        </a>

        <a href="<?= e(url('user/notifications.php')) ?>">
            Notifications

            <?php if ($unreadCount > 0): ?>

                (<?= $unreadCount ?>)

            <?php endif; ?>

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


    <div
        style="
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:20px;
            flex-wrap:wrap;
            margin-bottom:25px;
        "
    >

        <div>

            <p class="eyebrow">
                LIBRARY UPDATES
            </p>

            <h1 style="margin-bottom:5px;">
                Notifications
            </h1>

            <p class="muted">
                Stay updated about your reservations
                and borrowed books.
            </p>

        </div>


        <?php if ($unreadCount > 0): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="mark_all_read"
                >

                <button
                    type="submit"
                    class="btn primary"
                >
                    Mark All as Read
                </button>

            </form>

        <?php endif; ?>


    </div>


    <?php if (isset($_GET['marked'])): ?>

        <div
            style="
                background:#e8f7f0;
                color:#176638;
                padding:13px 16px;
                border-radius:10px;
                margin-bottom:20px;
            "
        >
            All notifications have been marked as read.
        </div>

    <?php endif; ?>


    <?php if (!$notifications): ?>


        <div
            class="card"
            style="
                text-align:center;
                padding:50px 25px;
            "
        >

            <div
                style="
                    font-size:42px;
                    margin-bottom:15px;
                "
            >
                🔔
            </div>

            <h2>
                No Notifications Yet
            </h2>

            <p class="muted">
                Updates about your reservations and
                borrowed books will appear here.
            </p>

        </div>


    <?php else: ?>


        <div
            style="
                display:flex;
                flex-direction:column;
                gap:14px;
            "
        >


            <?php foreach ($notifications as $notification): ?>


                <?php

                $typeStyle =
                    notification_type_style(
                        $notification['type']
                    );

                $target =
                    $notification['link']
                    ?: 'user/notifications.php';

                ?>


                <article
                    class="card"
                    style="
                        position:relative;

                        <?php if (
                            (int)$notification['is_read'] === 0
                        ): ?>

                            border-left:
                                5px solid #2f7fa8;

                        <?php endif; ?>
                    "
                >


                    <div
                        style="
                            display:flex;
                            justify-content:space-between;
                            align-items:flex-start;
                            gap:20px;
                            flex-wrap:wrap;
                        "
                    >


                        <div style="flex:1;">


                            <div
                                style="
                                    display:flex;
                                    gap:8px;
                                    align-items:center;
                                    flex-wrap:wrap;
                                    margin-bottom:10px;
                                "
                            >

                                <span
                                    style="
                                        display:inline-block;
                                        padding:5px 9px;
                                        border-radius:999px;
                                        font-size:11px;
                                        font-weight:700;
                                        background:
                                            <?= e(
                                                $typeStyle[
                                                    'background'
                                                ]
                                            ) ?>;
                                        color:
                                            <?= e(
                                                $typeStyle[
                                                    'color'
                                                ]
                                            ) ?>;
                                    "
                                >

                                    <?= e(
                                        notification_type_label(
                                            $notification['type']
                                        )
                                    ) ?>

                                </span>


                                <?php if (
                                    (int)$notification['is_read'] === 0
                                ): ?>

                                    <span
                                        style="
                                            background:#2f7fa8;
                                            color:white;
                                            padding:4px 8px;
                                            border-radius:999px;
                                            font-size:10px;
                                            font-weight:800;
                                        "
                                    >
                                        NEW
                                    </span>

                                <?php endif; ?>


                            </div>


                            <h3
                                style="
                                    margin:
                                        0 0 8px;
                                "
                            >
                                <?= e(
                                    $notification['title']
                                ) ?>
                            </h3>


                            <p
                                style="
                                    margin:
                                        0 0 12px;
                                    line-height:1.6;
                                "
                            >
                                <?= e(
                                    $notification['message']
                                ) ?>
                            </p>


                            <div
                                class="muted"
                                style="
                                    font-size:12px;
                                "
                            >

                                <?= date(
                                    'M d, Y • h:i A',
                                    strtotime(
                                        $notification[
                                            'created_at'
                                        ]
                                    )
                                ) ?>

                            </div>


                        </div>


                        <?php if (
    !empty(
        $notification['link']
    )
): ?>

    <form
        method="POST"
        action="<?= e(
            url(
                'user/open-notification.php'
            )
        ) ?>"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrf_token()) ?>"
        >

        <input
            type="hidden"
            name="notification_id"
            value="<?= (int)$notification['id'] ?>"
        >

        <button
            type="submit"
            class="btn secondary"
        >
            View
        </button>

    </form>

<?php endif; ?>


                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


</body>

</html>