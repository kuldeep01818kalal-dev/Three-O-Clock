/**
 * Three-O-Clock Cafe
 * Menu Page JavaScript
 *
 * Handles:
 * - Menu card hover effects
 * - Search/filter form UX
 * - Clear search
 * - Category / food-type filter changes
 * - Menu sorting support
 * - Wishlist buttons
 * - Add-to-cart loading state
 * - View switching
 * - Empty result handling
 * - Smooth interactions
 */

document.addEventListener("DOMContentLoaded", function () {

    "use strict";

    /* ==========================================
       MENU ELEMENTS
    ========================================== */

    const menuPage =
        document.querySelector(".menu-page") ||
        document.querySelector(".container");

    const menuCards = document.querySelectorAll(
        ".menu-card, .card.h-100"
    );

    const searchInput =
        document.querySelector("[data-menu-search]") ||
        document.querySelector('input[name="search"]') ||
        document.querySelector(".menu-search input");

    const categoryFilter =
        document.querySelector("[data-menu-category]") ||
        document.querySelector('select[name="category"]');

    const foodTypeFilter =
        document.querySelector("[data-menu-food-type]") ||
        document.querySelector('select[name="food_type"]');

    const searchForm =
        document.querySelector("[data-menu-form]") ||
        (searchInput ? searchInput.closest("form") : null);

    /* ==========================================
       MENU CARD HOVER
    ========================================== */

    menuCards.forEach(function (card) {

        card.addEventListener("mouseenter", function () {
            card.classList.add("menu-card-hover");
        });

        card.addEventListener("mouseleave", function () {
            card.classList.remove("menu-card-hover");
        });

    });


    /* ==========================================
       SEARCH FORM
    ========================================== */

    if (searchForm) {

        searchForm.addEventListener("submit", function (event) {

            if (searchInput) {

                const value = searchInput.value.trim();

                searchInput.value = value;

            }

        });

    }


    /* ==========================================
       SEARCH INPUT - ESCAPE TO CLEAR
    ========================================== */

    if (searchInput) {

        searchInput.addEventListener("keydown", function (event) {

            if (event.key === "Escape") {

                searchInput.value = "";

                searchInput.focus();

            }

        });

    }


    /* ==========================================
       FILTER CHANGE
    ========================================== */

    function submitMenuFilters() {

        if (!searchForm) {
            return;
        }

        /*
         * Reset pagination when filters change.
         * The existing menu.php uses GET parameters.
         */

        let pageInput = searchForm.querySelector(
            'input[name="page"]'
        );

        if (!pageInput) {

            pageInput = document.createElement("input");

            pageInput.type = "hidden";
            pageInput.name = "page";
            pageInput.value = "1";

            searchForm.appendChild(pageInput);

        } else {

            pageInput.value = "1";

        }

        searchForm.submit();

    }


    if (categoryFilter) {

        categoryFilter.addEventListener("change", function () {

            /*
             * Only auto-submit when explicitly enabled.
             * This prevents changing the existing UX unexpectedly.
             */

            if (
                categoryFilter.hasAttribute("data-auto-submit") ||
                categoryFilter.dataset.autoSubmit === "true"
            ) {
                submitMenuFilters();
            }

        });

    }


    if (foodTypeFilter) {

        foodTypeFilter.addEventListener("change", function () {

            if (
                foodTypeFilter.hasAttribute("data-auto-submit") ||
                foodTypeFilter.dataset.autoSubmit === "true"
            ) {
                submitMenuFilters();
            }

        });

    }


    /* ==========================================
       CLEAR SEARCH BUTTON
    ========================================== */

    const clearSearchButtons = document.querySelectorAll(
        "[data-menu-clear-search], .menu-clear-search"
    );

    clearSearchButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            event.preventDefault();

            if (searchInput) {
                searchInput.value = "";
                searchInput.focus();
            }

            if (categoryFilter) {
                categoryFilter.value = "";
            }

            if (foodTypeFilter) {
                foodTypeFilter.value = "";
            }

        });

    });


    /* ==========================================
       ADD TO CART BUTTON
    ========================================== */

    const addToCartButtons = document.querySelectorAll(
        '.menu-add-cart, a[href*="cart.php?action=add"], [data-add-cart]'
    );

    addToCartButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            /*
             * Prevent accidental double clicking.
             */

            if (button.dataset.processing === "true") {
                return;
            }

            button.dataset.processing = "true";

            const originalHTML = button.innerHTML;

            button.classList.add("loading");

            /*
             * Keep navigation working for normal anchor links.
             * Restore button state shortly after.
             */

            setTimeout(function () {

                button.classList.remove("loading");
                button.dataset.processing = "false";

            }, 1200);

            /*
             * Store original content for buttons that remain
             * on the current page.
             */

            button.dataset.originalHtml = originalHTML;

        });

    });


    /* ==========================================
       WISHLIST BUTTONS
    ========================================== */

    const wishlistButtons = document.querySelectorAll(
        ".menu-wishlist, [data-menu-wishlist]"
    );

    wishlistButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            event.preventDefault();
            event.stopPropagation();

            button.classList.toggle("active");

            const icon = button.querySelector("i");

            if (icon) {

                if (button.classList.contains("active")) {

                    icon.classList.remove("bi-heart");
                    icon.classList.add("bi-heart-fill");

                } else {

                    icon.classList.remove("bi-heart-fill");
                    icon.classList.add("bi-heart");

                }

            }

        });

    });


    /* ==========================================
       MENU VIEW SWITCH
    ========================================== */

    const viewButtons = document.querySelectorAll(
        "[data-menu-view], .menu-view-btn"
    );

    const menuGrid =
        document.querySelector(".menu-grid") ||
        document.querySelector(".row");

    viewButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            const view = button.dataset.menuView;

            if (!view || !menuGrid) {
                return;
            }

            viewButtons.forEach(function (item) {
                item.classList.remove("active");
            });

            button.classList.add("active");

            if (view === "list") {

                menuGrid.classList.add("menu-list-view");

            } else {

                menuGrid.classList.remove("menu-list-view");

            }

        });

    });


    /* ==========================================
       SORTING SUPPORT
    ========================================== */

    const sortSelect =
        document.querySelector("[data-menu-sort]") ||
        document.querySelector(".menu-sort select");

    if (sortSelect) {

        sortSelect.addEventListener("change", function () {

            const value = sortSelect.value;

            if (!value) {
                return;
            }

            /*
             * If a real server-side sort is added later,
             * the select can use data-sort-url.
             */

            const sortURL = sortSelect.dataset.sortUrl;

            if (sortURL) {

                const separator =
                    sortURL.includes("?") ? "&" : "?";

                window.location.href =
                    sortURL +
                    separator +
                    "sort=" +
                    encodeURIComponent(value);

            }

        });

    }


    /* ==========================================
       PAGINATION LOADING STATE
    ========================================== */

    const paginationLinks = document.querySelectorAll(
        ".pagination a.page-link"
    );

    paginationLinks.forEach(function (link) {

        link.addEventListener("click", function () {

            if (
                link.closest(".page-item") &&
                link.closest(".page-item").classList.contains("disabled")
            ) {
                return;
            }

            link.classList.add("menu-pagination-loading");

        });

    });


    /* ==========================================
       IMAGE ERROR HANDLING
    ========================================== */

    const menuImages = document.querySelectorAll(
        ".menu-card-image img, .card img.card-img-top"
    );

    menuImages.forEach(function (image) {

        image.addEventListener("error", function () {

            image.classList.add("menu-image-error");

            /*
             * Avoid an infinite loop if the fallback
             * image itself is missing.
             */

            if (
                !image.dataset.fallbackApplied &&
                !image.src.includes("no-image.png")
            ) {

                image.dataset.fallbackApplied = "true";

                image.src = "assets/images/no-image.png";

            }

        });

    });


    /* ==========================================
       EMPTY MENU STATE
    ========================================== */

    const emptyMenu =
        document.querySelector(".menu-empty") ||
        document.querySelector(".menu-empty-icon");

    if (emptyMenu) {

        document.body.classList.add("menu-empty-state");

    }


    /* ==========================================
       SCROLL TO RESULTS AFTER FILTER
    ========================================== */

    const urlParams = new URLSearchParams(window.location.search);

    const hasMenuFilter =
        urlParams.has("search") ||
        urlParams.has("category") ||
        urlParams.has("food_type");

    if (hasMenuFilter) {

        const resultsHeading =
            document.querySelector(".menu-toolbar") ||
            document.querySelector(".menu-grid") ||
            document.querySelector(".row");

        /*
         * Only scroll when the user actually used a filter.
         * Do not force scroll on a normal menu visit.
         */

        if (resultsHeading && window.innerWidth < 768) {

            setTimeout(function () {

                resultsHeading.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });

            }, 100);

        }

    }


    /* ==========================================
       MOBILE FILTER UX
    ========================================== */

    const filterToggle =
        document.querySelector("[data-menu-filter-toggle]");

    const filterPanel =
        document.querySelector("[data-menu-filter-panel]");

    if (filterToggle && filterPanel) {

        filterToggle.addEventListener("click", function () {

            filterPanel.classList.toggle("show");

            const expanded =
                filterPanel.classList.contains("show");

            filterToggle.setAttribute(
                "aria-expanded",
                expanded ? "true" : "false"
            );

        });

    }


    /* ==========================================
       FOCUS SEARCH WITH /
    ========================================== */

    document.addEventListener("keydown", function (event) {

        /*
         * "/" focuses the search field.
         * Don't trigger while typing in another input.
         */

        const activeElement = document.activeElement;

        const isTyping =
            activeElement &&
            (
                activeElement.tagName === "INPUT" ||
                activeElement.tagName === "TEXTAREA" ||
                activeElement.tagName === "SELECT"
            );

        if (
            event.key === "/" &&
            searchInput &&
            !isTyping
        ) {

            event.preventDefault();

            searchInput.focus();

        }

    });


    /* ==========================================
       BUTTON DOUBLE-CLICK PROTECTION
    ========================================== */

    const actionButtons = document.querySelectorAll(
        ".menu-card-actions button, .menu-card-actions a"
    );

    actionButtons.forEach(function (button) {

        button.addEventListener("dblclick", function (event) {

            event.preventDefault();

        });

    });


    /* ==========================================
       PAGE READY MARKER
    ========================================== */

    if (menuPage) {

        menuPage.classList.add("menu-js-ready");

    }

    document.body.classList.add("menu-js-ready");


    console.log("Three-O-Clock menu.js loaded successfully.");

});