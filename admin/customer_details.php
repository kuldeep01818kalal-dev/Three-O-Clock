<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Customer Details";

$user_id = (int)($_GET['id'] ?? 0);

if($user_id <= 0){

    header("Location: customers.php");

    exit();

}

/*=========================================
CUSTOMER DETAILS
=========================================*/

$stmt = $pdo->prepare("
SELECT *
FROM users
WHERE user_id=?
LIMIT 1
");

$stmt->execute([$user_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$customer){

    $_SESSION['error'] = "Customer not found.";

    header("Location: customers.php");

    exit();

}

/*=========================================
ORDER STATISTICS
=========================================*/

$stmt = $pdo->prepare("
SELECT

COUNT(order_id) total_orders,

COALESCE(SUM(grand_total),0) total_spent,

MAX(ordered_at) last_order

FROM orders

WHERE user_id=?
");

$stmt->execute([$user_id]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

/*=========================================
LAST 5 ORDERS
=========================================*/

$stmt = $pdo->prepare("
SELECT

order_id,
order_number,
grand_total,
order_status,
payment_status,
ordered_at

FROM orders

WHERE user_id=?

ORDER BY ordered_at DESC

LIMIT 5
");

$stmt->execute([$user_id]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>
<div class="card shadow border-0 mb-4">

    <div class="card-header bg-primary text-white">

        <h5 class="mb-0">

            <i class="bi bi-person-fill me-2"></i>

            Customer Information

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6 mb-3">

                <strong>Full Name</strong>

                <p><?= htmlspecialchars($customer['full_name']); ?></p>

            </div>

            <div class="col-md-6 mb-3">

                <strong>Email</strong>

                <p><?= htmlspecialchars($customer['email']); ?></p>

            </div>

            <div class="col-md-6 mb-3">

                <strong>Phone</strong>

                <p><?= htmlspecialchars($customer['phone']); ?></p>

            </div>

            <div class="col-md-6 mb-3">

                <strong>Joined On</strong>

                <p>

                    <?= !empty($customer['created_at'])
                        ? date("d M Y", strtotime($customer['created_at']))
                        : "-"; ?>

                </p>

            </div>

            <div class="col-12">

                <strong>Address</strong>

                <p>

                    <?= !empty($customer['address'])
                        ? nl2br(htmlspecialchars($customer['address']))
                        : "No address available"; ?>

                </p>

            </div>

        </div>

    </div>

</div>
<div class="card shadow border-0">

<div class="card-header bg-primary text-white">

<h5 class="mb-0">

Recent Orders

</h5>

</div>

<div class="table-responsive">

<table class="table table-hover mb-0">

<thead>

<tr>

<th>Order</th>

<th>Date</th>

<th>Total</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php if(count($orders)>0): ?>

<?php foreach($orders as $order): ?>

<tr>

<td>

<?= htmlspecialchars($order['order_number']); ?>

</td>

<td>

<?= date("d M Y",strtotime($order['ordered_at'])); ?>

</td>

<td>

₹<?= number_format((float)$order['grand_total'],2); ?>

</td>

<td>

<span class="badge bg-primary">

<?= htmlspecialchars($order['order_status']); ?>

</span>

</td>

<td>

<a
href="view_order.php?id=<?= $order['order_id']; ?>"
class="btn btn-dark btn-sm">

<i class="bi bi-eye-fill"></i>

</a>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td colspan="5" class="text-center py-4">

No Orders Found

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>
<div class="mt-4">

<a
href="customers.php"
class="btn btn-secondary">

<i class="bi bi-arrow-left"></i>

Back to Customers

</a>

</div>

<?php include "includes/a-footer.php"; ?>