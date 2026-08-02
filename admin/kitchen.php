<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Kitchen Dashboard";

/*=========================================
STATUS FILTER
=========================================*/

$status = trim($_GET['status'] ?? '');

/*=========================================
FETCH ORDERS
=========================================*/

$sql = "
SELECT
    order_id,
    order_number,
    customer_name,
    phone,
    order_source,
    order_type,
    payment_method,
    payment_status,
    order_status,
    grand_total,
    notes,
    ordered_at
FROM orders
WHERE order_status!='Completed'
";

$params = [];

if($status != ''){

    $sql .= " AND order_status=? ";

    $params[] = $status;

}

$sql .= " ORDER BY ordered_at ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
ORDER ITEMS
=========================================*/

$itemStmt = $pdo->prepare("
SELECT

oi.order_id,

oi.quantity,

oi.special_instruction,

p.product_name

FROM order_items oi

INNER JOIN products p

ON oi.product_id=p.product_id

WHERE oi.order_id=?

");

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";

?>

<div class="admin-content">

<div class="container-fluid">

<div class="page-header">

<h2>

<i class="bi bi-fire me-2"></i>

Kitchen Dashboard

</h2>

<p>

Manage live kitchen orders.

</p>

</div>
<?php

/*=========================================
KITCHEN STATISTICS
=========================================*/

$pending = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE order_status='Pending'
")->fetchColumn();

$preparing = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE order_status='Preparing'
")->fetchColumn();

$ready = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE order_status='Ready'
")->fetchColumn();

$today = $pdo->query("
SELECT COUNT(*)
FROM orders
WHERE DATE(ordered_at)=CURDATE()
")->fetchColumn();

?>
<div class="row mb-4">

<div class="col-lg-3 col-md-6 mb-3">

<div class="dashboard-card border-start border-warning border-5">

<div class="card-body">

<h3><?= $pending; ?></h3>

<p>Pending Orders</p>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-3">

<div class="dashboard-card border-start border-info border-5">

<div class="card-body">

<h3><?= $preparing; ?></h3>

<p>Preparing</p>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-3">

<div class="dashboard-card border-start border-success border-5">

<div class="card-body">

<h3><?= $ready; ?></h3>

<p>Ready</p>

</div>

</div>

</div>

<div class="col-lg-3 col-md-6 mb-3">

<div class="dashboard-card border-start border-primary border-5">

<div class="card-body">

<h3><?= $today; ?></h3>

<p>Today's Orders</p>

</div>

</div>

</div>

</div>
<div class="card shadow-sm border-0 mb-4">

<div class="card-body">

<div class="d-flex flex-wrap gap-2">

<a href="kitchen.php"

class="btn <?= empty($status) ? 'btn-dark' : 'btn-outline-dark'; ?>">

All

</a>

<a href="kitchen.php?status=Pending"

class="btn <?= $status=='Pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">

Pending

</a>

<a href="kitchen.php?status=Preparing"

class="btn <?= $status=='Preparing' ? 'btn-info text-white' : 'btn-outline-info'; ?>">

Preparing

</a>

<a href="kitchen.php?status=Ready"

class="btn <?= $status=='Ready' ? 'btn-success' : 'btn-outline-success'; ?>">

Ready

</a>

</div>

</div>

</div>
<div class="row">

<?php foreach($orders as $order): ?>

<?php

$itemStmt->execute([$order['order_id']]);

$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$statusClass="pending-card";

switch($order['order_status']){

    case "Preparing":
        $statusClass="preparing-card";
        break;

    case "Ready":
        $statusClass="ready-card";
        break;

    case "Completed":
        $statusClass="completed-card";
        break;

}

?>

<div class="col-lg-4 col-md-6 mb-4">

<div class="kitchen-card <?= $statusClass; ?>">

<div class="kitchen-header">

<div>

<h4>

<?= htmlspecialchars($order['order_number']); ?>

</h4>

<small>

<?= date("d M Y h:i A",strtotime($order['ordered_at'])); ?>

</small>

</div>

<span class="status-badge">

<?= htmlspecialchars($order['order_status']); ?>

</span>

</div>

<hr>

<div class="customer-info">

<p>

<strong>Customer</strong><br>

<?= htmlspecialchars($order['customer_name']); ?>

</p>

<p>

<strong>Phone</strong><br>

<?= htmlspecialchars($order['phone']); ?>

</p>

</div>

<div class="order-info">

<div>

<strong>Source</strong><br>

<?= htmlspecialchars($order['order_source']); ?>

</div>

<div>

<strong>Type</strong><br>

<?= htmlspecialchars($order['order_type']); ?>

</div>

</div>

<hr>

<h6>

Ordered Items

</h6>

<ul class="item-list">

<?php foreach($items as $item): ?>

<li>

<?= htmlspecialchars($item['product_name']); ?>

<span>

×<?= $item['quantity']; ?>

</span>

</li>

<?php endforeach; ?>

</ul>

<?php if(!empty($order['notes'])): ?>

<div class="notes-box">

<strong>Chef Notes</strong>

<p>

<?= nl2br(htmlspecialchars($order['notes'])); ?>

</p>

</div>

<?php endif; ?>

<div class="price-row">

<h4>

₹<?= number_format((float)$order['grand_total'],2); ?>

</h4>

</div>

<div class="kitchen-actions">

<a

href="edit_order.php?id=<?= $order['order_id']; ?>"

class="btn btn-dark w-100">

Update Status

</a>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

