/* =========================================================
   THREE O' CLOCK CAFE
   BILLING / POS JAVASCRIPT
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    "use strict";


    /* =====================================================
       CONFIGURATION
    ===================================================== */

    const GST_RATE = 5;


    /* =====================================================
       ELEMENTS
    ===================================================== */

    const cartContainer =
        document.querySelector("[data-billing-cart]") ||
        document.querySelector("#billingCart") ||
        document.querySelector(".billing-cart");

    const subtotalElement =
        document.querySelector("[data-subtotal]") ||
        document.querySelector("#subtotal");

    const discountElement =
        document.querySelector("[data-discount]") ||
        document.querySelector("#discount");

    const taxElement =
        document.querySelector("[data-tax]") ||
        document.querySelector("#tax");

    const deliveryElement =
        document.querySelector("[data-delivery-charge]") ||
        document.querySelector("#deliveryCharge");

    const grandTotalElement =
        document.querySelector("[data-grand-total]") ||
        document.querySelector("#grandTotal");

    const discountInput =
        document.querySelector("[data-discount-input]") ||
        document.querySelector("#discountInput");

    const deliveryInput =
        document.querySelector("[data-delivery-input]") ||
        document.querySelector("#deliveryChargeInput");

    const paymentMethod =
        document.querySelector("[data-payment-method]") ||
        document.querySelector("#paymentMethod");

    const orderType =
        document.querySelector("[data-order-type]") ||
        document.querySelector("#orderType");


    /* =====================================================
       NUMBER FORMAT
    ===================================================== */

    function formatMoney(value) {

        const number =
            Number(value) || 0;

        return "₹" +
            number.toFixed(2);

    }


    /* =====================================================
       PARSE NUMBER
    ===================================================== */

    function parseNumber(value) {

        const number =
            parseFloat(value);

        return isNaN(number)
            ? 0
            : number;

    }


    /* =====================================================
       GET CART ITEMS
    ===================================================== */

    function getCartItems() {

        if (!cartContainer) {
            return [];
        }

        return Array.from(
            cartContainer.querySelectorAll(
                "[data-cart-item], .cart-item"
            )
        );

    }


    /* =====================================================
       GET ITEM PRICE
    ===================================================== */

    function getItemPrice(item) {

        const price =
            item.getAttribute("data-price");

        if (price !== null) {
            return parseNumber(price);
        }


        const priceElement =
            item.querySelector(
                "[data-item-price], .item-price"
            );

        if (priceElement) {

            return parseNumber(
                priceElement.textContent
                    .replace(/[₹,]/g, "")
            );

        }

        return 0;

    }


    /* =====================================================
       GET ITEM QUANTITY
    ===================================================== */

    function getItemQuantity(item) {

        const quantityInput =
            item.querySelector(
                "[data-item-quantity], .item-quantity"
            );

        if (quantityInput) {
            return Math.max(
                1,
                parseInt(quantityInput.value, 10) || 1
            );
        }


        const quantity =
            item.getAttribute("data-quantity");

        return Math.max(
            1,
            parseInt(quantity, 10) || 1
        );

    }


    /* =====================================================
       UPDATE ITEM TOTAL
    ===================================================== */

    function updateItemTotal(item) {

        const price =
            getItemPrice(item);

        const quantity =
            getItemQuantity(item);

        const total =
            price * quantity;

        const totalElement =
            item.querySelector(
                "[data-item-total], .item-total"
            );

        if (totalElement) {

            totalElement.textContent =
                formatMoney(total);

        }

        item.setAttribute(
            "data-quantity",
            quantity
        );

        return total;

    }


    /* =====================================================
       CALCULATE BILL
    ===================================================== */

    function calculateBill() {

        const items =
            getCartItems();

        let subtotal = 0;


        items.forEach(function (item) {

            const itemTotal =
                updateItemTotal(item);

            subtotal += itemTotal;

        });


        /* =============================================
           DISCOUNT
        ============================================= */

        let discount = 0;

        if (discountInput) {

            discount =
                Math.max(
                    0,
                    parseNumber(
                        discountInput.value
                    )
                );

        }


        /*
         * Discount cannot exceed subtotal.
         */

        discount =
            Math.min(
                discount,
                subtotal
            );


        /* =============================================
           TAXABLE AMOUNT
        ============================================= */

        const taxableAmount =
            Math.max(
                0,
                subtotal - discount
            );


        /* =============================================
           GST
        ============================================= */

        const tax =
            taxableAmount *
            GST_RATE /
            100;


        /* =============================================
           DELIVERY
        ============================================= */

        let deliveryCharge = 0;

        if (deliveryInput) {

            deliveryCharge =
                Math.max(
                    0,
                    parseNumber(
                        deliveryInput.value
                    )
                );

        }


        /*
         * For dine-in orders delivery charge
         * should normally be zero.
         */

        if (
            orderType &&
            orderType.value.toLowerCase()
                .includes("dine")
        ) {

            deliveryCharge = 0;

        }


        /* =============================================
           GRAND TOTAL
        ============================================= */

        const grandTotal =
            taxableAmount +
            tax +
            deliveryCharge;


        /* =============================================
           UPDATE UI
        ============================================= */

        if (subtotalElement) {

            subtotalElement.textContent =
                formatMoney(subtotal);

        }

        if (discountElement) {

            discountElement.textContent =
                formatMoney(discount);

        }

        if (taxElement) {

            taxElement.textContent =
                formatMoney(tax);

        }

        if (deliveryElement) {

            deliveryElement.textContent =
                formatMoney(deliveryCharge);

        }

        if (grandTotalElement) {

            grandTotalElement.textContent =
                formatMoney(grandTotal);

        }


        /* =============================================
           DATA ATTRIBUTES
        ============================================= */

        if (cartContainer) {

            cartContainer.setAttribute(
                "data-subtotal",
                subtotal.toFixed(2)
            );

            cartContainer.setAttribute(
                "data-discount",
                discount.toFixed(2)
            );

            cartContainer.setAttribute(
                "data-tax",
                tax.toFixed(2)
            );

            cartContainer.setAttribute(
                "data-delivery",
                deliveryCharge.toFixed(2)
            );

            cartContainer.setAttribute(
                "data-grand-total",
                grandTotal.toFixed(2)
            );

        }


        return {
            subtotal: subtotal,
            discount: discount,
            tax: tax,
            deliveryCharge: deliveryCharge,
            grandTotal: grandTotal
        };

    }


    /* =====================================================
       QUANTITY PLUS
    ===================================================== */

    document.querySelectorAll(
        "[data-quantity-plus]"
    ).forEach(function (button) {

        button.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                const item =
                    button.closest(
                        "[data-cart-item], .cart-item"
                    );

                if (!item) {
                    return;
                }

                const input =
                    item.querySelector(
                        "[data-item-quantity], .item-quantity"
                    );

                if (!input) {
                    return;
                }

                const max =
                    parseInt(
                        input.getAttribute("max"),
                        10
                    );

                let value =
                    parseInt(input.value, 10) || 1;

                value++;


                if (!isNaN(max)) {
                    value = Math.min(value, max);
                }

                input.value = value;

                calculateBill();

            }
        );

    });


    /* =====================================================
       QUANTITY MINUS
    ===================================================== */

    document.querySelectorAll(
        "[data-quantity-minus]"
    ).forEach(function (button) {

        button.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                const item =
                    button.closest(
                        "[data-cart-item], .cart-item"
                    );

                if (!item) {
                    return;
                }

                const input =
                    item.querySelector(
                        "[data-item-quantity], .item-quantity"
                    );

                if (!input) {
                    return;
                }

                let value =
                    parseInt(input.value, 10) || 1;

                value--;

                value =
                    Math.max(
                        1,
                        value
                    );

                input.value =
                    value;

                calculateBill();

            }
        );

    });


    /* =====================================================
       MANUAL QUANTITY CHANGE
    ===================================================== */

    document.querySelectorAll(
        "[data-item-quantity], .item-quantity"
    ).forEach(function (input) {

        input.addEventListener(
            "input",
            function () {

                let value =
                    parseInt(
                        input.value,
                        10
                    );

                if (
                    isNaN(value) ||
                    value < 1
                ) {

                    value = 1;

                }


                const max =
                    parseInt(
                        input.getAttribute("max"),
                        10
                    );

                if (!isNaN(max)) {

                    value =
                        Math.min(
                            value,
                            max
                        );

                }

                input.value =
                    value;

                calculateBill();

            }
        );

    });


    /* =====================================================
       REMOVE ITEM
    ===================================================== */

    document.querySelectorAll(
        "[data-remove-item]"
    ).forEach(function (button) {

        button.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                const item =
                    button.closest(
                        "[data-cart-item], .cart-item"
                    );

                if (!item) {
                    return;
                }


                const productName =
                    item.getAttribute(
                        "data-product-name"
                    ) ||
                    item.querySelector(
                        ".product-name"
                    )?.textContent?.trim() ||
                    "this item";


                if (
                    !confirm(
                        "Remove " +
                        productName +
                        " from the bill?"
                    )
                ) {

                    return;

                }


                item.remove();

                calculateBill();

                checkEmptyCart();

            }
        );

    });


    /* =====================================================
       EMPTY CART CHECK
    ===================================================== */

    function checkEmptyCart() {

        const items =
            getCartItems();

        const emptyMessage =
            document.querySelector(
                "[data-empty-cart]"
            );

        if (!emptyMessage) {
            return;
        }

        if (items.length === 0) {

            emptyMessage.style.display =
                "";

        } else {

            emptyMessage.style.display =
                "none";

        }

    }


    /* =====================================================
       DISCOUNT CHANGE
    ===================================================== */

    if (discountInput) {

        discountInput.addEventListener(
            "input",
            function () {

                if (
                    parseNumber(
                        discountInput.value
                    ) < 0
                ) {

                    discountInput.value = 0;

                }

                calculateBill();

            }
        );

    }


    /* =====================================================
       DELIVERY CHARGE CHANGE
    ===================================================== */

    if (deliveryInput) {

        deliveryInput.addEventListener(
            "input",
            function () {

                if (
                    parseNumber(
                        deliveryInput.value
                    ) < 0
                ) {

                    deliveryInput.value = 0;

                }

                calculateBill();

            }
        );

    }


    /* =====================================================
       ORDER TYPE
    ===================================================== */

    if (orderType) {

        orderType.addEventListener(
            "change",
            function () {

                const selected =
                    orderType.value
                        .toLowerCase();

                /*
                 * Dine-in should not have
                 * delivery charges.
                 */

                if (
                    selected.includes("dine")
                ) {

                    if (deliveryInput) {

                        deliveryInput.value =
                            "0";

                        deliveryInput.disabled =
                            true;

                    }

                } else {

                    if (deliveryInput) {

                        deliveryInput.disabled =
                            false;

                    }

                }

                calculateBill();

            }
        );

    }


    /* =====================================================
       PAYMENT METHOD
    ===================================================== */

    if (paymentMethod) {

        paymentMethod.addEventListener(
            "change",
            function () {

                const selected =
                    paymentMethod.value;

                document.dispatchEvent(
                    new CustomEvent(
                        "billingPaymentMethodChanged",
                        {
                            detail: {
                                method: selected
                            }
                        }
                    )
                );

            }
        );

    }


    /* =====================================================
       GENERATE BILL BUTTON
    ===================================================== */

    document.querySelectorAll(
        "[data-generate-bill]"
    ).forEach(function (button) {

        button.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                const bill =
                    calculateBill();


                if (
                    getCartItems().length === 0
                ) {

                    alert(
                        "Please add at least one item to the bill."
                    );

                    return;

                }


                /*
                 * Store calculated values so the
                 * existing PHP form can use them.
                 */

                const hiddenFields = {
                    subtotal:
                        bill.subtotal.toFixed(2),

                    discount:
                        bill.discount.toFixed(2),

                    tax:
                        bill.tax.toFixed(2),

                    delivery_charge:
                        bill.deliveryCharge.toFixed(2),

                    grand_total:
                        bill.grandTotal.toFixed(2)
                };


                const form =
                    button.closest("form") ||
                    document.querySelector(
                        "[data-billing-form]"
                    );


                if (form) {

                    Object.keys(
                        hiddenFields
                    ).forEach(function (name) {

                        let input =
                            form.querySelector(
                                "[name='" +
                                name +
                                "']"
                            );


                        if (!input) {

                            input =
                                document.createElement(
                                    "input"
                                );

                            input.type =
                                "hidden";

                            input.name =
                                name;

                            form.appendChild(
                                input
                            );

                        }

                        input.value =
                            hiddenFields[name];

                    });

                }


                /*
                 * Allow normal form submission
                 * after calculation.
                 */

                if (form) {

                    button.disabled =
                        true;

                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2"></span>' +
                        'Generating...';

                    form.submit();

                } else {

                    /*
                     * If there is no form,
                     * keep the calculated bill
                     * available for existing UI.
                     */

                    document.dispatchEvent(
                        new CustomEvent(
                            "billingGenerated",
                            {
                                detail: bill
                            }
                        )
                    );

                }

            }
        );

    });


    /* =====================================================
       PRINT BILL
    ===================================================== */

    document.querySelectorAll(
        "[data-print-bill]"
    ).forEach(function (button) {

        button.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                calculateBill();

                window.print();

            }
        );

    });


    /* =====================================================
       INITIAL CALCULATION
    ===================================================== */

    calculateBill();

    checkEmptyCart();


    /* =====================================================
       BILLING READY
    ===================================================== */

    document.body.classList.add(
        "billing-js-ready"
    );

});