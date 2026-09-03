<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Settings";

/* =========================================================
   HELPERS
========================================================= */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* =========================================================
   DATABASE CHECK
========================================================= */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection is not available.");
}

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['settings_csrf'];

/* =========================================================
   LOAD CURRENT SETTINGS
========================================================= */

$settings = [];

try {

    $stmt = $pdo->query("
        SELECT
            setting_id,
            cafe_name,
            owner_name,
            email,
            phone,
            whatsapp,
            address,
            city,
            state,
            pincode,
            gst_number,
            logo,
            favicon,
            opening_time,
            closing_time,
            currency,
            tax_percentage,
            delivery_charge,
            created_at,
            updated_at
        FROM settings
        ORDER BY setting_id ASC
        LIMIT 1
    ");

    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

} catch (Throwable $e) {

    $_SESSION['settings_error'] =
        "Unable to load cafe settings.";
}

/* =========================================================
   DEFAULT VALUES
========================================================= */

$settings = array_merge([
    'setting_id'      => 0,
    'cafe_name'       => '',
    'owner_name'      => '',
    'email'           => '',
    'phone'           => '',
    'whatsapp'        => '',
    'address'         => '',
    'city'            => '',
    'state'           => '',
    'pincode'         => '',
    'gst_number'      => '',
    'logo'            => '',
    'favicon'         => '',
    'opening_time'    => '',
    'closing_time'    => '',
    'currency'        => 'INR',
    'tax_percentage'  => '5.00',
    'delivery_charge' => '0.00'
], $settings);

/* =========================================================
   HANDLE SAVE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $_SESSION['settings_error'] =
            "Invalid security token.";

        header("Location: settings.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {

        $cafeName = trim(
            (string)($_POST['cafe_name'] ?? '')
        );

        $ownerName = trim(
            (string)($_POST['owner_name'] ?? '')
        );

        $email = trim(
            (string)($_POST['email'] ?? '')
        );

        $phone = trim(
            (string)($_POST['phone'] ?? '')
        );

        $whatsapp = trim(
            (string)($_POST['whatsapp'] ?? '')
        );

        $address = trim(
            (string)($_POST['address'] ?? '')
        );

        $city = trim(
            (string)($_POST['city'] ?? '')
        );

        $state = trim(
            (string)($_POST['state'] ?? '')
        );

        $pincode = trim(
            (string)($_POST['pincode'] ?? '')
        );

        $gstNumber = trim(
            (string)($_POST['gst_number'] ?? '')
        );

        $openingTime = trim(
            (string)($_POST['opening_time'] ?? '')
        );

        $closingTime = trim(
            (string)($_POST['closing_time'] ?? '')
        );

        $currency = trim(
            (string)($_POST['currency'] ?? 'INR')
        );

        $taxPercentage = (float)(
            $_POST['tax_percentage'] ?? 0
        );

        $deliveryCharge = (float)(
            $_POST['delivery_charge'] ?? 0
        );


        /* -------------------------------------------------
           VALIDATION
        ------------------------------------------------- */

        $errors = [];

        if ($cafeName === '') {
            $errors[] = "Cafe name is required.";
        }

        if (
            $email !== '' &&
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = "Please enter a valid email address.";
        }

        if ($taxPercentage < 0 || $taxPercentage > 100) {
            $errors[] =
                "Tax percentage must be between 0 and 100.";
        }

        if ($deliveryCharge < 0) {
            $errors[] =
                "Delivery charge cannot be negative.";
        }

        if (
            $openingTime !== '' &&
            !preg_match('/^\d{2}:\d{2}$/', $openingTime)
        ) {
            $errors[] =
                "Invalid opening time.";
        }

        if (
            $closingTime !== '' &&
            !preg_match('/^\d{2}:\d{2}$/', $closingTime)
        ) {
            $errors[] =
                "Invalid closing time.";
        }


        /* -------------------------------------------------
           SAVE SETTINGS
        ------------------------------------------------- */

        if (empty($errors)) {

            try {

                $pdo->beginTransaction();

                $settingId = (int)$settings['setting_id'];

                if ($settingId > 0) {

                    $stmt = $pdo->prepare("
                        UPDATE settings
                        SET
                            cafe_name = :cafe_name,
                            owner_name = :owner_name,
                            email = :email,
                            phone = :phone,
                            whatsapp = :whatsapp,
                            address = :address,
                            city = :city,
                            state = :state,
                            pincode = :pincode,
                            gst_number = :gst_number,
                            opening_time = :opening_time,
                            closing_time = :closing_time,
                            currency = :currency,
                            tax_percentage = :tax_percentage,
                            delivery_charge = :delivery_charge
                        WHERE setting_id = :setting_id
                    ");

                    $stmt->execute([
                        ':cafe_name'       => $cafeName,
                        ':owner_name'      => $ownerName ?: null,
                        ':email'           => $email ?: null,
                        ':phone'           => $phone ?: null,
                        ':whatsapp'        => $whatsapp ?: null,
                        ':address'         => $address ?: null,
                        ':city'            => $city ?: null,
                        ':state'           => $state ?: null,
                        ':pincode'         => $pincode ?: null,
                        ':gst_number'      => $gstNumber ?: null,
                        ':opening_time'    => $openingTime ?: null,
                        ':closing_time'    => $closingTime ?: null,
                        ':currency'        => $currency ?: 'INR',
                        ':tax_percentage'  => number_format(
                            $taxPercentage,
                            2,
                            '.',
                            ''
                        ),
                        ':delivery_charge' => number_format(
                            $deliveryCharge,
                            2,
                            '.',
                            ''
                        ),
                        ':setting_id'      => $settingId
                    ]);

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO settings
                        (
                            cafe_name,
                            owner_name,
                            email,
                            phone,
                            whatsapp,
                            address,
                            city,
                            state,
                            pincode,
                            gst_number,
                            opening_time,
                            closing_time,
                            currency,
                            tax_percentage,
                            delivery_charge
                        )
                        VALUES
                        (
                            :cafe_name,
                            :owner_name,
                            :email,
                            :phone,
                            :whatsapp,
                            :address,
                            :city,
                            :state,
                            :pincode,
                            :gst_number,
                            :opening_time,
                            :closing_time,
                            :currency,
                            :tax_percentage,
                            :delivery_charge
                        )
                    ");

                    $stmt->execute([
                        ':cafe_name'       => $cafeName,
                        ':owner_name'      => $ownerName ?: null,
                        ':email'           => $email ?: null,
                        ':phone'           => $phone ?: null,
                        ':whatsapp'        => $whatsapp ?: null,
                        ':address'         => $address ?: null,
                        ':city'            => $city ?: null,
                        ':state'           => $state ?: null,
                        ':pincode'         => $pincode ?: null,
                        ':gst_number'      => $gstNumber ?: null,
                        ':opening_time'    => $openingTime ?: null,
                        ':closing_time'    => $closingTime ?: null,
                        ':currency'        => $currency ?: 'INR',
                        ':tax_percentage'  => number_format(
                            $taxPercentage,
                            2,
                            '.',
                            ''
                        ),
                        ':delivery_charge' => number_format(
                            $deliveryCharge,
                            2,
                            '.',
                            ''
                        )
                    ]);
                }

                $pdo->commit();

                $_SESSION['settings_success'] =
                    "Cafe settings saved successfully.";

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $_SESSION['settings_error'] =
                    "Unable to save settings.";
            }

        } else {

            $_SESSION['settings_error'] =
                implode(' ', $errors);
        }

        header("Location: settings.php");
        exit;
    }
}

