<?php

$page_title = "Home";

require_once 'includes/header.php';
require_once 'includes/navbar.php';

?>

<!-- Hero Section -->

<section class="hero-section">

    <div class="container">

        <div class="row align-items-center min-vh-100">

            <div class="col-lg-6">

                <span class="badge bg-warning text-dark mb-3">

                    ☕ Premium Cafe Experience

                </span>

                <h1 class="display-3 fw-bold mb-4">

                    Fresh Coffee,

                    <span class="text-orange">

                        Delicious Food

                    </span>

                    & Sweet Moments

                </h1>

                <p class="lead mb-4">

                    Welcome to Three O' Clock Cafe.

                    Enjoy handcrafted coffee, fresh meals,

                    delicious desserts and memorable moments

                    with your family and friends.

                </p>

                <div class="d-flex flex-wrap gap-3">

                    <a href="menu.php"
                       class="btn btn-primary btn-lg">

                        Order Now

                    </a>

                    <a href="reservation.php"
                       class="btn btn-outline-primary btn-lg">

                        Book Table

                    </a>

                </div>

            </div>

            <div class="col-lg-6 text-center">
                <div class="hero-image">

                    <img src="assets/images/hero-coffee.png"
                        class="img-fluid"
                        alt="Coffee">

                </div>

            </div>
            

        </div>

    </div>

</section>

<!-- Categories -->

<section class="section-padding">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="section-title">

                Our Categories

            </h2>

            <p class="section-subtitle">

                Choose your favourite food & beverages

            </p>

        </div>

        <div class="row g-4">

            <div class="col-md-3">

                <div class="category-card">

                    <div class="icon">

                        ☕

                    </div>

                    <h4>Coffee</h4>

                </div>

            </div>

            <div class="col-md-3">

                <div class="category-card">

                    <div class="icon">

                        🍕

                    </div>

                    <h4>Pizza</h4>

                </div>

            </div>

            <div class="col-md-3">

                <div class="category-card">

                    <div class="icon">

                        🍔

                    </div>

                    <h4>Burgers</h4>

                </div>

            </div>

            <div class="col-md-3">

                <div class="category-card">

                    <div class="icon">

                        🍰

                    </div>

                    <h4>Desserts</h4>

                </div>

            </div>

        </div>

    </div>

</section>
<!-- ==========================================
     Featured Menu
========================================== -->

<section class="section-padding">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="section-title">

                Featured Menu

            </h2>

            <p class="section-subtitle">

                Freshly prepared favourites loved by our customers

            </p>

        </div>

        <div class="row g-4">

            <?php

            $featuredProducts = [

                [
                    "name"=>"Cappuccino",
                    "price"=>"199",
                    "image"=>"assets/images/menu/cappuccino.jpg",
                    "rating"=>"4.8",
                    "veg"=>true
                ],

                [
                    "name"=>"Margherita Pizza",
                    "price"=>"349",
                    "image"=>"assets/images/menu/pizza.jpg",
                    "rating"=>"4.9",
                    "veg"=>true
                ],

                [
                    "name"=>"Cheese Burger",
                    "price"=>"259",
                    "image"=>"assets/images/menu/burger.jpg",
                    "rating"=>"4.7",
                    "veg"=>false
                ],

                [
                    "name"=>"Chocolate Cake",
                    "price"=>"179",
                    "image"=>"assets/images/menu/cake.jpg",
                    "rating"=>"4.9",
                    "veg"=>true
                ]

            ];

            foreach($featuredProducts as $product):

            ?>

            <div class="col-lg-3 col-md-6">

                <div class="menu-card">

                    <div class="menu-image">

                        <img src="<?= $product['image']; ?>"
                             alt="<?= $product['name']; ?>">

                        <?php if($product['veg']){ ?>

                            <span class="badge bg-success">

                                Veg

                            </span>

                        <?php } else { ?>

                            <span class="badge bg-danger">

                                Non-Veg

                            </span>

                        <?php } ?>

                    </div>

                    <div class="menu-content">

                        <h4>

                            <?= $product['name']; ?>

                        </h4>

                        <div class="rating">

                            ⭐ <?= $product['rating']; ?>

                        </div>

                        <div class="price">

                            ₹<?= $product['price']; ?>

                        </div>

                        <a href="product.php"

                           class="btn btn-primary w-100 mt-3">

                            View Details

                        </a>

                    </div>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>
<!-- ==========================================
     Statistics
========================================== -->

