<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Edit Order";

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];

$allowedOrderStatuses = [
    'Pending',
    'Accepted',
    'Preparing',
    'Ready',
    'Out for Delivery',
    'Completed',
    'Cancelled'
];

$allowedPaymentStatuses = [
    'Pending',
    'Paid',
    'Failed',
    'Refunded'
];

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirectTo(string $url): never
{
    header("Location: " . $url);
    exit;
}

function redirectWithMessage(
    string $type,
    string $message,
    int $orderId
): never {
    header(
        "Location: edit_order.php?" .
        http_build_query([
            'id' => $orderId,
            'message_type' => $type,
            'message' => $message
        ])
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Order ID
|--------------------------------------------------------------------------
*/
$orderId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId < 1) {

    redirectTo(
        "orders.php?" .
        http_build_query([
            'message_type' => 'danger',
            'message' => 'Invalid order selected.'
        ])
    );
}

/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/
if (
    isset($_GET['message']) &&
    isset($_GET['message_type'])
) {
    $message = (string) $_GET['message'];

    $messageType = $_GET['message_type'] === 'success'
        ? 'success'
        : 'danger';
}


/*
|--------------------------------------------------------------------------
| Update Order
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {
        redirectWithMessage(
            'danger',
            'Invalid security token. Please try again.',
            $orderId
        );
    }

    $action = $_POST['action'] ?? '';

    if ($action !== 'update_order') {

        redirectWithMessage(
            'danger',
            'Invalid action.',
            $orderId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Posted Values
    |--------------------------------------------------------------------------
    */
    $orderStatus = trim(
        (string) ($_POST['order_status'] ?? '')
    );

    $paymentStatus = trim(
        (string) ($_POST['payment_status'] ?? '')
    );

    $notes = trim(
        (string) ($_POST['notes'] ?? '')
    );

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    if (!in_array(
        $orderStatus,
        $allowedOrderStatuses,
        true
    )) {
        redirectWithMessage(
            'danger',
            'Invalid order status selected.',
            $orderId
        );
    }

    if (!in_array(
        $paymentStatus,
        $allowedPaymentStatuses,
        true
    )) {
        redirectWithMessage(
            'danger',
            'Invalid payment status selected.',
            $orderId
        );
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Fetch Existing Order
        |--------------------------------------------------------------------------
        */
        $check = $pdo->prepare("
            SELECT
                order_id,
                order_number,
                order_status,
                payment_status,
                table_id
            FROM orders
            WHERE order_id = :order_id
            LIMIT 1
            FOR UPDATE
        ");

        $pdo->beginTransaction();

        $check->execute([
            ':order_id' => $orderId
        ]);

        $existingOrder = $check->fetch(PDO::FETCH_ASSOC);

        if (!$existingOrder) {

            $pdo->rollBack();

            redirectTo(
                "orders.php?" .
                http_build_query([
                    'message_type' => 'danger',
                    'message' => 'Order not found.'
                ])
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Order
        |--------------------------------------------------------------------------
        */
        $update = $pdo->prepare("
            UPDATE orders
            SET
                order_status = :order_status,
                payment_status = :payment_status,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE order_id = :order_id
        ");

        $update->execute([
            ':order_status' => $orderStatus,
            ':payment_status' => $paymentStatus,
            ':notes' => $notes !== ''
                ? $notes
                : null,
            ':order_id' => $orderId
        ]);


        /*
        |--------------------------------------------------------------------------
        | Table Status Synchronization
        |--------------------------------------------------------------------------
        |
        | Only update a table when this order is actually connected
        | to a cafe table.
        |
        */
        if (
            !empty($existingOrder['table_id']) &&
            $existingOrder['table_id'] !== null
        ) {

            $tableId = (int) $existingOrder['table_id'];

            /*
            |--------------------------------------------------------------------------
            | Completed / Cancelled
            |--------------------------------------------------------------------------
            |
            | Release the table only if no other active order is using it.
            |
            */
            if (
                in_array(
                    $orderStatus,
                    ['Completed', 'Cancelled'],
                    true
                )
            ) {

                $activeOrderCheck = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM orders
                    WHERE table_id = :table_id
                      AND order_id != :order_id
                      AND order_status IN (
                          'Pending',
                          'Accepted',
                          'Preparing',
                          'Ready'
                      )
                ");

                $activeOrderCheck->execute([
                    ':table_id' => $tableId,
                    ':order_id' => $orderId
                ]);

                $activeOrders = (int)
                    $activeOrderCheck->fetchColumn();

                /*
                |--------------------------------------------------------------------------
                | Check Confirmed Booking
                |--------------------------------------------------------------------------
                */
                $bookingCheck = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM table_bookings
                    WHERE table_id = :table_id
                      AND booking_status = 'Confirmed'
                      AND booking_date >= CURDATE()
                ");

                $bookingCheck->execute([
                    ':table_id' => $tableId
                ]);

                $confirmedBookings = (int)
                    $bookingCheck->fetchColumn();


                if (
                    $activeOrders === 0 &&
                    $confirmedBookings === 0
                ) {

                    $releaseTable = $pdo->prepare("
                        UPDATE cafe_tables
                        SET
                            status = 'Available',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE table_id = :table_id
                          AND status = 'Occupied'
                    ");

                    $releaseTable->execute([
                        ':table_id' => $tableId
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Active Order
            |--------------------------------------------------------------------------
            */
            elseif (
                in_array(
                    $orderStatus,
                    [
                        'Pending',
                        'Accepted',
                        'Preparing',
                        'Ready'
                    ],
                    true
                )
            ) {

                $occupyTable = $pdo->prepare("
                    UPDATE cafe_tables
                    SET
                        status = 'Occupied',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE table_id = :table_id
                      AND status != 'Maintenance'
                ");

                $occupyTable->execute([
                    ':table_id' => $tableId
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Payment Record Synchronization
        |--------------------------------------------------------------------------
        |
        | Existing payment record is updated when available.
        | Otherwise a payment record is created for paid orders.
        |
        */
        $paymentCheck = $pdo->prepare("
            SELECT
                payment_id
            FROM payments
            WHERE order_id = :order_id
            ORDER BY payment_id DESC
            LIMIT 1
        ");

        $paymentCheck->execute([
            ':order_id' => $orderId
        ]);

        $paymentRecord = $paymentCheck->fetch(PDO::FETCH_ASSOC);


        if ($paymentRecord) {

            $paymentUpdate = $pdo->prepare("
                UPDATE payments
                SET
                    payment_status = :payment_status,
                    amount = (
                        SELECT grand_total
                        FROM orders
                        WHERE order_id = :order_id
                    )
                WHERE payment_id = :payment_id
            ");

            /*
            |--------------------------------------------------------------------------
            | Payment Table Uses:
            | Pending / Success / Failed / Refunded
            |--------------------------------------------------------------------------
            */
            $paymentTableStatus = match ($paymentStatus) {
                'Paid' => 'Success',
                'Failed' => 'Failed',
                'Refunded' => 'Refunded',
                default => 'Pending'
            };

            $paymentUpdate->execute([
                ':payment_status' => $paymentTableStatus,
                ':order_id' => $orderId,
                ':payment_id' => $paymentRecord['payment_id']
            ]);

        } elseif ($paymentStatus !== 'Pending') {

            /*
            |--------------------------------------------------------------------------
            | Get Payment Method + Total
            |--------------------------------------------------------------------------
            */
            $paymentInfo = $pdo->prepare("
                SELECT
                    grand_total,
                    payment_method
                FROM orders
                WHERE order_id = :order_id
                LIMIT 1
            ");

            $paymentInfo->execute([
                ':order_id' => $orderId
            ]);

            $paymentData = $paymentInfo->fetch(PDO::FETCH_ASSOC);

            if ($paymentData) {

                $paymentTableStatus = match ($paymentStatus) {
                    'Paid' => 'Success',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                    default => 'Pending'
                };

                $insertPayment = $pdo->prepare("
                    INSERT INTO payments (
                        order_id,
                        payment_method,
                        payment_status,
                        amount,
                        payment_date
                    )
                    VALUES (
                        :order_id,
                        :payment_method,
                        :payment_status,
                        :amount,
                        CURRENT_TIMESTAMP
                    )
                ");

                $insertPayment->execute([
                    ':order_id' => $orderId,
                    ':payment_method' => $paymentData['payment_method'],
                    ':payment_status' => $paymentTableStatus,
                    ':amount' => $paymentData['grand_total']
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */
        $pdo->commit();

        redirectWithMessage(
            'success',
            'Order ' .
            $existingOrder['order_number'] .
            ' updated successfully.',
            $orderId
        );

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirectWithMessage(
            'danger',
            'Unable to update the order. Please try again.',
            $orderId
        );
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Complete Order
|--------------------------------------------------------------------------
*/
$order = null;

try {

    $stmt = $pdo->prepare("
        SELECT
            o.*,
            ct.table_number,
            ct.capacity AS table_capacity,
            ct.location AS table_location
        FROM orders o
        LEFT JOIN cafe_tables ct
            ON ct.table_id = o.table_id
        WHERE o.order_id = :order_id
        LIMIT 1
    ");

    $stmt->execute([
        ':order_id' => $orderId
    ]);

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $order = null;
}

if (!$order) {

    redirectTo(
        "orders.php?" .
        http_build_query([
            'message_type' => 'danger',
            'message' => 'Order not found.'
        ])
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Order Items
|--------------------------------------------------------------------------
*/
$orderItems = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            oi.item_id,
            oi.product_id,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            oi.special_instruction,
            p.product_name
        FROM order_items oi
        LEFT JOIN products p
            ON p.product_id = oi.product_id
        WHERE oi.order_id = :order_id
        ORDER BY oi.item_id ASC
    ");

    $stmt->execute([
        ':order_id' => $orderId
    ]);

    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $orderItems = [];
}


/*
|--------------------------------------------------------------------------
| Fetch Latest Payment
|--------------------------------------------------------------------------
*/
$payment = null;

try {

    $stmt = $pdo->prepare("
        SELECT
            payment_id,
            transaction_id,
            razorpay_order_id,
            razorpay_payment_id,
            payment_method,
            payment_status,
            amount,
            payment_date,
            remarks
        FROM payments
        WHERE order_id = :order_id
        ORDER BY payment_id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':order_id' => $orderId
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $payment = null;
}


/*
|--------------------------------------------------------------------------
| Header + Sidebar
|--------------------------------------------------------------------------
*/
require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- Header -->
        <div class="page-header mb-4">

            <div>

                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <h1 class="page-title mb-0">
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit Order
                    </h1>

                    <span class="order-number-badge">
                        #<?php echo h((string) $order['order_number']); ?>
                    </span>

                </div>

                <p class="page-subtitle">
                    Update order status, payment status and order notes.
                </p>

            </div>


            <div class="d-flex gap-2 flex-wrap">

                <a
                    href="order_details.php?id=<?php echo $orderId; ?>"
                    class="btn btn-outline-primary"
                >
                    <i class="bi bi-eye me-1"></i>
                    View Order
                </a>

                <a
                    href="orders.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Orders
                </a>

            </div>

        </div>


        <!-- Alert -->
        <?php if ($message !== ''): ?>

            <div
                class="alert alert-<?php echo h($messageType); ?> alert-dismissible fade show"
                data-auto-hide="true"
                role="alert"
            >

                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>

                <?php echo h($message); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>

            </div>

        <?php endif; ?>


        <div class="row g-4">

            <!-- Left Column -->
            <div class="col-xl-8">

                <!-- Order Status Card -->
                <div class="edit-order-card mb-4">

                    <div class="edit-order-card-header">

                        <div>

                            <h3>
                                <i class="bi bi-arrow-repeat me-2"></i>
                                Order Status
                            </h3>

                            <p>
                                Update the current progress of this order.
                            </p>

                        </div>

                    </div>


                    <div class="edit-order-card-body">

                        <form
                            method="POST"
                            action="edit_order.php?id=<?php echo $orderId; ?>"
                            id="editOrderForm"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo h($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="update_order"
                            >


                            <div class="row g-4">

                                <!-- Order Status -->
                                <div class="col-md-6">

                                    <label
                                        for="order_status"
                                        class="form-label fw-semibold"
                                    >
                                        Order Status
                                    </label>

                                    <select
                                        id="order_status"
                                        name="order_status"
                                        class="form-select form-select-lg"
                                        required
                                    >

                                        <?php foreach ($allowedOrderStatuses as $status): ?>

                                            <option
                                                value="<?php echo h($status); ?>"
                                                <?php echo $order['order_status'] === $status ? 'selected' : ''; ?>
                                            >
                                                <?php echo h($status); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <div class="form-text">
                                        Current:
                                        <strong>
                                            <?php echo h((string) $order['order_status']); ?>
                                        </strong>
                                    </div>

                                </div>


                                <!-- Payment Status -->
                                <div class="col-md-6">

                                    <label
                                        for="payment_status"
                                        class="form-label fw-semibold"
                                    >
                                        Payment Status
                                    </label>

                                    <select
                                        id="payment_status"
                                        name="payment_status"
                                        class="form-select form-select-lg"
                                        required
                                    >

                                        <?php foreach ($allowedPaymentStatuses as $status): ?>

                                            <option
                                                value="<?php echo h($status); ?>"
                                                <?php echo $order['payment_status'] === $status ? 'selected' : ''; ?>
                                            >
                                                <?php echo h($status); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                    <div class="form-text">
                                        Current:
                                        <strong>
                                            <?php echo h((string) $order['payment_status']); ?>
                                        </strong>
                                    </div>

                                </div>


                                <!-- Notes -->
                                <div class="col-12">

                                    <label
                                        for="notes"
                                        class="form-label fw-semibold"
                                    >
                                        Order Notes
                                    </label>

                                    <textarea
                                        id="notes"
                                        name="notes"
                                        class="form-control"
                                        rows="4"
                                        maxlength="1000"
                                        placeholder="Add an internal order note..."
                                    ><?php echo h((string) ($order['notes'] ?? '')); ?></textarea>

                                    <div class="form-text">
                                        Maximum 1000 characters.
                                    </div>

                                </div>


                                <!-- Submit -->
                                <div class="col-12">

                                    <div class="d-flex gap-2 flex-wrap">

                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            id="saveOrderButton"
                                        >
                                            <i class="bi bi-check-lg me-1"></i>
                                            Save Changes
                                        </button>

                                        <a
                                            href="order_details.php?id=<?php echo $orderId; ?>"
                                            class="btn btn-outline-secondary"
                                        >
                                            Cancel
                                        </a>

                                    </div>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>


                <!-- Order Items -->
                <div class="edit-order-card mb-4">

                    <div class="edit-order-card-header">

                        <div>

                            <h3>
                                <i class="bi bi-basket2 me-2"></i>
                                Order Items
                            </h3>

                            <p>
                                Items included in this order.
                            </p>

                        </div>

                        <span class="items-count-badge">
                            <?php echo count($orderItems); ?> item(s)
                        </span>

                    </div>


                    <div class="edit-order-card-body p-0">

                        <?php if (!empty($orderItems)): ?>

                            <div class="table-responsive">

                                <table class="table edit-items-table mb-0">

                                    <thead>

                                        <tr>

                                            <th>
                                                Product
                                            </th>

                                            <th class="text-center">
                                                Qty
                                            </th>

                                            <th class="text-end">
                                                Unit Price
                                            </th>

                                            <th class="text-end">
                                                Total
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($orderItems as $item): ?>

                                            <tr>

                                                <td>

                                                    <div class="fw-semibold">
                                                        <?php echo h((string) ($item['product_name'] ?? 'Product')); ?>
                                                    </div>

                                                    <?php if (!empty($item['special_instruction'])): ?>

                                                        <div class="small text-muted mt-1">

                                                            <i class="bi bi-chat-left-text me-1"></i>

                                                            <?php echo h((string) $item['special_instruction']); ?>

                                                        </div>

                                                    <?php endif; ?>

                                                </td>


                                                <td class="text-center">
                                                    <?php echo (int) $item['quantity']; ?>
                                                </td>


                                                <td class="text-end">
                                                    ₹<?php echo number_format((float) $item['unit_price'], 2); ?>
                                                </td>


                                                <td class="text-end fw-semibold">
                                                    ₹<?php echo number_format((float) $item['total_price'], 2); ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="empty-order-items">

                                <i class="bi bi-basket"></i>

                                <p>
                                    No order items found.
                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- Customer Details -->
                <div class="edit-order-card">

                    <div class="edit-order-card-header">

                        <div>

                            <h3>
                                <i class="bi bi-person me-2"></i>
                                Customer Details
                            </h3>

                        </div>

                    </div>


                    <div class="edit-order-card-body">

                        <div class="customer-detail-grid">

                            <div class="customer-detail-item">

                                <span>
                                    <i class="bi bi-person"></i>
                                    Name
                                </span>

                                <strong>
                                    <?php echo h((string) $order['customer_name']); ?>
                                </strong>

                            </div>


                            <div class="customer-detail-item">

                                <span>
                                    <i class="bi bi-telephone"></i>
                                    Phone
                                </span>

                                <strong>
                                    <?php echo h((string) ($order['phone'] ?? '—')); ?>
                                </strong>

                            </div>


                            <div class="customer-detail-item">

                                <span>
                                    <i class="bi bi-envelope"></i>
                                    Email
                                </span>

                                <strong>
                                    <?php echo h((string) ($order['email'] ?? '—')); ?>
                                </strong>

                            </div>


                            <div class="customer-detail-item">

                                <span>
                                    <i class="bi bi-geo-alt"></i>
                                    Address
                                </span>

                                <strong>
                                    <?php echo h((string) ($order['address'] ?? '—')); ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Right Column -->
            <div class="col-xl-4">

                <!-- Order Summary -->
                <div class="edit-order-card mb-4">

                    <div class="edit-order-card-header">

                        <div>

                            <h3>
                                <i class="bi bi-receipt me-2"></i>
                                Order Summary
                            </h3>

                        </div>

                    </div>


                    <div class="edit-order-card-body">

                        <div class="summary-row">

                            <span>
                                Subtotal
                            </span>

                            <strong>
                                ₹<?php echo number_format((float) $order['subtotal'], 2); ?>
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Discount
                            </span>

                            <strong class="text-success">
                                -₹<?php echo number_format((float) $order['discount'], 2); ?>
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Tax
                            </span>

                            <strong>
                                ₹<?php echo number_format((float) $order['tax'], 2); ?>
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Delivery Charge
                            </span>

                            <strong>
                                ₹<?php echo number_format((float) $order['delivery_charge'], 2); ?>
                            </strong>

                        </div>


                        <hr>


                        <div class="summary-total">

                            <span>
                                Grand Total
                            </span>

                            <strong>
                                ₹<?php echo number_format((float) $order['grand_total'], 2); ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <!-- Order Information -->
                <div class="edit-order-card mb-4">

                    <div class="edit-order-card-header">

                        <h3>
                            <i class="bi bi-info-circle me-2"></i>
                            Order Information
                        </h3>

                    </div>


                    <div class="edit-order-card-body">

                        <div class="info-list">

                            <div class="info-list-row">

                                <span>
                                    Order Number
                                </span>

                                <strong>
                                    #<?php echo h((string) $order['order_number']); ?>
                                </strong>

                            </div>


                            <div class="info-list-row">

                                <span>
                                    Source
                                </span>

                                <strong>
                                    <?php echo h((string) $order['order_source']); ?>
                                </strong>

                            </div>


                            <div class="info-list-row">

                                <span>
                                    Order Type
                                </span>

                                <strong>
                                    <?php echo h((string) $order['order_type']); ?>
                                </strong>

                            </div>


                            <div class="info-list-row">

                                <span>
                                    Payment Method
                                </span>

                                <strong>
                                    <?php echo h((string) $order['payment_method']); ?>
                                </strong>

                            </div>


                            <div class="info-list-row">

                                <span>
                                    Ordered At
                                </span>

                                <strong>
                                    <?php
                                    echo h(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime((string) $order['ordered_at'])
                                        )
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="info-list-row">

                                <span>
                                    Updated At
                                </span>

                                <strong>
                                    <?php
                                    echo h(
                                        date(
                                            'd M Y, h:i A',
                                            strtotime((string) $order['updated_at'])
                                        )
                                    );
                                    ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Table Information -->
                <?php if (!empty($order['table_id'])): ?>

                    <div class="edit-order-card mb-4">

                        <div class="edit-order-card-header">

                            <h3>
                                <i class="bi bi-grid-3x3-gap me-2"></i>
                                Table
                            </h3>

                        </div>


                        <div class="edit-order-card-body">

                            <div class="table-info-box">

                                <div class="table-info-icon">
                                    <i class="bi bi-grid-3x3-gap"></i>
                                </div>

                                <div>

                                    <strong>
                                        Table <?php echo h((string) $order['table_number']); ?>
                                    </strong>

                                    <span>
                                        <?php echo h((string) $order['table_location']); ?>
                                        ·
                                        <?php echo (int) $order['table_capacity']; ?> guests
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- Payment -->
                <div class="edit-order-card">

                    <div class="edit-order-card-header">

                        <h3>
                            <i class="bi bi-credit-card me-2"></i>
                            Payment
                        </h3>

                    </div>


                    <div class="edit-order-card-body">

                        <?php if ($payment): ?>

                            <div class="payment-info-list">

                                <div>

                                    <span>
                                        Status
                                    </span>

                                    <strong>
                                        <?php echo h((string) $payment['payment_status']); ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Method
                                    </span>

                                    <strong>
                                        <?php echo h((string) $payment['payment_method']); ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Amount
                                    </span>

                                    <strong>
                                        ₹<?php echo number_format((float) $payment['amount'], 2); ?>
                                    </strong>

                                </div>


                                <?php if (!empty($payment['transaction_id'])): ?>

                                    <div>

                                        <span>
                                            Transaction ID
                                        </span>

                                        <strong class="payment-id">
                                            <?php echo h((string) $payment['transaction_id']); ?>
                                        </strong>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($payment['razorpay_payment_id'])): ?>

                                    <div>

                                        <span>
                                            Razorpay Payment
                                        </span>

                                        <strong class="payment-id">
                                            <?php echo h((string) $payment['razorpay_payment_id']); ?>
                                        </strong>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($payment['payment_date'])): ?>

                                    <div>

                                        <span>
                                            Payment Date
                                        </span>

                                        <strong>
                                            <?php
                                            echo h(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime((string) $payment['payment_date'])
                                                )
                                            );
                                            ?>
                                        </strong>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php else: ?>

                            <div class="no-payment">

                                <i class="bi bi-credit-card-2-front"></i>

                                <span>
                                    No payment record found.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>


<style>
/* =========================================================
   EDIT ORDER
   ========================================================= */

.order-number-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 8px;
    background: #f3f4f6;
    color: #70492f;
    font-size: 13px;
    font-weight: 700;
}

.edit-order-card {
    background: #fff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    overflow: hidden;
}

.edit-order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    border-bottom: 1px solid #e8eaee;
}

.edit-order-card-header h3 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: #111827;
}

.edit-order-card-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.edit-order-card-body {
    padding: 20px;
}

.order-number-badge + .page-subtitle {
    margin-top: 8px;
}

.items-count-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    background: #f3f4f6;
    color: #374151;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
}

.edit-items-table {
    min-width: 650px;
}

.edit-items-table th {
    background: #fafafa;
    color: #6b7280;
    font-size: 12px;
    text-transform: uppercase;
    white-space: nowrap;
}

.edit-items-table td {
    padding: 15px 20px;
}

.empty-order-items {
    padding: 45px 20px;
    text-align: center;
    color: #9ca3af;
}

.empty-order-items i {
    display: block;
    margin-bottom: 10px;
    font-size: 38px;
}

.empty-order-items p {
    margin: 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 8px 0;
    color: #6b7280;
}

.summary-row strong {
    color: #111827;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    align-items: center;
    padding-top: 5px;
    font-size: 18px;
}

.summary-total strong {
    color: #70492f;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 13px;
}

.info-list-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f1f3;
}

.info-list-row:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.info-list-row span {
    color: #6b7280;
    font-size: 13px;
}

.info-list-row strong {
    max-width: 60%;
    text-align: right;
    color: #111827;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.table-info-box {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 15px;
    border-radius: 12px;
    background: #f8fafc;
}

.table-info-icon {
    width: 45px;
    height: 45px;
    min-width: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(112, 73, 47, .10);
    color: #70492f;
    font-size: 20px;
}

.table-info-box strong {
    display: block;
    color: #111827;
}

.table-info-box span {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 12px;
}

.payment-info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-info-list > div {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f1f3;
}

.payment-info-list > div:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.payment-info-list span {
    color: #6b7280;
    font-size: 13px;
}

.payment-info-list strong {
    color: #111827;
    font-size: 13px;
    text-align: right;
}

.payment-id {
    max-width: 180px;
    overflow-wrap: anywhere;
}

.no-payment {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 25px 10px;
    color: #9ca3af;
    text-align: center;
}

.no-payment i {
    font-size: 32px;
}

.customer-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.customer-detail-item {
    padding: 15px;
    border: 1px solid #eef0f2;
    border-radius: 10px;
}

.customer-detail-item span {
    display: block;
    margin-bottom: 6px;
    color: #6b7280;
    font-size: 12px;
}

.customer-detail-item strong {
    display: block;
    color: #111827;
    font-size: 14px;
    overflow-wrap: anywhere;
}

@media (max-width: 767px) {

    .edit-order-card-header,
    .edit-order-card-body {
        padding: 15px;
    }

    .customer-detail-grid {
        grid-template-columns: 1fr;
    }

    .info-list-row {
        flex-direction: column;
        gap: 4px;
    }

    .info-list-row strong {
        max-width: 100%;
        text-align: left;
    }

    .payment-info-list > div {
        flex-direction: column;
        gap: 4px;
    }

    .payment-info-list strong {
        text-align: left;
    }

}
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('editOrderForm');
    const button = document.getElementById('saveOrderButton');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', function () {

        if (form.dataset.submitted === 'true') {
            return;
        }

        form.dataset.submitted = 'true';

        button.disabled = true;

        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-1"></span>' +
            'Saving...';

    });

});
</script>


<?php require_once "includes/a-footer.php"; ?>