/* =========================================================
   FLASH MESSAGES
========================================================= */

$successMessage =
    $_SESSION['settings_success'] ?? '';

$errorMessage =
    $_SESSION['settings_error'] ?? '';

unset(
    $_SESSION['settings_success'],
    $_SESSION['settings_error']
);

/* =========================================================
   RELOAD SETTINGS AFTER SAVE
========================================================= */

try {

    $stmt = $pdo->query("
        SELECT
            setting_id,
            cafe_name,
            owner_name,
            email,
            phone,
            whatsapp,
            address,
            city,
            state,
            pincode,
            gst_number,
            logo,
            favicon,
            opening_time,
            closing_time,
            currency,
            tax_percentage,
            delivery_charge
        FROM settings
        ORDER BY setting_id ASC
        LIMIT 1
    ");

    $freshSettings =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if ($freshSettings) {
        $settings = array_merge(
            $settings,
            $freshSettings
        );
    }

} catch (Throwable $e) {
    // Existing values remain available.
}

/* =========================================================
   HEADER
========================================================= */

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="settings-page-header">

            <div>

                <div class="settings-breadcrumb">

                    <span>System</span>

                    <i class="bi bi-chevron-right"></i>

                    <strong>Settings</strong>

                </div>

                <h1 class="settings-title">
                    Cafe Settings
                </h1>

                <p class="settings-subtitle">
                    Manage your cafe information, contact details,
                    business hours and billing settings.
                </p>

            </div>

        </div>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($successMessage !== ''): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                data-auto-hide="true"
                role="alert"
            >

                <i class="bi bi-check-circle-fill me-2"></i>

                <?= e($successMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== ''): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                data-auto-hide="true"
                role="alert"
            >

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?= e($errorMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- =================================================
             SETTINGS FORM
        ================================================== -->

        <form
            method="POST"
            action="settings.php"
            id="settingsForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="save_settings"
            >


            <div class="row g-4">


                <!-- =================================================
                     CAFE INFORMATION
                ================================================== -->

                <div class="col-12 col-xl-8">

                    <div class="settings-card">

                        <div class="settings-card-header">

                            <div class="settings-section-icon">

                                <i class="bi bi-cup-hot-fill"></i>

                            </div>

                            <div>

                                <h2>
                                    Cafe Information
                                </h2>

                                <span>
                                    Basic information about your cafe.
                                </span>

                            </div>

                        </div>


                        <div class="settings-card-body">

                            <div class="row g-3">


                                <!-- CAFE NAME -->

                                <div class="col-12">

                                    <label
                                        for="cafe_name"
                                        class="settings-label"
                                    >
                                        Cafe Name
                                        <span>*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control settings-input"
                                        id="cafe_name"
                                        name="cafe_name"
                                        value="<?= e($settings['cafe_name']); ?>"
                                        maxlength="150"
                                        required
                                    >

                                </div>


                                <!-- OWNER -->

                                <div class="col-12 col-md-6">

                                    <label
                                        for="owner_name"
                                        class="settings-label"
                                    >
                                        Owner Name
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control settings-input"
                                        id="owner_name"
                                        name="owner_name"
                                        value="<?= e($settings['owner_name']); ?>"
                                        maxlength="150"
                                    >

                                </div>


                                <!-- EMAIL -->

                                <div class="col-12 col-md-6">

                                    <label
                                        for="email"
                                        class="settings-label"
                                    >
                                        Email Address
                                    </label>

                                    <div class="settings-input-icon">

                                        <i class="bi bi-envelope"></i>

                                        <input
                                            type="email"
                                            class="form-control settings-input"
                                            id="email"
                                            name="email"
                                            value="<?= e($settings['email']); ?>"
                                            maxlength="120"
                                        >

                                    </div>

                                </div>


                                <!-- PHONE -->

                                <div class="col-12 col-md-6">

                                    <label
                                        for="phone"
                                        class="settings-label"
                                    >
                                        Phone Number
                                    </label>

                                    <div class="settings-input-icon">

                                        <i class="bi bi-telephone"></i>

                                        <input
                                            type="text"
                                            class="form-control settings-input"
                                            id="phone"
                                            name="phone"
                                            value="<?= e($settings['phone']); ?>"
                                            maxlength="20"
                                        >

                                    </div>

                                </div>


                                <!-- WHATSAPP -->

                                <div class="col-12 col-md-6">

                                    <label
                                        for="whatsapp"
                                        class="settings-label"
                                    >
                                        WhatsApp Number
                                    </label>

                                    <div class="settings-input-icon">

                                        <i class="bi bi-whatsapp"></i>

                                        <input
                                            type="text"
                                            class="form-control settings-input"
                                            id="whatsapp"
                                            name="whatsapp"
                                            value="<?= e($settings['whatsapp']); ?>"
                                            maxlength="20"
                                        >

                                    </div>

                                    <small class="settings-help">
                                        Use country code for WhatsApp,
                                        e.g. 919876543210.
                                    </small>

                                </div>


                                <!-- GST -->

                                <div class="col-12">

                                    <label
                                        for="gst_number"
                                        class="settings-label"
                                    >
                                        GST Number
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control settings-input"
                                        id="gst_number"
                                        name="gst_number"
                                        value="<?= e($settings['gst_number']); ?>"
                                        maxlength="50"
                                        placeholder="Optional"
                                    >

                                </div>


                                <!-- ADDRESS -->

                                <div class="col-12">

                                    <label
                                        for="address"
                                        class="settings-label"
                                    >
                                        Address
                                    </label>

                                    <textarea
                                        class="form-control settings-input settings-textarea"
                                        id="address"
                                        name="address"
                                        rows="3"
                                        maxlength="1000"
                                    ><?= e($settings['address']); ?></textarea>

                                </div>


                                <!-- CITY -->

                                <div class="col-12 col-md-4">

                                    <label
                                        for="city"
                                        class="settings-label"
                                    >
                                        City
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control settings-input"
                                        id="city"
                                        name="city"
                                        value="<?= e($settings['city']); ?>"
                                        maxlength="80"
                                    >

                                </div>


                                <!-- STATE -->

                                <div class="col-12 col-md-4">

                                    <label
                                        for="state"
                                        class="settings-label"
                                    >
                                        State
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control settings-input"
                                        id="state"
                                        name="state"
                                        value="<?= e($settings['state']); ?>"
                                        maxlength="80"
                                    >

                                </div>


                                <!-- PINCODE -->

                                <div class="col-12 col-md-4">

                                    <label
                                        for="pincode"
                                        class="settings-label"
                                    >
                                        Pincode
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control settings-input"
                                        id="pincode"
                                        name="pincode"
                                        value="<?= e($settings['pincode']); ?>"
                                        maxlength="10"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     BUSINESS HOURS
                ================================================== -->

                <div class="col-12 col-xl-4">

                    <div class="settings-card">

                        <div class="settings-card-header">

                            <div class="settings-section-icon hours">

                                <i class="bi bi-clock-fill"></i>

                            </div>

                            <div>

                                <h2>
                                    Business Hours
                                </h2>

                                <span>
                                    Set your cafe operating time.
                                </span>

                            </div>

                        </div>


                        <div class="settings-card-body">

                            <div class="settings-time-box">

                                <label
                                    for="opening_time"
                                    class="settings-label"
                                >
                                    Opening Time
                                </label>

                                <input
                                    type="time"
                                    class="form-control settings-input"
                                    id="opening_time"
                                    name="opening_time"
                                    value="<?= e($settings['opening_time']); ?>"
                                >

                            </div>


                            <div class="settings-time-divider">

                                <i class="bi bi-arrow-down"></i>

                            </div>


                            <div class="settings-time-box">

                                <label
                                    for="closing_time"
                                    class="settings-label"
                                >
                                    Closing Time
                                </label>

                                <input
                                    type="time"
                                    class="form-control settings-input"
                                    id="closing_time"
                                    name="closing_time"
                                    value="<?= e($settings['closing_time']); ?>"
                                >

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         BILLING
                    ================================================== -->

                    <div class="settings-card settings-card-spaced">

                        <div class="settings-card-header">

                            <div class="settings-section-icon billing">

                                <i class="bi bi-receipt-cutoff"></i>

                            </div>

                            <div>

                                <h2>
                                    Billing Settings
                                </h2>

                                <span>
                                    Configure tax and delivery charges.
                                </span>

                            </div>

                        </div>


                        <div class="settings-card-body">

                            <!-- CURRENCY -->

                            <div class="mb-3">

                                <label
                                    for="currency"
                                    class="settings-label"
                                >
                                    Currency
                                </label>

                                <select
                                    class="form-select settings-input"
                                    id="currency"
                                    name="currency"
                                >

                                    <option
                                        value="INR"
                                        <?= $settings['currency'] === 'INR'
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        INR — ₹ Indian Rupee
                                    </option>

                                    <option
                                        value="USD"
                                        <?= $settings['currency'] === 'USD'
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        USD — $ US Dollar
                                    </option>

                                    <option
                                        value="EUR"
                                        <?= $settings['currency'] === 'EUR'
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        EUR — € Euro
                                    </option>

                                    <option
                                        value="GBP"
                                        <?= $settings['currency'] === 'GBP'
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        GBP — £ Pound
                                    </option>

                                </select>

                            </div>


                            <!-- TAX -->

                            <div class="mb-3">

                                <label
                                    for="tax_percentage"
                                    class="settings-label"
                                >
                                    Tax Percentage
                                </label>

                                <div class="settings-number-group">

                                    <input
                                        type="number"
                                        class="form-control settings-input"
                                        id="tax_percentage"
                                        name="tax_percentage"
                                        value="<?= e(
                                            (string)$settings['tax_percentage']
                                        ); ?>"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                    >

                                    <span>
                                        %
                                    </span>

                                </div>

                            </div>


                            <!-- DELIVERY -->

                            <div>

                                <label
                                    for="delivery_charge"
                                    class="settings-label"
                                >
                                    Delivery Charge
                                </label>

                                <div class="settings-number-group">

                                    <span>
                                        ₹
                                    </span>

                                    <input
                                        type="number"
                                        class="form-control settings-input"
                                        id="delivery_charge"
                                        name="delivery_charge"
                                        value="<?= e(
                                            (string)$settings['delivery_charge']
                                        ); ?>"
                                        min="0"
                                        step="0.01"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     SAVE BAR
                ================================================== -->

                <div class="col-12">

                    <div class="settings-save-bar">

                        <div class="settings-save-info">

                            <div class="settings-save-icon">

                                <i class="bi bi-shield-check"></i>

                            </div>

                            <div>

                                <strong>
                                    Settings are saved securely
                                </strong>

                                <span>
                                    Changes will be used across the
                                    cafe management system.
                                </span>

                            </div>

                        </div>


                        <div class="settings-save-actions">

                            <button
                                type="reset"
                                class="btn settings-reset-btn"
                            >
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Reset
                            </button>

                            <button
                                type="submit"
                                class="btn settings-save-btn"
                            >
                                <i class="bi bi-check2-circle me-1"></i>
                                Save Changes
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </form>

    </main>

