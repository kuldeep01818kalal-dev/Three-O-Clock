<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminName = $_SESSION['admin_name'] ?? 'Administrator';
$currentPage = basename($_SERVER['PHP_SELF']);

$pendingOrders = $pendingOrders ?? 0;
?>

<!-- =========================================================
     ADMIN NAVBAR
========================================================= -->

<header class="admin-navbar">

    <!-- LEFT -->
    <div class="navbar-left">

        <!-- MOBILE MENU -->
        <button
            type="button"
            class="menu-btn"
            id="menuBtn"
            aria-label="Open navigation menu"
            aria-controls="sidebar"
            aria-expanded="false">

            <i class="bi bi-list"></i>

        </button>


        <!-- PAGE INFORMATION -->
        <div class="navbar-page-info">

            <span>
                Three O' Clock Cafe
            </span>

            <strong>
                <?= htmlspecialchars($pageTitle ?? 'Admin Panel'); ?>
            </strong>

        </div>

    </div>


    <!-- =====================================================
         RIGHT SIDE
    ====================================================== -->

    <div class="navbar-right">


        <!-- SEARCH -->

        <div class="navbar-search">

            <i class="bi bi-search"></i>

            <input
                type="search"
                id="adminGlobalSearch"
                placeholder="Search..."
                autocomplete="off"
            >

        </div>


        <!-- NOTIFICATION -->

        <div class="navbar-notification">

            <button
                type="button"
                class="navbar-icon-btn"
                id="notificationBtn"
                aria-label="Notifications"
                aria-expanded="false">

                <i class="bi bi-bell"></i>

                <?php if ($pendingOrders > 0): ?>

                    <span class="notification-dot"></span>

                <?php endif; ?>

            </button>


            <!-- NOTIFICATION PANEL -->

            <div
                class="notification-panel"
                id="notificationPanel">

                <div class="notification-header">

                    <div>

                        <strong>
                            Notifications
                        </strong>

                        <small>
                            Recent activity
                        </small>

                    </div>

                    <button
                        type="button"
                        id="closeNotifications"
                        aria-label="Close notifications">

                        <i class="bi bi-x-lg"></i>

                    </button>

                </div>


                <div class="notification-body">

                    <?php if ($pendingOrders > 0): ?>

                        <a
                            href="orders.php"
                            class="notification-item">

                            <span class="notification-icon order-notification">

                                <i class="bi bi-receipt"></i>

                            </span>

                            <span>

                                <strong>
                                    Pending Orders
                                </strong>

                                <small>
                                    <?= (int)$pendingOrders; ?>
                                    order(s) need attention.
                                </small>

                            </span>

                        </a>

                    <?php else: ?>

                        <div class="notification-empty">

                            <i class="bi bi-check-circle"></i>

                            <strong>
                                All caught up
                            </strong>

                            <span>
                                No new notifications.
                            </span>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- =================================================
             ADMIN PROFILE
        ================================================== -->

        <div class="navbar-profile">

            <button
                type="button"
                class="profile-button"
                id="profileButton"
                aria-expanded="false">

                <span class="navbar-avatar">

                    <i class="bi bi-person-fill"></i>

                </span>

                <span class="navbar-profile-text">

                    <strong>
                        <?= htmlspecialchars($adminName); ?>
                    </strong>

                    <small>
                        Administrator
                    </small>

                </span>

                <i class="bi bi-chevron-down profile-arrow"></i>

            </button>


            <!-- PROFILE DROPDOWN -->

            <div
                class="profile-dropdown"
                id="profileDropdown">

                <div class="profile-dropdown-header">

                    <div class="dropdown-avatar">

                        <i class="bi bi-person-fill"></i>

                    </div>

                    <div>

                        <strong>
                            <?= htmlspecialchars($adminName); ?>
                        </strong>

                        <span>
                            Administrator
                        </span>

                    </div>

                </div>


                <div class="profile-dropdown-divider"></div>


                <a href="profile.php">

                    <i class="bi bi-person-circle"></i>

                    <span>
                        My Profile
                    </span>

                </a>


                <a href="settings.php">

                    <i class="bi bi-gear"></i>

                    <span>
                        Settings
                    </span>

                </a>


                <div class="profile-dropdown-divider"></div>


                <a
                    href="a-logout.php"
                    class="logout-link">

                    <i class="bi bi-box-arrow-right"></i>

                    <span>
                        Logout
                    </span>

                </a>

            </div>

        </div>

    </div>

</header>






<!-- =========================================================
     NAVBAR JAVASCRIPT
