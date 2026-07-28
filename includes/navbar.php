<?php

$currentPage = basename($_SERVER['PHP_SELF']);

$isLoggedIn = isset($_SESSION['user_id']);

?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">

    <div class="container">

        <!-- Logo -->

        <a class="navbar-brand fw-bold" href="index.php">

            <i class="bi bi-cup-hot-fill text-warning"></i>

            Three O' Clock Cafe

        </a>

        <!-- Mobile Toggle -->

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar">

            <span class="navbar-toggler-icon"></span>

        </button>

        <!-- Navbar -->

        <div class="collapse navbar-collapse"
             id="mainNavbar">

            <!-- Left Menu -->

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <li class="nav-item">

                    <a class="nav-link <?= $currentPage=='index.php'?'active':''; ?>" href="index.php">
                        Home
                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link <?= ($currentPage == 'about.php') ? 'active' : ''; ?>"
                       href="about.php">

                        About

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link <?= ($currentPage == 'menu.php') ? 'active' : ''; ?>"
                       href="menu.php">

                        Menu

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link <?= ($currentPage == 'gallery.php') ? 'active' : ''; ?>"
                       href="gallery.php">

                        Gallery

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link <?= ($currentPage == 'reservation.php') ? 'active' : ''; ?>"
                       href="reservation.php">

                        Reservation

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link <?= ($currentPage == 'contact.php') ? 'active' : ''; ?>"
                       href="contact.php">

                        Contact

                    </a>

                </li>

            </ul>

            <!-- Right Menu -->

            <ul class="navbar-nav ms-lg-4 align-items-lg-center">

                <!-- Search -->

                <li class="nav-item">

                    <a class="nav-link"
                       href="search.php">

                        <i class="bi bi-search"></i>

                    </a>

                </li>

                <!-- Cart -->

                <li class="nav-item">

                    <a class="nav-link"
                       href="cart.php">

                        <i class="bi bi-cart3"></i>

                    </a>

                </li>

                <?php if (!$isLoggedIn): ?>

                    <li class="nav-item ms-lg-2">

                        <a class="btn btn-outline-light me-lg-2 mt-2 mt-lg-0"
                           href="login.php">

                            Login

                        </a>

                    </li>

                    <li class="nav-item mt-2 mt-lg-0">

                        <a class="btn btn-warning"
                           href="register.php">

                            Register

                        </a>

                    </li>

                <?php else: ?>

                    <li class="nav-item dropdown">

                        <a class="nav-link dropdown-toggle"
                           href="#"
                           role="button"
                           data-bs-toggle="dropdown">

                            <i class="bi bi-person-circle"></i>

                            My Account

                        </a>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <li>

                                <a class="dropdown-item"
                                   href="profile.php">

                                    Profile

                                </a>

                            </li>

                            <li>

                                <a class="dropdown-item"
                                   href="my_orders.php">

                                    My Orders

                                </a>

                            </li>

                            <li>

                                <a class="dropdown-item"
                                   href="reservation.php">

                                    My Reservations

                                </a>

                            </li>

                            <li>

                                <hr class="dropdown-divider">

                            </li>

                            <li>

                                <a class="dropdown-item text-danger"
                                   href="logout.php">

                                    Logout

                                </a>

                            </li>

                        </ul>

                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>