</div>


<style>

/* =========================================================
   SETTINGS PAGE
========================================================= */

.settings-page-header {
    margin-bottom: 24px;
}

.settings-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    color: #9ca3af;
    font-size: 12px;
}

.settings-breadcrumb i {
    font-size: 10px;
}

.settings-breadcrumb strong {
    color: #6b7280;
}

.settings-title {
    margin: 0;
    color: #111827;
    font-size: 28px;
    font-weight: 700;
}

.settings-subtitle {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* =========================================================
   SETTINGS CARD
========================================================= */

.settings-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .04);
}

.settings-card-spaced {
    margin-top: 24px;
}

.settings-card-header {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 19px 22px;
    border-bottom: 1px solid #eef0f2;
}

.settings-section-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: #faf3ed;
    color: #8b5e3c;
    font-size: 18px;
}

.settings-section-icon.hours {
    background: #fff7ed;
    color: #ea580c;
}

.settings-section-icon.billing {
    background: #eff6ff;
    color: #2563eb;
}

.settings-card-header h2 {
    margin: 0 0 3px;
    color: #111827;
    font-size: 16px;
    font-weight: 700;
}

.settings-card-header span {
    color: #9ca3af;
    font-size: 11px;
}

.settings-card-body {
    padding: 22px;
}


