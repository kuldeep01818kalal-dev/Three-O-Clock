<?php
session_start();

require_once __DIR__ . '/config/db.php';

/*=========================================
CHECK LOGIN
=========================================*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

$user_id = $_SESSION['user_id'];

/*=========================================
POST REQUEST ONLY
=========================================*/

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    header("Location: cart.php");
    exit();

}

/*=========================================
GET DATA
=========================================*/

$cart_id  = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$cart_id || !$quantity || $quantity < 1) {

    $_SESSION['cart_error'] = "Invalid quantity.";

    header("Location: cart.php");
    exit();

}

/*=========================================
GET PRODUCT STOCK
=========================================*/

$stmt = $pdo->prepare("
SELECT
cart.product_id,
products.stock
FROM cart
INNER JOIN products
ON cart.product_id = products.product_id
WHERE cart.cart_id = ?
AND cart.user_id = ?
LIMIT 1
");

$stmt->execute([$cart_id, $user_id]);

$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {

    $_SESSION['cart_error'] = "Cart item not found.";

    header("Location: cart.php");
    exit();

}

if ($quantity > $item['stock']) {

    $_SESSION['cart_error'] =
        "Only {$item['stock']} items available.";

    header("Location: cart.php");
    exit();

}

/*=========================================
UPDATE QUANTITY
=========================================*/

$stmt = $pdo->prepare("
UPDATE cart
SET quantity=?
WHERE cart_id=?
AND user_id=?
");

$stmt->execute([
    $quantity,
    $cart_id,
    $user_id
]);

$_SESSION['cart_success'] =
    "Cart updated successfully.";

header("Location: cart.php");
exit();