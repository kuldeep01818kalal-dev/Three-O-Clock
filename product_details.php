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

require_once "includes/header.php";
require_once "includes/navbar.php";
?>
<!-- ==========================================
     Breadcrumb Section
========================================== -->

<section class="py-3 border-bottom bg-white">

    <div class="container">

        <nav aria-label="breadcrumb">

            <ol class="breadcrumb mb-0">

                <li class="breadcrumb-item">

                    <a
                        href="index.php"
                        class="text-decoration-none">

                        <i class="bi bi-house-door"></i>

                        Home

                    </a>

                </li>

                <li class="breadcrumb-item">

                    <a
                        href="menu.php"
                        class="text-decoration-none">

                        Menu

                    </a>

                </li>

                <li class="breadcrumb-item">

                    <a
                        href="menu.php?category=<?= $product['category_id']; ?>"
                        class="text-decoration-none">

                        <?= htmlspecialchars($product['category_name']); ?>

                    </a>

                </li>

                <li
                    class="breadcrumb-item active fw-semibold"
                    aria-current="page">

                    <?= htmlspecialchars($product['product_name']); ?>

                </li>

            </ol>

        </nav>

    </div>

</section>
<section class="product-details py-5">

<div class="container">

<div class="row g-5">

<!-- ==========================================
     Product Gallery
========================================== -->

<div class="col-lg-6">

<?php

$mainImage = "assets/images/no-image.png";

if(!empty($images)){

    $mainImage = "assets/images/products/".$images[0]['image_name'];

}

?>

<div class="product-gallery">

<div class="main-image position-relative">

<img
id="mainProductImage"
src="<?= htmlspecialchars($mainImage); ?>"
class="img-fluid rounded-4 shadow-sm w-100"
alt="<?= htmlspecialchars($product['product_name']); ?>">

<?php if($product['featured']==1): ?>

<span class="badge bg-warning text-dark featured-badge">

⭐ Featured

</span>

<?php endif; ?>

<?php if($discount>0): ?>

<span class="badge bg-danger discount-badge">

<?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

</span>

<?php endif; ?>

</div>

<?php if(count($images)>1): ?>

<div class="thumbnail-wrapper mt-4">

<?php foreach($images as $img): ?>

<img

src="assets/images/products/<?= htmlspecialchars($img['image_name']); ?>"

class="thumbnail"

onclick="changeImage(this)">

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</div>

<!-- ==========================================
     Product Information
========================================== -->

<div class="col-lg-6">

<div class="sticky-top" style="top:100px;">

<div class="mb-3">

<span class="badge bg-primary">

<?= htmlspecialchars($product['category_name']); ?>

</span>

<span class="badge bg-success">

<?= htmlspecialchars($product['food_type']); ?>

</span>

</div>

<h1 class="display-5 fw-bold mb-3">

<?= htmlspecialchars($product['product_name']); ?>

</h1>

<div class="mb-3">

<span class="text-warning fs-5">

★★★★★

</span>

<span class="text-muted ms-2">

4.8 (120 Reviews)

</span>

</div>

<div class="price-box mb-4">

<?php if($discount>0): ?>

<h2 class="text-success fw-bold">

₹<?= number_format($finalPrice,2); ?>

<small
class="text-decoration-line-through text-muted ms-2">

₹<?= number_format($price,2); ?>

</small>

<span class="badge bg-danger ms-2">

<?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

</span>

</h2>

<?php else: ?>

<h2 class="fw-bold">

₹<?= number_format($price,2); ?>

</h2>

<?php endif; ?>

</div>

<p class="lead text-muted">

<?= htmlspecialchars($product['short_description']); ?>

</p>

<div class="row g-3 mt-4">

<div class="col-6">

<div class="feature-card">

<i class="bi bi-clock"></i>

<h6>Preparation</h6>

<p>

<?= (int)$product['preparation_time']; ?>

Minutes

</p>

</div>

</div>

<div class="col-6">

<div class="feature-card">

<i class="bi bi-fire"></i>

<h6>Spice Level</h6>

<p>

<?= htmlspecialchars($product['spice_level']); ?>

</p>

</div>

</div>

<div class="col-6">

<div class="feature-card">

<i class="bi bi-box-seam"></i>

<h6>Stock</h6>

<p>

<?= (int)$product['stock']; ?>

Available

</p>

</div>

</div>

<div class="col-6">

<div class="feature-card">

<i class="bi bi-check-circle"></i>

<h6>Status</h6>

<p>

<?= htmlspecialchars($product['availability']); ?>

</p>

</div>

</div>

</div>

</div>

</div>

</div>

</div>

</section>






















































