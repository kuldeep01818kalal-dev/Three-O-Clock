</main>
</div>
<!-- End Main Content -->


<!-- =========================================
     ADMIN FOOTER
========================================= -->

<footer class="admin-footer">

    <div class="footer-left">

        <div class="brand">
            <i class="bi bi-cup-hot-fill"></i>
            <span>Three O' Clock Cafe</span>
        </div>

        <p>
            Restaurant Management System
            <span class="dot"></span>
            Admin Dashboard
        </p>

    </div>


    <div class="footer-center">

        <span class="footer-badge success">
            <i class="bi bi-circle-fill"></i>
            System Online
        </span>

        <span class="footer-badge">
            Version 1.0.0
        </span>

    </div>


    <div class="footer-right">

        <span>
            © <?= date("Y"); ?> Three O' Clock Cafe
        </span>

        <small>
            Designed & Developed by
            <strong>PW_F11_</strong>
        </small>

    </div>

</footer>


<!-- =========================================
     BOOTSTRAP
========================================= -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>


<script>

document.addEventListener("DOMContentLoaded", function () {

    /* =========================================
       AUTO HIDE ALERTS
    ========================================= */

    document.querySelectorAll(".alert").forEach(function (alert) {

        setTimeout(function () {

            alert.classList.add("fade");

            setTimeout(function () {
                alert.remove();
            }, 500);

        }, 4000);

    });


    /* =========================================
       MOBILE SIDEBAR
    ========================================= */

    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const sidebarClose = document.getElementById("sidebarClose");


    function openSidebar() {

        if (!sidebar) return;

        sidebar.classList.add("show");

        if (overlay) {
            overlay.classList.add("show");
        }

        if (menuBtn) {
            menuBtn.setAttribute("aria-expanded", "true");
        }

        document.body.classList.add("sidebar-open");
    }


    function closeSidebar() {

        if (!sidebar) return;

        sidebar.classList.remove("show");

        if (overlay) {
            overlay.classList.remove("show");
        }

        if (menuBtn) {
            menuBtn.setAttribute("aria-expanded", "false");
        }

        document.body.classList.remove("sidebar-open");
    }


    if (menuBtn) {

        menuBtn.addEventListener("click", function (event) {

            event.stopPropagation();

            if (sidebar && sidebar.classList.contains("show")) {

                closeSidebar();

            } else {

                openSidebar();

            }

        });

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


    /* =========================================
       CLOSE SIDEBAR AFTER MENU CLICK
    ========================================= */

    if (sidebar) {

        sidebar.querySelectorAll("a").forEach(function (link) {

            link.addEventListener("click", function () {

                if (window.innerWidth <= 991) {

                    closeSidebar();

                }

            });

        });

    }


    /* =========================================
       RESET SIDEBAR ON DESKTOP
    ========================================= */

    window.addEventListener("resize", function () {

        if (window.innerWidth > 991) {

            closeSidebar();

        }

    });


    /* =========================================
       PREVENT BACKGROUND SCROLL
    ========================================= */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            closeSidebar();

        }

    });

});

</script>


</body>
</html>