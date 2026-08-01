<?php
declare(strict_types=1);

session_start();

require_once "config/db.php";

$pageTitle = "Order Details";

/*=========================================
LOGIN CHECK
=========================================*/

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = "my_orders.php";

    header("Location: login.php");

    exit();

}

$user_id = (int)$_SESSION['user_id'];

/*=========================================
ORDER ID CHECK
=========================================*/

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {

    header("Location: my_orders.php");

    exit();

}

/*=========================================
FETCH ORDER
=========================================*/

$stmt = $pdo->prepare("
SELECT *

FROM orders

WHERE order_id = ?

AND user_id = ?

LIMIT 1
");

$stmt->execute([

    $order_id,

    $user_id

]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {

    $_SESSION['order_error'] = "Order not found.";

    header("Location: my_orders.php");

    exit();

}

/*=========================================
FETCH ORDER ITEMS
=========================================*/

$stmt = $pdo->prepare("
SELECT

oi.item_id,
oi.quantity,
oi.unit_price,
oi.total_price,

p.product_id,
p.product_name,

pi.image_name

FROM order_items oi

INNER JOIN products p

ON oi.product_id = p.product_id

LEFT JOIN product_images pi

ON pi.product_id = p.product_id

AND pi.is_primary = 1

WHERE oi.order_id = ?

ORDER BY oi.item_id ASC
");

$stmt->execute([$order_id]);

$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
LAYOUT
=========================================*/

require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<section class="order-details-section py-5">

<div class="container">

<div class="row">

<div class="col-lg-12">

<h2 class="fw-bold mb-2">

<i class="bi bi-receipt-cutoff me-2"></i>

Order Details

</h2>

<p class="text-muted">

Track your order and view complete purchase information.

</p>

</div>

</div>
<div class="row g-4 mb-4">

    <!-- ================================
         ORDER INFORMATION
    ================================= -->

    <div class="col-lg-8">

        <div class="order-card">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <div>

                    <h3 class="mb-2">

                        <i class="bi bi-receipt-cutoff me-2"></i>

                        <?= htmlspecialchars($order['order_number']); ?>

                    </h3>

                    <p class="text-muted mb-0">

                        <i class="bi bi-calendar-event me-2"></i>

                        <?= date("d M Y • h:i A", strtotime($order['ordered_at'])); ?>

                    </p>

                </div>

                <div>

                    <?php

                    $statusColor = "secondary";

                    switch($order['order_status']){

                        case "Pending":
                            $statusColor = "warning";
                            break;

                        case "Preparing":
                            $statusColor = "info";
                            break;

                        case "Ready":
                            $statusColor = "primary";
                            break;

                        case "Out for Delivery":
                            $statusColor = "dark";
                            break;

                        case "Completed":
                            $statusColor = "success";
                            break;

                        case "Cancelled":
                            $statusColor = "danger";
                            break;

                    }

                    ?>

                    <span class="badge bg-<?= $statusColor; ?> px-3 py-2">

                        <?= htmlspecialchars($order['order_status']); ?>

                    </span>

                </div>

            </div>

            <hr>

            <div class="row">

                <div class="col-md-6 mb-3">

                    <h6>

                        <i class="bi bi-person-fill me-2"></i>

                        Customer

                    </h6>

                    <p class="mb-1">

                        <?= htmlspecialchars($order['customer_name']); ?>

                    </p>

                    <small class="text-muted">

                        <?= htmlspecialchars($order['phone']); ?>

                    </small>

                    <br>

                    <small class="text-muted">

                        <?= htmlspecialchars($order['email']); ?>

                    </small>

                </div>

                <div class="col-md-6 mb-3">

                    <h6>

                        <i class="bi bi-geo-alt-fill me-2"></i>

                        Delivery Address

                    </h6>

                    <p class="mb-0">

                        <?= nl2br(htmlspecialchars($order['address'])); ?>

                    </p>

                </div>

            </div>

        </div>

    </div>

    <!-- ================================
         PAYMENT INFORMATION
    ================================= -->

    <div class="col-lg-4">

        <div class="payment-card">

            <h5 class="mb-4">

                <i class="bi bi-credit-card-2-front me-2"></i>

                Payment Details

            </h5>

            <div class="payment-row">

                <span>Method</span>

                <strong>

                    <?= htmlspecialchars($order['payment_method']); ?>

                </strong>

            </div>

            <div class="payment-row">

                <span>Payment</span>

                <span class="badge bg-success">

                    <?= htmlspecialchars($order['payment_status']); ?>

                </span>

            </div>

            <div class="payment-row">

                <span>Total Amount</span>

                <strong class="text-success">

                    ₹<?= number_format((float)$order['grand_total'],2); ?>

                </strong>

            </div>

        </div>

    </div>

</div>
<!-- =========================================
     ORDER ITEMS
========================================= -->

<div class="order-card mt-4">

    <h4 class="mb-4">

        <i class="bi bi-bag-fill me-2"></i>

        Ordered Items

    </h4>

    <?php foreach($orderItems as $item): ?>

    <div class="product-item">

        <div class="row align-items-center">

            <!-- Product Image -->

            <div class="col-md-2 col-4">

                <?php

                $image = !empty($item['image_name'])
                    ? "uploads/products/".$item['image_name']
                    : "assets/images/no-image.png";

                ?>

                <img
                    src="<?= $image; ?>"
                    class="product-image"
                    alt="<?= htmlspecialchars($item['product_name']); ?>">

            </div>

            <!-- Product Info -->

            <div class="col-md-5 col-8">

                <h5 class="product-name">

                    <?= htmlspecialchars($item['product_name']); ?>

                </h5>

                <p class="text-muted mb-0">

                    ₹<?= number_format((float)$item['unit_price'],2); ?>

                    ×

                    <?= (int)$item['quantity']; ?>

                </p>

            </div>

            <!-- Quantity -->

            <div class="col-md-2 text-center">

                <span class="qty-badge">

                    Qty

                    <?= (int)$item['quantity']; ?>

                </span>

            </div>

            <!-- Total -->

            <div class="col-md-3 text-end">

                <h5 class="item-total">

                    ₹<?= number_format((float)$item['total_price'],2); ?>

                </h5>

            </div>

        </div>

    </div>

    <?php endforeach; ?>

</div>

<!-- =========================================
     BILL SUMMARY
========================================= -->

<div class="row mt-4">

    <div class="col-lg-5 ms-auto">

        <div class="summary-card">

            <h4 class="mb-4">

                Bill Summary

            </h4>

            <div class="summary-row">

                <span>Subtotal</span>

                <span>

                    ₹<?= number_format((float)$order['subtotal'],2); ?>

                </span>

            </div>

            <div class="summary-row">

                <span>GST</span>

                <span>

                    ₹<?= number_format((float)$order['tax'],2); ?>

                </span>

            </div>

            <div class="summary-row">

                <span>Delivery Charge</span>

                <span>

                    <?=
                    ((float)$order['delivery_charge'] == 0)
                    ? "FREE"
                    : "₹".number_format((float)$order['delivery_charge'],2);
                    ?>

                </span>

            </div>

            <hr>

            <div class="summary-total">

                <h5>

                    Grand Total

                </h5>

                <h4>

                    ₹<?= number_format((float)$order['grand_total'],2); ?>

                </h4>

            </div>

        </div>

    </div>

</div>