/* =========================================================
   FORM
========================================================= */

.settings-label {
    display: block;
    margin-bottom: 6px;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
}

.settings-label span {
    color: #dc2626;
}

.settings-input {
    min-height: 42px;
    border-color: #dfe3e8;
    border-radius: 9px;
    color: #111827;
    font-size: 13px;
    box-shadow: none;
}

.settings-input:focus {
    border-color: #8b5e3c;
    box-shadow: 0 0 0 3px rgba(139, 94, 60, .10);
}

.settings-textarea {
    resize: vertical;
    min-height: 90px;
}

.settings-input-icon {
    position: relative;
}

.settings-input-icon > i {
    position: absolute;
    z-index: 2;
    top: 50%;
    left: 13px;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 14px;
}

.settings-input-icon .settings-input {
    padding-left: 38px;
}

.settings-help {
    display: block;
    margin-top: 5px;
    color: #9ca3af;
    font-size: 10px;
}


/* =========================================================
   BUSINESS HOURS
========================================================= */

.settings-time-box {
    padding: 15px;
    background: #fafafa;
    border: 1px solid #eef0f2;
    border-radius: 11px;
}

.settings-time-divider {
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}


/* =========================================================
   NUMBER INPUTS
========================================================= */

.settings-number-group {
    position: relative;
}

