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
/*=========================================
START TRANSACTION
=========================================*/

try {

    $pdo->beginTransaction();
        $stmt = $pdo->prepare("
    INSERT INTO orders
    (
        user_id,
        customer_name,
        email,
        phone,
        address,
        landmark,
        city,
        pincode,
        subtotal,
        gst,
        delivery_charge,
        total_amount,
        payment_method,
        payment_status,
        order_status,
        order_date
    )
    VALUES
    (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()
    )
    ");

    $stmt->execute([

        $user_id,
        $full_name,
        $email,
        $phone,
        $address,
        $landmark,
        $city,
        $pincode,
        $subtotal,
        $gst,
        $delivery,
        $grandTotal,
        $payment_method,
        "Pending",
        "Pending"

    ]);

    $order_id = $pdo->lastInsertId();
        $itemStmt = $pdo->prepare("
    INSERT INTO order_items
    (
        order_id,
        product_id,
        quantity,
        price,
        total_price
    )
    VALUES
    (
        ?,?,?,?,?
    )
    ");

    foreach($cartItems as $item){

        $lineTotal =
            $item['price_after_discount'] *
            $item['quantity'];

        $itemStmt->execute([

            $order_id,

            $item['product_id'],

            $item['quantity'],

            $item['price_after_discount'],

            $lineTotal

        ]);

    }
        $stockStmt = $pdo->prepare("
    UPDATE products
    SET stock = stock - ?
    WHERE product_id = ?
    ");

    foreach($cartItems as $item){

        $stockStmt->execute([

            $item['quantity'],

            $item['product_id']

        ]);

    }
        $stmt = $pdo->prepare("
    DELETE FROM cart
    WHERE user_id = ?
    ");

    $stmt->execute([$user_id]);
        $pdo->commit();

    $_SESSION['last_order_id'] = $order_id;

    $_SESSION['order_success'] =
        "Order placed successfully.";

    header("Location: order_success.php");

    exit();

} catch (Exception $e) {

    $pdo->rollBack();

    $_SESSION['checkout_error'] =
        "Unable to place order. Please try again.";

    header("Location: checkout.php");

    exit();

}