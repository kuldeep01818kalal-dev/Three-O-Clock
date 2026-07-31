<?php
session_start();

require_once "config/db.php";

/* ======================================================
   Validate Product ID
====================================================== */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    header("Location: menu.php");
    exit();

}

$product_id = (int)$_GET['id'];


/* ======================================================
   Fetch Product
====================================================== */

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

LIMIT 1

");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$product){

    header("Location: menu.php");
    exit();

}


/* ======================================================
   Product Images
====================================================== */

$imageStmt = $pdo->prepare("

SELECT

*

FROM product_images

WHERE product_id=?

ORDER BY

is_primary DESC,
display_order ASC,
image_id ASC

");

$imageStmt->execute([$product_id]);

$images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);


/* ======================================================
   Main Image
====================================================== */

$mainImage = "assets/images/no-image.png";

if(!empty($images)){

    $mainImage =
        "assets/images/products/" .
        $images[0]['image_name'];

}


/* ======================================================
   Discount
====================================================== */

$price = (float)$product['price'];

$discount = (float)$product['discount_percent'];

$finalPrice = $price;

if($discount>0){

    $finalPrice =
        $price -
        (($price*$discount)/100);

}


/* ======================================================
   Reviews
======================================================

Table Required

reviews

--------------------------------

review_id
product_id
user_id
rating
review_title
review
status
created_at

====================================================== */

$reviews = [];

$totalReviews = 0;

$averageRating = 0;

$ratingData = [
    5=>0,
    4=>0,
    3=>0,
    2=>0,
    1=>0
];

