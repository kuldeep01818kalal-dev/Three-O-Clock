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