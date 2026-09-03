/* =========================================================
   THREE O' CLOCK CAFE
   ADMIN PANEL JAVASCRIPT
   Common Admin Functions
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       SIDEBAR
    ===================================================== */

    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");
    const sidebarOverlay = document.getElementById("sidebarOverlay");
    const sidebarClose = document.getElementById("sidebarClose");

    function openSidebar() {
        if (!sidebar) return;

        sidebar.classList.add("show");

        if (sidebarOverlay) {
            sidebarOverlay.classList.add("show");
        }

        document.body.classList.add("sidebar-open");

        if (menuBtn) {
            menuBtn.setAttribute("aria-expanded", "true");
        }
    }

    function closeSidebar() {
        if (!sidebar) return;

        sidebar.classList.remove("show");

        if (sidebarOverlay) {
            sidebarOverlay.classList.remove("show");
        }

        document.body.classList.remove("sidebar-open");

        if (menuBtn) {
            menuBtn.setAttribute("aria-expanded", "false");
        }
    }

    if (menuBtn) {
        menuBtn.addEventListener("click", function (event) {
            event.preventDefault();

            if (sidebar && sidebar.classList.contains("show")) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener("click", closeSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener("click", closeSidebar);
    }


    /* =====================================================
       CLOSE SIDEBAR AFTER LINK CLICK ON MOBILE
    ===================================================== */

    if (sidebar) {

        const sidebarLinks = sidebar.querySelectorAll("a");

        sidebarLinks.forEach(function (link) {

            link.addEventListener("click", function () {

                if (window.innerWidth <= 991) {
                    closeSidebar();
                }

            });

        });

    }


    /* =====================================================
       ESC KEY
    ===================================================== */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {
            closeSidebar();
        }

    });


    /* =====================================================
       WINDOW RESIZE
    ===================================================== */

    window.addEventListener("resize", function () {

        if (window.innerWidth > 991) {
            closeSidebar();
        }

    });


    /* =====================================================
       AUTO HIDE ALERTS
    ===================================================== */

    const alerts = document.querySelectorAll(
        ".alert[data-auto-hide='true'], .admin-alert[data-auto-hide='true']"
    );

    alerts.forEach(function (alert) {

        setTimeout(function () {

            alert.style.opacity = "0";
            alert.style.transform = "translateY(-8px)";

            setTimeout(function () {

                if (alert.parentNode) {
                    alert.remove();
                }

            }, 300);

        }, 4000);

    });


    /* =====================================================
       DELETE CONFIRMATION
    ===================================================== */

    document.querySelectorAll("[data-confirm-delete]").forEach(function (element) {

        element.addEventListener("click", function (event) {

            const message =
                element.getAttribute("data-confirm-delete") ||
                "Are you sure you want to delete this item?";

            if (!confirm(message)) {
                event.preventDefault();
            }

        });

    });


    /* =====================================================
       GENERAL CONFIRMATION
    ===================================================== */

    document.querySelectorAll("[data-confirm]").forEach(function (element) {

        element.addEventListener("click", function (event) {

            const message =
                element.getAttribute("data-confirm") ||
                "Are you sure you want to continue?";

            if (!confirm(message)) {
                event.preventDefault();
            }

        });

    });


    /* =====================================================
       PREVENT DOUBLE FORM SUBMISSION
    ===================================================== */

    document.querySelectorAll("form[data-prevent-double-submit]").forEach(function (form) {

        form.addEventListener("submit", function () {

            const submitButtons = form.querySelectorAll(
                "button[type='submit'], input[type='submit']"
            );

            submitButtons.forEach(function (button) {

                button.disabled = true;

                const originalText =
                    button.innerHTML || button.value;

                button.setAttribute(
                    "data-original-text",
                    originalText
                );

                if (button.tagName.toLowerCase() === "button") {
                    button.innerHTML = "Processing...";
                } else {
                    button.value = "Processing...";
                }

            });

        });

    });


    /* =====================================================
       PASSWORD SHOW / HIDE
    ===================================================== */

    document.querySelectorAll("[data-password-toggle]").forEach(function (button) {

        button.addEventListener("click", function () {

            const targetId =
                button.getAttribute("data-password-toggle");

            const passwordInput =
                document.getElementById(targetId);

            if (!passwordInput) return;

            if (passwordInput.type === "password") {

                passwordInput.type = "text";

                button.classList.add("active");

                button.setAttribute(
                    "aria-label",
                    "Hide password"
                );

            } else {

                passwordInput.type = "password";

                button.classList.remove("active");

                button.setAttribute(
                    "aria-label",
                    "Show password"
                );

            }

        });

    });


    /* =====================================================
       SELECT ALL CHECKBOXES
    ===================================================== */

    document.querySelectorAll("[data-select-all]").forEach(function (selectAll) {

        const targetSelector =
            selectAll.getAttribute("data-select-all");

        const checkboxes =
            document.querySelectorAll(targetSelector);

        selectAll.addEventListener("change", function () {

            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });

        });

        checkboxes.forEach(function (checkbox) {

            checkbox.addEventListener("change", function () {

                const checkedCount =
                    document.querySelectorAll(
                        targetSelector + ":checked"
                    ).length;

                selectAll.checked =
                    checkedCount === checkboxes.length;

                selectAll.indeterminate =
                    checkedCount > 0 &&
                    checkedCount < checkboxes.length;

            });

        });

    });


    /* =====================================================
       TABLE ROW CLICK
    ===================================================== */

    document.querySelectorAll("[data-row-link]").forEach(function (row) {

        row.addEventListener("click", function (event) {

            if (
                event.target.closest("a") ||
                event.target.closest("button") ||
                event.target.closest("input") ||
                event.target.closest("select") ||
                event.target.closest("textarea")
            ) {
                return;
            }

            const url =
                row.getAttribute("data-row-link");

            if (url) {
                window.location.href = url;
            }

        });

    });


    /* =====================================================
       SEARCH CLEAR BUTTON
    ===================================================== */

    document.querySelectorAll("[data-clear-search]").forEach(function (button) {

        button.addEventListener("click", function () {

            const targetId =
                button.getAttribute("data-clear-search");

            const input =
                document.getElementById(targetId);

            if (!input) return;

            input.value = "";

            input.focus();

            input.dispatchEvent(
                new Event("input", {
                    bubbles: true
                })
            );

        });

    });


    /* =====================================================
       NUMBER INPUT VALIDATION
    ===================================================== */

    document.querySelectorAll("input[type='number']").forEach(function (input) {

        input.addEventListener("input", function () {

            if (input.min !== "") {

                const min = parseFloat(input.min);

                if (!isNaN(min) && input.value !== "") {

                    if (parseFloat(input.value) < min) {
                        input.value = min;
                    }

                }

            }

            if (input.max !== "") {

                const max = parseFloat(input.max);

                if (!isNaN(max) && input.value !== "") {

                    if (parseFloat(input.value) > max) {
                        input.value = max;
                    }

                }

            }

        });

    });


    /* =====================================================
       IMAGE PREVIEW
    ===================================================== */

    document.querySelectorAll("[data-image-preview]").forEach(function (input) {

        input.addEventListener("change", function () {

            const previewId =
                input.getAttribute("data-image-preview");

            const preview =
                document.getElementById(previewId);

            if (!preview || !input.files || !input.files[0]) {
                return;
            }

            const file = input.files[0];

            if (!file.type.startsWith("image/")) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                preview.src = event.target.result;
                preview.style.display = "block";
            };

            reader.readAsDataURL(file);

        });

    });


    /* =====================================================
       TOOLTIP SUPPORT
    ===================================================== */

    document.querySelectorAll("[title]").forEach(function (element) {

        element.addEventListener("mouseenter", function () {
            element.setAttribute(
                "data-original-title",
                element.getAttribute("title")
            );
        });

    });


    /* =====================================================
       CURRENT YEAR
    ===================================================== */

    document.querySelectorAll("[data-current-year]").forEach(function (element) {

        element.textContent = new Date().getFullYear();

    });


    /* =====================================================
       BODY READY
    ===================================================== */

    document.body.classList.add("admin-js-ready");

});