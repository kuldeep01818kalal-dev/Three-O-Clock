<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';

/*=========================================
LOGIN CHECK
=========================================*/

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}

$user_id = (int)$_SESSION['user_id'];

/*=========================================
POST ONLY
=========================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: checkout.php");
    exit();

}

/*=========================================
CUSTOMER DETAILS
=========================================*/

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');
$landmark = trim($_POST['landmark'] ?? '');

$payment_method = $_POST['payment_method'] ?? 'COD';

if (
    $full_name == '' ||
    $phone == '' ||
    $address == '' ||
    $city == ''
) {

    $_SESSION['checkout_error'] =
        "Please fill all required fields.";

    header("Location: checkout.php");
    exit();

}
/*=========================================
FETCH CART
=========================================*/

$stmt = $pdo->prepare("
SELECT

c.product_id,
c.quantity,

p.product_name,
p.price,
p.discount_percent,
p.stock

FROM cart c

INNER JOIN products p

ON p.product_id = c.product_id

WHERE c.user_id = ?
");

$stmt->execute([$user_id]);

$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($cartItems) == 0) {

    $_SESSION['cart_error'] = "Your cart is empty.";

    header("Location: cart.php");

    exit();

}
$subtotal = 0;

foreach ($cartItems as &$item) {

    if ($item['quantity'] > $item['stock']) {

        $_SESSION['checkout_error'] =
            $item['product_name'] . " is out of stock.";

        header("Location: cart.php");
        exit();

    }

    $price = (float)$item['price'];

    if ($item['discount_percent'] > 0) {

        $price -=
            ($price * $item['discount_percent']) / 100;

    }

    $item['price_after_discount'] = $price;

    $subtotal +=
        ($price * $item['quantity']);

}

$gst = round($subtotal * 0.05, 2);

$delivery = ($subtotal >= 500) ? 0 : 40;

$grandTotal =

$subtotal +

$gst +

$delivery;