try{

    $reviewStmt = $pdo->prepare("

    SELECT

    r.*,
    u.name

    FROM reviews r

    LEFT JOIN users u

    ON u.user_id=r.user_id

    WHERE

    r.product_id=?
    AND r.status='Approved'

    ORDER BY
    r.created_at DESC

    ");

    $reviewStmt->execute([$product_id]);

    $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalReviews = count($reviews);

    if($totalReviews>0){

        $sum = 0;

        foreach($reviews as $row){

            $sum += $row['rating'];

            if(isset($ratingData[$row['rating']])){

                $ratingData[$row['rating']]++;

            }

        }

        $averageRating =
            round($sum/$totalReviews,1);

    }

}catch(Exception $e){

    $reviews=[];

}


/* ======================================================
   Rating Percentage
====================================================== */

$ratingPercent=[];

foreach($ratingData as $star=>$count){

    $ratingPercent[$star]=

    $totalReviews>0

    ?

    round(($count/$totalReviews)*100)

    :

    0;

}


/* ======================================================
   Related Products
====================================================== */

$relatedStmt=$pdo->prepare("

SELECT

p.*,

c.category_name,

pi.image_name

FROM products p

LEFT JOIN categories c

ON c.category_id=p.category_id

LEFT JOIN product_images pi

ON

pi.product_id=p.product_id

AND pi.is_primary=1

WHERE

p.category_id=?

AND p.product_id!=?

AND p.status='Active'

ORDER BY RAND()

LIMIT 4

");

$relatedStmt->execute([

$product['category_id'],

$product_id

]);

$relatedProducts=$relatedStmt->fetchAll(PDO::FETCH_ASSOC);


/* ======================================================
   Product Ingredients

Optional Table

product_ingredients

====================================================== */

$ingredients=[];

try{

    $ingredientStmt=$pdo->prepare("

    SELECT *

    FROM product_ingredients

    WHERE product_id=?

    ORDER BY ingredient_name

    ");

    $ingredientStmt->execute([$product_id]);

    $ingredients=

    $ingredientStmt->fetchAll(PDO::FETCH_ASSOC);

}catch(Exception $e){

    $ingredients=[];

}


/* ======================================================
   Page Title
====================================================== */

$pageTitle=

$product['product_name'];


/* ======================================================
   Include Layout
====================================================== */

require_once "includes/header.php";

require_once "includes/navbar.php";
?>
<!-- =====================================================
     SECTION 2 - PART 1A
     Breadcrumb + Product Gallery
====================================================== -->

<section class="product-hero">

<div class="container">

    <!-- Breadcrumb -->

    <nav class="mb-4" aria-label="breadcrumb">

        <ol class="breadcrumb mb-0">

            <li class="breadcrumb-item">

                <a href="index.php">

                    <i class="bi bi-house-door-fill me-1"></i>

                    Home

                </a>

            </li>

            <li class="breadcrumb-item">

                <a href="menu.php">

                    Menu

                </a>

            </li>

            <li class="breadcrumb-item">

                <a href="menu.php?category=<?= (int)$product['category_id']; ?>">

                    <?= htmlspecialchars($product['category_name']); ?>

                </a>

            </li>

            <li
                class="breadcrumb-item active"
                aria-current="page">

                <?= htmlspecialchars($product['product_name']); ?>

            </li>

        </ol>

    </nav>


    <div class="row g-5 align-items-start">

        <!-- ======================================
             LEFT COLUMN
        ======================================= -->

        <div class="col-lg-6">

            <div class="gallery-card">

                <div class="gallery-image position-relative">

                    <img

                        id="mainProductImage"

                        src="<?= htmlspecialchars($mainImage); ?>"

                        class="img-fluid w-100"

                        alt="<?= htmlspecialchars($product['product_name']); ?>">


                    <!-- Featured -->

                    <?php if(!empty($product['featured'])): ?>

                        <span class="gallery-badge featured">

                            <i class="bi bi-star-fill"></i>

                            Featured

                        </span>

                    <?php endif; ?>


                    <!-- Discount -->

                    <?php if($discount > 0): ?>

                        <span class="gallery-badge discount">

                            <?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

                        </span>

                    <?php endif; ?>


                    <!-- Stock -->

                    <?php if((int)$product['stock'] <= 0): ?>

                        <span class="gallery-badge stock">

                            Out of Stock

                        </span>

                    <?php endif; ?>

                </div>


                <!-- Thumbnails -->

                <?php if(!empty($images)): ?>

                <div class="thumbnail-list mt-4">

                    <?php foreach($images as $index => $img): ?>

                        <?php

                        $thumb="assets/images/products/".$img['image_name'];

                        ?>

                        <img

                            src="<?= htmlspecialchars($thumb); ?>"

                            class="gallery-thumb <?= $index==0 ? 'active-thumb' : ''; ?>"

                            onclick="changeImage(this)"

                            alt="Thumbnail">

                    <?php endforeach; ?>

                </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- RIGHT COLUMN STARTS IN PART 1B -->
         <!-- ==========================================
     RIGHT PRODUCT INFORMATION
========================================== -->

<div class="col-lg-6">

<div class="product-info">

<!-- Category & Food Type -->

<div class="product-badges mb-3">

<span class="badge category-badge">

<i class="bi bi-grid-fill me-1"></i>

<?= htmlspecialchars($product['category_name']); ?>

</span>

<span class="badge food-badge">

<i class="bi bi-circle-fill me-1"></i>

<?= htmlspecialchars($product['food_type']); ?>

</span>

<?php if($discount > 0): ?>

<span class="badge offer-badge">

<?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

</span>

<?php endif; ?>

</div>

<!-- Product Name -->

<h1 class="product-title">

<?= htmlspecialchars($product['product_name']); ?>

</h1>

<!-- Rating -->

<div class="product-rating d-flex align-items-center mb-4">

<div class="rating-stars">

<?php

$displayRating = $averageRating > 0 ? $averageRating : 5;

for($i=1;$i<=5;$i++):

?>

<?php if($i <= floor($displayRating)): ?>

<i class="bi bi-star-fill"></i>

<?php elseif($i - $displayRating < 1): ?>

<i class="bi bi-star-half"></i>

<?php else: ?>

<i class="bi bi-star"></i>

<?php endif; ?>

<?php endfor; ?>

</div>

<span class="rating-value ms-2">

<?= number_format($displayRating,1); ?>

</span>

<span class="rating-count ms-2">

(<?= $totalReviews; ?> Reviews)

</span>

</div>

<!-- Price -->

<div class="price-card mb-4">

<?php if($discount > 0): ?>

<div class="d-flex align-items-center flex-wrap">

<h2 class="current-price mb-0">

₹<?= number_format($finalPrice,2); ?>

</h2>

<span class="old-price ms-3">

₹<?= number_format($price,2); ?>

</span>

<span class="save-badge ms-3">

Save

₹<?= number_format($price-$finalPrice,2); ?>

</span>

</div>

<?php else: ?>

<h2 class="current-price">

₹<?= number_format($price,2); ?>

</h2>

<?php endif; ?>

</div>

<!-- Short Tagline -->

<?php if(!empty($product['short_description'])): ?>

<p class="product-tagline">

<?= htmlspecialchars($product['short_description']); ?>

</p>

<?php endif; ?>
<!-- ==========================================
     PURCHASE PANEL
========================================== -->

<div class="purchase-card">

    <h5 class="quantity-title">

        Quantity

    </h5>

    <form action="cart_action.php" method="POST">

        <input
            type="hidden"
            name="product_id"
            value="<?= $product_id; ?>">

        <div class="quantity-box">

            <button
                type="button"
                class="qty-btn"
                onclick="decreaseQty()">

                −

            </button>

            <input
                type="number"
                id="quantity"
                name="quantity"
                class="qty-input"
                value="1"
                min="1"
                max="<?= (int)$product['stock']; ?>">

            <button
                type="button"
                class="qty-btn"
                onclick="increaseQty()">

                +

            </button>

            <div class="ms-auto">

                <?php if((int)$product['stock']>0): ?>

                    <span class="text-success fw-semibold">

                        <i class="bi bi-check-circle-fill"></i>

                        <?= (int)$product['stock']; ?>

                        Available

                    </span>

                <?php else: ?>

                    <span class="text-danger fw-semibold">

                        <i class="bi bi-x-circle-fill"></i>

                        Out of Stock

                    </span>

                <?php endif; ?>

            </div>

        </div>


        <?php if((int)$product['stock']>0): ?>

            <button
                type="submit"
                name="add_to_cart"
                class="btn-cart">

                <i class="bi bi-cart-plus me-2"></i>

                Add To Cart

            </button>

            <button
                type="submit"
                name="buy_now"
                class="btn-buy mt-3">

                <i class="bi bi-lightning-fill me-2"></i>

                Buy Now

            </button>

        <?php else: ?>

            <button
                class="btn btn-secondary w-100"
                disabled>

                Currently Unavailable

            </button>

        <?php endif; ?>

    </form>

</div>


<!-- ==========================================
     BENEFITS
========================================== -->

<div class="benefits-grid">

    <div class="benefit-card">

        <i class="bi bi-truck"></i>

        <h6>Fast Delivery</h6>

        <p>Fresh food delivered quickly.</p>

    </div>

    <div class="benefit-card">

        <i class="bi bi-shield-check"></i>

        <h6>Quality Assured</h6>

        <p>Prepared using premium ingredients.</p>

    </div>

    <div class="benefit-card">

        <i class="bi bi-arrow-repeat"></i>

        <h6>Easy Reorder</h6>

        <p>Order your favourite meal anytime.</p>

    </div>

    <div class="benefit-card">

        <i class="bi bi-headset"></i>

        <h6>Customer Support</h6>

        <p>We're here to help whenever you need.</p>

    </div>

</div>

</div> <!-- /.product-info -->

</div> <!-- /.col-lg-6 -->

</div> <!-- /.row -->

</div> <!-- /.container -->

</section>