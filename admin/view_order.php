<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";

$pageTitle = "View Order";

/*=========================================
ADMIN LOGIN CHECK
=========================================*/

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

/*=========================================
ORDER ID
=========================================*/

$order_id = (int)($_GET['id'] ?? 0);

if($order_id<=0){

    header("Location: orders.php");

    exit();

}

/*=========================================
ORDER DETAILS
=========================================*/

$stmt=$pdo->prepare("
SELECT *
FROM orders
WHERE order_id=?
LIMIT 1
");

$stmt->execute([$order_id]);

$order=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$order){

    $_SESSION['error']="Order not found.";

    header("Location: orders.php");

    exit();

}

/*=========================================
ORDER ITEMS
=========================================*/

$stmt=$pdo->prepare("
SELECT

oi.*,

p.product_name,

pi.image_name

FROM order_items oi

INNER JOIN products p

ON oi.product_id=p.product_id

LEFT JOIN product_images pi

ON pi.product_id=p.product_id

AND pi.is_primary=1

WHERE oi.order_id=?

ORDER BY oi.item_id ASC
");

$stmt->execute([$order_id]);

$orderItems=$stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-content">

<div class="container-fluid">

<div class="page-header">

<h2>

<i class="bi bi-eye-fill me-2"></i>

Order Details

</h2>

<p>

Complete customer order information.

</p>

</div>
<div class="row g-4">

<div class="col-lg-8">

<div class="table-card p-4">

<h4 class="mb-4">

Order Information

</h4>

<div class="row">

<div class="col-md-6 mb-3">

<strong>Order Number</strong>

<p><?= htmlspecialchars($order['order_number']); ?></p>

</div>

<div class="col-md-6 mb-3">

<strong>Order Date</strong>

<p>

<?= date("d M Y h:i A",strtotime($order['ordered_at'])); ?>

</p>

</div>

<div class="col-md-6 mb-3">

<strong>Customer</strong>

<p>

<?= htmlspecialchars($order['customer_name']); ?>

</p>

</div>

<div class="col-md-6 mb-3">

<strong>Phone</strong>

<p>

<?= htmlspecialchars($order['phone']); ?>

</p>

</div>

<div class="col-md-6">

<strong>Email</strong>

<p>

<?= htmlspecialchars($order['email']); ?>

</p>

</div>

<div class="col-md-6">

<strong>Payment</strong>

<p>

<?= htmlspecialchars($order['payment_method']); ?>

</p>

</div>

</div>

</div>

</div>
<div class="col-lg-4">

<div class="table-card p-4">

<h4>

Status

</h4>

<hr>

<p>

<strong>Order Status</strong>

</p>

<span class="badge bg-warning">

<?= htmlspecialchars($order['order_status']); ?>

</span>

<hr>

<p>

<strong>Payment Status</strong>

</p>

<span class="badge bg-success">

<?= htmlspecialchars($order['payment_status']); ?>

</span>

<hr>

<h3 class="text-success">

₹<?= number_format((float)$order['grand_total'],2); ?>

</h3>

<p class="text-muted">

Grand Total

</p>

</div>

</div>

</div>
<div class="table-card mt-4">

<div class="p-4">

<h4>

Ordered Products

</h4>

</div>

<div class="table-responsive">

<table class="table align-middle">

<thead>

<tr>

<th>Image</th>

<th>Product</th>

<th>Qty</th>

<th>Price</th>

<th>Total</th>

</tr>

</thead>

<tbody>

<?php foreach($orderItems as $item): ?>

<tr>

<td>

<?php

$image = "../assets/images/menu/pizza.jpg";

if (!empty($item['image_name'])) {

    if (file_exists("../assets/images/menu/".$item['image_name'])) {

        $image = "../assets/images/menu/".$item['image_name'];

    }
}

?>

<img
src="<?= $image; ?>"
class="img-thumbnail"
style="width:70px;height:70px;object-fit:cover;border-radius:10px;">

</td>

<td>

<?= htmlspecialchars($item['product_name']); ?>

</td>

<td>

<?= (int)$item['quantity']; ?>

</td>

<td>

₹<?= number_format((float)$item['unit_price'],2); ?>

</td>

<td>

<strong>

₹<?= number_format((float)$item['total_price'],2); ?>

</strong>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>
<div class="text-end mt-4">

<a
href="orders.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back

</a>

<a
href="edit_order.php?id=<?= $order['order_id']; ?>"
class="btn btn-warning">

<i class="bi bi-pencil-square"></i>

Update Status

</a>

</div>

</div>

</div>

<?php require_once "includes/a-footer.php"; ?>