<?php
session_start();

require_once "config/db.php";

$pageTitle = "Checkout";

/*=========================================
LOGIN CHECK
=========================================*/

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = "checkout.php";

    header("Location: login.php");

    exit();

}

$user_id = $_SESSION['user_id'];

/*=========================================
GET CUSTOMER DETAILS
=========================================*/

$stmt = $pdo->prepare("
SELECT
user_id,
full_name,
email,
phone,
address,
city,
state,
pincode
FROM users
WHERE user_id = ?
LIMIT 1
");

$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

/*=========================================
GET CART ITEMS
=========================================*/

$stmt = $pdo->prepare("
SELECT

c.cart_id,
c.quantity,

p.product_id,
p.product_name,
p.price,
p.discount_percent,
p.stock,

pi.image_name

FROM cart c

INNER JOIN products p

ON p.product_id = c.product_id

LEFT JOIN product_images pi

ON pi.product_id = p.product_id
AND pi.is_primary = 1

WHERE c.user_id = ?
");

$stmt->execute([$user_id]);

$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
EMPTY CART
=========================================*/

if (count($cartItems) == 0) {

    $_SESSION['cart_error'] = "Your cart is empty.";

    header("Location: cart.php");

    exit();

}

/*=========================================
CALCULATE TOTAL
=========================================*/

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

$gst = round($subtotal * 0.05, 2);

$delivery = ($subtotal >= 500) ? 0 : 40;

$grandTotal =

$subtotal +

$gst +

$delivery;

/*=========================================
LAYOUT
=========================================*/

require_once "includes/header.php";
require_once "includes/navbar.php";
?>