<?php
declare(strict_types=1);

session_start();

require_once "config/db.php";

/*=========================================
LOGIN CHECK
=========================================*/

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = "checkout.php";

    header("Location: login.php");

    exit();

}

$user_id = (int)$_SESSION['user_id'];

/*=========================================
REQUEST CHECK
=========================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: checkout.php");

    exit();

}

/*=========================================
GET FORM DATA
=========================================*/

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$landmark = trim($_POST['landmark'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');

$payment_method = trim($_POST['payment_method'] ?? 'Cash');

$notes = trim($_POST['notes'] ?? '');

/*=========================================
VALIDATION
=========================================*/

$errors = [];

if ($full_name === '') {

    $errors[] = "Full Name is required.";

}

if ($email === '') {

    $errors[] = "Email is required.";

}

if ($phone === '') {

    $errors[] = "Mobile Number is required.";

}

if ($address === '') {

    $errors[] = "Delivery Address is required.";

}

if (!empty($errors)) {

    $_SESSION['checkout_errors'] = $errors;

    header("Location: checkout.php");

    exit();

}

/*=========================================
CREATE FULL ADDRESS
=========================================*/

$fullAddress = $address;

if ($landmark !== '') {

    $fullAddress .= "\nLandmark : " . $landmark;

}

if ($city !== '') {

    $fullAddress .= "\nCity : " . $city;

}

if ($pincode !== '') {

    $fullAddress .= "\nPincode : " . $pincode;

}

/*=========================================
GET CART ITEMS
=========================================*/

$stmt = $pdo->prepare("
SELECT

c.cart_id,
c.product_id,
c.quantity,

p.product_name,
p.price,
p.discount_percent,
p.stock

FROM cart c

INNER JOIN products p

ON c.product_id = p.product_id

WHERE c.user_id = ?

ORDER BY c.cart_id ASC
");

$stmt->execute([$user_id]);

$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
EMPTY CART
=========================================*/

if (count($cartItems) === 0) {

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

    if ($discount > 0) {

        $price -= ($price * $discount / 100);

    }

    $item['unit_price'] = $price;

    $item['total_price'] = $price * $item['quantity'];

    $subtotal += $item['total_price'];

}

unset($item);

$tax = round($subtotal * 0.05, 2);

$delivery = ($subtotal >= 500) ? 0 : 40;

$grandTotal = $subtotal + $tax + $delivery;