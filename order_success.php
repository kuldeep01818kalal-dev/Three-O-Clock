<?php
session_start();

require_once "config/db.php";

$pageTitle = "Order Successful";

/*=========================================
CHECK ORDER
=========================================*/

if (
    !isset($_SESSION['last_order_id']) ||
    !isset($_SESSION['user_id'])
) {

    header("Location: index.php");
    exit();

}

$order_id = (int)$_SESSION['last_order_id'];

$stmt = $pdo->prepare("
SELECT *
FROM orders
WHERE order_id = ?
AND user_id = ?
LIMIT 1
");

$stmt->execute([
    $order_id,
    $_SESSION['user_id']
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {

    header("Location: index.php");
    exit();

}

require_once "includes/header.php";
require_once "includes/navbar.php";
?>
<section class="success-section py-5">

<div class="container">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="success-card text-center">

<div class="success-icon">

<i class="bi bi-check-circle-fill"></i>

</div>

<h2 class="mt-4">

Order Placed Successfully 🎉

</h2>

<p class="text-muted">

Thank you for ordering from

<strong>Three O' Clock Cafe</strong>

</p>

<hr>

<div class="row text-start mt-4">

<div class="col-md-6">

<p>

<strong>Order ID</strong>

<br>

#<?= $order['order_id']; ?>

</p>

<p>

<strong>Customer</strong>

<br>

<?= htmlspecialchars($order['customer_name']); ?>

</p>

<p>

<strong>Phone</strong>

<br>

<?= htmlspecialchars($order['phone']); ?>

</p>

</div>

<div class="col-md-6">

<p>

<strong>Payment</strong>

<br>

<?= htmlspecialchars($order['payment_method']); ?>

</p>

<p>

<strong>Status</strong>

<br>

<span class="badge bg-warning">

<?= htmlspecialchars($order['order_status']); ?>

</span>

</p>

<p>

<strong>Total</strong>

<br>

₹<?= number_format($order['total_amount'],2); ?>

</p>

</div>

</div>

<hr>

<div class="d-grid gap-3 d-md-flex justify-content-center">

<a
href="menu.php"
class="btn btn-success">

Continue Shopping

</a>

<a
href="my_orders.php"
class="btn btn-outline-dark">

My Orders

</a>

</div>

</div>

</div>

</div>

</div>

</section>

<?php

unset($_SESSION['last_order_id']);

require_once "includes/footer.php";

?>