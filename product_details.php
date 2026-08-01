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
<?php if(isset($_SESSION['cart_success'])): ?>

<div class="container mt-3">

    <div class="alert alert-success alert-dismissible fade show">

        <i class="bi bi-check-circle-fill me-2"></i>

        <?= htmlspecialchars($_SESSION['cart_success']); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

</div>

<?php unset($_SESSION['cart_success']); ?>

<?php endif; ?>

<?php if(isset($_SESSION['cart_error'])): ?>

<div class="container mt-3">

    <div class="alert alert-danger alert-dismissible fade show">

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

        <?= htmlspecialchars($_SESSION['cart_error']); ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert">
        </button>

    </div>

</div>

<?php unset($_SESSION['cart_error']); ?>

<?php endif; ?>
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

                <?php if(count($images) > 1): ?>

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

           <form action="cart_action.php" method="POST">

    <input type="hidden"
           name="product_id"
           value="<?= $product['product_id']; ?>">

    <input type="hidden"
           name="quantity"
           id="cartQty"
           value="1">

    <button type="submit"
            class="btn btn-cart">

        <i class="bi bi-cart-plus"></i>
        Add To Cart

    </button>

</form>
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
<!-- ==========================================
     PRODUCT TABS
========================================== -->

<section class="product-tabs py-5">

<div class="container">

<ul class="nav nav-pills justify-content-center mb-5" id="productTabs">

<li class="nav-item">

<button
class="nav-link active"
data-bs-toggle="pill"
data-bs-target="#description">

Description

</button>

</li>

<li class="nav-item">

<button
class="nav-link"
data-bs-toggle="pill"
data-bs-target="#information">

Additional Information

</button>

</li>

<li class="nav-item">

<button
class="nav-link"
data-bs-toggle="pill"
data-bs-target="#reviews">

Reviews (<?= $totalReviews; ?>)

</button>

</li>

</ul>

<div class="tab-content">

<!-- ===========================
     DESCRIPTION
=========================== -->

<div class="tab-pane fade show active" id="description">

<div class="tab-card">

<h3>Description</h3>

<?php if(!empty($product['description'])): ?>

<p>

<?= nl2br(htmlspecialchars($product['description'])); ?>

</p>

<?php else: ?>

<p>

No description available.

</p>

<?php endif; ?>

</div>

</div>

<!-- ===========================
     INFORMATION
=========================== -->

<div class="tab-pane fade" id="information">

<div class="tab-card">

<h3>Product Information</h3>

<table class="table table-bordered">

<tr>

<th>Category</th>

<td><?= htmlspecialchars($product['category_name']); ?></td>

</tr>

<tr>

<th>Food Type</th>

<td><?= htmlspecialchars($product['food_type']); ?></td>

</tr>

<tr>

<th>Preparation Time</th>

<td><?= htmlspecialchars($product['preparation_time']); ?> Minutes</td>

</tr>

<tr>

<th>Spice Level</th>

<td><?= htmlspecialchars($product['spice_level']); ?></td>

</tr>

<tr>

<th>Availability</th>

<td><?= htmlspecialchars($product['availability']); ?></td>

</tr>

<tr>

<th>Stock</th>

<td><?= (int)$product['stock']; ?></td>

</tr>

</table>

</div>

</div>

<!-- ===========================
     REVIEWS
=========================== -->

<div class="tab-pane fade" id="reviews">

<div class="tab-card">

<h3>Customer Reviews</h3>

<?php if($totalReviews>0): ?>

<?php foreach($reviews as $review): ?>

<div class="review-box">

<div class="d-flex justify-content-between">

<h5>

<?= htmlspecialchars($review['name']); ?>

</h5>

<div>

<?php

for($i=1;$i<=5;$i++):

?>

<?php if($i <= $review['rating']): ?>

<i class="bi bi-star-fill text-warning"></i>

<?php else: ?>

<i class="bi bi-star text-warning"></i>

<?php endif; ?>

<?php endfor; ?>

</div>

</div>

<?php if(!empty($review['review_title'])): ?>

<h6 class="mt-2">

<?= htmlspecialchars($review['review_title']); ?>

</h6>

<?php endif; ?>

<p>

<?= nl2br(htmlspecialchars($review['review'])); ?>

</p>

<small class="text-muted">

<?= date("d M Y",strtotime($review['created_at'])); ?>

</small>

</div>

<hr>

<?php endforeach; ?>

<?php else: ?>

<div class="text-center py-4">

