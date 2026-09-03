<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Order Details";

/* =========================================================
   HELPERS
========================================================= */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function statusClass(string $status): string
{
    return strtolower(
        preg_replace('/[^a-z0-9]+/i', '-', trim($status))
    );
}

function paymentClass(string $status): string
{
    return strtolower(
        preg_replace('/[^a-z0-9]+/i', '-', trim($status))
    );
}

/* =========================================================
   VALIDATE ORDER ID
========================================================= */

$orderId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId <= 0) {
    $_SESSION['error'] = "Invalid order.";
    header("Location: orders.php");
    exit;
}

/* =========================================================
   FETCH ORDER
========================================================= */

$orderStmt = $pdo->prepare("
    SELECT
        o.*,
        ct.table_number,
        ct.capacity AS table_capacity,
        ct.location AS table_location
    FROM orders o
    LEFT JOIN cafe_tables ct
        ON ct.table_id = o.table_id
    WHERE o.order_id = :order_id
    LIMIT 1
");

$orderStmt->execute([
    ':order_id' => $orderId
]);

$order = $orderStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = "Order not found.";
    header("Location: orders.php");
    exit;
}

/* =========================================================
   FETCH ORDER ITEMS
========================================================= */

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
        ON p.product_id = oi.product_id
    WHERE oi.order_id = :order_id
    ORDER BY oi.item_id ASC
");

$itemStmt->execute([
    ':order_id' => $orderId
]);

$orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   FETCH PAYMENT RECORDS
========================================================= */

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
");

$paymentStmt->execute([
    ':order_id' => $orderId
]);

$payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$latestPayment = $payments[0] ?? null;

/* =========================================================
   CALCULATIONS
========================================================= */

$itemCount = count($orderItems);

$totalQuantity = 0;

foreach ($orderItems as $item) {
    $totalQuantity += (int)$item['quantity'];
}

$orderStatusClass = statusClass($order['order_status']);
$paymentStatusClass = paymentClass($order['payment_status']);

