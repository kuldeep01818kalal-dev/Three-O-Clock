<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Invoices";

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
   VIEW SINGLE INVOICE
===================================================== */

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$selectedOrder = null;
$selectedItems = [];
$selectedPayment = null;

if ($invoiceId > 0) {

    $orderStmt = $pdo->prepare("
        SELECT
            o.*,
            ct.table_number
        FROM orders o
        LEFT JOIN cafe_tables ct
            ON o.table_id = ct.table_id
        WHERE o.order_id = :order_id
        LIMIT 1
    ");

    $orderStmt->execute([
        ':order_id' => $invoiceId
    ]);

    $selectedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedOrder) {

        $itemStmt = $pdo->prepare("
            SELECT
                oi.item_id,
                oi.product_id,
                oi.quantity,
                oi.unit_price,
                oi.total_price,
                oi.special_instruction,
                p.product_name
            FROM order_items oi
            INNER JOIN products p
                ON oi.product_id = p.product_id
            WHERE oi.order_id = :order_id
            ORDER BY oi.item_id ASC
        ");

        $itemStmt->execute([
            ':order_id' => $invoiceId
        ]);

        $selectedItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $paymentStmt = $pdo->prepare("
            SELECT
                payment_id,
                transaction_id,
                razorpay_order_id,
                razorpay_payment_id,
                payment_method,
                payment_status,
                amount,
                payment_date,
                remarks
            FROM payments
            WHERE order_id = :order_id
            ORDER BY payment_id DESC
            LIMIT 1
        ");

        $paymentStmt->execute([
            ':order_id' => $invoiceId
        ]);

        $selectedPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    }
}

/* =====================================================
   INVOICE LIST
===================================================== */

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "(
        o.order_number LIKE :search
        OR o.customer_name LIKE :search
        OR o.phone LIKE :search
    )";

    $params[':search'] = '%' . $search . '%';
}

