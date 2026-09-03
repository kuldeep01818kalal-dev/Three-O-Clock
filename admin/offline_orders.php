<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Offline Orders";

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

$where = [
    "o.order_source = 'Walk-In'"
];

$params = [];

if ($search !== '') {

    $where[] = "(
        o.order_number LIKE :search
        OR o.customer_name LIKE :search
        OR o.phone LIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

$allowedStatuses = [
    'Pending',
    'Accepted',
    'Preparing',
    'Ready',
    'Out for Delivery',
    'Completed',
    'Cancelled'
];

if ($status !== '' && in_array($status, $allowedStatuses, true)) {

    $where[] = "o.order_status = :status";
    $params[':status'] = $status;
}

$whereSql = implode(' AND ', $where);

/* =====================================================
   ORDERS
===================================================== */

$stmt = $pdo->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.customer_name,
        o.phone,
        o.order_type,
        o.table_id,
        ct.table_number,
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
    LEFT JOIN cafe_tables ct
        ON o.table_id = ct.table_id
    WHERE $whereSql
    ORDER BY o.order_id DESC
");

$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   STATISTICS
===================================================== */

$totalOrders = count($orders);

$pendingOrders = 0;
$completedOrders = 0;
$totalRevenue = 0;
$paidRevenue = 0;

foreach ($orders as $order) {

    $totalRevenue += (float)$order['grand_total'];

    if ($order['payment_status'] === 'Paid') {
        $paidRevenue += (float)$order['grand_total'];
    }

    if (
        in_array(
            $order['order_status'],
            ['Pending', 'Accepted', 'Preparing', 'Ready'],
            true
        )
    ) {
        $pendingOrders++;
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
                    Offline Orders
                </h1>

                <p class="text-muted mb-0">
                    Manage walk-in orders created at the cafe.
                </p>

            </div>

            <div class="d-flex gap-2">

                <a href="a-billing.php"
                   class="btn btn-primary">

                    <i class="bi bi-calculator me-2"></i>
                    New Billing

                </a>

                <a href="orders.php"
                   class="btn btn-outline-secondary">

                    <i class="bi bi-list-check me-2"></i>
                    All Orders

                </a>

            </div>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="row g-3 mb-4">

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    WALK-IN ORDERS
                                </div>

                                <div class="fs-3 fw-bold mt-1">
                                    <?= $totalOrders; ?>
                                </div>

                            </div>

                            <div
                                class="rounded-3 p-3"
                                style="background:#f3ece7;color:#8b5e3c;"
                            >
                                <i class="bi bi-shop fs-4"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    ACTIVE ORDERS
                                </div>

                                <div class="fs-3 fw-bold mt-1 text-warning">
                                    <?= $pendingOrders; ?>
                                </div>

                            </div>

                            <div class="rounded-3 p-3 bg-warning-subtle text-warning">

                                <i class="bi bi-hourglass-split fs-4"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


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


            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <div class="text-muted small">
                                    WALK-IN VALUE
                                </div>

                                <div class="fs-3 fw-bold mt-1">
                                    <?= money($totalRevenue); ?>
                                </div>

                            </div>

                            <div class="rounded-3 p-3 bg-primary-subtle text-primary">

                                <i class="bi bi-currency-rupee fs-4"></i>

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

                        <div class="col-lg-6">

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
                                    placeholder="Order number, customer name or phone"
                                    value="<?= e($search); ?>"
                                >

                            </div>

                        </div>


                        <div class="col-lg-3">

                            <label class="form-label fw-semibold">
                                Order Status
                            </label>

                            <select name="status"
                                    class="form-select">

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


                        <div class="col-lg-3">

                            <div class="d-flex gap-2">

                                <button
                                    type="submit"
                                    class="btn btn-primary flex-grow-1"
                                >
                                    <i class="bi bi-funnel me-2"></i>
                                    Filter
                                </button>

                                <a
                                    href="offline_orders.php"
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
             ORDERS TABLE
        ================================================== -->

        <div class="card border-0 shadow-sm">

            <div class="card-body p-0">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 p-4 border-bottom">

                    <div>

                        <h5 class="fw-bold mb-1">
                            <i class="bi bi-shop me-2"></i>
                            Walk-In Orders
                        </h5>

                        <span class="text-muted small">
                            Orders created directly at the cafe.
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
                                    colspan="8"
                                    class="text-center py-5"
                                >

                                    <div class="mb-3">

                                        <i
                                            class="bi bi-shop display-5 text-muted"
                                        ></i>

                                    </div>

                                    <h5 class="fw-semibold">
                                        No Offline Orders Found
                                    </h5>

                                    <p class="text-muted mb-3">
                                        No walk-in orders match the selected filters.
                                    </p>

                                    <a
                                        href="a-billing.php"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-plus-lg me-2"></i>
                                        Create New Order
                                    </a>

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

                                ?>

                                <tr>

                                    <!-- ORDER -->
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


                                    <!-- TYPE -->
                                    <td>

                                        <span class="badge text-bg-light border">

                                            <?= e($order['order_type']); ?>

                                        </span>

                                        <?php if (!empty($order['table_number'])): ?>

                                            <div class="small text-muted mt-1">

                                                <i class="bi bi-grid-3x3-gap me-1"></i>

                                                Table
                                                <?= e($order['table_number']); ?>

                                            </div>

                                        <?php endif; ?>

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