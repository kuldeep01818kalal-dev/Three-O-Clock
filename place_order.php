<?php
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
/*=========================================
START TRANSACTION
=========================================*/

try {

    $pdo->beginTransaction();

    /*=========================================
    GENERATE ORDER NUMBER
    =========================================*/

    $stmt = $pdo->query("
    SELECT MAX(order_id) AS last_id
    FROM orders
    ");

    $lastOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextId = (int)($lastOrder['last_id'] ?? 0) + 1;

    $orderNumber = sprintf("TOC-%06d", $nextId);

    /*=========================================
    INSERT ORDER
    =========================================*/

    $stmt = $pdo->prepare("
    INSERT INTO orders
    (
        order_number,
        user_id,
        customer_name,
        phone,
        email,
        address,
        order_source,
        order_type,
        table_id,
        subtotal,
        discount,
        tax,
        delivery_charge,
        grand_total,
        payment_status,
        order_status,
        payment_method,
        notes,
        ordered_at
    )
    VALUES
    (
        ?,?,?,?,?,?,
        ?,?,
        ?,
        ?,?,?,?,?,
        ?,?,?,?,
        NOW()
    )
    ");
    $stmt->execute([

        $orderNumber,

        $user_id,

        $full_name,

        $phone,

        $email,

        $fullAddress,

        "Website",

        "Delivery",

        null,

        $subtotal,

        $discount,

        $tax,

        $delivery,

        $grandTotal,

        ($payment_method == "ONLINE") ? "Pending" : "Pending",

        "Pending",

        $payment_method,

        $notes

    ]);

    $order_id = (int)$pdo->lastInsertId();
    /*=========================================
INSERT ORDER ITEMS
=========================================*/

$itemStmt = $pdo->prepare("
INSERT INTO order_items
(
    order_id,
    product_id,
    quantity,
    unit_price,
    total_price,
    special_instruction,
    created_at
)
VALUES
(
    ?, ?, ?, ?, ?, ?, NOW()
)
");

foreach ($cartItems as $item) {

    $lineTotal =

        $item['unit_price'] *

        $item['quantity'];

    $itemStmt->execute([

        $order_id,

        $item['product_id'],

        $item['quantity'],

        $item['unit_price'],

        $lineTotal,

        $notes

    ]);

}
/*=========================================
UPDATE PRODUCT STOCK
=========================================*/

$stockStmt = $pdo->prepare("
UPDATE products
SET stock = stock - ?
WHERE product_id = ?
");

foreach ($cartItems as $item) {

    $stockStmt->execute([

        $item['quantity'],

        $item['product_id']

    ]);

}

/*=========================================
CLEAR USER CART
=========================================*/

$deleteCart = $pdo->prepare("
DELETE FROM cart
WHERE user_id = ?
");

$deleteCart->execute([$user_id]);

/*=========================================
COMMIT TRANSACTION
=========================================*/

$pdo->commit();

/*=========================================
SUCCESS SESSION
=========================================*/

$_SESSION['last_order_id'] = $order_id;

$_SESSION['order_number'] = $orderNumber;

$_SESSION['success_message'] =
"Your order has been placed successfully.";

/*=========================================
REDIRECT
=========================================*/

header("Location: order_success.php");

exit();

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<h3>Database Error</h3>";

    echo "<strong>Message:</strong><br>";
    echo $e->getMessage();

    echo "<br><br>";

    echo "<strong>File:</strong><br>";
    echo $e->getFile();

    echo "<br><br>";

    echo "<strong>Line:</strong><br>";
    echo $e->getLine();

    exit();



    $_SESSION['checkout_error'] =
    "Unable to place your order. Please try again.";

    // Uncomment while developing
    // die($e->getMessage());

    header("Location: checkout.php");

    exit();

}