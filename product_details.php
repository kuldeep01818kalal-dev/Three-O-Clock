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
<!-- ==========================================
     Quantity Selector
========================================== -->

<form action="cart.php" method="GET">

<input type="hidden" name="action" value="add">

<input type="hidden"
name="id"
value="<?= $product['product_id']; ?>">

<div class="mt-4">

<h5 class="fw-semibold mb-3">

Quantity

</h5>

<div class="d-flex align-items-center">

<button
type="button"
class="qty-btn"
onclick="decreaseQty()">

<i class="bi bi-dash-lg"></i>

</button>

<input

type="number"

id="quantity"

name="qty"

value="1"

min="1"

max="<?= max(1,$product['stock']); ?>"

class="qty-input">

<button
type="button"
class="qty-btn"
onclick="increaseQty()">

<i class="bi bi-plus-lg"></i>

</button>

<span class="ms-4 text-muted">

Max :

<?= (int)$product['stock']; ?>

</span>

</div>

</div>

<!-- ==========================================
     Buttons
========================================== -->

<div class="d-grid gap-3 mt-4">

<button
type="submit"
class="btn btn-cart">

<i class="bi bi-cart-plus-fill"></i>

Add To Cart

</button>

<a
href="checkout.php?id=<?= $product['product_id']; ?>"
class="btn btn-buy">

<i class="bi bi-lightning-charge-fill"></i>

Buy Now

</a>

</div>

</form>

<!-- ==========================================
     Product Benefits
========================================== -->

<div class="row g-3 mt-4">

<div class="col-6">

<div class="benefit-box">

<i class="bi bi-flower1"></i>

Fresh Ingredients

</div>

</div>

<div class="col-6">

<div class="benefit-box">

<i class="bi bi-shield-check"></i>

Hygienically Prepared

</div>

</div>

<div class="col-6">

<div class="benefit-box">

<i class="bi bi-truck"></i>

Fast Delivery

</div>

</div>

<div class="col-6">

<div class="benefit-box">

<i class="bi bi-award"></i>

Premium Quality

</div>

</div>

</div>

<!-- ==========================================
     Product Tabs
========================================== -->

<div class="card border-0 shadow-sm rounded-4 mt-5">

<div class="card-body">

<ul
class="nav nav-tabs border-0"
id="productTabs">

<li class="nav-item">

<button

class="nav-link active"

data-bs-toggle="tab"

data-bs-target="#description">

Description

</button>

</li>

<li class="nav-item">

<button

class="nav-link"

data-bs-toggle="tab"

data-bs-target="#ingredients">

Ingredients

</button>

</li>

<li class="nav-item">

<button

class="nav-link"

data-bs-toggle="tab"

data-bs-target="#nutrition">

Nutrition

</button>

</li>

</ul>

<div class="tab-content mt-4">

<div
class="tab-pane fade show active"
id="description">

<?= nl2br(htmlspecialchars($product['description'])); ?>

</div>

<div
class="tab-pane fade"
id="ingredients">

<p class="text-muted">

Coming Soon

</p>

</div>

<div
class="tab-pane fade"
id="nutrition">

<p class="text-muted">

Coming Soon

</p>

</div>

</div>

</div>

</div>





















































