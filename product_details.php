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
        <!-- ==========================================
     Product Image Gallery
========================================== -->

<div class="col-lg-6 mb-4">

<?php

$mainImage = "assets/images/no-image.png";

if(!empty($images)){

    $mainImage = "assets/images/products/".$images[0]['image_name'];

}

?>

<div class="position-relative">

    <img
        id="mainProductImage"
        src="<?= htmlspecialchars($mainImage); ?>"
        alt="<?= htmlspecialchars($product['product_name']); ?>"
        class="img-fluid rounded shadow w-100"
        style="height:500px;object-fit:cover;">

    <?php if($product['featured']==1): ?>

    <span
        class="badge bg-warning text-dark position-absolute top-0 start-0 m-3 fs-6">

        ⭐ Featured

    </span>

    <?php endif; ?>

    <?php if($discount>0): ?>

    <span
        class="badge bg-danger position-absolute top-0 end-0 m-3 fs-6">

        <?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

    </span>

    <?php endif; ?>

</div>

<!-- ==========================================
     Thumbnail Gallery
========================================== -->

<?php if(count($images)>1): ?>

<div class="row mt-3">

<?php foreach($images as $img): ?>

<div class="col-3 mb-3">

<img
    src="assets/images/products/<?= htmlspecialchars($img['image_name']); ?>"
    class="img-fluid rounded border thumbnail-image"
    style="height:90px;object-fit:cover;cursor:pointer;"
    onclick="changeImage(this)">

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

<!-- ==========================================
     Product Information
========================================== -->

<div class="col-lg-6">