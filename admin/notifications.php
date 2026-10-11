<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/notifications.php';

require_admin();

$admin = current_user();

$adminId = (int)$admin['id'];


/*
|--------------------------------------------------------------------------
| Mark All Notifications as Read
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {

        mark_all_notifications_read(
            $adminId
        );

        redirect(
            'admin/notifications.php'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Load Notifications
|--------------------------------------------------------------------------
*/

$notifications =
    get_user_notifications(
        $adminId,
        100
    );

$unreadCount =
    unread_notification_count(
        $adminId
    );


/*
|--------------------------------------------------------------------------
| Notification Labels
|--------------------------------------------------------------------------
*/

function admin_notification_label(
    string $type
): string {

    $labels = [

        'inventory_low'
            => 'Low Stock',

        'inventory_out'
            => 'Out of Stock',

        'general'
            => 'General'
    ];

    return $labels[$type]
        ?? ucwords(
            str_replace(
                '_',
                ' ',
                $type
            )
        );
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
        Sentro ng Karunungan — Admin
    </strong>


    <nav>

        <a href="<?= e(url('admin/dashboard.php')) ?>">
            Dashboard
        </a>

        <a href="<?= e(url('admin/books.php')) ?>">
            Books
        </a>

        <a href="<?= e(url('admin/reservations.php')) ?>">
            Reservations
        </a>

        <a href="<?= e(url('admin/users.php')) ?>">
            Users
        </a>

        <a href="<?= e(url('admin/inventory.php')) ?>">
            Inventory
        </a>

        <a href="<?= e(url('admin/reports.php')) ?>">
            Reports
        </a>

        <a href="<?= e(url('admin/audit-logs.php')) ?>">
            Activity Logs
        </a>


        <a
            href="<?= e(url('admin/notifications.php')) ?>"
            class="notification-bell"
            title="Notifications"
        >

            <span class="notification-icon">
                🔔
            </span>

            <?php if ($unreadCount > 0): ?>

                <span class="notification-badge">

                    <?= $unreadCount > 99
                        ? '99+'
                        : $unreadCount
                    ?>

                </span>

            <?php endif; ?>

        </a>


        <span>
            <?= e($admin['name']) ?>
        </span>


        <a href="<?= e(url('logout.php')) ?>">
            Logout
        </a>

    </nav>

</header>


<main class="container">


    <p class="eyebrow">
        ADMIN ALERTS
    </p>


    <h1>
        Notifications
    </h1>


    <p class="muted">

        You have

        <strong>
            <?= $unreadCount ?>
        </strong>

        unread
        <?= $unreadCount === 1
            ? 'notification'
            : 'notifications'
        ?>.

    </p>


    <?php if ($unreadCount > 0): ?>

        <form
            method="POST"
            style="margin:20px 0;"
        >

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


    <section
        class="card"
        style="margin-top:25px;"
    >


        <?php if (!$notifications): ?>

            <div
                style="
                    text-align:center;
                    padding:40px;
                "
            >

                <h3>
                    No Notifications
                </h3>

                <p class="muted">
                    Admin alerts will appear here.
                </p>

            </div>


        <?php else: ?>


            <?php foreach (
                $notifications
                as $notification
            ): ?>


                <div
                    style="
                        padding:18px 4px;
                        border-bottom:
                            1px solid #e5e7eb;
                    "
                >


                    <div
                        style="
                            display:flex;
                            justify-content:
                                space-between;
                            gap:15px;
                            align-items:flex-start;
                        "
                    >


                        <div style="flex:1;">


                            <div
                                style="
                                    display:flex;
                                    gap:8px;
                                    flex-wrap:wrap;
                                    align-items:center;
                                "
                            >

                                <strong>

                                    <?= e(
                                        $notification[
                                            'title'
                                        ]
                                    ) ?>

                                </strong>


                                <span
                                    style="
                                        display:inline-block;
                                        padding:4px 8px;
                                        border-radius:999px;
                                        background:#eaf4ff;
                                        color:#2454a6;
                                        font-size:11px;
                                        font-weight:700;
                                    "
                                >

                                    <?= e(
                                        admin_notification_label(
                                            $notification[
                                                'type'
                                            ]
                                        )
                                    ) ?>

                                </span>


                                <?php if (
                                    !(int)$notification[
                                        'is_read'
                                    ]
                                ): ?>

                                    <span
                                        style="
                                            color:#b42318;
                                            font-size:11px;
                                            font-weight:700;
                                        "
                                    >
                                        NEW
                                    </span>

                                <?php endif; ?>


                            </div>


                            <p
                                style="
                                    margin:
                                        8px 0 4px;
                                "
                            >

                                <?= e(
                                    $notification[
                                        'message'
                                    ]
                                ) ?>

                            </p>


                            <div
                                class="muted"
                                style="
                                    font-size:12px;
                                "
                            >

                                <?= date(
                                    'M d, Y h:i A',
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
                                        'admin/open-notification.php'
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
                                    name="action"
                                    value="open_notification"
                                >

                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?= (int)
                                        $notification['id']
                                    ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn"
                                >
                                    View
                                </button>

                            </form>

                        <?php endif; ?>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </section>


</main>


</body>

</html>