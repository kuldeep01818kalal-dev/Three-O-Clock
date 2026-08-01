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
GET CART ID
=========================================*/

$cart_id = filter_input(INPUT_GET, 'cart_id', FILTER_VALIDATE_INT);

if (!$cart_id) {

    $_SESSION['cart_error'] = "Invalid cart item.";

    header("Location: cart.php");
    exit();

}

/*=========================================
DELETE ITEM
=========================================*/

$stmt = $pdo->prepare("
DELETE FROM cart
WHERE cart_id=?
AND user_id=?
");

$stmt->execute([
    $cart_id,
    $user_id
]);

$_SESSION['cart_success'] =
    "Item removed from cart.";

header("Location: cart.php");
exit();