/* =========================================================
   HEADER
========================================================= */

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="order-details-page">

            <div class="order-details-page-header">

                <div>

                    <span class="order-details-eyebrow">
                        ORDER MANAGEMENT
                    </span>

                    <h1>
                        Order Details
                    </h1>

                    <p>
                        Complete information for
                        <strong><?= e($order['order_number']); ?></strong>
                    </p>

                </div>

                <div class="order-details-actions">

                    <a
                        href="orders.php"
                        class="order-details-btn secondary"
                    >
                        <i class="bi bi-arrow-left"></i>
                        Back
                    </a>

                    <a
                        href="a-invoice.php?id=<?= (int)$order['order_id']; ?>"
                        class="order-details-btn secondary"
                    >
                        <i class="bi bi-receipt"></i>
                        Invoice
                    </a>

                    <a
                        href="edit_order.php?id=<?= (int)$order['order_id']; ?>"
                        class="order-details-btn primary"
                    >
                        <i class="bi bi-pencil-square"></i>
                        Update Order
                    </a>

                </div>

            </div>


            <!-- =================================================
                 MAIN GRID
            ================================================== -->

            <div class="order-details-grid">

                <!-- =============================================
                     LEFT COLUMN
                ============================================== -->

                <div>


                    <!-- =========================================
                         ORDER SUMMARY
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-bag-check me-2"></i>
                                    Order Summary
                                </h2>

                                <p>
                                    Basic order information
                                </p>

                            </div>

                            <span
                                class="order-detail-status <?= e($orderStatusClass); ?>"
                            >
                                <?= e($order['order_status']); ?>
                            </span>

                        </div>

                        <div class="order-summary">

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    Order Number
                                </span>

                                <span class="order-summary-value order-number">
                                    <?= e($order['order_number']); ?>
                                </span>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    Order Source
                                </span>

                                <span class="order-summary-value">
                                    <?= e($order['order_source']); ?>
                                </span>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    Order Type
                                </span>

                                <span class="order-summary-value">
                                    <?= e($order['order_type']); ?>
                                </span>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    Ordered At
                                </span>

                                <span class="order-summary-value">
                                    <?= e(
                                        date(
                                            "d M Y, h:i A",
                                            strtotime($order['ordered_at'])
                                        )
                                    ); ?>
                                </span>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    Last Updated
                                </span>

                                <span class="order-summary-value">
                                    <?= e(
                                        date(
                                            "d M Y, h:i A",
                                            strtotime($order['updated_at'])
                                        )
                                    ); ?>
                                </span>

                            </div>

                            <div class="order-summary-item">

                                <span class="order-summary-label">
                                    Items
                                </span>

                                <span class="order-summary-value">
                                    <?= $itemCount; ?>
                                    item<?= $itemCount === 1 ? '' : 's'; ?>
                                    /
                                    <?= $totalQuantity; ?> qty
                                </span>

                            </div>

                        </div>

                    </div>


                    <!-- =========================================
                         CUSTOMER INFORMATION
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-person-circle me-2"></i>
                                    Customer Information
                                </h2>

                                <p>
                                    Customer contact and order details
                                </p>

                            </div>

                        </div>

                        <div class="customer-info">

                            <div class="customer-info-item">

                                <span class="customer-info-label">
                                    Customer Name
                                </span>

                                <div class="customer-info-value">
                                    <strong>
                                        <?= e($order['customer_name']); ?>
                                    </strong>
                                </div>

                            </div>

                            <div class="customer-info-item">

                                <span class="customer-info-label">
                                    Phone
                                </span>

                                <div class="customer-info-value">
                                    <?= e($order['phone']); ?>
                                </div>

                            </div>

                            <div class="customer-info-item">

                                <span class="customer-info-label">
                                    Email
                                </span>

                                <div class="customer-info-value">
                                    <?= !empty($order['email'])
                                        ? e($order['email'])
                                        : 'Not provided'; ?>
                                </div>

                            </div>

                            <div class="customer-info-item">

                                <span class="customer-info-label">
                                    User ID
                                </span>

                                <div class="customer-info-value">
                                    <?= !empty($order['user_id'])
                                        ? '#' . (int)$order['user_id']
                                        : 'Guest'; ?>
                                </div>

                            </div>

                            <?php if ($order['order_type'] === 'Dine-In'): ?>

                                <div class="customer-info-item">

                                    <span class="customer-info-label">
                                        Table
                                    </span>

                                    <div class="customer-info-value">

                                        <?php if (!empty($order['table_number'])): ?>

                                            <strong>
                                                <?= e($order['table_number']); ?>
                                            </strong>

                                            <?php if (!empty($order['table_location'])): ?>

                                                <span>
                                                    -
                                                    <?= e($order['table_location']); ?>
                                                </span>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            Not assigned

                                        <?php endif; ?>

                                    </div>

                                </div>

                                <?php if (!empty($order['table_capacity'])): ?>

                                    <div class="customer-info-item">

                                        <span class="customer-info-label">
                                            Table Capacity
                                        </span>

                                        <div class="customer-info-value">
                                            <?= (int)$order['table_capacity']; ?>
                                            seats
                                        </div>

                                    </div>

                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- =========================================
                         ORDER ITEMS
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-list-ul me-2"></i>
                                    Ordered Items
                                </h2>

                                <p>
                                    Products included in this order
                                </p>

                            </div>

                            <span class="badge bg-light text-dark border">
                                <?= $itemCount; ?> Items
                            </span>

                        </div>

                        <div class="order-items-wrapper">

                            <table class="order-items-table">

                                <thead>

                                    <tr>

                                        <th>
                                            #
                                        </th>

                                        <th>
                                            Product
                                        </th>

                                        <th>
                                            Instruction
                                        </th>

                                        <th>
                                            Qty
                                        </th>

                                        <th>
                                            Unit Price
                                        </th>

                                        <th>
                                            Total
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php if (empty($orderItems)): ?>

                                    <tr>

                                        <td
                                            colspan="6"
                                            class="text-center py-4"
                                        >

                                            <span class="text-muted">
                                                No items found for this order.
                                            </span>

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($orderItems as $index => $item): ?>

                                        <tr>

                                            <td>
                                                <?= $index + 1; ?>
                                            </td>

                                            <td>

                                                <span class="order-item-name">

                                                    <?= e(
                                                        $item['product_name']
                                                    ); ?>

                                                </span>

                                            </td>

                                            <td>

                                                <?php if (
                                                    !empty(
                                                        $item['special_instruction']
                                                    )
                                                ): ?>

                                                    <?= e(
                                                        $item[
                                                            'special_instruction'
                                                        ]
                                                    ); ?>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        —
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                            <td>

                                                <span class="order-item-qty">
                                                    <?= (int)$item['quantity']; ?>
                                                </span>

                                            </td>

                                            <td>

                                                <span class="order-item-price">
                                                    ₹<?= number_format(
                                                        (float)$item['unit_price'],
                                                        2
                                                    ); ?>
                                                </span>

                                            </td>

                                            <td>

                                                <span class="order-item-total">
                                                    ₹<?= number_format(
                                                        (float)$item['total_price'],
                                                        2
                                                    ); ?>
                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>


                    <!-- =========================================
                         BILL SUMMARY
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-calculator me-2"></i>
                                    Bill Summary
                                </h2>

                                <p>
                                    Complete order calculation
                                </p>

                            </div>

                        </div>

                        <div class="order-totals">

                            <div class="order-total-row">

                                <span>
                                    Subtotal
                                </span>

                                <strong>
                                    ₹<?= number_format(
                                        (float)$order['subtotal'],
                                        2
                                    ); ?>
                                </strong>

                            </div>

                            <div class="order-total-row discount">

                                <span>
                                    Discount
                                </span>

                                <strong>
                                    - ₹<?= number_format(
                                        (float)$order['discount'],
                                        2
                                    ); ?>
                                </strong>

                            </div>

                            <div class="order-total-row">

                                <span>
                                    Tax
                                </span>

                                <strong>
                                    ₹<?= number_format(
                                        (float)$order['tax'],
                                        2
                                    ); ?>
                                </strong>

                            </div>

                            <div class="order-total-row">

                                <span>
                                    Delivery Charge
                                </span>

                                <strong>
                                    ₹<?= number_format(
                                        (float)$order['delivery_charge'],
                                        2
                                    ); ?>
                                </strong>

                            </div>

                            <div class="order-total-divider"></div>

                            <div class="order-total-grand">

                                <span>
                                    Grand Total
                                </span>

                                <strong>
                                    ₹<?= number_format(
                                        (float)$order['grand_total'],
                                        2
                                    ); ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <!-- =========================================
                         ADDRESS
                    ========================================== -->

                    <?php if (!empty($order['address'])): ?>

                        <div class="order-details-card">

                            <div class="order-details-card-header">

                                <div>

                                    <h2>
                                        <i class="bi bi-geo-alt me-2"></i>
                                        Delivery Address
                                    </h2>

                                </div>

                            </div>

                            <div class="order-address">

                                <?= nl2br(
                                    e($order['address'])
                                ); ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- =========================================
                         NOTES
                    ========================================== -->

                    <?php if (!empty($order['notes'])): ?>

                        <div class="order-details-card">

                            <div class="order-details-card-header">

                                <div>

                                    <h2>
                                        <i class="bi bi-chat-left-text me-2"></i>
                                        Customer Notes
                                    </h2>

                                </div>

                            </div>

                            <div class="order-notes">

                                <strong>
                                    Special Note
                                </strong>

                                <?= nl2br(
                                    e($order['notes'])
                                ); ?>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- =============================================
                     RIGHT COLUMN
                ============================================== -->

                <div>


                    <!-- =========================================
                         STATUS
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-activity me-2"></i>
                                    Order Status
                                </h2>

                                <p>
                                    Current order progress
                                </p>

                            </div>

                        </div>

                        <div class="order-status-card-body">

                            <div class="order-status-current">

                                <span class="order-status-current-label">
                                    Current Status
                                </span>

                                <span
                                    class="order-detail-status <?= e($orderStatusClass); ?>"
                                >
                                    <?= e($order['order_status']); ?>
                                </span>

                            </div>

                            <a
                                href="edit_order.php?id=<?= (int)$order['order_id']; ?>"
                                class="order-status-update-btn text-decoration-none d-flex align-items-center justify-content-center"
                            >
                                <i class="bi bi-pencil-square me-2"></i>
                                Update Status
                            </a>

                        </div>

                    </div>


                    <!-- =========================================
                         PAYMENT
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-credit-card me-2"></i>
                                    Payment
                                </h2>

                                <p>
                                    Payment information
                                </p>

                            </div>

                        </div>

                        <div class="payment-info">

                            <div class="payment-info-row">

                                <span class="payment-info-label">
                                    Payment Method
                                </span>

                                <span class="payment-info-value">
                                    <?= e(
                                        $order['payment_method']
                                    ); ?>
                                </span>

                            </div>

                            <div class="payment-info-row">

                                <span class="payment-info-label">
                                    Order Payment
                                </span>

                                <span
                                    class="order-payment-status <?= e($paymentStatusClass); ?>"
                                >
                                    <?= e(
                                        $order['payment_status']
                                    ); ?>
                                </span>

                            </div>

                            <?php if ($latestPayment): ?>

                                <div class="payment-info-row">

                                    <span class="payment-info-label">
                                        Recorded Amount
                                    </span>

                                    <span class="payment-info-value">
                                        ₹<?= number_format(
                                            (float)$latestPayment['amount'],
                                            2
                                        ); ?>
                                    </span>

                                </div>

                                <div class="payment-info-row">

                                    <span class="payment-info-label">
                                        Payment Status
                                    </span>

                                    <span class="payment-info-value">
                                        <?= e(
                                            $latestPayment['payment_status']
                                        ); ?>
                                    </span>

                                </div>

                                <div class="payment-info-row">

                                    <span class="payment-info-label">
                                        Payment Date
                                    </span>

                                    <span class="payment-info-value">

                                        <?= e(
                                            date(
                                                "d M Y, h:i A",
                                                strtotime(
                                                    $latestPayment[
                                                        'payment_date'
                                                    ]
                                                )
                                            )
                                        ); ?>

                                    </span>

                                </div>

                                <?php if (
                                    !empty(
                                        $latestPayment['transaction_id']
                                    )
                                ): ?>

                                    <div class="payment-info-row">

                                        <span class="payment-info-label">
                                            Transaction ID
                                        </span>

                                        <span class="payment-info-value">

                                            <?= e(
                                                $latestPayment[
                                                    'transaction_id'
                                                ]
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>

                                <?php if (
                                    !empty(
                                        $latestPayment['razorpay_payment_id']
                                    )
                                ): ?>

                                    <div class="payment-info-row">

                                        <span class="payment-info-label">
                                            Razorpay Payment
                                        </span>

                                        <span class="payment-info-value">

                                            <?= e(
                                                $latestPayment[
                                                    'razorpay_payment_id'
                                                ]
                                            ); ?>

                                        </span>

                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                <div class="text-muted small mt-3">
                                    No payment transaction record found.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- =========================================
                         QUICK TOTAL
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-currency-rupee me-2"></i>
                                    Amount
                                </h2>

                            </div>

                        </div>

                        <div class="order-status-card-body">

                            <div class="text-muted small mb-2">
                                Final payable amount
                            </div>

                            <div class="fs-3 fw-bold">
                                ₹<?= number_format(
                                    (float)$order['grand_total'],
                                    2
                                ); ?>
                            </div>

                        </div>

                    </div>


                    <!-- =========================================
                         ORDER SOURCE
                    ========================================== -->

                    <div class="order-details-card">

                        <div class="order-details-card-header">

                            <div>

                                <h2>
                                    <i class="bi bi-diagram-3 me-2"></i>
                                    Order Channel
                                </h2>

                            </div>

                        </div>

                        <div class="payment-info">

                            <div class="payment-info-row">

                                <span class="payment-info-label">
                                    Source
                                </span>

                                <span class="payment-info-value">
                                    <?= e(
                                        $order['order_source']
                                    ); ?>
                                </span>

                            </div>

                            <div class="payment-info-row">

                                <span class="payment-info-label">
                                    Type
                                </span>

                                <span class="payment-info-value">
                                    <?= e(
                                        $order['order_type']
                                    ); ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

<?php require_once "includes/a-footer.php"; ?>