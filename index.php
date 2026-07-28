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

                <img src="assets/images/hero-coffee.png"

                     class="img-fluid"

                     alt="Coffee">

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

                <div class="card text-center p-4">

                    <div class="display-4 mb-3">

                        ☕

                    </div>

                    <h4>Coffee</h4>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card text-center p-4">

                    <div class="display-4 mb-3">

                        🍕

                    </div>

                    <h4>Pizza</h4>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card text-center p-4">

                    <div class="display-4 mb-3">

                        🍔

                    </div>

                    <h4>Burgers</h4>

                </div>

            </div>

            <div class="col-md-3">

                <div class="card text-center p-4">

                    <div class="display-4 mb-3">

                        🍰

                    </div>

                    <h4>Desserts</h4>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- About -->

<section class="section-padding bg-white">

    <div class="container">

        <div class="row align-items-center">

            <div class="col-lg-6">

                <img src="assets/images/about-cafe.jpg"

                     class="img-fluid rounded-custom shadow-custom"

                     alt="Cafe">

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

        <div class="card p-5">

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