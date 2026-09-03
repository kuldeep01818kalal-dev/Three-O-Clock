<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Reports";

/* =========================================================
   HELPERS
========================================================= */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return "₹" . number_format($amount, 2);
}

/* =========================================================
   FILTERS
========================================================= */

$defaultFrom = date('Y-m-01');
$defaultTo   = date('Y-m-d');

$fromDate = $_GET['from_date'] ?? $defaultFrom;
$toDate   = $_GET['to_date'] ?? $defaultTo;
$source   = trim($_GET['source'] ?? '');
$status   = trim($_GET['status'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = $defaultFrom;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = $defaultTo;
}

if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

/*
|--------------------------------------------------------------------------
| Existing database ENUM values
|--------------------------------------------------------------------------
*/

$validSources = [
    'Website',
    'Walk-In',
    'Swiggy',
    'Zomato'
];

$validStatuses = [
    'Pending',
    'Accepted',
    'Preparing',
    'Ready',
    'Out for Delivery',
    'Completed',
    'Cancelled'
];

if (!in_array($source, $validSources, true)) {
    $source = '';
}

if (!in_array($status, $validStatuses, true)) {
    $status = '';
}

/* =========================================================
   WHERE CONDITIONS
========================================================= */

$where = [
    "DATE(o.ordered_at) BETWEEN :from_date AND :to_date"
];

$params = [
    ':from_date' => $fromDate,
    ':to_date'   => $toDate
];

if ($source !== '') {
    $where[] = "o.order_source = :source";
    $params[':source'] = $source;
}

if ($status !== '') {
    $where[] = "o.order_status = :status";
    $params[':status'] = $status;
}

$whereSql = implode(" AND ", $where);

/* =========================================================
   MAIN REPORT STATISTICS
========================================================= */

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,

        COALESCE(
            SUM(
                CASE
                    WHEN o.payment_status = 'Paid'
                    THEN o.grand_total
                    ELSE 0
                END
            ),
            0
        ) AS paid_revenue,

        COALESCE(SUM(o.grand_total), 0) AS order_value,

        COALESCE(SUM(o.discount), 0) AS total_discount,

        COALESCE(SUM(o.tax), 0) AS total_tax,

        COALESCE(SUM(o.delivery_charge), 0) AS delivery_revenue,

        COALESCE(
            SUM(
                CASE
                    WHEN o.order_status = 'Completed'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS completed_orders,

        COALESCE(
            SUM(
                CASE
                    WHEN o.order_status = 'Cancelled'
                    THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS cancelled_orders

    FROM orders o

    WHERE {$whereSql}
");

$statsStmt->execute($params);

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$totalOrders      = (int)($stats['total_orders'] ?? 0);
$paidRevenue      = (float)($stats['paid_revenue'] ?? 0);
$orderValue       = (float)($stats['order_value'] ?? 0);
$totalDiscount    = (float)($stats['total_discount'] ?? 0);
$totalTax         = (float)($stats['total_tax'] ?? 0);
$deliveryRevenue  = (float)($stats['delivery_revenue'] ?? 0);
$completedOrders  = (int)($stats['completed_orders'] ?? 0);
$cancelledOrders  = (int)($stats['cancelled_orders'] ?? 0);

$averageOrderValue = $totalOrders > 0
    ? $orderValue / $totalOrders
    : 0;

/* =========================================================
   ORDER STATUS SUMMARY
========================================================= */

$statusStmt = $pdo->prepare("
    SELECT
        o.order_status,
        COUNT(*) AS total
    FROM orders o
    WHERE {$whereSql}
    GROUP BY o.order_status
    ORDER BY total DESC
");

$statusStmt->execute($params);

$statusSummary = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   ORDER SOURCE SUMMARY
========================================================= */

$sourceStmt = $pdo->prepare("
    SELECT
        o.order_source,
        COUNT(*) AS total_orders,
        COALESCE(SUM(o.grand_total), 0) AS revenue
    FROM orders o
    WHERE {$whereSql}
    GROUP BY o.order_source
    ORDER BY revenue DESC
");

$sourceStmt->execute($params);

$sourceSummary = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   PAYMENT SUMMARY
========================================================= */

$paymentStmt = $pdo->prepare("
    SELECT
        o.payment_method,
        COUNT(*) AS total_orders,
        COALESCE(SUM(o.grand_total), 0) AS amount
    FROM orders o
    WHERE {$whereSql}
    GROUP BY o.payment_method
    ORDER BY amount DESC
");

$paymentStmt->execute($params);

$paymentSummary = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   DAILY SALES
========================================================= */

$dailyStmt = $pdo->prepare("
    SELECT
        DATE(o.ordered_at) AS report_date,
        COUNT(*) AS total_orders,
        COALESCE(SUM(o.grand_total), 0) AS total_sales,

        COALESCE(
            SUM(
                CASE
                    WHEN o.payment_status = 'Paid'
                    THEN o.grand_total
                    ELSE 0
                END
            ),
            0
        ) AS paid_sales

    FROM orders o

    WHERE {$whereSql}

    GROUP BY DATE(o.ordered_at)

    ORDER BY report_date DESC
");

$dailyStmt->execute($params);

$dailySales = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   TOP PRODUCTS
========================================================= */

$productStmt = $pdo->prepare("
    SELECT
        p.product_name,
        SUM(oi.quantity) AS total_quantity,
        COALESCE(SUM(oi.total_price), 0) AS total_sales
    FROM order_items oi

    INNER JOIN orders o
        ON o.order_id = oi.order_id

    INNER JOIN products p
        ON p.product_id = oi.product_id

    WHERE {$whereSql}

    GROUP BY
        p.product_id,
        p.product_name

    ORDER BY total_quantity DESC, total_sales DESC

    LIMIT 10
");

$productStmt->execute($params);

$topProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   REPORT TITLE
========================================================= */

$reportPeriod = date(
    "d M Y",
    strtotime($fromDate)
) . " - " . date(
    "d M Y",
    strtotime($toDate)
);

/* =========================================================
   HEADER
========================================================= */

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <div class="reports-page">

            <!-- =================================================
                 PAGE HEADER
            ================================================== -->

            <div class="reports-header">

                <div>

                    <span class="reports-eyebrow">
                        BUSINESS ANALYTICS
                    </span>

                    <h1>
                        Reports
                    </h1>

                    <p>
                        Analyze orders, revenue, payments and sales
                        performance.
                    </p>

                </div>

                <div class="reports-header-actions">

                    <button
                        type="button"
                        class="btn btn-dark"
                        onclick="window.print();"
                    >
                        <i class="bi bi-printer me-2"></i>
                        Print Report
                    </button>

                </div>

            </div>


            <!-- =================================================
                 FILTERS
            ================================================== -->

            <div class="reports-card">

                <div class="reports-card-header">

                    <div>

                        <h2>
                            <i class="bi bi-funnel me-2"></i>
                            Report Filters
                        </h2>

                        <p>
                            Select the period and order filters.
                        </p>

                    </div>

                </div>

                <form
                    method="GET"
                    action="reports.php"
                    class="row g-3"
                >

                    <div class="col-lg-3 col-md-6">

                        <label
                            for="from_date"
                            class="form-label fw-semibold"
                        >
                            From Date
                        </label>

                        <input
                            type="date"
                            id="from_date"
                            name="from_date"
                            class="form-control"
                            value="<?= e($fromDate); ?>"
                        >

                    </div>


                    <div class="col-lg-3 col-md-6">

                        <label
                            for="to_date"
                            class="form-label fw-semibold"
                        >
                            To Date
                        </label>

                        <input
                            type="date"
                            id="to_date"
                            name="to_date"
                            class="form-control"
                            value="<?= e($toDate); ?>"
                        >

                    </div>


                    <div class="col-lg-2 col-md-6">

                        <label
                            for="source"
                            class="form-label fw-semibold"
                        >
                            Source
                        </label>

                        <select
                            id="source"
                            name="source"
                            class="form-select"
                        >

                            <option value="">
                                All Sources
                            </option>

                            <?php foreach ($validSources as $itemSource): ?>

                                <option
                                    value="<?= e($itemSource); ?>"
                                    <?= $source === $itemSource
                                        ? 'selected'
                                        : ''; ?>
                                >
                                    <?= e($itemSource); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-2 col-md-6">

                        <label
                            for="status"
                            class="form-label fw-semibold"
                        >
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <?php foreach ($validStatuses as $itemStatus): ?>

                                <option
                                    value="<?= e($itemStatus); ?>"
                                    <?= $status === $itemStatus
                                        ? 'selected'
                                        : ''; ?>
                                >
                                    <?= e($itemStatus); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-lg-2 col-md-12 d-flex align-items-end">

                        <div class="d-flex gap-2 w-100">

                            <button
                                type="submit"
                                class="btn btn-primary flex-grow-1"
                            >
                                <i class="bi bi-bar-chart-line me-1"></i>
                                Generate
                            </button>

                            <a
                                href="reports.php"
                                class="btn btn-outline-secondary"
                                title="Reset"
                            >
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>

                        </div>

                    </div>

                </form>

            </div>


            <!-- =================================================
                 REPORT PERIOD
            ================================================== -->

            <div class="report-period">

                <div>

                    <i class="bi bi-calendar3"></i>

                    <span>
                        Report Period:
                    </span>

                    <strong>
                        <?= e($reportPeriod); ?>
                    </strong>

                </div>

                <span class="report-period-filter">

                    <?php if ($source !== ''): ?>
                        Source: <?= e($source); ?>
                    <?php endif; ?>

                    <?php if ($status !== ''): ?>
                        <?= $source !== '' ? ' | ' : ''; ?>
                        Status: <?= e($status); ?>
                    <?php endif; ?>

                </span>

            </div>


            <!-- =================================================
                 KPI CARDS
            ================================================== -->

            <div class="row g-4 mb-4">

                <div class="col-xl-3 col-md-6">

                    <div class="report-stat-card">

                        <div class="report-stat-icon">
                            <i class="bi bi-receipt"></i>
                        </div>

                        <div>

                            <span>
                                Total Orders
                            </span>

                            <strong>
                                <?= number_format($totalOrders); ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="report-stat-card">

                        <div class="report-stat-icon revenue">
                            <i class="bi bi-currency-rupee"></i>
                        </div>

                        <div>

                            <span>
                                Paid Revenue
                            </span>

                            <strong>
                                <?= money($paidRevenue); ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="report-stat-card">

                        <div class="report-stat-icon average">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>

                        <div>

                            <span>
                                Average Order
                            </span>

                            <strong>
                                <?= money($averageOrderValue); ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="report-stat-card">

                        <div class="report-stat-icon completed">
                            <i class="bi bi-check-circle"></i>
                        </div>

                        <div>

                            <span>
                                Completed Orders
                            </span>

                            <strong>
                                <?= number_format($completedOrders); ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 SECONDARY STATS
            ================================================== -->

            <div class="row g-4 mb-4">

                <div class="col-lg-3 col-md-6">

                    <div class="mini-report-card">

                        <span>
                            Gross Order Value
                        </span>

                        <strong>
                            <?= money($orderValue); ?>
                        </strong>

                    </div>

                </div>


                <div class="col-lg-3 col-md-6">

                    <div class="mini-report-card">

                        <span>
                            Total Discount
                        </span>

                        <strong class="text-danger">
                            <?= money($totalDiscount); ?>
                        </strong>

                    </div>

                </div>


                <div class="col-lg-3 col-md-6">

                    <div class="mini-report-card">

                        <span>
                            Total Tax
                        </span>

                        <strong>
                            <?= money($totalTax); ?>
                        </strong>

                    </div>

                </div>


                <div class="col-lg-3 col-md-6">

                    <div class="mini-report-card">

                        <span>
                            Cancelled Orders
                        </span>

                        <strong class="text-danger">
                            <?= number_format($cancelledOrders); ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 SOURCE + STATUS
            ================================================== -->

            <div class="row g-4 mb-4">


                <!-- ORDER SOURCE -->

                <div class="col-lg-6">

                    <div class="reports-card h-100">

                        <div class="reports-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-diagram-3 me-2"></i>
                                    Sales by Source
                                </h2>

                                <p>
                                    Performance of each order channel.
                                </p>

                            </div>

                        </div>

                        <div class="table-responsive">

                            <table class="table align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            Source
                                        </th>

                                        <th>
                                            Orders
                                        </th>

                                        <th>
                                            Revenue
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php if (empty($sourceSummary)): ?>

                                    <tr>

                                        <td
                                            colspan="3"
                                            class="text-center py-4 text-muted"
                                        >
                                            No source data found.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($sourceSummary as $row): ?>

                                        <tr>

                                            <td>
                                                <strong>
                                                    <?= e(
                                                        $row['order_source']
                                                    ); ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?= number_format(
                                                    (int)$row['total_orders']
                                                ); ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= money(
                                                        (float)$row['revenue']
                                                    ); ?>
                                                </strong>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>


                <!-- ORDER STATUS -->

                <div class="col-lg-6">

                    <div class="reports-card h-100">

                        <div class="reports-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-activity me-2"></i>
                                    Order Status
                                </h2>

                                <p>
                                    Current order distribution.
                                </p>

                            </div>

                        </div>

                        <div class="table-responsive">

                            <table class="table align-middle mb-0">

                                <thead>

                                    <tr>

                                        <th>
                                            Status
                                        </th>

                                        <th>
                                            Orders
                                        </th>

                                        <th>
                                            Share
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php if (empty($statusSummary)): ?>

                                    <tr>

                                        <td
                                            colspan="3"
                                            class="text-center py-4 text-muted"
                                        >
                                            No status data found.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($statusSummary as $row): ?>

                                        <?php

                                        $statusTotal =
                                            (int)$row['total'];

                                        $percentage =
                                            $totalOrders > 0
                                            ? ($statusTotal / $totalOrders) * 100
                                            : 0;

                                        ?>

                                        <tr>

                                            <td>

                                                <span class="badge bg-light text-dark border">

                                                    <?= e(
                                                        $row['order_status']
                                                    ); ?>

                                                </span>

                                            </td>

                                            <td>
                                                <?= number_format(
                                                    $statusTotal
                                                ); ?>
                                            </td>

                                            <td>

                                                <div class="d-flex align-items-center gap-2">

                                                    <div
                                                        class="progress flex-grow-1"
                                                        style="height:7px;"
                                                    >

                                                        <div
                                                            class="progress-bar"
                                                            role="progressbar"
                                                            style="width: <?= min(
                                                                100,
                                                                $percentage
                                                            ); ?>%;"
                                                        ></div>

                                                    </div>

                                                    <small>
                                                        <?= number_format(
                                                            $percentage,
                                                            1
                                                        ); ?>%
                                                    </small>

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

            </div>


            <!-- =================================================
                 PAYMENT SUMMARY
            ================================================== -->

            <div class="reports-card mb-4">

                <div class="reports-card-header">

                    <div>

                        <h2>
                            <i class="bi bi-credit-card me-2"></i>
                            Payment Summary
                        </h2>

                        <p>
                            Orders and value grouped by payment method.
                        </p>

                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Payment Method
                                </th>

                                <th>
                                    Orders
                                </th>

                                <th>
                                    Amount
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($paymentSummary)): ?>

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center py-4 text-muted"
                                >
                                    No payment data found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($paymentSummary as $row): ?>

                                <tr>

                                    <td>

                                        <i class="bi bi-wallet2 me-2"></i>

                                        <strong>
                                            <?= e(
                                                $row['payment_method']
                                            ); ?>
                                        </strong>

                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int)$row['total_orders']
                                        ); ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= money(
                                                (float)$row['amount']
                                            ); ?>
                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- =================================================
                 DAILY SALES
            ================================================== -->

            <div class="reports-card mb-4">

                <div class="reports-card-header">

                    <div>

                        <h2>
                            <i class="bi bi-calendar3 me-2"></i>
                            Daily Sales
                        </h2>

                        <p>
                            Daily order and revenue performance.
                        </p>

                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Orders
                                </th>

                                <th>
                                    Order Value
                                </th>

                                <th>
                                    Paid Sales
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($dailySales)): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center py-5 text-muted"
                                >

                                    <i class="bi bi-bar-chart display-6"></i>

                                    <div class="mt-2">
                                        No sales data found for this period.
                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($dailySales as $row): ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                date(
                                                    "d M Y",
                                                    strtotime(
                                                        $row['report_date']
                                                    )
                                                )
                                            ); ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <?= number_format(
                                            (int)$row['total_orders']
                                        ); ?>

                                    </td>

                                    <td>

                                        <?= money(
                                            (float)$row['total_sales']
                                        ); ?>

                                    </td>

                                    <td>

                                        <strong class="text-success">

                                            <?= money(
                                                (float)$row['paid_sales']
                                            ); ?>

                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- =================================================
                 TOP PRODUCTS
            ================================================== -->

            <div class="reports-card mb-4">

                <div class="reports-card-header">

                    <div>

                        <h2>
                            <i class="bi bi-trophy me-2"></i>
                            Top Selling Products
                        </h2>

                        <p>
                            Most ordered products during the selected period.
                        </p>

                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Product
                                </th>

                                <th>
                                    Quantity Sold
                                </th>

                                <th>
                                    Sales
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($topProducts)): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center py-5 text-muted"
                                >
                                    No product sales found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($topProducts as $index => $product): ?>

                                <tr>

                                    <td>

                                        <span class="rank-number">
                                            <?= $index + 1; ?>
                                        </span>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= e(
                                                $product['product_name']
                                            ); ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <?= number_format(
                                            (int)$product['total_quantity']
                                        ); ?>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= money(
                                                (float)$product['total_sales']
                                            ); ?>
                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>


            <!-- =================================================
                 REPORT FOOTER
            ================================================== -->

            <div class="report-footer">

                <div>

                    <i class="bi bi-info-circle me-1"></i>

                    Generated on
                    <?= date("d M Y, h:i A"); ?>

                </div>

                <div>

                    Three O' Clock Cafe Management System

                </div>

            </div>

        </div>

    </main>

</div>


<!-- =========================================================
     REPORT STYLES
========================================================= -->

<style>

.reports-page {
    width: 100%;
}

.reports-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 25px;
}

.reports-eyebrow {
    display: inline-block;
    color: #8b5e3c;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1.4px;
    margin-bottom: 6px;
}

.reports-header h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 700;
    color: #111827;
}

.reports-header p {
    margin: 7px 0 0;
    color: #6b7280;
}

.reports-header-actions {
    flex-shrink: 0;
}

.reports-card {
    background: #fff;
    border: 1px solid #e8eaee;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,.04);
    padding: 24px;
}

.reports-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 22px;
}

