<?php
session_start();

require_once "config/db.php";

$pageTitle = "Shopping Cart";

/* ============================
   Login Check
============================ */

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];

    header("Location: login.php");

    exit();

}

$user_id = $_SESSION['user_id'];

/* ============================
   Fetch Cart Products
============================ */

$stmt = $pdo->prepare("

SELECT

c.cart_id,
c.quantity,

p.product_id,
p.product_name,
p.price,
p.discount_percent,
p.availability,
p.stock,

pi.image_name

FROM cart c

INNER JOIN products p

ON p.product_id = c.product_id

LEFT JOIN product_images pi

ON pi.product_id = p.product_id

AND pi.is_primary = 1

WHERE c.user_id = ?

ORDER BY c.cart_id DESC

");

$stmt->execute([$user_id]);

$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   Calculate Totals
============================ */

$subtotal = 0;

foreach ($cartItems as &$item) {

    $price = (float)$item['price'];

    $discount = (float)$item['discount_percent'];

    $finalPrice = $price;

    if ($discount > 0) {

        $finalPrice = $price - (($price * $discount) / 100);

    }

    $item['final_price'] = $finalPrice;

    $item['item_total'] =

        $finalPrice * $item['quantity'];

    $subtotal += $item['item_total'];

}

/* ============================
   Charges
============================ */

$gst = round($subtotal * 0.05,2);

$delivery = ($subtotal >= 500 || $subtotal == 0)

? 0

: 40;

$grandTotal =

$subtotal +

$gst +

$delivery;

/* ============================
   Includes
============================ */

require_once "includes/header.php";

require_once "includes/navbar.php";

?>
<!-- ==========================================
     SHOPPING CART
========================================== -->

<section class="cart-section py-5">

<div class="container">

<div class="row">

<!-- =========================
     LEFT SIDE
========================= -->

<div class="col-lg-8">

<h2 class="fw-bold mb-4">

<i class="bi bi-cart3 me-2"></i>

Shopping Cart

</h2>

<?php if(count($cartItems)>0): ?>

<div class="cart-table">

<?php foreach($cartItems as $item): ?>

<?php

$image = !empty($item['image_name'])

? "assets/images/products/".$item['image_name']

: "assets/images/no-image.png";

?>

<div class="cart-item">

<div class="row align-items-center">

<!-- Image -->

<div class="col-md-2">

<img

src="<?= htmlspecialchars($image); ?>"

class="cart-image"

alt="<?= htmlspecialchars($item['product_name']); ?>">

</div>

<!-- Product -->

<div class="col-md-4">

<h5 class="mb-2">

<?= htmlspecialchars($item['product_name']); ?>

</h5>

<p class="text-muted mb-1">

₹<?= number_format($item['final_price'],2); ?>

</p>

<?php if($item['discount_percent']>0): ?>

<small class="text-success">

<?= $item['discount_percent']; ?>% OFF

</small>

<?php endif; ?>

</div>

<!-- Quantity -->

<div class="col-md-3">

<form action="update_cart.php" method="POST">

    <input
        type="hidden"
        name="cart_id"
        value="<?= $item['cart_id']; ?>">

    <div class="qty-box">

        <button
            type="button"
            onclick="decreaseQty(this)">

            -

        </button>

        <input
            type="number"
            name="quantity"
            value="<?= $item['quantity']; ?>"
            min="1"
            max="<?= $item['stock']; ?>">

        <button
            type="button"
            onclick="increaseQty(this)">

            +

        </button>

    </div>

    <button
        class="btn btn-update"
        type="submit">

        Update

    </button>

</form>

<!-- Total -->

<div class="col-md-2 text-center">

<strong>

₹<?= number_format($item['item_total'],2); ?>

</strong>

</div>

<!-- Remove -->

<a
href="remove_cart.php?cart_id=<?= $item['cart_id']; ?>"
class="btn btn-danger"
onclick="return confirm('Remove this item?')">

<i class="bi bi-trash"></i>

</a>
</div>

</div>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="empty-cart">

<i class="bi bi-cart-x display-1 text-muted"></i>

<h3 class="mt-3">

Your Cart is Empty

</h3>

<p>

Looks like you haven't added anything yet.

</p>

<a

href="menu.php"

class="btn btn-success">

Continue Shopping

</a>

</div>

<?php endif; ?>

</div>

<!-- =========================
     ORDER SUMMARY
========================= -->

<div class="col-lg-4">

<div class="summary-card">

<h4 class="mb-4">

Order Summary

</h4>

<div class="summary-row">

<span>Subtotal</span>

<span>

₹<?= number_format($subtotal,2); ?>

</span>

</div>

<div class="summary-row">

<span>GST (5%)</span>

<span>

₹<?= number_format($gst,2); ?>

</span>

</div>

<div class="summary-row">

<span>Delivery</span>

<span>

<?= $delivery==0

? "FREE"

: "₹".number_format($delivery,2); ?>

</span>

</div>

<hr>

<div class="summary-total">

<span>Total</span>

<strong>

₹<?= number_format($grandTotal,2); ?>

</strong>

</div>

<div class="d-grid gap-3 mt-4">

<a

href="menu.php"

class="btn btn-outline-secondary">

Continue Shopping

</a>

<?php if(count($cartItems)>0): ?>

<a

href="checkout.php"

class="btn btn-success">

Proceed To Checkout

</a>

<?php endif; ?>

</div>

</div>

</div>

</div>

</div>

</section>

<?php

require_once "includes/footer.php";

?>