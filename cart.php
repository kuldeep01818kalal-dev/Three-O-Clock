<?php
session_start();

require_once "config/db.php";

$pageTitle = "Shopping Cart";

/* ============================
   Login Check
============================ */

if (!isset($_SESSION['user_id'])) {

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