.reports-card-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}

.reports-card-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.report-period {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    background: #f8f5f2;
    border: 1px solid #eadfd7;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 22px;
    color: #5b4636;
}

.report-period > div {
    display: flex;
    align-items: center;
    gap: 8px;
}

.report-period i {
    color: #8b5e3c;
}

.report-period-filter {
    font-size: 13px;
    color: #6b7280;
}

.report-stat-card {
    height: 100%;
    display: flex;
    align-items: center;
    gap: 15px;
    background: #fff;
    border: 1px solid #e8eaee;
    border-radius: 16px;
    padding: 21px;
    box-shadow: 0 4px 20px rgba(0,0,0,.04);
}

.report-stat-icon {
    width: 50px;
    height: 50px;
    min-width: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #f0f4ff;
    color: #4f6bed;
    font-size: 21px;
}

.report-stat-icon.revenue {
    background: #edf9f1;
    color: #219653;
}

.report-stat-icon.average {
    background: #fff6e8;
    color: #c77b19;
}

.report-stat-icon.completed {
    background: #edf9f1;
    color: #219653;
}

.report-stat-card span {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 4px;
}

.report-stat-card strong {
    display: block;
    color: #111827;
    font-size: 21px;
    font-weight: 700;
}

.mini-report-card {
    background: #fff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    padding: 18px;
}