========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", function () {


    /* =====================================================
       ELEMENTS
    ===================================================== */

    const menuBtn =
        document.getElementById("menuBtn");

    const sidebar =
        document.getElementById("sidebar");

    const overlay =
        document.getElementById("sidebarOverlay");

    const sidebarClose =
        document.getElementById("sidebarClose");


    const profileButton =
        document.getElementById("profileButton");

    const profileDropdown =
        document.getElementById("profileDropdown");


    const notificationBtn =
        document.getElementById("notificationBtn");

    const notificationPanel =
        document.getElementById("notificationPanel");

    const closeNotifications =
        document.getElementById("closeNotifications");


    /* =====================================================
       SIDEBAR
    ===================================================== */

    function openSidebar() {

        if (!sidebar) return;

        sidebar.classList.add("show");

        if (overlay) {
            overlay.classList.add("show");
        }

        if (menuBtn) {

            menuBtn.setAttribute(
                "aria-expanded",
                "true"
            );

        }

        document.body.classList.add(
            "sidebar-open"
        );

    }


    function closeSidebar() {

        if (!sidebar) return;

        sidebar.classList.remove("show");

        if (overlay) {
            overlay.classList.remove("show");
        }

        if (menuBtn) {

            menuBtn.setAttribute(
                "aria-expanded",
                "false"
            );

        }

        document.body.classList.remove(
            "sidebar-open"
        );

    }


    if (menuBtn) {

        menuBtn.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

                if (
                    sidebar &&
                    sidebar.classList.contains("show")
                ) {

                    closeSidebar();

                } else {

                    openSidebar();

                }

            }
        );

    }


    if (sidebarClose) {

        sidebarClose.addEventListener(
            "click",
            closeSidebar
        );

    }


    if (overlay) {

        overlay.addEventListener(
            "click",
            closeSidebar
        );

    }


    /* =====================================================
       CLOSE SIDEBAR WHEN MENU ITEM CLICKED
    ===================================================== */

    if (sidebar) {

        sidebar
            .querySelectorAll("a")
            .forEach(function (link) {

                link.addEventListener(
                    "click",
                    function () {

                        if (
                            window.innerWidth <= 991
                        ) {

                            closeSidebar();

                        }

                    }
                );

            });

    }


    /* =====================================================
       PROFILE DROPDOWN
    ===================================================== */

    if (
        profileButton &&
        profileDropdown
    ) {

        profileButton.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();


                /* Close notification */

                if (notificationPanel) {

                    notificationPanel.classList.remove(
                        "show"
                    );

                }


                profileDropdown.classList.toggle(
                    "show"
                );


                const expanded =
                    profileDropdown.classList.contains(
                        "show"
                    );


                profileButton.setAttribute(
                    "aria-expanded",
                    expanded
                );

            }
        );


        profileDropdown.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();

            }
        );

    }


    /* =====================================================
       NOTIFICATIONS
    ===================================================== */

    if (
        notificationBtn &&
        notificationPanel
    ) {

        notificationBtn.addEventListener(
            "click",
            function (event) {

                event.stopPropagation();


                /* Close profile */

                if (profileDropdown) {

                    profileDropdown.classList.remove(
                        "show"
                    );

                }


                notificationPanel.classList.toggle(
                    "show"
                );


                const expanded =
                    notificationPanel.classList.contains(
                        "show"
                    );


                notificationBtn.setAttribute(
                    "aria-expanded",
                    expanded
                );

            }
        );

    }


    /* =====================================================
       CLOSE NOTIFICATIONS
    ===================================================== */

    if (closeNotifications) {
    closeNotifications.addEventListener(
        "click",
        function (event) {

            event.stopPropagation();

            if (notificationPanel) {
                notificationPanel.classList.remove("show");
            }

            if (notificationBtn) {
                notificationBtn.setAttribute(
                    "aria-expanded",
                    "false"
                );
            }

        }
    );
}


    /* =====================================================
       OUTSIDE CLICK
    ===================================================== */

    document.addEventListener(
        "click",
        function (event) {


            /* Profile */

            if (
                profileDropdown &&
                profileButton &&
                !profileDropdown.contains(event.target) &&
                !profileButton.contains(event.target)
            ) {

                profileDropdown.classList.remove(
                    "show"
                );

                profileButton.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }


            /* Notification */

            if (
                notificationPanel &&
                notificationBtn &&
                !notificationPanel.contains(event.target) &&
                !notificationBtn.contains(event.target)
            ) {

                notificationPanel.classList.remove(
                    "show"
                );

                notificationBtn.setAttribute(
                    "aria-expanded",
                    "false"
                );

            }

        }
    );


    /* =====================================================
       ESCAPE KEY
    ===================================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "Escape") {

                closeSidebar();


                if (profileDropdown) {

                    profileDropdown.classList.remove(
                        "show"
                    );

                }


                if (notificationPanel) {

                    notificationPanel.classList.remove(
                        "show"
                    );

                }

            }

        }
    );


    /* =====================================================
       DESKTOP RESET
    ===================================================== */

    window.addEventListener(
        "resize",
        function () {

            if (window.innerWidth > 991) {

                closeSidebar();

            }

        }
    );

});

</script>