.settings-number-group > span {
    position: absolute;
    z-index: 2;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 13px;
}

.settings-number-group > span:first-child {
    left: 13px;
}

.settings-number-group > span:last-child {
    right: 13px;
}

.settings-number-group > span:first-child
~ .settings-input {
    padding-left: 32px;
}

.settings-number-group > .settings-input {
    padding-right: 32px;
}


/* =========================================================
   SAVE BAR
========================================================= */

.settings-save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 22px;
    background: #ffffff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, .04);
}

.settings-save-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.settings-save-icon {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: #ecfdf5;
    color: #059669;
    font-size: 18px;
}

.settings-save-info strong {
    display: block;
    color: #374151;
    font-size: 12px;
}

.settings-save-info span {
    display: block;
    margin-top: 3px;
    color: #9ca3af;
    font-size: 10px;
}

.settings-save-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.settings-reset-btn,
.settings-save-btn {
    min-height: 42px;
    padding: 0 17px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 600;
}

.settings-reset-btn {
    color: #6b7280;
    background: #ffffff;
    border: 1px solid #dfe3e8;
}

.settings-reset-btn:hover {
    color: #374151;
    background: #f9fafb;
}

.settings-save-btn {
    color: #ffffff;
    background: #8b5e3c;
    border: 1px solid #8b5e3c;
}