.mini-report-card span {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 6px;
}

.mini-report-card strong {
    font-size: 19px;
    color: #111827;
}

.rank-number {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f8f1eb;
    color: #8b5e3c;
    font-weight: 700;
}

.report-footer {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
    padding: 18px 3px;
    color: #6b7280;
    font-size: 12px;
}

@media (max-width: 767px) {

    .reports-header {
        flex-direction: column;
    }

    .reports-header-actions {
        width: 100%;
    }

    .reports-header-actions .btn {
        width: 100%;
    }

    .reports-header h1 {
        font-size: 27px;
    }

    .reports-card {
        padding: 18px;
        border-radius: 13px;
    }

    .report-period {
        align-items: flex-start;
        flex-direction: column;
    }

    .reports-card .table {
        min-width: 600px;
    }

}

@media print {

    body {
        background: #fff !important;
    }

    .sidebar,
    .admin-navbar,
    .reports-header-actions,
    .reports-card:first-of-type,
    .report-footer {
        display: none !important;
    }

    .admin-main {
        width: 100% !important;
    }

    .admin-content {
        padding: 0 !important;
    }

    .reports-card,
    .report-stat-card,
    .mini-report-card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }

    .reports-header {
        margin-bottom: 15px;
    }

}

</style>

<?php require_once "includes/a-footer.php"; ?>