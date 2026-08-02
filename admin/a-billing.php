<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Billing";

/*=========================================
FETCH CATEGORIES
=========================================*/

$stmt = $pdo->query("
SELECT
category_id,
category_name
FROM categories
WHERE status='Active'
ORDER BY category_name
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
FETCH PRODUCTS
=========================================*/

$stmt = $pdo->query("
SELECT

product_id,
product_name,
price,
discount_percent,
stock,

category_id

FROM products

WHERE status='Active'

ORDER BY product_name
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
FETCH TABLES
=========================================*/

$stmt = $pdo->query("
SELECT

table_id,
table_name

FROM cafe_tables

WHERE status='Available'

ORDER BY table_name
");

$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-content">

<div class="container-fluid">

<div class="page-header">

<h2>

<i class="bi bi-receipt-cutoff me-2"></i>

Billing / POS

</h2>

<p>

Create Walk-In, Dine-In and Takeaway Orders.

</p>

</div>
<div class="row">

<div class="col-lg-7">

<div class="col-lg-7">

<div class="card shadow-sm border-0 rounded-4">

<div class="card-header bg-dark text-white">

<h4 class="mb-0">

<i class="bi bi-cup-hot-fill me-2"></i>

Products

</h4>

</div>

<div class="card-body">

<!-- Search -->

<div class="row mb-3">

<div class="col-md-8">

<input
type="text"
id="productSearch"
class="form-control"
placeholder="Search Product...">

</div>

<div class="col-md-4">

<select
id="categoryFilter"
class="form-select">

<option value="">

All Categories

</option>

<?php foreach($categories as $category): ?>

<option
value="<?= $category['category_id']; ?>">

<?= htmlspecialchars($category['category_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

<!-- Products -->

<div class="row g-3" id="productContainer">

<?php foreach($products as $product): ?>

<?php

$price = (float)$product['price'];

$discount = (float)$product['discount_percent'];

$finalPrice = $price;

if($discount > 0){

    $finalPrice =

        $price -

        (($price * $discount)/100);

}

?>

<div
class="col-lg-4 col-md-6 product-card"

data-name="<?= strtolower($product['product_name']); ?>"

data-category="<?= $product['category_id']; ?>">

<div class="card h-100 product-item shadow-sm">

<div class="card-body text-center">

<h6>

<?= htmlspecialchars($product['product_name']); ?>

</h6>

<?php if($discount>0): ?>

<small class="text-decoration-line-through text-muted">

₹<?= number_format($price,2); ?>

</small>

<br>

<?php endif; ?>

<h5 class="text-success">

₹<?= number_format($finalPrice,2); ?>

</h5>

<?php if($product['stock']>0): ?>

<button
class="btn btn-success btn-sm mt-2 addToCart"

data-id="<?= $product['product_id']; ?>"

data-name="<?= htmlspecialchars($product['product_name']); ?>"

data-price="<?= $finalPrice; ?>">

<i class="bi bi-plus-circle"></i>

Add

</button>

<?php else: ?>

<button
class="btn btn-secondary btn-sm mt-2"
disabled>

Out of Stock

</button>

<?php endif; ?>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

</div>

<div class="col-lg-5">

<!-- Cart Area -->

</div>

</div>
</div>

</div>
<script>

const search = document.getElementById("productSearch");

const category = document.getElementById("categoryFilter");

function filterProducts(){

let keyword = search.value.toLowerCase();

let cat = category.value;

document.querySelectorAll(".product-card").forEach(card=>{

let name = card.dataset.name;

let categoryId = card.dataset.category;

let show = true;

if(keyword && !name.includes(keyword))
show = false;

if(cat && categoryId !== cat)
show = false;

card.style.display = show ? "" : "none";

});

}

search.addEventListener("keyup",filterProducts);

category.addEventListener("change",filterProducts);

</script>
<?php require_once "includes/a-footer.php"; ?>