.settings-save-btn:hover {
    color: #ffffff;
    background: #70492f;
    border-color: #70492f;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991px) {

    .settings-title {
        font-size: 24px;
    }

    .settings-save-bar {
        align-items: flex-start;
        flex-direction: column;
    }

    .settings-save-actions {
        width: 100%;
    }

    .settings-reset-btn,
    .settings-save-btn {
        flex: 1;
    }
}


@media (max-width: 575px) {

    .settings-title {
        font-size: 21px;
    }

    .settings-subtitle {
        font-size: 12px;
        line-height: 1.6;
    }

    .settings-card-header {
        padding: 16px;
    }

    .settings-card-body {
        padding: 16px;
    }

    .settings-save-bar {
        padding: 16px;
    }

    .settings-save-info {
        align-items: flex-start;
    }

    .settings-save-actions {
        flex-direction: column;
    }

    .settings-reset-btn,
    .settings-save-btn {
        width: 100%;
    }

}

</style>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("settingsForm");

    if (!form) {
        return;
    }

    /* ---------------------------------------------
       RESET CONFIRMATION
    --------------------------------------------- */

    const resetButton =
        form.querySelector('button[type="reset"]');

    if (resetButton) {

        resetButton.addEventListener("click", function (event) {

            const confirmed = confirm(
                "Reset the form to the last saved values?"
            );

            if (!confirmed) {
                event.preventDefault();
            }

        });

    }


    /* ---------------------------------------------
       PHONE NUMBER CLEANUP
    --------------------------------------------- */

    const phone =
        document.getElementById("phone");

    const whatsapp =
        document.getElementById("whatsapp");

    const pincode =
        document.getElementById("pincode");


    function cleanNumberField(input) {

        if (!input) {
            return;
        }

        input.addEventListener("input", function () {

            this.value = this.value.replace(
                /[^0-9+\-\s]/g,
                ""
            );

        });

    }

    cleanNumberField(phone);
    cleanNumberField(whatsapp);
    cleanNumberField(pincode);


    /* ---------------------------------------------
       TAX VALIDATION
    --------------------------------------------- */

    const tax =
        document.getElementById("tax_percentage");

    if (tax) {

        tax.addEventListener("input", function () {

            let value = parseFloat(this.value);

            if (Number.isNaN(value)) {
                return;
            }

            if (value < 0) {
                this.value = "0";
            }

            if (value > 100) {
                this.value = "100";
            }

        });

    }


    /* ---------------------------------------------
       DELIVERY VALIDATION
    --------------------------------------------- */

    const delivery =
        document.getElementById("delivery_charge");

    if (delivery) {

        delivery.addEventListener("input", function () {

            const value =
                parseFloat(this.value);

            if (!Number.isNaN(value) && value < 0) {
                this.value = "0";
            }

        });

    }


    /* ---------------------------------------------
       OPENING / CLOSING TIME
    --------------------------------------------- */

    const opening =
        document.getElementById("opening_time");

    const closing =
        document.getElementById("closing_time");


    function validateTimes() {

        if (
            opening &&
            closing &&
            opening.value &&
            closing.value
        ) {

            if (closing.value <= opening.value) {

                closing.setCustomValidity(
                    "Closing time must be later than opening time."
                );

            } else {

                closing.setCustomValidity("");

            }

        }

    }


    if (opening) {
        opening.addEventListener(
            "change",
            validateTimes
        );
    }

    if (closing) {
        closing.addEventListener(
            "change",
            validateTimes
        );
    }


    /* ---------------------------------------------
       SUBMIT LOADING STATE
    --------------------------------------------- */

    form.addEventListener("submit", function (event) {

        validateTimes();

        if (!form.checkValidity()) {
            event.preventDefault();
            form.classList.add("was-validated");
            return;
        }

        const submitButton =
            form.querySelector(
                'button[type="submit"]'
            );

        if (submitButton) {

            submitButton.disabled = true;

            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Saving...';

        }

    });

});

</script>

<?php require_once "includes/a-footer.php"; ?>