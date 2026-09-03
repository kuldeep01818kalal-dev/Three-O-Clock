<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Online Orders";

/* =====================================================
   HELPERS
===================================================== */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return "₹" . number_format($amount, 2);
}

/* =====================================================
   SEARCH & FILTER
===================================================== */

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$source = trim($_GET['source'] ?? '');

$onlineSources = [
    'Website',
    'Swiggy',
    'Zomato'
];

$allowedStatuses = [
    'Pending',
    'Accepted',
    'Preparing',
    'Ready',
    'Out for Delivery',
    'Completed',
    'Cancelled'
];

$where = [
    "o.order_source IN ('Website', 'Swiggy', 'Zomato')"
];

$params = [];

if ($search !== '') {

    $where[] = "(
        o.order_number LIKE :search
        OR o.customer_name LIKE :search
        OR o.phone LIKE :search
        OR o.email LIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

if ($source !== '' && in_array($source, $onlineSources, true)) {

    $where[] = "o.order_source = :source";
    $params[':source'] = $source;
}

if ($status !== '' && in_array($status, $allowedStatuses, true)) {

    $where[] = "o.order_status = :status";
    $params[':status'] = $status;
}

$whereSql = implode(' AND ', $where);

/* =====================================================
   FETCH ONLINE ORDERS
===================================================== */

$stmt = $pdo->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.customer_name,
        o.phone,
        o.email,
        o.order_source,
        o.order_type,
        o.subtotal,
        o.discount,
        o.tax,
        o.delivery_charge,
        o.grand_total,
        o.payment_status,
        o.payment_method,
        o.order_status,
        o.notes,
        o.ordered_at
    FROM orders o
    WHERE $whereSql
    ORDER BY o.order_id DESC
");

$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   STATISTICS
===================================================== */

$totalOrders = count($orders);

$websiteOrders = 0;
$swiggyOrders = 0;
$zomatoOrders = 0;

$activeOrders = 0;
$completedOrders = 0;

$totalRevenue = 0;
$paidRevenue = 0;

foreach ($orders as $order) {

    $totalRevenue += (float)$order['grand_total'];

    if ($order['payment_status'] === 'Paid') {
        $paidRevenue += (float)$order['grand_total'];
    }

    switch ($order['order_source']) {

        case 'Website':
            $websiteOrders++;
            break;

        case 'Swiggy':
            $swiggyOrders++;
            break;

        case 'Zomato':
            $zomatoOrders++;
            break;
    }

    if (
        in_array(
            $order['order_status'],
            [
                'Pending',
                'Accepted',
                'Preparing',
                'Ready',
                'Out for Delivery'
            ],
            true
        )
    ) {
        $activeOrders++;
    }

    if ($order['order_status'] === 'Completed') {
        $completedOrders++;
    }
}

/* =====================================================
   HEADER
===================================================== */

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

            <div>

                <div class="text-muted small mb-1">
                    ORDER MANAGEMENT
                </div>

                <h1 class="h3 fw-bold mb-1">
                    Online Orders
                </h1>

                <p class="text-muted mb-0">
                    Manage orders received from the website and delivery platforms.
                </p>

            </div>

            <div class="d-flex gap-2">

                <a
                    href="orders.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-list-check me-2"></i>
                    All Orders
                </a>

                <a
                    href="offline_orders.php"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-shop me-2"></i>
                    Offline Orders
                </a>

            </div>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="row g-3 mb-4">

            <!-- TOTAL ONLINE -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    ONLINE ORDERS
                                </div>

                                <div class="fs-3 fw-bold mt-1">
                                    <?= $totalOrders; ?>
                                </div>

                            </div>

                            <div class="rounded-3 p-3 bg-primary-subtle text-primary">

                                <i class="bi bi-globe2 fs-4"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ACTIVE -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    ACTIVE ORDERS
                                </div>

                                <div class="fs-3 fw-bold mt-1 text-warning">
                                    <?= $activeOrders; ?>
                                </div>

                            </div>

                            <div class="rounded-3 p-3 bg-warning-subtle text-warning">

                                <i class="bi bi-hourglass-split fs-4"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    COMPLETED
                                </div>

                                <div class="fs-3 fw-bold mt-1 text-success">
                                    <?= $completedOrders; ?>
                                </div>

                            </div>

                            <div class="rounded-3 p-3 bg-success-subtle text-success">

                                <i class="bi bi-check-circle fs-4"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- REVENUE -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    ORDER VALUE
                                </div>

                                <div class="fs-3 fw-bold mt-1">
                                    <?= money($totalRevenue); ?>
                                </div>

                            </div>

                            <div class="rounded-3 p-3 bg-success-subtle text-success">

                                <i class="bi bi-currency-rupee fs-4"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             SOURCE SUMMARY
        ================================================== -->

        <div class="row g-3 mb-4">

            <div class="col-lg-4">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <span class="text-muted small">
                                    WEBSITE
                                </span>

                                <h4 class="fw-bold mt-1 mb-0">
                                    <?= $websiteOrders; ?>
                                </h4>

                            </div>

                            <div class="text-primary fs-3">
                                <i class="bi bi-globe"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-lg-4">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <span class="text-muted small">
                                    SWIGGY
                                </span>

                                <h4 class="fw-bold mt-1 mb-0">
                                    <?= $swiggyOrders; ?>
                                </h4>

                            </div>

                            <div class="text-warning fs-3">
                                <i class="bi bi-bag-check"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-lg-4">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <span class="text-muted small">
                                    ZOMATO
                                </span>

                                <h4 class="fw-bold mt-1 mb-0">
                                    <?= $zomatoOrders; ?>
                                </h4>

                            </div>

                            <div class="text-danger fs-3">
                                <i class="bi bi-bag-heart"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             SEARCH & FILTER
        ================================================== -->

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body">

                <form method="GET">

                    <div class="row g-3 align-items-end">

                        <!-- SEARCH -->

                        <div class="col-lg-4">

                            <label class="form-label fw-semibold">
                                Search Order
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>

                                <input
                                    type="search"
                                    name="search"
                                    class="form-control"
                                    placeholder="Order number, customer, phone or email"
                                    value="<?= e($search); ?>"
                                >

                            </div>

                        </div>


                        <!-- SOURCE -->

                        <div class="col-lg-3">

                            <label class="form-label fw-semibold">
                                Order Source
                            </label>

                            <select
                                name="source"
                                class="form-select"
                            >

                                <option value="">
                                    All Online Sources
                                </option>

                                <?php foreach ($onlineSources as $onlineSource): ?>

                                    <option
                                        value="<?= e($onlineSource); ?>"
                                        <?= $source === $onlineSource ? 'selected' : ''; ?>
                                    >
                                        <?= e($onlineSource); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- STATUS -->

                        <div class="col-lg-3">

                            <label class="form-label fw-semibold">
                                Order Status
                            </label>

                            <select
                                name="status"
                                class="form-select"
                            >

                                <option value="">
                                    All Status
                                </option>

                                <?php foreach ($allowedStatuses as $orderStatus): ?>

                                    <option
                                        value="<?= e($orderStatus); ?>"
                                        <?= $status === $orderStatus ? 'selected' : ''; ?>
                                    >
                                        <?= e($orderStatus); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- BUTTONS -->

                        <div class="col-lg-2">

                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >
                                    <i class="bi bi-funnel me-2"></i>
                                    Filter
                                </button>

                                <a
                                    href="online_orders.php"
                                    class="btn btn-outline-secondary"
                                    title="Reset"
                                >
                                    <i class="bi bi-arrow-clockwise"></i>
                                </a>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- =================================================
             ONLINE ORDERS TABLE
        ================================================== -->

        <div class="card border-0 shadow-sm">

            <div class="card-body p-0">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-4 border-bottom">

                    <div>

                        <h5 class="fw-bold mb-1">

                            <i class="bi bi-globe2 me-2"></i>

                            Online Orders

                        </h5>

                        <span class="text-muted small">

                            Website, Swiggy and Zomato orders.

                        </span>

                    </div>

                    <span class="badge bg-primary fs-6">

                        <?= $totalOrders; ?> Orders

                    </span>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>

                                <th class="px-4">
                                    Order No.
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Source
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Date
                                </th>

                                <th class="text-center px-4">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if (empty($orders)): ?>

                            <tr>

                                <td
                                    colspan="9"
                                    class="text-center py-5"
                                >

                                    <div class="mb-3">

                                        <i
                                            class="bi bi-globe2 display-5 text-muted"
                                        ></i>

                                    </div>

                                    <h5 class="fw-semibold">
                                        No Online Orders Found
                                    </h5>

                                    <p class="text-muted mb-0">
                                        No online orders match the selected filters.
                                    </p>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($orders as $order): ?>

                                <?php

                                $statusClass = match ($order['order_status']) {

                                    'Pending' => 'warning',

                                    'Accepted' => 'primary',

                                    'Preparing' => 'info',

                                    'Ready' => 'success',

                                    'Out for Delivery' => 'dark',

                                    'Completed' => 'success',

                                    'Cancelled' => 'danger',

                                    default => 'secondary'
                                };

                                $paymentClass = match ($order['payment_status']) {

                                    'Paid' => 'success',

                                    'Pending' => 'warning',

                                    'Failed' => 'danger',

                                    'Refunded' => 'secondary',

                                    default => 'secondary'
                                };

                                $sourceClass = match ($order['order_source']) {

                                    'Website' => 'primary',

                                    'Swiggy' => 'warning',

                                    'Zomato' => 'danger',

                                    default => 'secondary'
                                };

                                ?>

                                <tr>

                                    <!-- ORDER NUMBER -->

                                    <td class="px-4">

                                        <a
                                            href="view_order.php?id=<?= (int)$order['order_id']; ?>"
                                            class="text-decoration-none fw-semibold"
                                        >
                                            <?= e($order['order_number']); ?>
                                        </a>

                                    </td>


                                    <!-- CUSTOMER -->

                                    <td>

                                        <div class="fw-semibold">
                                            <?= e($order['customer_name']); ?>
                                        </div>

                                        <small class="text-muted">

                                            <?= e($order['phone']); ?>

                                        </small>

                                    </td>


                                    <!-- SOURCE -->

                                    <td>

                                        <span
                                            class="badge text-bg-<?= $sourceClass; ?>"
                                        >

                                            <?php if ($order['order_source'] === 'Website'): ?>

                                                <i class="bi bi-globe me-1"></i>

                                            <?php elseif ($order['order_source'] === 'Swiggy'): ?>

                                                <i class="bi bi-bag-check me-1"></i>

                                            <?php elseif ($order['order_source'] === 'Zomato'): ?>

                                                <i class="bi bi-bag-heart me-1"></i>

                                            <?php endif; ?>

                                            <?= e($order['order_source']); ?>

                                        </span>

                                    </td>


                                    <!-- ORDER TYPE -->

                                    <td>

                                        <span class="badge text-bg-light border">

                                            <?= e($order['order_type']); ?>

                                        </span>

                                    </td>


                                    <!-- TOTAL -->

                                    <td>

                                        <strong>
                                            <?= money((float)$order['grand_total']); ?>
                                        </strong>

                                    </td>


                                    <!-- PAYMENT -->

                                    <td>

                                        <span
                                            class="badge text-bg-<?= $paymentClass; ?>"
                                        >
                                            <?= e($order['payment_status']); ?>
                                        </span>

                                        <div class="small text-muted mt-1">

                                            <?= e($order['payment_method']); ?>

                                        </div>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="badge text-bg-<?= $statusClass; ?>"
                                        >
                                            <?= e($order['order_status']); ?>
                                        </span>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <div>

                                            <?= date(
                                                'd M Y',
                                                strtotime($order['ordered_at'])
                                            ); ?>

                                        </div>

                                        <small class="text-muted">

                                            <?= date(
                                                'h:i A',
                                                strtotime($order['ordered_at'])
                                            ); ?>

                                        </small>

                                    </td>


                                    <!-- ACTION -->

                                    <td class="text-center px-4">

                                        <div
                                            class="d-flex justify-content-center gap-2"
                                        >

                                            <a
                                                href="view_order.php?id=<?= (int)$order['order_id']; ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="View Order"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </a>

                                            <a
                                                href="a-invoice.php?id=<?= (int)$order['order_id']; ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="View Invoice"
                                            >
                                                <i class="bi bi-receipt"></i>
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "includes/a-footer.php"; ?>