<section class="stats-section section-padding">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="section-title">

                Why Customers Love Us

            </h2>

            <p class="section-subtitle">

                Every number tells a story of trust and quality.

            </p>

        </div>

        <div class="row g-4">

            <div class="col-lg-3 col-md-6">

                <div class="stats-card">

                    <div class="stats-icon">

                        <i class="bi bi-people-fill"></i>

                    </div>

                    <h2>5000+</h2>

                    <p>Happy Customers</p>

                </div>

            </div>

            <div class="col-lg-3 col-md-6">

                <div class="stats-card">

                    <div class="stats-icon">

                        <i class="bi bi-cup-hot-fill"></i>

                    </div>

                    <h2>15000+</h2>

                    <p>Cups Served</p>

                </div>

            </div>

            <div class="col-lg-3 col-md-6">

                <div class="stats-card">

                    <div class="stats-icon">

                        <i class="bi bi-star-fill"></i>

                    </div>

                    <h2>4.9</h2>

                    <p>Average Rating</p>

                </div>

            </div>

            <div class="col-lg-3 col-md-6">

                <div class="stats-card">

                    <div class="stats-icon">

                        <i class="bi bi-award-fill"></i>

                    </div>

                    <h2>8+</h2>

                    <p>Years Experience</p>

                </div>

            </div>

        </div>

    </div>

</section>
<!-- ==========================================
     Customer Testimonials
========================================== -->

<section class="section-padding bg-light">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="section-title">

                What Our Customers Say

            </h2>

            <p class="section-subtitle">

                Hear from the people who enjoy our food and coffee every day.

            </p>

        </div>

        <div class="row g-4">

            <div class="col-lg-4 col-md-6">

                <div class="testimonial-card">

                    <div class="testimonial-header">
                        <div>

                            <h5>Rahul Sharma</h5>

                            <small>Ahmedabad</small>

                        </div>

                    </div>

                    <div class="stars">

                        ★★★★★

                    </div>

                    <p>

                        Amazing coffee and excellent ambience.
                        The food quality is outstanding and the
                        service is always fast.

                    </p>

                </div>

            </div>

            <div class="col-lg-4 col-md-6">

                <div class="testimonial-card">

                    <div class="testimonial-header">
                        <div>

                            <h5>Priya Patel</h5>

                            <small>Gandhinagar</small>

                        </div>

                    </div>

                    <div class="stars">

                        ★★★★★

                    </div>

                    <p>

                        Beautiful cafe with delicious desserts.
                        Perfect place for family and friends.

                    </p>

                </div>

            </div>

            <div class="col-lg-4 col-md-6">

                <div class="testimonial-card">

                    <div class="testimonial-header">
                        <div>

                            <h5>Amit Verma</h5>

                            <small>Vadodara</small>

                        </div>

                    </div>

                    <div class="stars">

                        ★★★★★

                    </div>

                    <p>

                        One of the best cafes I've visited.
                        Highly recommended for coffee lovers.

                    </p>

                </div>

            </div>

        </div>

    </div>

</section>
<!-- ==========================================
     Gallery Preview
========================================== -->

<section class="gallery-preview section-padding">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="section-title">
                Our Gallery
            </h2>

            <p class="section-subtitle">
                A glimpse of our delicious food, handcrafted coffee, and cozy atmosphere.
            </p>

        </div>

        <div class="row g-4">

            <div class="col-lg-4 col-md-6">
                <div class="gallery-card">
                    <img src="assets/images/gallery/gallery1.jpg" alt="Coffee">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="gallery-card">
                    <img src="assets/images/gallery/gallery2.jpg" alt="Pizza">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="gallery-card">
                    <img src="assets/images/gallery/gallery3.jpg" alt="Dessert">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="gallery-card">
                    <img src="assets/images/gallery/gallery4.jpg" alt="Cafe Interior">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="gallery-card">
                    <img src="assets/images/gallery/gallery5.jpg" alt="Burger">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="gallery-card">
                    <img src="assets/images/gallery/gallery6.jpg" alt="Breakfast">
                </div>
            </div>

        </div>

        <div class="text-center mt-5">

            <a href="gallery.php" class="btn btn-primary btn-lg">
                View Full Gallery
            </a>

        </div>

    </div>

</section>
<!-- About -->

<section class="section-padding bg-white">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-6">

                <div class="about-image">

                    <img src="assets/images/about-cafe.jpg"
                        class="img-fluid"
                        alt="Cafe">

                </div>

            </div>

            <div class="col-lg-6">

                <h2 class="section-title text-start">

                    Why Choose Us?

                </h2>

                <p>

                    At Three O' Clock Cafe, every cup of coffee

                    and every meal is prepared with fresh

                    ingredients and passion.

                </p>

                <ul class="mt-4">

                    <li class="mb-3">✔ Premium Coffee Beans</li>

                    <li class="mb-3">✔ Fresh Ingredients</li>

                    <li class="mb-3">✔ Hygienic Kitchen</li>

                    <li class="mb-3">✔ Comfortable Seating</li>

                    <li class="mb-3">✔ Fast Delivery</li>

                </ul>

                <a href="about.php"

                   class="btn btn-secondary">

                    Learn More

                </a>

            </div>

        </div>

    </div>

</section>

<!-- CTA -->

<section class="section-padding text-center">

    <div class="container">

        <div class="cta-card">

            <h2 class="mb-3">

                Reserve Your Table Today

            </h2>

            <p class="mb-4">

                Experience delicious food in a warm and relaxing atmosphere.

            </p>

            <a href="reservation.php"

               class="btn btn-primary btn-lg">

                Book Now

            </a>

        </div>

    </div>

</section>

<?php

require_once 'includes/footer.php';

?>