<i class="bi bi-chat-square-text fs-1 text-muted"></i>

<h5 class="mt-3">

No Reviews Yet

</h5>

<p class="text-muted">

Be the first customer to review this product.

</p>

</div>

<?php endif; ?>

</div>

</div>

</div>

</div>

</section>
<!-- ==========================================
     RELATED PRODUCTS
========================================== -->

<section class="related-products py-5">

<div class="container">

<div class="section-heading text-center mb-5">

<h2>You May Also Like</h2>

<p class="text-muted">

Explore more delicious dishes from the same category.

</p>

</div>

<div class="row g-4">

<?php if(!empty($relatedProducts)): ?>

<?php foreach($relatedProducts as $item): ?>

<?php

$image = !empty($item['image_name'])

? "assets/images/products/".$item['image_name']

: "assets/images/no-image.png";

$price = (float)$item['price'];

$discount = (float)$item['discount_percent'];

$finalPrice = $price;

if($discount>0){

$finalPrice = $price-(($price*$discount)/100);

}

?>

<div class="col-lg-3 col-md-6">

<div class="related-card h-100">

<div class="related-image">

<img
src="<?= htmlspecialchars($image); ?>"
alt="<?= htmlspecialchars($item['product_name']); ?>">

<?php if($discount>0): ?>

<span class="discount-badge">

<?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

</span>

<?php endif; ?>

</div>

<div class="related-content">

<span class="category-name">

<?= htmlspecialchars($item['category_name']); ?>

</span>

<h5>

<?= htmlspecialchars($item['product_name']); ?>

</h5>

<div class="price-area">

<span class="current-price">

₹<?= number_format($finalPrice,2); ?>

</span>

<?php if($discount>0): ?>

<span class="old-price">

₹<?= number_format($price,2); ?>

</span>

<?php endif; ?>

</div>

<div class="d-grid gap-2 mt-3">

<a
href="product_details.php?id=<?= $item['product_id']; ?>"
class="btn btn-outline-dark">

View Details

</a>

<a
href="cart_action.php?product_id=<?= $item['product_id']; ?>"
class="btn btn-success">

<i class="bi bi-cart-plus"></i>

Add To Cart

</a>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="col-12 text-center">

<h5>No Related Products Found.</h5>

</div>

<?php endif; ?>

</div>

</div>

</section>
<!-- ==========================================
     WHY CHOOSE US
========================================== -->

<section class="why-choose py-5">

<div class="container">

<div class="text-center mb-5">

<h2 class="fw-bold">

Why Choose Three O' Clock Cafe?

</h2>

<p class="text-muted">

Freshly prepared meals, premium ingredients, and an unforgettable dining experience.

</p>

</div>

<div class="row g-4">

<div class="col-lg-3 col-md-6">

<div class="feature-box">

<div class="feature-icon">

<i class="bi bi-truck"></i>

</div>

<h5>Fast Delivery</h5>

<p>

Quick doorstep delivery while your food is fresh and hot.

</p>

</div>

</div>

<div class="col-lg-3 col-md-6">

<div class="feature-box">

<div class="feature-icon">

<i class="bi bi-award-fill"></i>

</div>

<h5>Premium Quality</h5>

<p>

Only premium ingredients are used in every dish.

</p>

</div>

</div>

<div class="col-lg-3 col-md-6">

<div class="feature-box">

<div class="feature-icon">

<i class="bi bi-cup-hot-fill"></i>

</div>

<h5>Fresh Coffee</h5>

<p>

Prepared by experienced baristas using quality coffee beans.

</p>

</div>

</div>

<div class="col-lg-3 col-md-6">

<div class="feature-box">

<div class="feature-icon">

<i class="bi bi-heart-fill"></i>

</div>

<h5>Customer First</h5>

<p>

Thousands of happy customers enjoy our food every day.

</p>

</div>

</div>

</div>

</div>

</section>
<script>

function changeImage(image){

document.getElementById("mainProductImage").src=image.src;

document.querySelectorAll(".gallery-thumb").forEach(function(item){

item.classList.remove("active-thumb");

});

image.classList.add("active-thumb");

}

function increaseQty(){

let qty=document.getElementById("quantity");

qty.value=parseInt(qty.value)+1;

}

function decreaseQty(){

let qty=document.getElementById("quantity");

if(parseInt(qty.value)>1){

qty.value=parseInt(qty.value)-1;

}

}

</script>
<?php

require_once "includes/footer.php";

?>