if ($status !== '') {

    $allowedStatuses = [
        'Paid',
        'Pending',
        'Failed',
        'Refunded'
    ];

    if (in_array($status, $allowedStatuses, true)) {
        $where[] = "o.payment_status = :status";
        $params[':status'] = $status;
    }
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$listStmt = $pdo->prepare("
    SELECT
        o.order_id,
        o.order_number,
        o.customer_name,
        o.phone,
        o.order_type,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.grand_total,
        o.ordered_at
    FROM orders o
    $whereSql
    ORDER BY o.order_id DESC
");

$listStmt->execute($params);

$invoices = $listStmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   STATISTICS
===================================================== */

$totalInvoices = count($invoices);

$paidInvoices = 0;
$pendingInvoices = 0;
$totalInvoiceValue = 0;

foreach ($invoices as $invoice) {

    $totalInvoiceValue += (float)$invoice['grand_total'];

    if ($invoice['payment_status'] === 'Paid') {
        $paidInvoices++;
    }

    if ($invoice['payment_status'] === 'Pending') {
        $pendingInvoices++;
    }
}

/* =====================================================
   LOAD ADMIN HEADER
===================================================== */

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- =================================================
             SINGLE INVOICE VIEW
        ================================================== -->

        <?php if ($invoiceId > 0): ?>

            <?php if (!$selectedOrder): ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">

                        <div class="mb-3">
                            <i class="bi bi-receipt-cutoff display-4 text-muted"></i>
                        </div>

                        <h4 class="fw-semibold">
                            Invoice Not Found
                        </h4>

                        <p class="text-muted mb-4">
                            The requested order/invoice could not be found.
                        </p>

                        <a href="a-invoice.php"
                           class="btn btn-primary">
                            <i class="bi bi-arrow-left me-2"></i>
                            Back to Invoices
                        </a>

                    </div>
                </div>

            <?php else: ?>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                    <div>
                        <div class="text-muted small mb-1">
                            BUSINESS / INVOICE
                        </div>

                        <h1 class="h3 fw-bold mb-1">
                            Invoice
                        </h1>

                        <p class="text-muted mb-0">
                            <?= e($selectedOrder['order_number']); ?>
                        </p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">

                        <a href="a-invoice.php"
                           class="btn btn-outline-secondary">

                            <i class="bi bi-arrow-left me-2"></i>
                            Back
                        </a>

                        <button type="button"
                                class="btn btn-dark"
                                onclick="window.print();">

                            <i class="bi bi-printer me-2"></i>
                            Print Invoice
                        </button>

                    </div>

                </div>


                <!-- INVOICE -->
                <div class="card border-0 shadow-sm invoice-print-area">

                    <div class="card-body p-4 p-lg-5">

                        <!-- HEADER -->
                        <div class="row align-items-start g-4 mb-4">

                            <div class="col-md-7">

                                <div class="d-flex align-items-center gap-3">

                                    <div
                                        style="
                                            width:52px;
                                            height:52px;
                                            border-radius:14px;
                                            background:#8b5e3c;
                                            color:#fff;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            font-size:24px;
                                        "
                                    >
                                        <i class="bi bi-cup-hot-fill"></i>
                                    </div>

                                    <div>

                                        <h3 class="fw-bold mb-1">
                                            Three O' Clock Cafe
                                        </h3>

                                        <p class="text-muted mb-0">
                                            Restaurant Management System
                                        </p>

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-5 text-md-end">

                                <div class="text-uppercase text-muted small fw-semibold">
                                    Invoice
                                </div>

                                <h3 class="fw-bold mb-1">
                                    <?= e($selectedOrder['order_number']); ?>
                                </h3>

                                <div class="text-muted">
                                    <?= date(
                                        'd M Y, h:i A',
                                        strtotime($selectedOrder['ordered_at'])
                                    ); ?>
                                </div>

                            </div>

                        </div>

                        <hr>


                        <!-- CUSTOMER + ORDER INFO -->
                        <div class="row g-4 my-4">

                            <div class="col-md-6">

                                <div class="text-muted small text-uppercase fw-semibold mb-2">
                                    Bill To
                                </div>

                                <h5 class="fw-semibold mb-2">
                                    <?= e($selectedOrder['customer_name']); ?>
                                </h5>

                                <div class="text-muted mb-1">
                                    <i class="bi bi-telephone me-2"></i>
                                    <?= e($selectedOrder['phone']); ?>
                                </div>

                                <?php if (!empty($selectedOrder['email'])): ?>

                                    <div class="text-muted mb-1">
                                        <i class="bi bi-envelope me-2"></i>
                                        <?= e($selectedOrder['email']); ?>
                                    </div>

                                <?php endif; ?>

                                <?php if (!empty($selectedOrder['address'])): ?>

                                    <div class="text-muted">
                                        <i class="bi bi-geo-alt me-2"></i>
                                        <?= nl2br(e($selectedOrder['address'])); ?>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small text-uppercase fw-semibold mb-2">
                                    Order Details
                                </div>

                                <div class="row g-2">

                                    <div class="col-6 text-muted">
                                        Order Type
                                    </div>

                                    <div class="col-6 text-end fw-semibold">
                                        <?= e($selectedOrder['order_type']); ?>
                                    </div>

                                    <div class="col-6 text-muted">
                                        Order Status
                                    </div>

                                    <div class="col-6 text-end">
                                        <span class="badge text-bg-secondary">
                                            <?= e($selectedOrder['order_status']); ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($selectedOrder['table_number'])): ?>

                                        <div class="col-6 text-muted">
                                            Table
                                        </div>

                                        <div class="col-6 text-end fw-semibold">
                                            <?= e($selectedOrder['table_number']); ?>
                                        </div>

                                    <?php endif; ?>

                                    <div class="col-6 text-muted">
                                        Payment
                                    </div>

                                    <div class="col-6 text-end fw-semibold">
                                        <?= e($selectedOrder['payment_method']); ?>
                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- ITEMS -->
                        <div class="table-responsive">

                            <table class="table align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th style="width:55px;">
                                            #
                                        </th>

                                        <th>
                                            Item
                                        </th>

                                        <th class="text-center">
                                            Qty
                                        </th>

                                        <th class="text-end">
                                            Unit Price
                                        </th>

                                        <th class="text-end">
                                            Total
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php if (empty($selectedItems)): ?>

                                    <tr>

                                        <td colspan="5"
                                            class="text-center text-muted py-4">

                                            No items found for this order.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($selectedItems as $index => $item): ?>

                                        <tr>

                                            <td class="text-muted">
                                                <?= $index + 1; ?>
                                            </td>

                                            <td>

                                                <div class="fw-semibold">
                                                    <?= e($item['product_name']); ?>
                                                </div>

                                                <?php if (!empty($item['special_instruction'])): ?>

                                                    <small class="text-muted">
                                                        <?= e($item['special_instruction']); ?>
                                                    </small>

                                                <?php endif; ?>

                                            </td>

                                            <td class="text-center">
                                                <?= (int)$item['quantity']; ?>
                                            </td>

                                            <td class="text-end">
                                                <?= money((float)$item['unit_price']); ?>
                                            </td>

                                            <td class="text-end fw-semibold">
                                                <?= money((float)$item['total_price']); ?>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                                </tbody>

                            </table>

                        </div>


                        <!-- TOTALS -->
                        <div class="row justify-content-end mt-4">

                            <div class="col-md-6 col-lg-5">

                                <div class="d-flex justify-content-between py-2">

                                    <span class="text-muted">
                                        Subtotal
                                    </span>

                                    <strong>
                                        <?= money((float)$selectedOrder['subtotal']); ?>
                                    </strong>

                                </div>

                                <div class="d-flex justify-content-between py-2">

                                    <span class="text-muted">
                                        Discount
                                    </span>

                                    <strong>
                                        - <?= money((float)$selectedOrder['discount']); ?>
                                    </strong>

                                </div>

                                <div class="d-flex justify-content-between py-2">

                                    <span class="text-muted">
                                        Tax
                                    </span>

                                    <strong>
                                        <?= money((float)$selectedOrder['tax']); ?>
                                    </strong>

                                </div>

                                <div class="d-flex justify-content-between py-2">

                                    <span class="text-muted">
                                        Delivery Charge
                                    </span>

                                    <strong>
                                        <?= money((float)$selectedOrder['delivery_charge']); ?>
                                    </strong>

                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center py-2">

                                    <span class="fw-bold fs-5">
                                        Grand Total
                                    </span>

                                    <strong class="fs-4"
                                            style="color:#8b5e3c;">

                                        <?= money((float)$selectedOrder['grand_total']); ?>

                                    </strong>

                                </div>

                            </div>

                        </div>


                        <!-- PAYMENT -->
                        <div class="row g-4 mt-4 pt-4 border-top">

                            <div class="col-md-6">

                                <div class="text-muted small text-uppercase fw-semibold mb-2">
                                    Payment Status
                                </div>

                                <?php

                                $paymentBadge = match (
                                    $selectedOrder['payment_status']
                                ) {
                                    'Paid' => 'success',
                                    'Pending' => 'warning',
                                    'Failed' => 'danger',
                                    'Refunded' => 'secondary',
                                    default => 'secondary'
                                };

                                ?>

                                <span class="badge text-bg-<?= $paymentBadge; ?> px-3 py-2">

                                    <?= e($selectedOrder['payment_status']); ?>

                                </span>

                            </div>


                            <div class="col-md-6">

                                <div class="text-muted small text-uppercase fw-semibold mb-2">
                                    Payment Information
                                </div>

                                <?php if ($selectedPayment): ?>

                                    <div class="small text-muted">

                                        Method:
                                        <strong>
                                            <?= e($selectedPayment['payment_method']); ?>
                                        </strong>

                                    </div>

                                    <div class="small text-muted">

                                        Amount:
                                        <strong>
                                            <?= money((float)$selectedPayment['amount']); ?>
                                        </strong>

                                    </div>

                                    <?php if (!empty($selectedPayment['transaction_id'])): ?>

                                        <div class="small text-muted">

                                            Transaction ID:
                                            <strong>
                                                <?= e($selectedPayment['transaction_id']); ?>
                                            </strong>

                                        </div>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="text-muted small">
                                        No payment record available.
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <?php if (!empty($selectedOrder['notes'])): ?>

                            <div class="alert alert-light border mt-4 mb-0">

                                <strong>
                                    <i class="bi bi-sticky me-2"></i>
                                    Order Notes
                                </strong>

                                <div class="text-muted mt-2">
                                    <?= nl2br(e($selectedOrder['notes'])); ?>
                                </div>

                            </div>

                        <?php endif; ?>


                        <!-- FOOTER -->
                        <div class="text-center text-muted small mt-5 pt-4 border-top">

                            <p class="mb-1">
                                Thank you for choosing Three O' Clock Cafe.
                            </p>

                            <p class="mb-0">
                                This is a computer-generated invoice.
                            </p>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


        <?php else: ?>


            <!-- =================================================
                 INVOICE LIST
            ================================================== -->

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                <div>

                    <div class="text-muted small mb-1">
                        BUSINESS MANAGEMENT
                    </div>

                    <h1 class="h3 fw-bold mb-1">
                        Invoices
                    </h1>

                    <p class="text-muted mb-0">
                        View and print customer invoices.
                    </p>

                </div>

                <a href="a-billing.php"
                   class="btn btn-primary">

                    <i class="bi bi-calculator me-2"></i>
                    Open Billing / POS

                </a>

            </div>


            <!-- STAT CARDS -->
            <div class="row g-3 mb-4">

                <div class="col-xl-4 col-md-6">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        TOTAL INVOICES
                                    </div>

                                    <div class="fs-3 fw-bold mt-1">
                                        <?= $totalInvoices; ?>
                                    </div>

                                </div>

                                <div class="rounded-3 p-3"
                                     style="background:#f3ece7;color:#8b5e3c;">

                                    <i class="bi bi-receipt fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-xl-4 col-md-6">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        PAID INVOICES
                                    </div>

                                    <div class="fs-3 fw-bold mt-1 text-success">
                                        <?= $paidInvoices; ?>
                                    </div>

                                </div>

                                <div class="rounded-3 p-3 bg-success-subtle text-success">

                                    <i class="bi bi-check-circle fs-4"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-xl-4 col-md-12">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="text-muted small">
                                        INVOICE VALUE
                                    </div>

                                    <div class="fs-3 fw-bold mt-1">
                                        <?= money($totalInvoiceValue); ?>
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


            <!-- SEARCH / FILTER -->
            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <form method="GET"
                          class="row g-3 align-items-end">

                        <div class="col-lg-6">

                            <label class="form-label fw-semibold">
                                Search Invoice
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
                                Payment Status
                            </label>

                            <select name="status"
                                    class="form-select">

                                <option value="">
                                    All Statuses
                                </option>

                                <option value="Paid"
                                    <?= $status === 'Paid' ? 'selected' : ''; ?>>
                                    Paid
                                </option>

                                <option value="Pending"
                                    <?= $status === 'Pending' ? 'selected' : ''; ?>>
                                    Pending
                                </option>

                                <option value="Failed"
                                    <?= $status === 'Failed' ? 'selected' : ''; ?>>
                                    Failed
                                </option>

                                <option value="Refunded"
                                    <?= $status === 'Refunded' ? 'selected' : ''; ?>>
                                    Refunded
                                </option>

                            </select>

                        </div>


                        <div class="col-lg-3">

                            <div class="d-flex gap-2">

                                <button type="submit"
                                        class="btn btn-primary flex-grow-1">

                                    <i class="bi bi-funnel me-2"></i>
                                    Filter

                                </button>

                                <a href="a-invoice.php"
                                   class="btn btn-outline-secondary">

                                    <i class="bi bi-arrow-clockwise"></i>

                                </a>

                            </div>

                        </div>

                    </form>

                </div>

            </div>


            <!-- INVOICE TABLE -->
            <div class="card border-0 shadow-sm">

                <div class="card-body p-0">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th class="px-4">
                                        Invoice
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
                                        Date
                                    </th>

                                    <th class="text-center px-4">
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php if (empty($invoices)): ?>

                                <tr>

                                    <td colspan="7"
                                        class="text-center py-5">

                                        <div class="mb-3">
                                            <i class="bi bi-receipt display-5 text-muted"></i>
                                        </div>

                                        <h5 class="fw-semibold">
                                            No invoices found
                                        </h5>

                                        <p class="text-muted mb-0">
                                            Try changing your search or payment filter.
                                        </p>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($invoices as $invoice): ?>

                                    <?php

                                    $paymentBadge = match (
                                        $invoice['payment_status']
                                    ) {
                                        'Paid' => 'success',
                                        'Pending' => 'warning',
                                        'Failed' => 'danger',
                                        'Refunded' => 'secondary',
                                        default => 'secondary'
                                    };

                                    ?>

                                    <tr>

                                        <td class="px-4">

                                            <a
                                                href="a-invoice.php?id=<?= (int)$invoice['order_id']; ?>"
                                                class="text-decoration-none fw-semibold"
                                            >
                                                <?= e($invoice['order_number']); ?>
                                            </a>

                                        </td>


                                        <td>

                                            <div class="fw-semibold">
                                                <?= e($invoice['customer_name']); ?>
                                            </div>

                                            <small class="text-muted">
                                                <?= e($invoice['phone']); ?>
                                            </small>

                                        </td>


                                        <td>

                                            <span class="badge text-bg-light border">
                                                <?= e($invoice['order_type']); ?>
                                            </span>

                                        </td>


                                        <td>

                                            <strong>
                                                <?= money((float)$invoice['grand_total']); ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <span class="badge text-bg-<?= $paymentBadge; ?>">

                                                <?= e($invoice['payment_status']); ?>

                                            </span>

                                            <div class="small text-muted mt-1">
                                                <?= e($invoice['payment_method']); ?>
                                            </div>

                                        </td>


                                        <td>

                                            <div>
                                                <?= date(
                                                    'd M Y',
                                                    strtotime($invoice['ordered_at'])
                                                ); ?>
                                            </div>

                                            <small class="text-muted">
                                                <?= date(
                                                    'h:i A',
                                                    strtotime($invoice['ordered_at'])
                                                ); ?>
                                            </small>

                                        </td>


                                        <td class="text-center px-4">

                                            <div class="d-flex justify-content-center gap-2">

                                                <a
                                                    href="a-invoice.php?id=<?= (int)$invoice['order_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="View Invoice"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                </a>

                                                <a
                                                    href="a-invoice.php?id=<?= (int)$invoice['order_id']; ?>"
                                                    target="_blank"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Open Invoice"
                                                >
                                                    <i class="bi bi-box-arrow-up-right"></i>
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

        <?php endif; ?>

    </main>

</div>


<!-- =====================================================
     PRINT CSS
===================================================== -->

<style>

@media print {

    body {
        background: #fff !important;
    }

    .sidebar,
    .sidebar-overlay,
    .admin-navbar,
    .admin-footer,
    .btn,
    button,
    .no-print {
        display: none !important;
    }

    .admin-main {
        width: 100% !important;
    }

    .admin-content {
        padding: 0 !important;
        margin: 0 !important;
    }

    .invoice-print-area {
        box-shadow: none !important;
        border: none !important;
    }

    .invoice-print-area .card-body {
        padding: 0 !important;
    }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    @page {
        size: A4;
        margin: 12mm;
    }
}

</style>

<?php require_once "includes/a-footer.php"; ?>