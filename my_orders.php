<?php
declare(strict_types=1);

session_start();

require_once "config/db.php";

$pageTitle = "My Orders";

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
FETCH ORDERS
=========================================*/

$stmt = $pdo->prepare("
SELECT

order_id,
order_number,
ordered_at,
grand_total,
payment_method,
payment_status,
order_status

FROM orders

WHERE user_id = ?

ORDER BY ordered_at DESC
");

$stmt->execute([$user_id]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<section class="orders-section py-5">

<div class="container">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

<i class="bi bi-bag-check-fill me-2"></i>

My Orders

</h2>

<p class="text-muted">

Track all your previous orders.

</p>

</div>

<a
href="menu.php"
class="btn btn-success">

<i class="bi bi-plus-circle me-2"></i>

Order Again

</a>

</div>

<?php if(empty($orders)): ?>

<div class="alert alert-info">

You haven't placed any orders yet.

</div>

<?php else: ?>
<div class="row mb-5">

    <div class="col-md-4">
        <div class="stat-card">
            <h3><?= count($orders); ?></h3>
            <p>Total Orders</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card warning">
            <h3>
                <?= count(array_filter($orders,function($o){
                    return $o['order_status']=="Pending";
                })); ?>
            </h3>
            <p>Pending Orders</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card success">
            <h3>
                <?= count(array_filter($orders,function($o){
                    return $o['order_status']=="Completed";
                })); ?>
            </h3>
            <p>Completed</p>
        </div>
    </div>

</div>
<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>Order No.</th>

<th>Date</th>

<th>Total</th>

<th>Payment</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php foreach($orders as $order): ?>

<?php

$statusColor = "secondary";

switch($order['order_status']){

case "Pending":

$statusColor="warning";

break;

case "Preparing":

$statusColor="info";

break;

case "Ready":

$statusColor="primary";

break;

case "Out for Delivery":

$statusColor="dark";

break;

case "Completed":

$statusColor="success";

break;

case "Cancelled":

$statusColor="danger";

break;

}

?>

<tr>
<td data-label="Order No.">

<strong>
<?= htmlspecialchars($order['order_number']); ?>
</strong>

</td>

<td>

<?= date("d M Y, h:i A", strtotime($order['ordered_at'])); ?>

</td>

<td>

₹<?= number_format((double)$order['grand_total'], 2); ?>
</td>

<td>

<?= htmlspecialchars($order['payment_method']); ?>

</td>

<td>

<span class="badge bg-<?= $statusColor; ?>">

<?= htmlspecialchars($order['order_status']); ?>

</span>

</td>

<td>

<a
href="order_details.php?id=<?= $order['order_id']; ?>"
class="btn btn-dark btn-sm">

<i class="bi bi-eye"></i>

View

</a>

<?php if($order['order_status']=="Pending"): ?>

<a
href="cancel_order.php?id=<?= $order['order_id']; ?>"
class="btn btn-outline-danger btn-sm ms-1"
onclick="return confirm('Cancel this order?');">

Cancel

</a>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>
</section>


<?php require_once "includes/footer.php"; ?>