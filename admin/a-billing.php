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
p.product_id,
p.product_name,
p.price,
p.discount_percent,
p.stock,
p.category_id,
pi.image_name

FROM products p

LEFT JOIN product_images pi
ON p.product_id = pi.product_id
AND pi.is_primary = 1

WHERE p.status='Active'

ORDER BY p.product_name
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
FETCH TABLES
=========================================*/
$stmt = $pdo->query("
SELECT
table_id,
table_number,
capacity,
location
FROM cafe_tables
WHERE status='Available'
ORDER BY table_number
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
class="col-xl-3 col-lg-4 col-md-6 mb-4 product-card"

data-name="<?= strtolower($product['product_name']); ?>"

data-category="<?= $product['category_id']; ?>">



<div class="card product-card-new shadow-sm">



<div class="card-body">

<h6>

<?= htmlspecialchars($product['product_name']); ?>

</h6>

<div class="price-area">

<?php if($discount>0): ?>

<small class="old-price">

₹<?= number_format($price,2); ?>

</small>

<?php endif; ?>

<h5>

₹<?= number_format($finalPrice,2); ?>

</h5>

</div>

<div class="stock">

Stock :

<?= (int)$product['stock']; ?>

</div>

<button

class="btn btn-success w-100 mt-3 addToCart"

data-id="<?= $product['product_id']; ?>"

data-name="<?= htmlspecialchars($product['product_name']); ?>"

data-price="<?= $finalPrice; ?>">

<i class="bi bi-plus-circle-fill"></i>

Add to Bill

</button>

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

<div class="card shadow-sm border-0 rounded-4">

<div class="card-header bg-success text-white">

<h4 class="mb-0">

<i class="bi bi-cart-fill me-2"></i>

Current Bill

</h4>

</div>

<div class="card-body">

<div id="cartItems">

<div class="empty-cart text-center text-muted py-5">

<i class="bi bi-cart-x display-4"></i>

<p class="mt-3">

No Products Added

</p>

</div>

</div>

<hr>

<div class="d-flex justify-content-between">

<strong>Subtotal</strong>

<strong id="subtotal">

₹0.00

</strong>

</div>

<div class="d-flex justify-content-between mt-2">

<strong>GST (5%)</strong>

<strong id="gst">

₹0.00

</strong>

</div>

<hr>

<div class="d-flex justify-content-between">

<h4>

Grand Total

</h4>

<h4 class="text-success" id="grandTotal">

₹0.00

</h4>

</div>

<hr>

<div class="mb-3">

<label class="form-label">

Payment Method

</label>

<select
class="form-select"
id="paymentMethod">

<option value="Cash">Cash</option>

<option value="UPI">UPI</option>

<option value="Card">Card</option>

</select>

</div>

<div class="mb-3">

<label class="form-label">

Order Type

</label>

<select
class="form-select"
id="orderType">

<option value="Walk-In">

Walk-In

</option>

<option value="Dine-In">

Dine-In

</option>

<option value="Takeaway">

Takeaway

</option>

<option value="Delivery">

Delivery

</option>

</select>

</div>

<div
id="tableSection"
style="display:none;">

<label class="form-label">

Select Table

</label>

<select class="form-select">

<?php foreach($tables as $table): ?>

<option value="<?= $table['table_id']; ?>">

<?= htmlspecialchars($table['table_number']); ?>

(<?= $table['capacity']; ?> Seats)

</option>

<?php endforeach; ?>

</select>
</div>

<button
class="btn btn-success w-100 py-3 mt-4">

<i class="bi bi-receipt"></i>

Generate Bill

</button>

</div>

</div>

</div>

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

document
.getElementById("orderType")
.addEventListener("change",function(){

document.getElementById("tableSection").style.display=

this.value==="Dine-In"

?

"block"

:

"none";

});
</script>
<script>

let cart = {};

const gstRate = 5;

function renderCart(){

const cartBox = document.getElementById("cartItems");

let html = "";

let subtotal = 0;

let count = 0;

for(const id in cart){

count++;

const item = cart[id];

subtotal += item.price * item.qty;

html += `

<div class="cart-item mb-3">

<div class="d-flex justify-content-between">

<div>

<strong>${item.name}</strong>

<br>

<small>₹${item.price.toFixed(2)}</small>

</div>

<div class="text-end">

<div class="btn-group">

<button class="btn btn-sm btn-outline-secondary"

onclick="changeQty(${id},-1)">

-

</button>

<button class="btn btn-sm btn-light">

${item.qty}

</button>

<button class="btn btn-sm btn-outline-secondary"

onclick="changeQty(${id},1)">

+

</button>

</div>

<br>

<button

class="btn btn-link text-danger p-0 mt-2"

onclick="removeItem(${id})">

Remove

</button>

</div>

</div>

</div>

`;

}

if(count===0){

html = `

<div class="empty-cart text-center text-muted py-5">

<i class="bi bi-cart-x display-4"></i>

<p class="mt-3">

No Products Added

</p>

</div>

`;

}

cartBox.innerHTML = html;

const gst = subtotal * gstRate / 100;

const total = subtotal + gst;

document.getElementById("subtotal").innerHTML =

"₹"+subtotal.toFixed(2);

document.getElementById("gst").innerHTML =

"₹"+gst.toFixed(2);

document.getElementById("grandTotal").innerHTML =

"₹"+total.toFixed(2);

}

document.querySelectorAll(".addToCart").forEach(btn=>{

btn.addEventListener("click",function(){

const id = this.dataset.id;

const name = this.dataset.name;

const price = parseFloat(this.dataset.price);

if(cart[id]){

cart[id].qty++;

}else{

cart[id]={

name:name,

price:price,

qty:1

};

}

renderCart();

});

});

function changeQty(id,value){

cart[id].qty += value;

if(cart[id].qty<=0){

delete cart[id];

}

renderCart();

}

function removeItem(id){

delete cart[id];

renderCart();

}

</script>

<?php require_once "includes/a-footer.php"; ?>