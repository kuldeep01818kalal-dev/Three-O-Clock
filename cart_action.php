<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/db.php';

/*======================================================
=            LOGIN CHECK
======================================================*/

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? 'menu.php';

    header("Location: login.php");

    exit();

}

$user_id = (int)$_SESSION['user_id'];

/*======================================================
=            VALIDATE REQUEST
======================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: menu.php");

    exit();

}

/*======================================================
=            GET FORM DATA
======================================================*/

$product_id = filter_input(
    INPUT_POST,
    'product_id',
    FILTER_VALIDATE_INT
);

$quantity = filter_input(
    INPUT_POST,
    'quantity',
    FILTER_VALIDATE_INT
);

if (!$product_id || !$quantity || $quantity < 1) {

    $_SESSION['cart_error'] = "Invalid product request.";

    header("Location: menu.php");

    exit();

}

/*======================================================
=            FETCH PRODUCT
======================================================*/

$stmt = $pdo->prepare("
    SELECT
        product_id,
        product_name,
        price,
        stock,
        availability,
        status
    FROM products
    WHERE product_id = ?
    LIMIT 1
");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

/*======================================================
=            PRODUCT VALIDATION
======================================================*/

if (!$product) {

    $_SESSION['cart_error'] = "Product not found.";

    header("Location: menu.php");

    exit();

}

if ($product['status'] !== 'Active') {

    $_SESSION['cart_error'] = "Product is unavailable.";

    header("Location: product_details.php?id=" . $product_id);

    exit();

}

if ($product['availability'] !== 'Available') {

    $_SESSION['cart_error'] = "Currently out of stock.";

    header("Location: product_details.php?id=" . $product_id);

    exit();

}

if ($product['stock'] < $quantity) {

    $_SESSION['cart_error'] = "Only {$product['stock']} item(s) available.";

    header("Location: product_details.php?id=" . $product_id);

    exit();

}

/*******************************************************
 * Part 1 Ends Here
 * Part 2:
 * Check Existing Cart Item
 * Insert / Update Quantity
 *******************************************************/