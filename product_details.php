<?php
session_start();

require_once "config/db.php";

/* ==========================================
   Validate Product ID
========================================== */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header("Location: menu.php");
    exit();

}

$product_id = (int)$_GET['id'];

/* ==========================================
   Fetch Product Details
========================================== */

$stmt = $pdo->prepare("

SELECT

p.*,

c.category_name

FROM products p

LEFT JOIN categories c

ON c.category_id = p.category_id

WHERE

p.product_id = ?

AND p.status='Active'

AND p.availability='Available'

LIMIT 1

");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {

    header("Location: menu.php");
    exit();

}

/* ==========================================
   Fetch Product Images
========================================== */

$imageStmt = $pdo->prepare("

SELECT

image_name,
is_primary,
display_order

FROM product_images

WHERE product_id = ?

ORDER BY

is_primary DESC,
display_order ASC,
image_id ASC

");

$imageStmt->execute([$product_id]);

$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   Discount Calculation
========================================== */

$price = (float)$product['price'];

$discount = (float)$product['discount_percent'];

$finalPrice = $price;

if ($discount > 0) {

    $finalPrice = $price - (($price * $discount) / 100);

}

/* ==========================================
   Fetch Related Products
========================================== */

$relatedStmt = $pdo->prepare("

SELECT

p.*,

pi.image_name

FROM products p

LEFT JOIN product_images pi

ON pi.product_id = p.product_id

AND pi.is_primary = 1

WHERE

p.category_id = ?

AND p.product_id != ?

AND p.status='Active'

AND p.availability='Available'

ORDER BY RAND()

LIMIT 4

");

$relatedStmt->execute([
    $product['category_id'],
    $product_id
]);

$relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   Page Title
========================================== */

$pageTitle = $product['product_name'];

/* ==========================================
   Include Header
========================================== */

include "includes/header.php";
?>

<div class="container py-5">

    <div class="row">