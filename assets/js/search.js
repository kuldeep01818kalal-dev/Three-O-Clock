/* =========================================================
   THREE O' CLOCK CAFE
   SEARCH JAVASCRIPT
   Live Search + Filtering
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       SEARCH INPUTS
    ===================================================== */

    const searchInputs = document.querySelectorAll(
        "[data-search-input], .search-input"
    );

    searchInputs.forEach(function (input) {

        const targetSelector =
            input.getAttribute("data-search-target");

        if (!targetSelector) {
            return;
        }

        const targetContainer =
            document.querySelector(targetSelector);

        if (!targetContainer) {
            return;
        }

        const items =
            targetContainer.querySelectorAll(
                "[data-search-item], .search-item"
            );

        const noResults =
            document.querySelector(
                input.getAttribute("data-no-results") || ""
            );


        /* =================================================
           FILTER ITEMS
        ================================================= */

        function performSearch() {

            const searchValue =
                input.value.trim().toLowerCase();

            let visibleCount = 0;

            items.forEach(function (item) {

                const searchableText =
                    (
                        item.getAttribute("data-search-text") ||
                        item.textContent ||
                        ""
                    ).toLowerCase();

                if (
                    searchValue === "" ||
                    searchableText.includes(searchValue)
                ) {

                    item.style.display = "";

                    visibleCount++;

                } else {

                    item.style.display = "none";

                }

            });


            /* =============================================
               NO RESULTS
            ============================================= */

            if (noResults) {

                if (visibleCount === 0 && searchValue !== "") {

                    noResults.style.display = "";

                } else {

                    noResults.style.display = "none";

                }

            }


            /* =============================================
               SEARCH EVENT
            ============================================= */

            input.dispatchEvent(
                new CustomEvent("adminSearch", {
                    detail: {
                        value: searchValue,
                        visibleCount: visibleCount
                    }
                })
            );

        }


        /* =================================================
           INPUT EVENT
        ================================================= */

        input.addEventListener("input", performSearch);


        /* =================================================
           ENTER KEY
        ================================================= */

        input.addEventListener("keydown", function (event) {

            if (event.key === "Enter") {

                event.preventDefault();

                performSearch();

            }

        });


        /* =================================================
           INITIAL SEARCH
        ================================================= */

        if (input.value.trim() !== "") {
            performSearch();
        }

    });


    /* =====================================================
       CLEAR SEARCH BUTTON
    ===================================================== */

    document.querySelectorAll(
        "[data-search-clear]"
    ).forEach(function (button) {

        button.addEventListener("click", function (event) {

            event.preventDefault();

            const targetId =
                button.getAttribute("data-search-clear");

            const input =
                document.getElementById(targetId);

            if (!input) {
                return;
            }

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
       SIMPLE TABLE SEARCH
       Can be used without data-search-item
    ===================================================== */

    document.querySelectorAll(
        "[data-table-search]"
    ).forEach(function (input) {

        const tableSelector =
            input.getAttribute("data-table-search");

        const table =
            document.querySelector(tableSelector);

        if (!table) {
            return;
        }

        const rows =
            table.querySelectorAll("tbody tr");

        const noResultsId =
            input.getAttribute("data-no-results");

        const noResults =
            noResultsId
                ? document.querySelector(noResultsId)
                : null;


        function filterTable() {

            const searchValue =
                input.value.trim().toLowerCase();

            let visibleRows = 0;

            rows.forEach(function (row) {

                const rowText =
                    row.textContent.toLowerCase();

                if (
                    searchValue === "" ||
                    rowText.includes(searchValue)
                ) {

                    row.style.display = "";

                    visibleRows++;

                } else {

                    row.style.display = "none";

                }

            });


            if (noResults) {

                if (
                    visibleRows === 0 &&
                    searchValue !== ""
                ) {

                    noResults.style.display = "";

                } else {

                    noResults.style.display = "none";

                }

            }

        }


        input.addEventListener(
            "input",
            filterTable
        );

    });


    /* =====================================================
       CATEGORY FILTER
    ===================================================== */

    document.querySelectorAll(
        "[data-category-filter]"
    ).forEach(function (select) {

        const targetSelector =
            select.getAttribute("data-category-filter");

        const container =
            document.querySelector(targetSelector);

        if (!container) {
            return;
        }

        const items =
            container.querySelectorAll(
                "[data-category]"
            );


        function filterCategory() {

            const selectedCategory =
                select.value.toLowerCase();

            items.forEach(function (item) {

                const category =
                    (
                        item.getAttribute("data-category") ||
                        ""
                    ).toLowerCase();

                if (
                    selectedCategory === "" ||
                    selectedCategory === "all" ||
                    category === selectedCategory
                ) {

                    item.style.display = "";

                } else {

                    item.style.display = "none";

                }

            });

        }


        select.addEventListener(
            "change",
            filterCategory
        );

    });


    /* =====================================================
       STATUS FILTER
    ===================================================== */

    document.querySelectorAll(
        "[data-status-filter]"
    ).forEach(function (select) {

        const targetSelector =
            select.getAttribute("data-status-filter");

        const container =
            document.querySelector(targetSelector);

        if (!container) {
            return;
        }

        const items =
            container.querySelectorAll(
                "[data-status]"
            );


        function filterStatus() {

            const selectedStatus =
                select.value.toLowerCase();

            items.forEach(function (item) {

                const status =
                    (
                        item.getAttribute("data-status") ||
                        ""
                    ).toLowerCase();

                if (
                    selectedStatus === "" ||
                    selectedStatus === "all" ||
                    status === selectedStatus
                ) {

                    item.style.display = "";

                } else {

                    item.style.display = "none";

                }

            });

        }


        select.addEventListener(
            "change",
            filterStatus
        );

    });


    /* =====================================================
       SEARCH FORM - EMPTY SEARCH PROTECTION
    ===================================================== */

    document.querySelectorAll(
        "form[data-search-form]"
    ).forEach(function (form) {

        form.addEventListener("submit", function (event) {

            const input =
                form.querySelector(
                    "input[type='search'], input[name='search'], .search-input"
                );

            if (!input) {
                return;
            }

            if (input.value.trim() === "") {

                event.preventDefault();

                input.focus();

            }

        });

    });


    /* =====================================================
       ESCAPE KEY - CLEAR SEARCH
    ===================================================== */

    document.querySelectorAll(
        "[data-search-input], .search-input"
    ).forEach(function (input) {

        input.addEventListener("keydown", function (event) {

            if (event.key === "Escape") {

                input.value = "";

                input.dispatchEvent(
                    new Event("input", {
                        bubbles: true
                    })
                );

                input.blur();

            }

        });

    });

});