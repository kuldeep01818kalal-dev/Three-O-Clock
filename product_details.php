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
<!-- ==========================================
     Product Information
========================================== -->

<h1 class="fw-bold mb-3">

    <?= htmlspecialchars($product['product_name']); ?>

</h1>

<div class="mb-3">

    <span class="badge bg-primary me-2">

        <?= htmlspecialchars($product['category_name']); ?>

    </span>

    <?php

    switch($product['food_type']){

        case "Veg":

            echo '<span class="badge bg-success">Veg</span>';

        break;

        case "Non-Veg":

            echo '<span class="badge bg-danger">Non-Veg</span>';

        break;

        case "Egg":

            echo '<span class="badge bg-warning text-dark">Egg</span>';

        break;

        default:

            echo '<span class="badge bg-secondary">N/A</span>';

    }

    ?>

</div>

<!-- ==========================================
     Price
========================================== -->

<div class="mb-4">

<?php if($discount > 0): ?>

<h2 class="text-success fw-bold mb-1">

    ₹<?= number_format($finalPrice,2); ?>

</h2>

<h5 class="text-muted text-decoration-line-through">

    ₹<?= number_format($price,2); ?>

</h5>

<span class="badge bg-danger fs-6">

    <?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

</span>

<?php else: ?>

<h2 class="fw-bold">

    ₹<?= number_format($price,2); ?>

</h2>

<?php endif; ?>

</div>

<!-- ==========================================
     Description
========================================== -->

<h5 class="fw-bold">

    Description

</h5>

<p class="text-muted mb-4">

    <?= nl2br(htmlspecialchars($product['description'])); ?>

</p>

<!-- ==========================================
     Product Details
========================================== -->

<div class="card border-0 shadow-sm mb-4">

    <div class="card-body">

        <div class="row">

            <div class="col-6 mb-3">

                <strong>

                    <i class="bi bi-clock"></i>

                    Preparation

                </strong>

                <br>

                <?= (int)$product['preparation_time']; ?> Minutes

            </div>

            <div class="col-6 mb-3">

                <strong>

                    <i class="bi bi-fire"></i>

                    Spice Level

                </strong>

                <br>

                <?= htmlspecialchars($product['spice_level']); ?>

            </div>

            <div class="col-6">

                <strong>

                    <i class="bi bi-box"></i>

                    Stock

                </strong>

                <br>

                <?php if($product['stock'] > 0): ?>

                    <span class="badge bg-success">

                        <?= (int)$product['stock']; ?> Available

                    </span>

                <?php else: ?>

                    <span class="badge bg-danger">

                        Out Of Stock

                    </span>

                <?php endif; ?>

            </div>

            <div class="col-6">

                <strong>

                    <i class="bi bi-check-circle"></i>

                    Availability

                </strong>

                <br>

                <?php if($product['availability']=="Available"): ?>

                    <span class="badge bg-success">

                        Available

                    </span>

                <?php else: ?>

                    <span class="badge bg-secondary">

                        Unavailable

                    </span>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>
<!-- ==========================================
     Quantity & Add To Cart
========================================== -->

<form
    action="cart.php"
    method="GET"
    class="mb-4">

    <input
        type="hidden"
        name="action"
        value="add">

    <input
        type="hidden"
        name="id"
        value="<?= $product['product_id']; ?>">

    <label class="form-label fw-bold">

        Quantity

    </label>

    <div class="d-flex align-items-center mb-3">

        <button
            type="button"
            class="btn btn-outline-secondary"
            onclick="decreaseQty()">

            <i class="bi bi-dash-lg"></i>

        </button>

        <input
            type="number"
            id="quantity"
            name="qty"
            class="form-control text-center mx-2"
            value="1"
            min="1"
            max="<?= max(1,$product['stock']); ?>"
            style="width:90px;">

        <button
            type="button"
            class="btn btn-outline-secondary"
            onclick="increaseQty()">

            <i class="bi bi-plus-lg"></i>

        </button>

    </div>

    <div class="d-grid gap-2">

        <?php if($product['stock'] > 0): ?>

        <button
            type="submit"
            class="btn btn-primary btn-lg">

            <i class="bi bi-cart-plus-fill"></i>

            Add To Cart

        </button>

        <a
            href="checkout.php?id=<?= $product['product_id']; ?>"
            class="btn btn-success btn-lg">

            <i class="bi bi-lightning-charge-fill"></i>

            Buy Now

        </a>

        <?php else: ?>

        <button
            class="btn btn-danger btn-lg"
            disabled>

            Out Of Stock

        </button>

        <?php endif; ?>

    </div>

</form>

<!-- ==========================================
     Product Information
========================================== -->

<div class="card border-0 shadow-sm mb-4">

    <div class="card-header bg-light">

        <strong>

            Product Information

        </strong>

    </div>

    <div class="card-body">

        <table class="table table-borderless mb-0">

            <tr>

                <th width="140">

                    Product ID

                </th>

                <td>

                    #<?= $product['product_id']; ?>

                </td>

            </tr>

            <tr>

                <th>

                    Category

                </th>

                <td>

                    <?= htmlspecialchars($product['category_name']); ?>

                </td>

            </tr>

            <tr>

                <th>

                    Food Type

                </th>

                <td>

                    <?= htmlspecialchars($product['food_type']); ?>

                </td>

            </tr>

            <tr>

                <th>

                    Preparation

                </th>

                <td>

                    <?= (int)$product['preparation_time']; ?> Minutes

                </td>

            </tr>

            <tr>

                <th>

                    Spice Level

                </th>

                <td>

                    <?= htmlspecialchars($product['spice_level']); ?>

                </td>

            </tr>

            <tr>

                <th>

                    Slug

                </th>

                <td>

                    <?= htmlspecialchars($product['slug']); ?>

                </td>

            </tr>

        </table>

    </div>

</div>

<!-- ==========================================
     Share Buttons
========================================== -->

<div class="mb-4">

    <h5 class="mb-3">

        Share Product

    </h5>

    <a
        href="#"
        class="btn btn-outline-primary me-2">

        <i class="bi bi-facebook"></i>

    </a>

    <a
        href="#"
        class="btn btn-outline-info me-2">

        <i class="bi bi-twitter-x"></i>

    </a>

    <a
        href="#"
        class="btn btn-outline-success me-2">

        <i class="bi bi-whatsapp"></i>

    </a>

    <a
        href="#"
        class="btn btn-outline-danger">

        <i class="bi bi-instagram"></i>

    </a>

</div>

<a
    href="menu.php"
    class="btn btn-outline-dark">

    <i class="bi bi-arrow-left"></i>

    Back To Menu

</a>

</div>

</div>

<script>

function increaseQty(){

    let qty=document.getElementById("quantity");

    let max=parseInt(qty.max);

    let value=parseInt(qty.value);

    if(value<max){

        qty.value=value+1;

    }

}

function decreaseQty(){

    let qty=document.getElementById("quantity");

    let value=parseInt(qty.value);

    if(value>1){

        qty.value=value-1;

    }

}

</script>
























































<script>

function changeImage(img){

    document.getElementById("mainProductImage").src = img.src;

}

</script>