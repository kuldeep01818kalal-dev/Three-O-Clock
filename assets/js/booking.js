/**
 * Three-O-Clock Cafe
 * Booking / Reservation JavaScript
 *
 * Handles:
 * - Booking form validation
 * - Date validation
 * - Time validation
 * - Guest quantity controls
 * - Minimum booking date
 * - Minimum booking time
 * - Duplicate submission protection
 * - Loading state
 * - Reset handling
 * - Dynamic table availability support
 */

document.addEventListener("DOMContentLoaded", function () {

    "use strict";

    /* ==========================================
       BOOKING FORM
    ========================================== */

    const bookingForm =
        document.querySelector("[data-booking-form]") ||
        document.querySelector("#bookingForm") ||
        document.querySelector(".booking-form form") ||
        document.querySelector("form.booking-form");


    if (!bookingForm) {
        document.body.classList.add("booking-js-ready");
        return;
    }


    /* ==========================================
       FORM ELEMENTS
    ========================================== */

    const dateInput =
        bookingForm.querySelector(
            '[name="booking_date"], [name="date"], [data-booking-date]'
        );

    const timeInput =
        bookingForm.querySelector(
            '[name="booking_time"], [name="time"], [data-booking-time]'
        );

    const guestsInput =
        bookingForm.querySelector(
            '[name="guests"], [name="guest_count"], [name="number_of_guests"], [data-guests]'
        );

    const tableInput =
        bookingForm.querySelector(
            '[name="table_id"], [data-table-id]'
        );

    const submitButton =
        bookingForm.querySelector(
            'button[type="submit"], input[type="submit"]'
        );


    /* ==========================================
       SET MINIMUM DATE
    ========================================== */

    if (dateInput) {

        const today = new Date();

        const year = today.getFullYear();

        const month = String(
            today.getMonth() + 1
        ).padStart(2, "0");

        const day = String(
            today.getDate()
        ).padStart(2, "0");

        const todayString =
            `${year}-${month}-${day}`;

        /*
         * Do not overwrite an existing
         * stricter minimum date.
         */

        if (
            !dateInput.min ||
            dateInput.min < todayString
        ) {
            dateInput.min = todayString;
        }

    }


    /* ==========================================
       DATE VALIDATION
    ========================================== */

    function validateDate() {

        if (!dateInput || !dateInput.value) {
            return true;
        }

        const selectedDate =
            new Date(dateInput.value + "T00:00:00");

        const today = new Date();

        today.setHours(0, 0, 0, 0);

        if (selectedDate < today) {

            showFieldError(
                dateInput,
                "Please select today or a future date."
            );

            return false;
        }

        clearFieldError(dateInput);

        return true;
    }


    /* ==========================================
       TIME VALIDATION
    ========================================== */

    function validateTime() {

        if (
            !timeInput ||
            !timeInput.value ||
            !dateInput ||
            !dateInput.value
        ) {
            return true;
        }

        const selectedDate =
            new Date(
                `${dateInput.value}T${timeInput.value}`
            );

        const now = new Date();

        /*
         * If booking is for today,
         * selected time cannot be in the past.
         */

        const selectedDateOnly =
            dateInput.value;

        const today = new Date();

        const todayString =
            `${today.getFullYear()}-${String(
                today.getMonth() + 1
            ).padStart(2, "0")}-${String(
                today.getDate()
            ).padStart(2, "0")}`;

        if (
            selectedDateOnly === todayString &&
            selectedDate <= now
        ) {

            showFieldError(
                timeInput,
                "Please select a future time."
            );

            return false;
        }

        clearFieldError(timeInput);

        return true;
    }


    /* ==========================================
       GUEST VALIDATION
    ========================================== */

    function validateGuests() {

        if (!guestsInput || !guestsInput.value) {
            return true;
        }

        let guests =
            parseInt(guestsInput.value, 10);

        if (Number.isNaN(guests)) {
            guests = 1;
        }

        const min =
            parseInt(
                guestsInput.min || "1",
                10
            );

        const max =
            parseInt(
                guestsInput.max || "50",
                10
            );

        if (guests < min) {

            guestsInput.value = min;

            showFieldError(
                guestsInput,
                `Minimum ${min} guest required.`
            );

            return false;
        }

        if (guests > max) {

            guestsInput.value = max;

            showFieldError(
                guestsInput,
                `Maximum ${max} guests allowed.`
            );

            return false;
        }

        clearFieldError(guestsInput);

        return true;
    }


    /* ==========================================
       FIELD ERROR
    ========================================== */

    function showFieldError(field, message) {

        if (!field) {
            return;
        }

        field.classList.add("is-invalid");

        let error =
            field.parentElement.querySelector(
                ".booking-field-error"
            );

        if (!error) {

            error =
                document.createElement("div");

            error.className =
                "booking-field-error text-danger small mt-1";

            field.parentElement.appendChild(error);

        }

        error.textContent = message;

    }


    /* ==========================================
       CLEAR FIELD ERROR
    ========================================== */

    function clearFieldError(field) {

        if (!field) {
            return;
        }

        field.classList.remove("is-invalid");

        const error =
            field.parentElement.querySelector(
                ".booking-field-error"
            );

        if (error) {
            error.remove();
        }

    }


    /* ==========================================
       GUEST + / - BUTTONS
    ========================================== */

    const guestPlusButtons =
        bookingForm.querySelectorAll(
            "[data-guests-plus], .guest-plus"
        );

    const guestMinusButtons =
        bookingForm.querySelectorAll(
            "[data-guests-minus], .guest-minus"
        );


    guestPlusButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            if (!guestsInput) {
                return;
            }

            let value =
                parseInt(
                    guestsInput.value || "1",
                    10
                );

            const max =
                parseInt(
                    guestsInput.max || "50",
                    10
                );

            if (value < max) {
                value++;
            }

            guestsInput.value = value;

            clearFieldError(guestsInput);

        });

    });


    guestMinusButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            if (!guestsInput) {
                return;
            }

            let value =
                parseInt(
                    guestsInput.value || "1",
                    10
                );

            const min =
                parseInt(
                    guestsInput.min || "1",
                    10
                );

            if (value > min) {
                value--;
            }

            guestsInput.value = value;

            clearFieldError(guestsInput);

        });

    });


    /* ==========================================
       GUEST MANUAL INPUT
    ========================================== */

    if (guestsInput) {

        guestsInput.addEventListener(
            "input",
            function () {

                validateGuests();

            }
        );

    }


    /* ==========================================
       DATE CHANGE
    ========================================== */

    if (dateInput) {

        dateInput.addEventListener(
            "change",
            function () {

                validateDate();

                /*
                 * Reset time validation when
                 * date changes.
                 */

                if (timeInput) {
                    clearFieldError(timeInput);
                }

                /*
                 * Optional dynamic table availability.
                 */

                loadTableAvailability();

            }
        );

    }


    /* ==========================================
       TIME CHANGE
    ========================================== */

    if (timeInput) {

        timeInput.addEventListener(
            "change",
            function () {

                validateTime();

                loadTableAvailability();

            }
        );

    }


    /* ==========================================
       TABLE AVAILABILITY
    ========================================== */

    function loadTableAvailability() {

        /*
         * This is intentionally optional.
         *
         * When the booking page provides:
         *
         * data-availability-url="/..."
         *
         * the JS can request available tables.
         */

        const availabilityURL =
            bookingForm.dataset.availabilityUrl;

        if (
            !availabilityURL ||
            !dateInput ||
            !timeInput
        ) {
            return;
        }

        if (
            !dateInput.value ||
            !timeInput.value
        ) {
            return;
        }

        const separator =
            availabilityURL.includes("?")
                ? "&"
                : "?";

        const url =
            availabilityURL +
            separator +
            "date=" +
            encodeURIComponent(
                dateInput.value
            ) +
            "&time=" +
            encodeURIComponent(
                timeInput.value
            );

        fetch(url, {
            method: "GET",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        })
        .then(function (response) {

            if (!response.ok) {
                throw new Error(
                    "Unable to load table availability."
                );
            }

            return response.json();

        })
        .then(function (data) {

            updateTableOptions(data);

        })
        .catch(function (error) {

            console.warn(
                "Table availability:",
                error.message
            );

        });

    }


    /* ==========================================
       UPDATE TABLE OPTIONS
    ========================================== */

    function updateTableOptions(data) {

        if (!tableInput) {
            return;
        }

        /*
         * Only process an expected array response.
         */

        if (!Array.isArray(data)) {

            if (
                data &&
                Array.isArray(data.tables)
            ) {
                data = data.tables;
            } else {
                return;
            }

        }

        const currentValue =
            tableInput.value;

        /*
         * Remove existing dynamic options.
         */

        Array.from(tableInput.options)
            .forEach(function (option) {

                if (
                    option.dataset &&
                    option.dataset.dynamic === "true"
                ) {
                    option.remove();
                }

            });


        data.forEach(function (table) {

            const option =
                document.createElement("option");

            option.value =
                table.table_id ||
                table.id;

            option.textContent =
                table.table_name ||
                table.table_number ||
                `Table ${option.value}`;

            option.dataset.dynamic =
                "true";

            if (
                String(option.value) ===
                String(currentValue)
            ) {
                option.selected = true;
            }

            tableInput.appendChild(option);

        });

    }


    /* ==========================================
       FORM SUBMISSION
    ========================================== */

    bookingForm.addEventListener(
        "submit",
        function (event) {

            const dateValid =
                validateDate();

            const timeValid =
                validateTime();

            const guestsValid =
                validateGuests();

            if (
                !dateValid ||
                !timeValid ||
                !guestsValid
            ) {

                event.preventDefault();

                const firstInvalid =
                    bookingForm.querySelector(
                        ".is-invalid"
                    );

                if (firstInvalid) {

                    firstInvalid.focus();

                    firstInvalid.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });

                }

                return;
            }


            /*
             * Prevent duplicate submission.
             */

            if (
                bookingForm.dataset.submitting ===
                "true"
            ) {

                event.preventDefault();

                return;

            }


            bookingForm.dataset.submitting =
                "true";


            if (submitButton) {

                submitButton.disabled = true;

                submitButton.classList.add(
                    "booking-submit-loading"
                );

                submitButton.dataset.originalText =
                    submitButton.textContent.trim();

                /*
                 * Only change text for normal buttons.
                 */

                if (
                    submitButton.tagName ===
                    "BUTTON"
                ) {

                    submitButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';

                }

            }

        }
    );


    /* ==========================================
       FORM RESET
    ========================================== */

    bookingForm.addEventListener(
        "reset",
        function () {

            bookingForm.dataset.submitting =
                "false";

            bookingForm
                .querySelectorAll(".is-invalid")
                .forEach(function (field) {

                    clearFieldError(field);

                });

            if (submitButton) {

                submitButton.disabled = false;

                submitButton.classList.remove(
                    "booking-submit-loading"
                );

            }

        }
    );


    /* ==========================================
       PHONE NUMBER VALIDATION
    ========================================== */

    const phoneInput =
        bookingForm.querySelector(
            'input[name="phone"], input[type="tel"]'
        );

    if (phoneInput) {

        phoneInput.addEventListener(
            "input",
            function () {

                /*
                 * Keep only digits, + and spaces.
                 */

                phoneInput.value =
                    phoneInput.value.replace(
                        /[^0-9+\s-]/g,
                        ""
                    );

            }
        );

    }


    /* ==========================================
       CHARACTER COUNTER
    ========================================== */

    const messageInputs =
        bookingForm.querySelectorAll(
            "textarea[data-max-length]"
        );

    messageInputs.forEach(function (textarea) {

        const maxLength =
            parseInt(
                textarea.dataset.maxLength,
                10
            );

        if (
            Number.isNaN(maxLength) ||
            maxLength <= 0
        ) {
            return;
        }

        const counter =
            document.createElement("small");

        counter.className =
            "booking-character-count text-muted";

        textarea.parentElement.appendChild(
            counter
        );


        function updateCounter() {

            const currentLength =
                textarea.value.length;

            counter.textContent =
                `${currentLength}/${maxLength}`;

        }


        textarea.addEventListener(
            "input",
            updateCounter
        );

        updateCounter();

    });


    /* ==========================================
       CANCEL / BACK BUTTON
    ========================================== */

    const cancelButtons =
        bookingForm.querySelectorAll(
            "[data-booking-cancel]"
        );

    cancelButtons.forEach(function (button) {

        button.addEventListener(
            "click",
            function (event) {

                if (
                    bookingForm.dataset.submitting ===
                    "true"
                ) {
                    return;
                }

                const confirmed =
                    window.confirm(
                        "Are you sure you want to cancel this booking?"
                    );

                if (!confirmed) {
                    event.preventDefault();
                }

            }
        );

    });


    /* ==========================================
       READY MARKER
    ========================================== */

    bookingForm.classList.add(
        "booking-js-ready"
    );

    document.body.classList.add(
        "booking-js-ready"
    );


    console.log(
        "Three-O-Clock booking.js loaded successfully."
    );

});