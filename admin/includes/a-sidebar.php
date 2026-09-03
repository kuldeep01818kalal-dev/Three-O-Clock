<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================================
   CURRENT PAGE
========================================= */

$currentPage = basename($_SERVER['PHP_SELF']);


/* =========================================
   HELPER FUNCTION
========================================= */

function sidebarActive(array $pages, string $currentPage): string
{
    return in_array($currentPage, $pages, true) ? 'active' : '';
}
?>

<!-- =========================================
     MOBILE OVERLAY
========================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay">
</div>


<!-- =========================================
     ADMIN SIDEBAR
========================================= -->

<aside
    class="admin-sidebar"
    id="sidebar">


    <!-- =====================================
         BRAND
    ====================================== -->

    <div class="sidebar-brand">

        <div class="sidebar-brand-icon">
            <i class="bi bi-cup-hot-fill"></i>
        </div>

        <div class="sidebar-brand-text">

            <strong>
                Three O' Clock
            </strong>

            <small>
                Cafe Admin
            </small>

        </div>

    </div>


    <!-- =====================================
         ADMIN PROFILE
    ====================================== -->

    <div class="sidebar-profile">

        <div class="sidebar-profile-icon">
            <i class="bi bi-person-fill"></i>
        </div>

        <div class="sidebar-profile-info">

            <strong>
                <?= htmlspecialchars(
                    $_SESSION['admin_name'] ?? 'Super Admin'
                ); ?>
            </strong>

            <small>
                Administrator
            </small>

        </div>

    </div>


    <!-- =====================================
         NAVIGATION
    ====================================== -->

    <nav class="sidebar-nav">


        <!-- MAIN MENU -->

        <div class="sidebar-section-title">
            Main Menu
        </div>


        <!-- Dashboard -->

        <a
            href="a-dashboard.php"
            class="sidebar-link <?= sidebarActive(
                ['a-dashboard.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-grid-1x2-fill"></i>
            </span>

            <span class="sidebar-link-text">
                Dashboard
            </span>

        </a>


        <!-- Orders -->

        <a
            href="orders.php"
            class="sidebar-link <?= sidebarActive(
                ['orders.php', 'view_order.php', 'edit_order.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-receipt"></i>
            </span>

            <span class="sidebar-link-text">
                Orders
            </span>

        </a>


        <!-- Kitchen -->

        <a
            href="kitchen.php"
            class="sidebar-link <?= sidebarActive(
                ['kitchen.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-fire"></i>
            </span>

            <span class="sidebar-link-text">
                Kitchen
            </span>

            <?php if (!empty($pendingKitchenOrders)): ?>

                <span class="sidebar-badge">
                    <?= (int)$pendingKitchenOrders; ?>
                </span>

            <?php endif; ?>

        </a>


        <!-- Billing -->

        <a
            href="a-billing.php"
            class="sidebar-link <?= sidebarActive(
                ['a-billing.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-receipt-cutoff"></i>
            </span>

            <span class="sidebar-link-text">
                Billing / POS
            </span>

        </a>


        <!-- MANAGEMENT -->

        <div class="sidebar-section-title">
            Management
        </div>


        <!-- Products -->

        <a
            href="products.php"
            class="sidebar-link <?= sidebarActive(
                ['products.php', 'add_product.php', 'edit_product.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-box-seam"></i>
            </span>

            <span class="sidebar-link-text">
                Products
            </span>

        </a>


        <!-- Categories -->

        <a
            href="categories.php"
            class="sidebar-link <?= sidebarActive(
                ['categories.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-tags"></i>
            </span>

            <span class="sidebar-link-text">
                Categories
            </span>

        </a>


        <!-- Customers -->

        <a
            href="customers.php"
            class="sidebar-link <?= sidebarActive(
                ['customers.php', 'customer_details.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-people-fill"></i>
            </span>

            <span class="sidebar-link-text">
                Customers
            </span>

        </a>


        <!-- Cafe Tables -->

        <a
            href="cafe_tables.php"
            class="sidebar-link <?= sidebarActive(
                ['cafe_tables.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-grid-3x3-gap-fill"></i>
            </span>

            <span class="sidebar-link-text">
                Cafe Tables
            </span>

        </a>


        <!-- Reservations -->

        <a
            href="reservations.php"
            class="sidebar-link <?= sidebarActive(
                ['reservations.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-calendar-check"></i>
            </span>

            <span class="sidebar-link-text">
                Reservations
            </span>

        </a>


        <!-- BUSINESS -->

        <div class="sidebar-section-title">
            Business
        </div>


        <!-- Invoices -->

        <a
            href="invoices.php"
            class="sidebar-link <?= sidebarActive(
                ['invoices.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-file-earmark-text"></i>
            </span>

            <span class="sidebar-link-text">
                Invoices
            </span>

        </a>


        <!-- Reports -->

        <a
            href="reports.php"
            class="sidebar-link <?= sidebarActive(
                ['reports.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-bar-chart-line-fill"></i>
            </span>

            <span class="sidebar-link-text">
                Reports
            </span>

        </a>


        <!-- Reviews -->

        <a
            href="reviews.php"
            class="sidebar-link <?= sidebarActive(
                ['reviews.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-star-fill"></i>
            </span>

            <span class="sidebar-link-text">
                Reviews
            </span>

        </a>


        <!-- SETTINGS -->

        <div class="sidebar-section-title">
            System
        </div>


        <!-- Settings -->

        <a
            href="settings.php"
            class="sidebar-link <?= sidebarActive(
                ['settings.php'],
                $currentPage
            ); ?>">

            <span class="sidebar-link-icon">
                <i class="bi bi-gear-fill"></i>
            </span>

            <span class="sidebar-link-text">
                Settings
            </span>

        </a>

    </nav>


    <!-- =====================================
         SIDEBAR FOOTER
    ====================================== -->

    <div class="sidebar-footer">

        <a
            href="logout.php"
            class="sidebar-logout">

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>