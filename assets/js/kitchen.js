/* =========================================================
   THREE O' CLOCK CAFE
   KITCHEN MANAGEMENT JAVASCRIPT
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       KITCHEN ORDER CARDS
    ===================================================== */

    const kitchenCards =
        document.querySelectorAll(".kitchen-card");


    /* =====================================================
       STATUS BUTTON LOADING
    ===================================================== */

    document.querySelectorAll(
        ".kitchen-actions a, .kitchen-actions button"
    ).forEach(function (button) {

        button.addEventListener("click", function () {

            if (button.tagName.toLowerCase() === "a") {

                const href = button.getAttribute("href");

                if (!href || href === "#") {
                    return;
                }

            }

            button.classList.add("disabled");

            button.setAttribute(
                "aria-disabled",
                "true"
            );

            const originalHTML =
                button.innerHTML;

            button.setAttribute(
                "data-original-html",
                originalHTML
            );

            button.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Loading...';

        });

    });


    /* =====================================================
       KITCHEN CARD HOVER
    ===================================================== */

    kitchenCards.forEach(function (card) {

        card.addEventListener("mouseenter", function () {

            card.classList.add("kitchen-card-active");

        });

        card.addEventListener("mouseleave", function () {

            card.classList.remove("kitchen-card-active");

        });

    });


    /* =====================================================
       AUTO REFRESH
       Only enabled when explicitly requested.
       
       Add:
       data-kitchen-auto-refresh="true"
       
       to the kitchen page/container.
    ===================================================== */

    const kitchenContainer =
        document.querySelector(
            "[data-kitchen-auto-refresh]"
        );

    if (kitchenContainer) {

        const autoRefresh =
            kitchenContainer.getAttribute(
                "data-kitchen-auto-refresh"
            );

        const refreshSeconds =
            parseInt(
                kitchenContainer.getAttribute(
                    "data-refresh-seconds"
                ) || "30",
                10
            );

        if (
            autoRefresh === "true" &&
            refreshSeconds > 0
        ) {

            let countdown =
                refreshSeconds;

            const refreshIndicator =
                document.querySelector(
                    "[data-kitchen-refresh-countdown]"
                );


            const refreshTimer =
                setInterval(function () {

                    countdown--;

                    if (refreshIndicator) {

                        refreshIndicator.textContent =
                            countdown + "s";

                    }

                    if (countdown <= 0) {

                        clearInterval(refreshTimer);

                        window.location.reload();

                    }

                }, 1000);

        }

    }


    /* =====================================================
       MANUAL REFRESH BUTTON
    ===================================================== */

    document.querySelectorAll(
        "[data-kitchen-refresh]"
    ).forEach(function (button) {

        button.addEventListener("click", function (event) {

            event.preventDefault();

            const icon =
                button.querySelector("i");

            const originalHTML =
                button.innerHTML;

            button.disabled = true;

            button.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Refreshing...';

            setTimeout(function () {

                window.location.reload();

            }, 500);

        });

    });


    /* =====================================================
       STATUS FILTER
    ===================================================== */

    document.querySelectorAll(
        "[data-kitchen-filter]"
    ).forEach(function (filter) {

        filter.addEventListener("change", function () {

            const selectedStatus =
                filter.value.trim();

            if (selectedStatus === "") {
                return;
            }

            const url =
                new URL(window.location.href);

            url.searchParams.set(
                "status",
                selectedStatus
            );

            window.location.href =
                url.toString();

        });

    });


    /* =====================================================
       ORDER CARD CLICK
    ===================================================== */

    kitchenCards.forEach(function (card) {

        card.addEventListener("click", function (event) {

            /*
             * Do not trigger when clicking
             * buttons or links.
             */

            if (
                event.target.closest("a") ||
                event.target.closest("button") ||
                event.target.closest("input") ||
                event.target.closest("select")
            ) {
                return;
            }

            const orderLink =
                card.querySelector(
                    ".kitchen-actions a"
                );

            if (orderLink) {

                const href =
                    orderLink.getAttribute("href");

                if (href) {
                    window.location.href = href;
                }

            }

        });

    });


    /* =====================================================
       KITCHEN NOTES
    ===================================================== */

    document.querySelectorAll(
        ".notes-box"
    ).forEach(function (notes) {

        const text =
            notes.querySelector("p");

        if (!text) {
            return;
        }

        if (text.textContent.trim() === "") {

            notes.style.display = "none";

        }

    });


    /* =====================================================
       ORDER ITEM QUANTITY
    ===================================================== */

    document.querySelectorAll(
        ".item-list li"
    ).forEach(function (item) {

        item.classList.add(
            "kitchen-item"
        );

    });


    /* =====================================================
       STATUS BADGE ANIMATION
    ===================================================== */

    document.querySelectorAll(
        ".status-badge"
    ).forEach(function (badge) {

        const status =
            badge.textContent
                .trim()
                .toLowerCase();

        if (status === "pending") {

            badge.classList.add(
                "status-pending"
            );

        }

        else if (status === "preparing") {

            badge.classList.add(
                "status-preparing"
            );

        }

        else if (status === "ready") {

            badge.classList.add(
                "status-ready"
            );

        }

    });


    /* =====================================================
       CURRENT TIME DISPLAY
    ===================================================== */

    const kitchenClock =
        document.querySelector(
            "[data-kitchen-clock]"
        );

    if (kitchenClock) {

        function updateClock() {

            const now =
                new Date();

            kitchenClock.textContent =
                now.toLocaleTimeString(
                    "en-IN",
                    {
                        hour: "2-digit",
                        minute: "2-digit",
                        second: "2-digit"
                    }
                );

        }

        updateClock();

        setInterval(
            updateClock,
            1000
        );

    }


    /* =====================================================
       PREVENT DOUBLE CLICK
    ===================================================== */

    document.querySelectorAll(
        ".kitchen-actions"
    ).forEach(function (actionContainer) {

        actionContainer
            .querySelectorAll("a, button")
            .forEach(function (button) {

                button.addEventListener(
                    "dblclick",
                    function (event) {

                        event.preventDefault();

                    }
                );

            });

    });


    /* =====================================================
       KEYBOARD SHORTCUT
       R = Refresh Kitchen
    ===================================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            /*
             * Don't trigger while typing.
             */

            const activeElement =
                document.activeElement;

            const isTyping =
                activeElement &&
                (
                    activeElement.tagName === "INPUT" ||
                    activeElement.tagName === "TEXTAREA" ||
                    activeElement.tagName === "SELECT"
                );

            if (isTyping) {
                return;
            }


            if (
                event.key.toLowerCase() === "r"
            ) {

                const refreshButton =
                    document.querySelector(
                        "[data-kitchen-refresh]"
                    );

                if (refreshButton) {

                    refreshButton.click();

                }

            }

        }
    );


    /* =====================================================
       PAGE READY
    ===================================================== */

    document.body.classList.add(
        "kitchen-js-ready"
    );

});