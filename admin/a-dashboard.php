<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Dashboard";


/*
|--------------------------------------------------------------------------
| ADMIN INFORMATION
|--------------------------------------------------------------------------
*/

$adminName = $_SESSION['admin_name'] ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| DEFAULT SETTINGS
|--------------------------------------------------------------------------
*/

$currency = "₹";


/*
|--------------------------------------------------------------------------
| LOAD CURRENCY
|--------------------------------------------------------------------------
*/

try {

    $settingsStmt = $pdo->query("
        SELECT currency
        FROM settings
        ORDER BY setting_id ASC
        LIMIT 1
    ");

    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if (
        $settings &&
        !empty($settings['currency'])
    ) {

        $currency = (string)$settings['currency'];

    }

} catch (PDOException $e) {

    $currency = "₹";

}


/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$totalOrders = 0;
$totalRevenue = 0.00;
$pendingOrders = 0;
$todayRevenue = 0.00;
$todayOrders = 0;


/*
|--------------------------------------------------------------------------
| TOTAL ORDERS
|--------------------------------------------------------------------------
*/

try {

    $totalOrders = (int)$pdo->query("
        SELECT COUNT(*)
        FROM orders
    ")->fetchColumn();

} catch (PDOException $e) {

    $totalOrders = 0;

}


/*
|--------------------------------------------------------------------------
| TOTAL PAID REVENUE
|--------------------------------------------------------------------------
*/

try {

    $totalRevenue = (float)$pdo->query("
        SELECT COALESCE(
            SUM(grand_total),
            0
        )
        FROM orders
        WHERE payment_status = 'Paid'
    ")->fetchColumn();

} catch (PDOException $e) {

    $totalRevenue = 0.00;

}


/*
|--------------------------------------------------------------------------
| PENDING / ACTIVE ORDERS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Your database does NOT use "Confirmed".
|
| Current order statuses:
|
| Pending
| Accepted
| Preparing
| Ready
| Out for Delivery
| Completed
| Cancelled
|
|--------------------------------------------------------------------------
*/

try {

    $pendingOrders = (int)$pdo->query("
        SELECT COUNT(*)
        FROM orders
        WHERE order_status IN (
            'Pending',
            'Accepted',
            'Preparing',
            'Ready',
            'Out for Delivery'
        )
    ")->fetchColumn();

} catch (PDOException $e) {

    $pendingOrders = 0;

}


/*
|--------------------------------------------------------------------------
| TODAY'S REVENUE
|--------------------------------------------------------------------------
*/

try {

    $todayRevenue = (float)$pdo->query("
        SELECT COALESCE(
            SUM(grand_total),
            0
        )
        FROM orders
        WHERE DATE(ordered_at) = CURDATE()
        AND payment_status = 'Paid'
    ")->fetchColumn();

} catch (PDOException $e) {

    $todayRevenue = 0.00;

}


/*
|--------------------------------------------------------------------------
| TODAY'S ORDERS
|--------------------------------------------------------------------------
*/

try {

    $todayOrders = (int)$pdo->query("
        SELECT COUNT(*)
        FROM orders
        WHERE DATE(ordered_at) = CURDATE()
    ")->fetchColumn();

} catch (PDOException $e) {

    $todayOrders = 0;

}


/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/

$recentOrders = [];

try {

    $stmt = $pdo->query("
        SELECT
            order_id,
            order_number,
            customer_name,
            grand_total,
            order_status,
            payment_status,
            ordered_at
        FROM orders
        ORDER BY order_id DESC
        LIMIT 6
    ");

    $recentOrders =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $recentOrders = [];

}


/*
|--------------------------------------------------------------------------
| ORDER STATUS SUMMARY
|--------------------------------------------------------------------------
*/

$orderStatuses = [];

try {

    $statusStmt = $pdo->query("
        SELECT
            order_status,
            COUNT(*) AS total
        FROM orders
        GROUP BY order_status
        ORDER BY total DESC
    ");

    $orderStatuses =
        $statusStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $orderStatuses = [];

}


/*
|--------------------------------------------------------------------------
| TODAY'S ORDER STATUS
|--------------------------------------------------------------------------
*/

$todayStatuses = [];

try {

    $todayStatusStmt = $pdo->query("
        SELECT
            order_status,
            COUNT(*) AS total
        FROM orders
        WHERE DATE(ordered_at) = CURDATE()
        GROUP BY order_status
        ORDER BY total DESC
    ");

    $todayStatuses =
        $todayStatusStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $todayStatuses = [];

}


/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

require_once "includes/a-header.php";

require_once "includes/a-sidebar.php";

?>

<div class="admin-main">


    <?php require_once "includes/a-navbar.php"; ?>


    <main class="admin-content">


        <div class="dashboard-page">


            <!-- =========================================================
                 DASHBOARD HEADER
            ========================================================== -->

            <div class="dashboard-heading">


                <div>

                    <span class="dashboard-eyebrow">

                        RESTAURANT OVERVIEW

                    </span>


                    <h1>

                        Dashboard

                    </h1>


                    <p>

                        Welcome back,
                        <?= htmlspecialchars(
                            $adminName,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                        👋

                    </p>

                </div>


                <div class="dashboard-date">

                    <i class="bi bi-calendar3"></i>

                    <?= date("d M Y"); ?>

                </div>


            </div>



            <!-- =========================================================
                 STATISTICS
            ========================================================== -->

            <div class="stats-grid">


                <!-- TODAY ORDERS -->

                <div class="stat-card blue">


                    <div class="stat-top">

                        <div class="stat-icon">

                            <i class="bi bi-bag-check-fill"></i>

                        </div>


                        <span class="stat-label">

                            TODAY

                        </span>

                    </div>


                    <div class="stat-value">

                        <?= $todayOrders; ?>

                    </div>


                    <div class="stat-title">

                        Orders Today

                    </div>


                </div>



                <!-- TODAY REVENUE -->

                <div class="stat-card green">


                    <div class="stat-top">

                        <div class="stat-icon">

                            <i class="bi bi-currency-rupee"></i>

                        </div>


                        <span class="stat-label">

                            TODAY

                        </span>

                    </div>


                    <div class="stat-value">

                        <?= htmlspecialchars($currency); ?><?= number_format(
                            $todayRevenue,
                            2
                        ); ?>

                    </div>


                    <div class="stat-title">

                        Today's Revenue

                    </div>


                </div>



                <!-- TOTAL ORDERS -->

                <div class="stat-card purple">


                    <div class="stat-top">

                        <div class="stat-icon">

                            <i class="bi bi-receipt"></i>

                        </div>


                        <span class="stat-label">

                            ALL TIME

                        </span>

                    </div>


                    <div class="stat-value">

                        <?= $totalOrders; ?>

                    </div>


                    <div class="stat-title">

                        Total Orders

                    </div>


                </div>



                <!-- TOTAL REVENUE -->

                <div class="stat-card orange">


                    <div class="stat-top">

                        <div class="stat-icon">

                            <i class="bi bi-graph-up-arrow"></i>

                        </div>


                        <span class="stat-label">

                            ALL TIME

                        </span>

                    </div>


                    <div class="stat-value">

                        <?= htmlspecialchars($currency); ?><?= number_format(
                            $totalRevenue,
                            2
                        ); ?>

                    </div>


                    <div class="stat-title">

                        Total Revenue

                    </div>


                </div>


            </div>



            <!-- =========================================================
                 QUICK ACTIONS
            ========================================================== -->

            <div class="section-title">


                <div>

                    <h3>

                        Quick Actions

                    </h3>


                    <p>

                        Frequently used operations

                    </p>

                </div>


            </div>



            <div class="quick-actions">


                <!-- BILLING -->

                <a
                    href="a-billing.php"
                    class="quick-action"
                >

                    <span class="quick-icon green-icon">

                        <i class="bi bi-receipt-cutoff"></i>

                    </span>


                    <span>

                        <strong>

                            New Billing

                        </strong>


                        <small>

                            Create new order

                        </small>

                    </span>


                    <i class="bi bi-arrow-right"></i>

                </a>



                <!-- ORDERS -->

                <a
                    href="orders.php"
                    class="quick-action"
                >

                    <span class="quick-icon blue-icon">

                        <i class="bi bi-bag-check"></i>

                    </span>


                    <span>

                        <strong>

                            View Orders

                        </strong>


                        <small>

                            Manage customer orders

                        </small>

                    </span>


                    <i class="bi bi-arrow-right"></i>

                </a>



                <!-- KITCHEN -->

                <a
                    href="kitchen.php"
                    class="quick-action"
                >

                    <span class="quick-icon orange-icon">

                        <i class="bi bi-fire"></i>

                    </span>


                    <span>

                        <strong>

                            Kitchen

                        </strong>


                        <small>

                            Manage kitchen queue

                        </small>

                    </span>


                    <i class="bi bi-arrow-right"></i>

                </a>



                <!-- PRODUCTS -->

                <a
                    href="a-products.php"
                    class="quick-action"
                >

                    <span class="quick-icon purple-icon">

                        <i class="bi bi-cup-straw"></i>

                    </span>


                    <span>

                        <strong>

                            Products

                        </strong>


                        <small>

                            Manage menu items

                        </small>

                    </span>


                    <i class="bi bi-arrow-right"></i>

                </a>


            </div>



            <!-- =========================================================
                 MAIN DASHBOARD GRID
            ========================================================== -->

            <div class="dashboard-grid">


                <!-- =====================================================
                     RECENT ORDERS
                ====================================================== -->

                <div class="dashboard-card recent-orders">


                    <div class="card-heading">


                        <div>

                            <h3>

                                Recent Orders

                            </h3>


                            <p>

                                Latest customer orders

                            </p>

                        </div>


                        <a href="orders.php">

                            View All

                        </a>


                    </div>



                    <div class="orders-table-wrapper">


                        <table class="dashboard-table">


                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Total
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php if (empty($recentOrders)): ?>


                                <tr>

                                    <td
                                        colspan="5"
                                        class="empty-state"
                                    >

                                        <i class="bi bi-receipt fs-3 d-block mb-2"></i>

                                        No orders found.

                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php foreach (
                                    $recentOrders
                                    as $order
                                ): ?>


                                    <?php

                                    $status =
                                        strtolower(
                                            trim(
                                                (string)$order[
                                                    'order_status'
                                                ]
                                            )
                                        );


                                    $statusClass =
                                        match ($status) {

                                            'completed' =>
                                                'success',

                                            'ready' =>
                                                'ready',

                                            'cancelled' =>
                                                'danger',

                                            'out for delivery' =>
                                                'success',

                                            default =>
                                                'pending'

                                        };

                                    ?>


                                    <tr>


                                        <!-- ORDER -->

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $order[
                                                        'order_number'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>

                                            </strong>

                                        </td>



                                        <!-- CUSTOMER -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $order[
                                                    'customer_name'
                                                ] ?: 'Guest',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </td>



                                        <!-- TOTAL -->

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $currency
                                                ); ?><?= number_format(
                                                    (float)$order[
                                                        'grand_total'
                                                    ],
                                                    2
                                                ); ?>

                                            </strong>

                                        </td>



                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="order-status <?= htmlspecialchars(
                                                    $statusClass,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    $order[
                                                        'order_status'
                                                    ],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>

                                            </span>

                                        </td>



                                        <!-- DATE -->

                                        <td>

                                            <?= date(
                                                "d M, h:i A",
                                                strtotime(
                                                    $order[
                                                        'ordered_at'
                                                    ]
                                                )
                                            ); ?>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            <?php endif; ?>


                            </tbody>


                        </table>


                    </div>


                </div>



                <!-- =====================================================
                     ORDER OVERVIEW
                ====================================================== -->

                <div class="dashboard-card order-overview">


                    <div class="card-heading">


                        <div>

                            <h3>

                                Order Overview

                            </h3>


                            <p>

                                Current order status

                            </p>

                        </div>


                        <i class="bi bi-pie-chart-fill"></i>


                    </div>



                    <div class="status-list">


                        <?php if (
                            empty($orderStatuses)
                        ): ?>


                            <div class="empty-state">

                                No order data available.

                            </div>


                        <?php else: ?>


                            <?php foreach (
                                $orderStatuses
                                as $item
                            ): ?>


                                <?php

                                $statusName =
                                    (string)$item[
                                        'order_status'
                                    ];

                                $statusTotal =
                                    (int)$item[
                                        'total'
                                    ];

                                $percentage =
                                    $totalOrders > 0
                                        ? round(
                                            (
                                                $statusTotal /
                                                $totalOrders
                                            ) * 100
                                        )
                                        : 0;

                                ?>


                                <div class="status-item">


                                    <div class="status-info">


                                        <span>

                                            <?= htmlspecialchars(
                                                $statusName,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                        </span>


                                        <strong>

                                            <?= $statusTotal; ?>

                                        </strong>


                                    </div>



                                    <div class="progress">


                                        <div
                                            class="progress-bar"
                                            style="width:<?= $percentage; ?>%;"
                                        ></div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php endif; ?>


                    </div>


                </div>


            </div>



            <!-- =========================================================
                 BOTTOM CARDS
            ========================================================== -->

            <div class="bottom-grid">


                <!-- PENDING ORDERS -->

                <a
                    href="orders.php?status=Pending"
                    class="mini-dashboard-card"
                >

                    <div class="mini-icon warning-icon">

                        <i class="bi bi-hourglass-split"></i>

                    </div>


                    <div>

                        <span>

                            Active Orders

                        </span>


                        <strong>

                            <?= $pendingOrders; ?>

                        </strong>


                        <small>

                            Need attention

                        </small>

                    </div>

                </a>



                <!-- KITCHEN -->

                <a
                    href="kitchen.php"
                    class="mini-dashboard-card"
                >

                    <div class="mini-icon kitchen-icon">

                        <i class="bi bi-fire"></i>

                    </div>


                    <div>

                        <span>

                            Kitchen Queue

                        </span>


                        <strong>

                            <?= $pendingOrders; ?>

                        </strong>


                        <small>

                            View kitchen

                        </small>

                    </div>

                </a>



                <!-- BILLING -->

                <a
                    href="a-billing.php"
                    class="mini-dashboard-card"
                >

                    <div class="mini-icon billing-icon">

                        <i class="bi bi-credit-card"></i>

                    </div>


                    <div>

                        <span>

                            Billing

                        </span>


                        <strong>

                            POS

                        </strong>


                        <small>

                            Create new bill

                        </small>

                    </div>

                </a>


            </div>


        </div>


    </main>


</div>


<?php require_once "includes/a-footer.php"; ?>