<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Billing / POS";

$errors = [];
$success = "";


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['billing_csrf'])) {
    $_SESSION['billing_csrf'] = bin2hex(random_bytes(32));
}


/*
|--------------------------------------------------------------------------
| DEFAULT SETTINGS
|--------------------------------------------------------------------------
*/

$taxPercentage = 5.00;
$deliveryCharge = 40.00;
$currency = "₹";


/*
|--------------------------------------------------------------------------
| LOAD CAFE SETTINGS
|--------------------------------------------------------------------------
*/

try {

    $settingsStmt = $pdo->query("
        SELECT
            tax_percentage,
            delivery_charge,
            currency
        FROM settings
        ORDER BY setting_id ASC
        LIMIT 1
    ");

    $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);

    if ($settings) {

        $taxPercentage = is_numeric($settings['tax_percentage'])
            ? (float)$settings['tax_percentage']
            : 5.00;

        $deliveryCharge = is_numeric($settings['delivery_charge'])
            ? (float)$settings['delivery_charge']
            : 40.00;

        if (!empty($settings['currency'])) {
            $currency = (string)$settings['currency'];
        }
    }

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Keep Default Values
    |--------------------------------------------------------------------------
    */

    $taxPercentage = 5.00;
    $deliveryCharge = 40.00;
    $currency = "₹";
}


/*
|--------------------------------------------------------------------------
| GENERATE ORDER NUMBER
|--------------------------------------------------------------------------
*/

function generateOrderNumber(): string
{
    return "ORD-" .
        date("Ymd") .
        "-" .
        strtoupper(
            substr(
                bin2hex(random_bytes(4)),
                0,
                8
            )
        );
}


