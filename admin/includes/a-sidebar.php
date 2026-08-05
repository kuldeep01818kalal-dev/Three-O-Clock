<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">

    <!-- Logo -->
    <div class="sidebar-header">

        <a href="a-dashboard.php" class="logo">

            <div class="logo-icon">

                <i class="bi bi-cup-hot-fill"></i>

            </div>

            <div class="logo-text">

                <h4>Three O' Clock</h4>

                <span>Restaurant ERP</span>

            </div>

        </a>

    </div>

    <div class="sidebar-body">

        <!-- MAIN -->
        <h6 class="menu-title">MAIN</h6>

        <ul class="menu">

            <li class="<?= $currentPage=='a-dashboard.php'?'active':''; ?>">
                <a href="a-dashboard.php">
                    <i class="bi bi-grid-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="<?= in_array($currentPage,['orders.php','view_order.php','edit_order.php'])?'active':''; ?>">
                <a href="orders.php">
                    <i class="bi bi-bag-check-fill"></i>
                    <span>Orders</span>
                    <small class="badge-count">5</small>
                </a>
            </li>

            <li class="<?= $currentPage=='kitchen.php'?'active':''; ?>">
                <a href="kitchen.php">
                    <i class="bi bi-fire"></i>
                    <span>Kitchen</span>
                    <small class="badge-count danger">2</small>
                </a>
            </li>

            <li class="<?= $currentPage=='a-billing.php'?'active':''; ?>">
                <a href="a-billing.php">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Billing / POS</span>
                </a>
            </li>

        </ul>

        <!-- MANAGEMENT -->
        <h6 class="menu-title">MANAGEMENT</h6>

        <ul class="menu">

            <li><a href="products.php"><i class="bi bi-cup-straw"></i><span>Products</span></a></li>

            <li><a href="categories.php"><i class="bi bi-grid-3x3-gap-fill"></i><span>Categories</span></a></li>

            <li><a href="customers.php"><i class="bi bi-people-fill"></i><span>Customers</span></a></li>

            <li><a href="table_management.php"><i class="bi bi-table"></i><span>Cafe Tables</span></a></li>

            <li><a href="reservations.php"><i class="bi bi-calendar-check-fill"></i><span>Reservations</span></a></li>

            <li><a href="gallery.php"><i class="bi bi-images"></i><span>Gallery</span></a></li>

        </ul>

        <!-- REPORTS -->
        <h6 class="menu-title">REPORTS</h6>

        <ul class="menu">

            <li><a href="reports.php"><i class="bi bi-bar-chart-line-fill"></i><span>Analytics</span></a></li>

        </ul>

        <!-- SYSTEM -->
        <h6 class="menu-title">SYSTEM</h6>

        <ul class="menu">

            <li><a href="settings.php"><i class="bi bi-gear-fill"></i><span>Settings</span></a></li>

        </ul>

    </div>

    <!-- Footer -->
    <div class="sidebar-footer">

        <div class="admin-profile">

            <div class="avatar">

                <i class="bi bi-person-fill"></i>

            </div>

            <div>

                <strong>Administrator</strong>

                <small>Online</small>

            </div>

        </div>

        <a href="a-logout.php" class="logout-btn">

            <i class="bi bi-box-arrow-right"></i>

            Logout

        </a>

    </div>

</aside>

<!-- Main Content -->
<main class="main-content">