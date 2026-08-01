<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";

$pageTitle = "Dashboard";

/*=========================================
ADMIN LOGIN CHECK
=========================================*/

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

/*=========================================
TOTAL ORDERS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*) AS total
FROM orders
");

$totalOrders = (int)$stmt->fetchColumn();

/*=========================================
TOTAL CUSTOMERS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*) AS total
FROM users
");

$totalCustomers = (int)$stmt->fetchColumn();

/*=========================================
TOTAL PRODUCTS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*) AS total
FROM products
");

$totalProducts = (int)$stmt->fetchColumn();

/*=========================================
TOTAL REVENUE
=========================================*/

$stmt = $pdo->query("
SELECT
COALESCE(SUM(grand_total),0)
FROM orders
WHERE order_status != 'Cancelled'
");

$totalRevenue = (float)$stmt->fetchColumn();

/*=========================================
PENDING ORDERS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE order_status='Pending'
");

$pendingOrders = (int)$stmt->fetchColumn();

/*=========================================
COMPLETED ORDERS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE order_status='Completed'
");

$completedOrders = (int)$stmt->fetchColumn();

/*=========================================
TODAY'S ORDERS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE DATE(ordered_at)=CURDATE()
");

$todayOrders = (int)$stmt->fetchColumn();

/*=========================================
TODAY'S REVENUE
=========================================*/

$stmt = $pdo->query("
SELECT
COALESCE(SUM(grand_total),0)
FROM orders
WHERE DATE(ordered_at)=CURDATE()
");

$todayRevenue = (float)$stmt->fetchColumn();

/*=========================================
LOW STOCK PRODUCTS
=========================================*/

$stmt = $pdo->query("
SELECT COUNT(*)
FROM products
WHERE stock<=5
");

$lowStockProducts = (int)$stmt->fetchColumn();

/*=========================================
RECENT ORDERS
=========================================*/

$stmt = $pdo->query("
SELECT

order_id,
order_number,
customer_name,
grand_total,
order_status,
ordered_at

FROM orders

ORDER BY ordered_at DESC

LIMIT 5
");

$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*=========================================
LOW STOCK PRODUCT LIST
=========================================*/

$stmt = $pdo->query("
SELECT

product_id,
product_name,
stock

FROM products

WHERE stock <= 5

ORDER BY stock ASC

LIMIT 5
");

$lowStockList = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*=========================================
LAYOUT
=========================================*/

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-content">

<div class="container-fluid">

<div class="page-header">

<h2>

<i class="bi bi-speedometer2 me-2"></i>

Dashboard

</h2>

<p>

Welcome back, Admin 👋

</p>

</div>
</div>

</div>
<!-- =========================================
     DASHBOARD CARDS
========================================= -->

<div class="row g-4 mb-5">

    <!-- Total Orders -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card orders-card">

            <div class="card-icon">

                <i class="bi bi-bag-check-fill"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= number_format($totalOrders); ?>

                </h3>

                <p>Total Orders</p>

            </div>

        </div>

    </div>

    <!-- Revenue -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card revenue-card">

            <div class="card-icon">

                <i class="bi bi-currency-rupee"></i>

            </div>

            <div class="card-info">

                <h3>

                    ₹<?= number_format($totalRevenue,2); ?>

                </h3>

                <p>Total Revenue</p>

            </div>

        </div>

    </div>

    <!-- Customers -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card customer-card">

            <div class="card-icon">

                <i class="bi bi-people-fill"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= number_format($totalCustomers); ?>

                </h3>

                <p>Customers</p>

            </div>

        </div>

    </div>

    <!-- Products -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card product-card">

            <div class="card-icon">

                <i class="bi bi-cup-hot-fill"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= number_format($totalProducts); ?>

                </h3>

                <p>Products</p>

            </div>

        </div>

    </div>

</div>
<div class="row g-4 mb-5">

    <!-- Pending -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card pending-card">

            <div class="card-icon">

                <i class="bi bi-hourglass-split"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= $pendingOrders; ?>

                </h3>

                <p>Pending Orders</p>

            </div>

        </div>

    </div>

    <!-- Completed -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card complete-card">

            <div class="card-icon">

                <i class="bi bi-check-circle-fill"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= $completedOrders; ?>

                </h3>

                <p>Completed</p>

            </div>

        </div>

    </div>

    <!-- Today -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card today-card">

            <div class="card-icon">

                <i class="bi bi-calendar2-check-fill"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= $todayOrders; ?>

                </h3>

                <p>Today's Orders</p>

            </div>

        </div>

    </div>

    <!-- Low Stock -->

    <div class="col-xl-3 col-md-6">

        <div class="dashboard-card stock-card">

            <div class="card-icon">

                <i class="bi bi-exclamation-triangle-fill"></i>

            </div>

            <div class="card-info">

                <h3>

                    <?= $lowStockProducts; ?>

                </h3>

                <p>Low Stock</p>

            </div>

        </div>

    </div>

</div>
<div class="row mb-5">

<div class="col-lg-12">

<div class="overview-card">

<div class="row text-center">

<div class="col-md-4">

<h4>

₹<?= number_format($todayRevenue,2); ?>

</h4>

<p>Today's Revenue</p>

</div>

<div class="col-md-4">

<h4>

<?= $todayOrders; ?>

</h4>

<p>Today's Orders</p>

</div>

<div class="col-md-4">

<h4>

<?= $pendingOrders; ?>

</h4>

<p>Orders Waiting</p>

</div>

</div>

</div>

</div>

</div>
<!-- =========================================
     RECENT ORDERS
========================================= -->

<div class="row">

    <div class="col-lg-12">

        <div class="table-card">

            <div class="d-flex justify-content-between align-items-center p-4">

                <h4 class="mb-0">

                    <i class="bi bi-clock-history me-2"></i>

                    Recent Orders

                </h4>

                <a
                    href="orders.php"
                    class="btn btn-primary">

                    View All

                </a>

            </div>

            <div class="table-responsive">

                <table class="table align-middle">

                    <thead>

                        <tr>

                            <th>Order No.</th>

                            <th>Customer</th>

                            <th>Total</th>

                            <th>Status</th>

                            <th>Date</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if(empty($recentOrders)): ?>

                        <tr>

                            <td colspan="6" class="text-center py-5">

                                No recent orders found.

                            </td>

                        </tr>

                        <?php else: ?>

                        <?php foreach($recentOrders as $order): ?>

                        <?php

                        $statusClass = "secondary";

                        switch($order['order_status']){

                            case "Pending":
                                $statusClass="warning";
                                break;

                            case "Preparing":
                                $statusClass="info";
                                break;

                            case "Ready":
                                $statusClass="primary";
                                break;

                            case "Out for Delivery":
                                $statusClass="dark";
                                break;

                            case "Completed":
                                $statusClass="success";
                                break;

                            case "Cancelled":
                                $statusClass="danger";
                                break;

                        }

                        ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= htmlspecialchars($order['order_number']); ?>

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars($order['customer_name']); ?>

                            </td>

                            <td>

                                <strong class="text-success">

                                    ₹<?= number_format((float)$order['grand_total'],2); ?>

                                </strong>

                            </td>

                            <td>

                                <span class="badge bg-<?= $statusClass; ?>">

                                    <?= htmlspecialchars($order['order_status']); ?>

                                </span>

                            </td>

                            <td>

                                <?= date("d M Y", strtotime($order['ordered_at'])); ?>

                            </td>

                            <td>

                                <a
                                    href="order_details.php?id=<?= $order['order_id']; ?>"
                                    class="btn btn-sm btn-dark">

                                    <i class="bi bi-eye-fill"></i>

                                </a>

                            </td>

                        </tr>

                        <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
<?php require_once "includes/a-footer.php"; ?>