/*
|--------------------------------------------------------------------------
| CREATE BILL / ORDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'create_order') {

        /*
        |--------------------------------------------------------------------------
        | CSRF VALIDATION
        |--------------------------------------------------------------------------
        */

        $csrfToken = $_POST['csrf_token'] ?? '';

        if (
            !hash_equals(
                $_SESSION['billing_csrf'],
                $csrfToken
            )
        ) {

            $errors[] =
                "Invalid security token. Please refresh the page.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | CUSTOMER DETAILS
            |--------------------------------------------------------------------------
            */

            $customerName = trim(
                (string)($_POST['customer_name'] ?? '')
            );

            $phone = trim(
                (string)($_POST['phone'] ?? '')
            );

            $email = trim(
                (string)($_POST['email'] ?? '')
            );

            $address = trim(
                (string)($_POST['address'] ?? '')
            );


            /*
            |--------------------------------------------------------------------------
            | ORDER DETAILS
            |--------------------------------------------------------------------------
            */

            $orderType = trim(
                (string)($_POST['order_type'] ?? 'Walk-In')
            );

            $paymentMethod = trim(
                (string)($_POST['payment_method'] ?? 'Cash')
            );

            $tableId = filter_var(
                $_POST['table_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $notes = trim(
                (string)($_POST['notes'] ?? '')
            );


            /*
            |--------------------------------------------------------------------------
            | CART
            |--------------------------------------------------------------------------
            */

            $cartJson = (string)(
                $_POST['cart_data'] ?? ''
            );

            $cart = json_decode(
                $cartJson,
                true
            );


            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            $allowedOrderTypes = [
                'Walk-In',
                'Dine-In',
                'Takeaway',
                'Delivery'
            ];

            $allowedPaymentMethods = [
                'Cash',
                'UPI',
                'Card'
            ];


            if (
                !in_array(
                    $orderType,
                    $allowedOrderTypes,
                    true
                )
            ) {

                $errors[] =
                    "Invalid order type.";

            }


            if (
                !in_array(
                    $paymentMethod,
                    $allowedPaymentMethods,
                    true
                )
            ) {

                $errors[] =
                    "Invalid payment method.";

            }


            if ($customerName === '') {

                $errors[] =
                    "Customer name is required.";

            }


            if (
                $phone !== '' &&
                !preg_match(
                    '/^[0-9+\-\s()]{7,20}$/',
                    $phone
                )
            ) {

                $errors[] =
                    "Please enter a valid phone number.";

            }


            if (
                $email !== '' &&
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $errors[] =
                    "Please enter a valid email address.";

            }


            if (
                $orderType === 'Dine-In' &&
                (!$tableId || $tableId < 1)
            ) {

                $errors[] =
                    "Please select a table for Dine-In.";

            }


            if (!is_array($cart) || empty($cart)) {

                $errors[] =
                    "Please add at least one product.";

            }


            /*
            |--------------------------------------------------------------------------
            | CREATE ORDER
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                try {

                    $pdo->beginTransaction();


                    /*
                    |--------------------------------------------------------------------------
                    | PREPARE TOTALS
                    |--------------------------------------------------------------------------
                    */

                    $subtotal = 0.00;

                    $validatedItems = [];


                    /*
                    |--------------------------------------------------------------------------
                    | VALIDATE PRODUCTS FROM DATABASE
                    |--------------------------------------------------------------------------
                    | Never trust prices coming from JavaScript.
                    |--------------------------------------------------------------------------
                    */

                    foreach ($cart as $item) {

                        $productId = filter_var(
                            $item['id'] ?? null,
                            FILTER_VALIDATE_INT
                        );

                        $quantity = filter_var(
                            $item['qty'] ?? null,
                            FILTER_VALIDATE_INT
                        );


                        if (
                            !$productId ||
                            $productId < 1 ||
                            !$quantity ||
                            $quantity < 1
                        ) {

                            throw new RuntimeException(
                                "Invalid product or quantity."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | LOCK PRODUCT ROW
                        |--------------------------------------------------------------------------
                        */

                        $productStmt = $pdo->prepare("
                            SELECT
                                product_id,
                                product_name,
                                price,
                                discount_price,
                                stock,
                                availability,
                                status
                            FROM products
                            WHERE product_id = ?
                            LIMIT 1
                            FOR UPDATE
                        ");

                        $productStmt->execute([
                            $productId
                        ]);

                        $product = $productStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                        if (!$product) {

                            throw new RuntimeException(
                                "One of the selected products no longer exists."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | PRODUCT STATUS
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $product['status'] !== 'Active'
                        ) {

                            throw new RuntimeException(
                                htmlspecialchars(
                                    $product['product_name']
                                ) .
                                " is currently inactive."
                            );

                        }


                        if (
                            isset($product['availability']) &&
                            $product['availability'] !== 'Available'
                        ) {

                            throw new RuntimeException(
                                $product['product_name'] .
                                " is currently unavailable."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | STOCK CHECK
                        |--------------------------------------------------------------------------
                        */

                        $stock = (int)$product['stock'];

                        if ($stock < $quantity) {

                            throw new RuntimeException(
                                $product['product_name'] .
                                " has only " .
                                $stock .
                                " item(s) in stock."
                            );

                        }


                        /*
                        |--------------------------------------------------------------------------
                        | CALCULATE SELLING PRICE
                        |--------------------------------------------------------------------------
                        */

                        $regularPrice =
                            (float)$product['price'];

                        $discountPrice =
                            $product['discount_price'] !== null
                                ? (float)$product['discount_price']
                                : 0.00;


                        $unitPrice = $regularPrice;


                        if (
                            $discountPrice > 0 &&
                            $discountPrice < $regularPrice
                        ) {

                            $unitPrice =
                                $discountPrice;

                        }


                        $itemTotal =
                            $unitPrice * $quantity;


                        $subtotal += $itemTotal;


                        $validatedItems[] = [
                            'product_id' => $productId,
                            'product_name' =>
                                $product['product_name'],
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total_price' => $itemTotal,
                            'stock' => $stock
                        ];
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | TAX
                    |--------------------------------------------------------------------------
                    */

                    $tax =
                        round(
                            ($subtotal * $taxPercentage) / 100,
                            2
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | DELIVERY CHARGE
                    |--------------------------------------------------------------------------
                    */

                    $calculatedDeliveryCharge = 0.00;

                    if ($orderType === 'Delivery') {

                        $calculatedDeliveryCharge =
                            $deliveryCharge;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | GRAND TOTAL
                    |--------------------------------------------------------------------------
                    */

                    $grandTotal =
                        round(
                            $subtotal +
                            $tax +
                            $calculatedDeliveryCharge,
                            2
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | TABLE VALIDATION
                    |--------------------------------------------------------------------------
                    */

                    if ($orderType === 'Dine-In') {

                        $tableStmt = $pdo->prepare("
                            SELECT
                                table_id,
                                table_number,
                                capacity,
                                status
                            FROM cafe_tables
                            WHERE table_id = ?
                            LIMIT 1
                            FOR UPDATE
                        ");

                        $tableStmt->execute([
                            $tableId
                        ]);

                        $table = $tableStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                        if (!$table) {

                            throw new RuntimeException(
                                "Selected table does not exist."
                            );

                        }


                        if (
                            $table['status'] !== 'Available'
                        ) {

                            throw new RuntimeException(
                                "Selected table is no longer available."
                            );

                        }

                    } else {

                        $tableId = null;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | ORDER NUMBER
                    |--------------------------------------------------------------------------
                    */

                    $orderNumber =
                        generateOrderNumber();


                    /*
                    |--------------------------------------------------------------------------
                    | INSERT ORDER
                    |--------------------------------------------------------------------------
                    */

                    $orderStmt = $pdo->prepare("
                        INSERT INTO orders (
                            order_number,
                            user_id,
                            customer_name,
                            phone,
                            email,
                            address,
                            order_source,
                            order_type,
                            table_id,
                            subtotal,
                            discount,
                            tax,
                            delivery_charge,
                            grand_total,
                            payment_status,
                            order_status,
                            payment_method,
                            notes,
                            ordered_at,
                            updated_at
                        )
                        VALUES (
                            ?,
                            NULL,
                            ?,
                            ?,
                            ?,
                            ?,
                            'Walk-In',
                            ?,
                            ?,
                            ?,
                            0,
                            ?,
                            ?,
                            ?,
                            ?,
                            'Pending',
                            ?,
                            ?,
                            NOW(),
                            NOW()
                        )
                    ");


                    /*
                    |--------------------------------------------------------------------------
                    | PAYMENT STATUS
                    |--------------------------------------------------------------------------
                    | Cash / UPI / Card are treated as paid for POS.
                    |--------------------------------------------------------------------------
                    */

                    $paymentStatus = 'Paid';


                    $orderStmt->execute([

                        $orderNumber,

                        $customerName,

                        $phone !== ''
                            ? $phone
                            : null,

                        $email !== ''
                            ? $email
                            : null,

                        $address !== ''
                            ? $address
                            : null,

                        $orderType,

                        $tableId,

                        $subtotal,

                        $tax,

                        $calculatedDeliveryCharge,

                        $grandTotal,

                        $paymentStatus,

                        $paymentMethod,

                        $notes !== ''
                            ? $notes
                            : null

                    ]);


                    $orderId =
                        (int)$pdo->lastInsertId();


                    /*
                    |--------------------------------------------------------------------------
                    | INSERT ORDER ITEMS
                    |--------------------------------------------------------------------------
                    */

                    $itemStmt = $pdo->prepare("
                        INSERT INTO order_items (
                            order_id,
                            product_id,
                            quantity,
                            unit_price,
                            total_price,
                            special_instruction
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )
                    ");


                    /*
                    |--------------------------------------------------------------------------
                    | UPDATE STOCK
                    |--------------------------------------------------------------------------
                    */

                    $stockStmt = $pdo->prepare("
                        UPDATE products
                        SET stock = stock - ?
                        WHERE product_id = ?
                        AND stock >= ?
                    ");


                    foreach ($validatedItems as $item) {

                        $itemStmt->execute([

                            $orderId,

                            $item['product_id'],

                            $item['quantity'],

                            $item['unit_price'],

                            $item['total_price'],

                            null

                        ]);


                        $stockStmt->execute([

                            $item['quantity'],

                            $item['product_id'],

                            $item['quantity']

                        ]);


                        if ($stockStmt->rowCount() !== 1) {

                            throw new RuntimeException(
                                "Unable to update stock for " .
                                $item['product_name'] .
                                "."
                            );

                        }

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | PAYMENT RECORD
                    |--------------------------------------------------------------------------
                    */

                    $paymentStmt = $pdo->prepare("
                        INSERT INTO payments (
                            order_id,
                            transaction_id,
                            razorpay_order_id,
                            razorpay_payment_id,
                            payment_method,
                            payment_status,
                            amount,
                            payment_date,
                            remarks
                        )
                        VALUES (
                            ?,
                            ?,
                            NULL,
                            NULL,
                            ?,
                            'Success',
                            ?,
                            NOW(),
                            ?
                        )
                    ");


                    $transactionId =
                        "POS-" .
                        date("YmdHis") .
                        "-" .
                        $orderId;


                    $paymentStmt->execute([

                        $orderId,

                        $transactionId,

                        $paymentMethod,

                        $grandTotal,

                        "POS payment for " .
                        $orderNumber

                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | DINE-IN TABLE STATUS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $orderType === 'Dine-In' &&
                        $tableId
                    ) {

                        $tableUpdateStmt =
                            $pdo->prepare("
                                UPDATE cafe_tables
                                SET status = 'Occupied'
                                WHERE table_id = ?
                                AND status = 'Available'
                            ");

                        $tableUpdateStmt->execute([
                            $tableId
                        ]);


                        if (
                            $tableUpdateStmt->rowCount() !== 1
                        ) {

                            throw new RuntimeException(
                                "Unable to reserve the selected table."
                            );

                        }

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | COMMIT
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */

                    $success =
                        "Bill generated successfully. Order #" .
                        $orderNumber;


                    /*
                    |--------------------------------------------------------------------------
                    | NEW CSRF TOKEN
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['billing_csrf'] =
                        bin2hex(random_bytes(32));


                } catch (
                    Throwable $e
                ) {

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();

                    }


                    $errors[] =
                        $e->getMessage();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| FETCH ACTIVE CATEGORIES
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $stmt = $pdo->query("
        SELECT
            category_id,
            category_name
        FROM categories
        WHERE status = 'Active'
        ORDER BY category_name ASC
    ");

    $categories =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $categories = [];

}


/*
|--------------------------------------------------------------------------
| FETCH ACTIVE PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

try {

    $stmt = $pdo->query("
        SELECT
            p.product_id,
            p.product_name,
            p.price,
            p.discount_price,
            p.stock,
            p.category_id,
            p.food_type,
            p.availability
        FROM products p
        WHERE p.status = 'Active'
        ORDER BY p.product_name ASC
    ");

    $products =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $products = [];

}


/*
|--------------------------------------------------------------------------
| FETCH AVAILABLE TABLES
|--------------------------------------------------------------------------
*/

$tables = [];

try {

    $stmt = $pdo->query("
        SELECT
            table_id,
            table_number,
            capacity,
            location,
            status
        FROM cafe_tables
        WHERE status = 'Available'
        ORDER BY table_number ASC
    ");

    $tables =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $tables = [];

}


/*
|--------------------------------------------------------------------------
| LOAD ADMIN HEADER & SIDEBAR
|--------------------------------------------------------------------------
*/

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";

?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>


    <main class="admin-content">

        <div class="container-fluid py-4">


            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">

                <div>

                    <h2 class="fw-bold mb-1">

                        <i class="bi bi-calculator-fill me-2"></i>

                        Billing / POS

                    </h2>

                    <p class="text-muted mb-0">

                        Create Walk-In, Dine-In, Takeaway and Delivery orders.

                    </p>

                </div>


                <div class="text-end">

                    <span class="badge bg-success px-3 py-2">

                        <i class="bi bi-circle-fill me-1"></i>

                        POS Ready

                    </span>

                </div>

            </div>



            <!-- =========================================================
                 SUCCESS MESSAGE
            ========================================================== -->

            <?php if ($success !== ''): ?>

                <div class="alert alert-success alert-dismissible fade show">

                    <i class="bi bi-check-circle-fill me-2"></i>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>



            <!-- =========================================================
                 ERROR MESSAGES
            ========================================================== -->

            <?php if (!empty($errors)): ?>

                <div class="alert alert-danger">

                    <div class="fw-bold mb-2">

                        <i class="bi bi-exclamation-triangle-fill me-2"></i>

                        Unable to generate bill

                    </div>

                    <ul class="mb-0">

                        <?php foreach ($errors as $error): ?>

                            <li>

                                <?= htmlspecialchars(
                                    $error,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            <?php endif; ?>



            <!-- =========================================================
                 BILLING FORM
            ========================================================== -->

            <form
                method="POST"
                id="billingForm"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="action"
                    value="create_order"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['billing_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="cart_data"
                    id="cartData"
                    value=""
                >


                <div class="row g-4">


                    <!-- =================================================
                         PRODUCTS
                    ================================================== -->

                    <div class="col-lg-7">

                        <div class="card border-0 shadow-sm rounded-4">

                            <div class="card-header bg-dark text-white rounded-top-4 py-3">

                                <h5 class="mb-0">

                                    <i class="bi bi-cup-hot-fill me-2"></i>

                                    Products

                                </h5>

                            </div>


                            <div class="card-body">


                                <!-- SEARCH -->

                                <div class="row g-2 mb-4">

                                    <div class="col-md-8">

                                        <div class="input-group">

                                            <span class="input-group-text">

                                                <i class="bi bi-search"></i>

                                            </span>

                                            <input
                                                type="text"
                                                id="productSearch"
                                                class="form-control"
                                                placeholder="Search product..."
                                            >

                                        </div>

                                    </div>


                                    <div class="col-md-4">

                                        <select
                                            id="categoryFilter"
                                            class="form-select"
                                        >

                                            <option value="">
                                                All Categories
                                            </option>

                                            <?php foreach ($categories as $category): ?>

                                                <option
                                                    value="<?= (int)$category['category_id']; ?>"
                                                >

                                                    <?= htmlspecialchars(
                                                        $category['category_name'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ); ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>

                                </div>



                                <!-- PRODUCTS -->

                                <div
                                    class="row g-3"
                                    id="productContainer"
                                >

                                    <?php if (empty($products)): ?>

                                        <div class="col-12">

                                            <div class="alert alert-warning">

                                                No active products available.

                                            </div>

                                        </div>

                                    <?php else: ?>

                                        <?php foreach ($products as $product): ?>

                                            <?php

                                            $regularPrice =
                                                (float)$product['price'];

                                            $discountPrice =
                                                $product['discount_price'] !== null
                                                    ? (float)$product['discount_price']
                                                    : 0.00;

                                            $finalPrice =
                                                $regularPrice;

                                            if (
                                                $discountPrice > 0 &&
                                                $discountPrice < $regularPrice
                                            ) {

                                                $finalPrice =
                                                    $discountPrice;

                                            }

                                            $stock =
                                                (int)$product['stock'];

                                            ?>

                                            <div
                                                class="col-xl-4 col-md-6 product-item"
                                                data-name="<?= htmlspecialchars(
                                                    strtolower(
                                                        $product['product_name']
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>"
                                                data-category="<?= (int)$product['category_id']; ?>"
                                            >

                                                <div class="card h-100 border shadow-sm">

                                                    <div class="card-body">

                                                        <div class="d-flex justify-content-between align-items-start gap-2">

                                                            <h6 class="fw-bold mb-2">

                                                                <?= htmlspecialchars(
                                                                    $product['product_name'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ); ?>

                                                            </h6>


                                                            <?php if (
                                                                strtolower(
                                                                    (string)$product['food_type']
                                                                ) === 'veg'
                                                            ): ?>

                                                                <span class="badge bg-success">
                                                                    Veg
                                                                </span>

                                                            <?php elseif (
                                                                strtolower(
                                                                    (string)$product['food_type']
                                                                ) === 'non-veg'
                                                            ): ?>

                                                                <span class="badge bg-danger">
                                                                    Non-Veg
                                                                </span>

                                                            <?php endif; ?>

                                                        </div>


                                                        <div class="mb-2">

                                                            <?php if (
                                                                $discountPrice > 0 &&
                                                                $discountPrice < $regularPrice
                                                            ): ?>

                                                                <small class="text-muted text-decoration-line-through">

                                                                    <?= htmlspecialchars(
                                                                        $currency
                                                                    ); ?><?= number_format(
                                                                        $regularPrice,
                                                                        2
                                                                    ); ?>

                                                                </small>

                                                            <?php endif; ?>


                                                            <div class="fs-5 fw-bold text-success">

                                                                <?= htmlspecialchars(
                                                                    $currency
                                                                ); ?><?= number_format(
                                                                    $finalPrice,
                                                                    2
                                                                ); ?>

                                                            </div>

                                                        </div>


                                                        <small class="<?= $stock > 0 ? 'text-muted' : 'text-danger'; ?>">

                                                            <?php if ($stock > 0): ?>

                                                                <i class="bi bi-box-seam me-1"></i>

                                                                Stock:
                                                                <?= $stock; ?>

                                                            <?php else: ?>

                                                                <i class="bi bi-x-circle me-1"></i>

                                                                Out of Stock

                                                            <?php endif; ?>

                                                        </small>


                                                        <button
                                                            type="button"
                                                            class="btn btn-success w-100 mt-3 addToCart"
                                                            data-id="<?= (int)$product['product_id']; ?>"
                                                            data-name="<?= htmlspecialchars(
                                                                $product['product_name'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ); ?>"
                                                            data-price="<?= htmlspecialchars(
                                                                number_format(
                                                                    $finalPrice,
                                                                    2,
                                                                    '.',
                                                                    ''
                                                                )
                                                            ); ?>"
                                                            data-stock="<?= $stock; ?>"
                                                            <?= $stock <= 0 ? 'disabled' : ''; ?>
                                                        >

                                                            <i class="bi bi-plus-circle-fill me-1"></i>

                                                            <?= $stock > 0
                                                                ? 'Add to Bill'
                                                                : 'Out of Stock'; ?>

                                                        </button>

                                                    </div>

                                                </div>

                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </div>


                                <div
                                    id="noProductsFound"
                                    class="text-center text-muted py-5 d-none"
                                >

                                    <i class="bi bi-search fs-1"></i>

                                    <p class="mt-2 mb-0">
                                        No products found.
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>



                    <!-- =================================================
                         CURRENT BILL
                    ================================================== -->

                    <div class="col-lg-5">

                        <div class="card border-0 shadow-sm rounded-4">

                            <div class="card-header bg-success text-white rounded-top-4 py-3">

                                <h5 class="mb-0">

                                    <i class="bi bi-receipt-cutoff me-2"></i>

                                    Current Bill

                                </h5>

                            </div>


                            <div class="card-body">


                                <!-- CART -->

                                <div
                                    id="cartItems"
                                    class="mb-3"
                                >

                                    <div class="text-center text-muted py-5">

                                        <i class="bi bi-cart-x fs-1"></i>

                                        <p class="mt-3 mb-0">
                                            No Products Added
                                        </p>

                                    </div>

                                </div>


                                <hr>


                                <!-- SUBTOTAL -->

                                <div class="d-flex justify-content-between">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong id="subtotal">
                                        <?= htmlspecialchars($currency); ?>0.00
                                    </strong>

                                </div>


                                <!-- TAX -->

                                <div class="d-flex justify-content-between mt-2">

                                    <span>
                                        GST
                                        (<?= number_format($taxPercentage, 2); ?>%)
                                    </span>

                                    <strong id="gst">
                                        <?= htmlspecialchars($currency); ?>0.00
                                    </strong>

                                </div>


                                <!-- DELIVERY -->

                                <div
                                    class="d-flex justify-content-between mt-2"
                                    id="deliveryRow"
                                    style="display:none !important;"
                                >

                                    <span>
                                        Delivery Charge
                                    </span>

                                    <strong id="deliveryCharge">
                                        <?= htmlspecialchars($currency); ?>0.00
                                    </strong>

                                </div>


                                <hr>


                                <!-- GRAND TOTAL -->

                                <div class="d-flex justify-content-between align-items-center">

                                    <h4 class="mb-0">
                                        Grand Total
                                    </h4>

                                    <h4
                                        class="text-success mb-0"
                                        id="grandTotal"
                                    >
                                        <?= htmlspecialchars($currency); ?>0.00
                                    </h4>

                                </div>


                                <hr>


                                <!-- CUSTOMER -->

                                <h6 class="fw-bold mb-3">

                                    <i class="bi bi-person-fill me-1"></i>

                                    Customer Details

                                </h6>


                                <div class="mb-3">

                                    <label
                                        for="customer_name"
                                        class="form-label"
                                    >
                                        Customer Name
                                        <span class="text-danger">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        name="customer_name"
                                        id="customer_name"
                                        class="form-control"
                                        placeholder="Enter customer name"
                                        required
                                    >

                                </div>


                                <div class="row g-2">

                                    <div class="col-md-6">

                                        <label
                                            for="phone"
                                            class="form-label"
                                        >
                                            Phone
                                        </label>

                                        <input
                                            type="text"
                                            name="phone"
                                            id="phone"
                                            class="form-control"
                                            placeholder="Phone number"
                                        >

                                    </div>


                                    <div class="col-md-6">

                                        <label
                                            for="email"
                                            class="form-label"
                                        >
                                            Email
                                        </label>

                                        <input
                                            type="email"
                                            name="email"
                                            id="email"
                                            class="form-control"
                                            placeholder="Email"
                                        >

                                    </div>

                                </div>


                                <!-- ORDER TYPE -->

                                <div class="mt-3">

                                    <label
                                        for="orderType"
                                        class="form-label"
                                    >
                                        Order Type
                                    </label>

                                    <select
                                        class="form-select"
                                        name="order_type"
                                        id="orderType"
                                    >

                                        <option value="Walk-In">
                                            Walk-In
                                        </option>

                                        <option value="Dine-In">
                                            Dine-In
                                        </option>

                                        <option value="Takeaway">
                                            Takeaway
                                        </option>

                                        <option value="Delivery">
                                            Delivery
                                        </option>

                                    </select>

                                </div>


                                <!-- TABLE -->

                                <div
                                    id="tableSection"
                                    class="mt-3"
                                    style="display:none;"
                                >

                                    <label
                                        for="tableId"
                                        class="form-label"
                                    >
                                        Select Table
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select
                                        class="form-select"
                                        name="table_id"
                                        id="tableId"
                                    >

                                        <option value="">
                                            Select available table
                                        </option>

                                        <?php foreach ($tables as $table): ?>

                                            <option
                                                value="<?= (int)$table['table_id']; ?>"
                                            >

                                                Table
                                                <?= htmlspecialchars(
                                                    $table['table_number'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>

                                                —
                                                <?= (int)$table['capacity']; ?>
                                                Seats
                                                —
                                                <?= htmlspecialchars(
                                                    $table['location'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>


                                <!-- ADDRESS -->

                                <div class="mt-3">

                                    <label
                                        for="address"
                                        class="form-label"
                                    >
                                        Address
                                    </label>

                                    <textarea
                                        name="address"
                                        id="address"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Customer address"
                                    ></textarea>

                                </div>


                                <!-- PAYMENT -->

                                <div class="mt-3">

                                    <label
                                        for="paymentMethod"
                                        class="form-label"
                                    >
                                        Payment Method
                                    </label>

                                    <select
                                        class="form-select"
                                        name="payment_method"
                                        id="paymentMethod"
                                    >

                                        <option value="Cash">
                                            Cash
                                        </option>

                                        <option value="UPI">
                                            UPI
                                        </option>

                                        <option value="Card">
                                            Card
                                        </option>

                                    </select>

                                </div>


                                <!-- NOTES -->

                                <div class="mt-3">

                                    <label
                                        for="notes"
                                        class="form-label"
                                    >
                                        Notes
                                    </label>

                                    <textarea
                                        name="notes"
                                        id="notes"
                                        class="form-control"
                                        rows="2"
                                        placeholder="Special order notes..."
                                    ></textarea>

                                </div>


                                <!-- GENERATE BILL -->

                                <button
                                    type="submit"
                                    class="btn btn-success w-100 py-3 mt-4"
                                    id="generateBillButton"
                                    disabled
                                >

                                    <i class="bi bi-receipt-cutoff me-2"></i>

                                    Generate Bill

                                </button>


                                <!-- CLEAR -->

                                <button
                                    type="button"
                                    class="btn btn-outline-danger w-100 mt-2"
                                    id="clearBill"
                                >

                                    <i class="bi bi-trash3 me-1"></i>

                                    Clear Bill

                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </main>

</div>


<!-- =============================================================
     BILLING JAVASCRIPT
============================================================== -->

<script>

"use strict";


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

const cart = {};

const taxRate =
    <?= json_encode($taxPercentage); ?>;

const defaultDeliveryCharge =
    <?= json_encode($deliveryCharge); ?>;

const currency =
    <?= json_encode($currency); ?>;


/*
|--------------------------------------------------------------------------
| DOM ELEMENTS
|--------------------------------------------------------------------------
*/

const productSearch =
    document.getElementById("productSearch");

const categoryFilter =
    document.getElementById("categoryFilter");

const cartItems =
    document.getElementById("cartItems");

const subtotalElement =
    document.getElementById("subtotal");

const gstElement =
    document.getElementById("gst");

const deliveryRow =
    document.getElementById("deliveryRow");

const deliveryChargeElement =
    document.getElementById("deliveryCharge");

const grandTotalElement =
    document.getElementById("grandTotal");

const orderType =
    document.getElementById("orderType");

const tableSection =
    document.getElementById("tableSection");

const tableId =
    document.getElementById("tableId");

const cartData =
    document.getElementById("cartData");

const generateBillButton =
    document.getElementById("generateBillButton");

const billingForm =
    document.getElementById("billingForm");

const noProductsFound =
    document.getElementById("noProductsFound");


/*
|--------------------------------------------------------------------------
| FORMAT MONEY
|--------------------------------------------------------------------------
*/

function money(value) {

    return currency +
        Number(value).toFixed(2);

}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}


/*
|--------------------------------------------------------------------------
| FILTER PRODUCTS
|--------------------------------------------------------------------------
*/

function filterProducts() {

    const keyword =
        productSearch.value
            .trim()
            .toLowerCase();

    const category =
        categoryFilter.value;

    let visibleCount = 0;


    document
        .querySelectorAll(".product-item")
        .forEach(card => {

            const name =
                card.dataset.name || "";

            const cardCategory =
                card.dataset.category || "";


            let show = true;


            if (
                keyword &&
                !name.includes(keyword)
            ) {

                show = false;

            }


            if (
                category &&
                cardCategory !== category
            ) {

                show = false;

            }


            card.style.display =
                show ? "" : "none";


            if (show) {

                visibleCount++;

            }

        });


    if (visibleCount === 0) {

        noProductsFound.classList.remove(
            "d-none"
        );

    } else {

        noProductsFound.classList.add(
            "d-none"
        );

    }

}


productSearch.addEventListener(
    "input",
    filterProducts
);

categoryFilter.addEventListener(
    "change",
    filterProducts
);


/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(".addToCart")
    .forEach(button => {

        button.addEventListener(
            "click",
            function () {

                const id =
                    this.dataset.id;

                const name =
                    this.dataset.name;

                const price =
                    parseFloat(
                        this.dataset.price
                    );

                const stock =
                    parseInt(
                        this.dataset.stock,
                        10
                    );


                if (
                    !id ||
                    !name ||
                    !Number.isFinite(price) ||
                    stock <= 0
                ) {

                    return;

                }


                if (cart[id]) {

                    if (
                        cart[id].qty >= stock
                    ) {

                        alert(
                            "Maximum available stock reached."
                        );

                        return;

                    }

                    cart[id].qty++;

                } else {

                    cart[id] = {

                        id: id,

                        name: name,

                        price: price,

                        qty: 1,

                        stock: stock

                    };

                }


                renderCart();

            }
        );

    });


/*
|--------------------------------------------------------------------------
| CHANGE QUANTITY
|--------------------------------------------------------------------------
*/

function changeQty(id, change) {

    if (!cart[id]) {
        return;
    }


    const newQuantity =
        cart[id].qty + change;


    if (newQuantity <= 0) {

        delete cart[id];

    } else if (
        newQuantity > cart[id].stock
    ) {

        alert(
            "Maximum available stock reached."
        );

        return;

    } else {

        cart[id].qty =
            newQuantity;

    }


    renderCart();

}


/*
|--------------------------------------------------------------------------
| REMOVE ITEM
|--------------------------------------------------------------------------
*/

function removeItem(id) {

    if (cart[id]) {

        delete cart[id];

    }

    renderCart();

}


/*
|--------------------------------------------------------------------------
| RENDER CART
|--------------------------------------------------------------------------
*/

function renderCart() {

    const ids =
        Object.keys(cart);


    let subtotal = 0;


    if (ids.length === 0) {

        cartItems.innerHTML = `

            <div class="text-center text-muted py-5">

                <i class="bi bi-cart-x fs-1"></i>

                <p class="mt-3 mb-0">
                    No Products Added
                </p>

            </div>

        `;

        generateBillButton.disabled =
            true;

    } else {

        let html = "";


        ids.forEach(id => {

            const item =
                cart[id];

            const itemTotal =
                item.price * item.qty;


            subtotal += itemTotal;


            html += `

                <div class="border rounded-3 p-3 mb-2">

                    <div class="d-flex justify-content-between gap-2">

                        <div>

                            <strong>
                                ${escapeHtml(item.name)}
                            </strong>

                            <div class="small text-muted mt-1">

                                ${money(item.price)}
                                ×
                                ${item.qty}

                            </div>

                        </div>


                        <strong class="text-success">

                            ${money(itemTotal)}

                        </strong>

                    </div>


                    <div class="d-flex justify-content-between align-items-center mt-3">

                        <div class="btn-group btn-group-sm">

                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                onclick="changeQty('${id}', -1)"
                            >
                                −
                            </button>


                            <button
                                type="button"
                                class="btn btn-light"
                                disabled
                            >
                                ${item.qty}
                            </button>


                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                onclick="changeQty('${id}', 1)"
                            >
                                +
                            </button>

                        </div>


                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            onclick="removeItem('${id}')"
                        >

                            <i class="bi bi-trash3"></i>

                        </button>

                    </div>

                </div>

            `;

        });


        cartItems.innerHTML =
            html;


        generateBillButton.disabled =
            false;

    }


    /*
    |--------------------------------------------------------------------------
    | TAX
    |--------------------------------------------------------------------------
    */

    const gst =
        subtotal *
        taxRate /
        100;


    /*
    |--------------------------------------------------------------------------
    | DELIVERY
    |--------------------------------------------------------------------------
    */

    let delivery = 0;


    if (
        orderType.value === "Delivery"
    ) {

        delivery =
            defaultDeliveryCharge;

        deliveryRow.style
            .setProperty(
                "display",
                "flex",
                "important"
            );

    } else {

        deliveryRow.style
            .setProperty(
                "display",
                "none",
                "important"
            );

    }


    /*
    |--------------------------------------------------------------------------
    | GRAND TOTAL
    |--------------------------------------------------------------------------
    */

    const total =
        subtotal +
        gst +
        delivery;


    subtotalElement.textContent =
        money(subtotal);

    gstElement.textContent =
        money(gst);

    deliveryChargeElement.textContent =
        money(delivery);

    grandTotalElement.textContent =
        money(total);


    /*
    |--------------------------------------------------------------------------
    | CART DATA
    |--------------------------------------------------------------------------
    */

    const cartArray =
        Object.values(cart)
            .map(item => ({

                id: item.id,

                qty: item.qty

            }));


    cartData.value =
        JSON.stringify(cartArray);

}


/*
|--------------------------------------------------------------------------
| ORDER TYPE
|--------------------------------------------------------------------------
*/

orderType.addEventListener(
    "change",
    function () {

        if (
            this.value === "Dine-In"
        ) {

            tableSection.style.display =
                "block";

            tableId.required =
                true;

        } else {

            tableSection.style.display =
                "none";

            tableId.required =
                false;

            tableId.value =
                "";

        }


        renderCart();

    }
);


/*
|--------------------------------------------------------------------------
| CLEAR BILL
|--------------------------------------------------------------------------
*/

document
    .getElementById("clearBill")
    .addEventListener(
        "click",
        function () {

            Object.keys(cart)
                .forEach(id => {

                    delete cart[id];

                });


            billingForm.reset();


            tableSection.style.display =
                "none";

            tableId.required =
                false;


            renderCart();

        }
    );


/*
|--------------------------------------------------------------------------
| FORM SUBMIT VALIDATION
|--------------------------------------------------------------------------
*/

billingForm.addEventListener(
    "submit",
    function (event) {

        const cartItemsCount =
            Object.keys(cart).length;


        if (cartItemsCount === 0) {

            event.preventDefault();

            alert(
                "Please add at least one product."
            );

            return;

        }


        if (
            orderType.value === "Dine-In" &&
            !tableId.value
        ) {

            event.preventDefault();

            alert(
                "Please select a table for Dine-In."
            );

            tableId.focus();

            return;

        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE CART DATA BEFORE SUBMIT
        |--------------------------------------------------------------------------
        */

        const cartArray =
            Object.values(cart)
                .map(item => ({

                    id: item.id,

                    qty: item.qty

                }));


        cartData.value =
            JSON.stringify(cartArray);


        /*
        |--------------------------------------------------------------------------
        | DISABLE BUTTON
        |--------------------------------------------------------------------------
        */

        generateBillButton.disabled =
            true;

        generateBillButton.innerHTML = `

            <span
                class="spinner-border spinner-border-sm me-2"
            ></span>

            Generating Bill...

        `;

    }
);


/*
|--------------------------------------------------------------------------
| INITIAL RENDER
|--------------------------------------------------------------------------
*/

renderCart();

</script>


<?php require_once "includes/a-footer.php"; ?>