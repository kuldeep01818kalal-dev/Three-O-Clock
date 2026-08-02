<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";

$pageTitle="Update Order";

/*=============================
ADMIN LOGIN
==============================*/

if(!isset($_SESSION['admin_id'])){

    header("Location: login.php");

    exit();

}

$order_id=(int)($_GET['id'] ?? 0);

if($order_id<=0){

    header("Location: orders.php");

    exit();

}

/*=============================
GET ORDER
==============================*/

$stmt=$pdo->prepare("
SELECT *
FROM orders
WHERE order_id=?
LIMIT 1
");

$stmt->execute([$order_id]);

$order=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$order){

    header("Location: orders.php");

    exit();

}
if($_SERVER["REQUEST_METHOD"]=="POST"){

    $order_status=$_POST['order_status'];

    $payment_status=$_POST['payment_status'];

    $stmt=$pdo->prepare("
    UPDATE orders

    SET

    order_status=?,
    payment_status=?,
    updated_at=NOW()

    WHERE order_id=?
    ");

    $stmt->execute([

        $order_status,

        $payment_status,

        $order_id

    ]);

    $_SESSION['success']="Order updated successfully.";

    header("Location:view_order.php?id=".$order_id);

    exit();

}
require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-content">

<div class="container-fluid">

<div class="page-header">

<h2>

<i class="bi bi-pencil-square me-2"></i>

Update Order

</h2>

</div>
<div class="table-card p-4">

<form method="POST">

<div class="mb-4">

<label class="form-label">

Order Status

</label>

<select
name="order_status"
class="form-select">

<?php

$statusList=[

"Pending",

"Preparing",

"Ready",

"Out for Delivery",

"Completed",

"Cancelled"

];

foreach($statusList as $status):

?>

<option

value="<?= $status; ?>"

<?= $order['order_status']==$status ? "selected":""; ?>

>

<?= $status; ?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="mb-4">

<label class="form-label">

Payment Status

</label>

<select
name="payment_status"
class="form-select">

<?php

$payment=[

"Pending",

"Paid",

"Failed",

"Refunded"

];

foreach($payment as $pay):

?>

<option

value="<?= $pay; ?>"

<?= $order['payment_status']==$pay ? "selected":""; ?>

>

<?= $pay; ?>

</option>

<?php endforeach; ?>

</select>

</div>

<button
class="btn btn-success">

<i class="bi bi-check-circle-fill me-2"></i>

Update Order

</button>

<a
href="view_order.php?id=<?= $order_id; ?>"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>
</div>

</div>

<?php require_once "includes/a-footer.php"; ?>