<?php
$pageTitle = "Menu";
include 'includes/header.php';
include 'includes/navbar.php';
?>

<!-- ==========================================
     Menu Hero
========================================== -->

<section class="menu-hero section-padding">

    <div class="container text-center">

        <span class="badge bg-warning text-dark mb-3">
            Fresh • Delicious • Handcrafted
        </span>

        <h1 class="display-4 fw-bold mb-3">
            Explore Our Menu
        </h1>

        <p class="lead">
            From handcrafted coffee to delicious meals and desserts,
            discover your next favorite dish.
        </p>

    </div>

</section>
<section class="section-padding pt-0">

    <div class="container">

        <div class="menu-filter text-center">

            <button class="btn btn-primary active">All</button>

            <button class="btn btn-outline-primary">Coffee</button>

            <button class="btn btn-outline-primary">Pizza</button>

            <button class="btn btn-outline-primary">Burger</button>

            <button class="btn btn-outline-primary">Dessert</button>

            <button class="btn btn-outline-primary">Beverages</button>

        </div>

    </div>

</section>
<div class="container mb-5">

    <div class="row justify-content-center">

        <div class="col-lg-6">

            <input
                type="text"
                class="form-control form-control-lg"
                placeholder="Search your favorite food...">

        </div>

    </div>

</div>
<div class="container">

<div class="row g-4">

<?php

$products=[

["Cappuccino","199","coffee1.jpg"],

["Latte","219","coffee2.jpg"],

["Cheese Burger","259","burger.jpg"],

["Margherita Pizza","349","pizza.jpg"],

["Chocolate Cake","179","cake.jpg"],

["Cold Coffee","149","coldcoffee.jpg"],

["Pasta","299","pasta.jpg"],

["Brownie","159","brownie.jpg"]

];

foreach($products as $p){

?>

<div class="col-lg-3 col-md-6">

<div class="menu-card">

<div class="menu-image">

<img src="assets/images/menu/<?php echo $p[2]; ?>">

</div>

<div class="menu-content">

<h4><?php echo $p[0]; ?></h4>

<div class="price">₹<?php echo $p[1]; ?></div>

<div class="d-grid mt-3">

<a href="product.php" class="btn btn-primary">

View Details

</a>

</div>

</div>

</div>

</div>

<?php } ?>

</div>

</div>

<?php include 'includes/footer.php'; ?>