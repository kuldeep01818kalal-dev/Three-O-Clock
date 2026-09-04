<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Orders";

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

$allowedSources = [
    'Website',
    'Walk-In',
    'Swiggy',
    'Zomato'
];

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $type, string $message): never
{
    header(
        "Location: orders.php?" .
        http_build_query([
            'message_type' => $type,
            'message' => $message
        ])
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/
if (isset($_GET['message'], $_GET['message_type'])) {
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

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {
        redirectWithMessage(
            'danger',
            'Invalid security token. Please try again.'
        );
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_order') {

        $orderId = filter_var(
            $_POST['order_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $orderStatus = trim(
            (string) ($_POST['order_status'] ?? '')
        );

        $paymentStatus = trim(
            (string) ($_POST['payment_status'] ?? '')
        );

        if (!$orderId || $orderId < 1) {
            redirectWithMessage(
                'danger',
                'Invalid order selected.'
            );
        }

        if (!in_array(
            $orderStatus,
            $allowedOrderStatuses,
            true
        )) {
            redirectWithMessage(
                'danger',
                'Invalid order status.'
            );
        }

        if (!in_array(
            $paymentStatus,
            $allowedPaymentStatuses,
            true
        )) {
            redirectWithMessage(
                'danger',
                'Invalid payment status.'
            );
        }

        try {

            $check = $pdo->prepare("
                SELECT
                    order_id,
                    order_number,
                    order_status,
                    payment_status
                FROM orders
                WHERE order_id = :order_id
                LIMIT 1
            ");

            $check->execute([
                ':order_id' => $orderId
            ]);

            $order = $check->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                redirectWithMessage(
                    'danger',
                    'Order not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Update Order
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                UPDATE orders
                SET
                    order_status = :order_status,
                    payment_status = :payment_status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE order_id = :order_id
            ");

            $stmt->execute([
                ':order_status' => $orderStatus,
                ':payment_status' => $paymentStatus,
                ':order_id' => $orderId
            ]);

            redirectWithMessage(
                'success',
                'Order ' .
                $order['order_number'] .
                ' updated successfully.'
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'danger',
                'Unable to update order.'
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$paymentFilter = trim((string) ($_GET['payment_status'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? ''));
$dateFilter = trim((string) ($_GET['date'] ?? ''));

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$stats = [
    'total' => 0,
    'pending' => 0,
    'preparing' => 0,
    'ready' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'today' => 0
];

try {

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,

            SUM(
                order_status = 'Pending'
            ) AS pending,

            SUM(
                order_status = 'Preparing'
            ) AS preparing,

            SUM(
                order_status = 'Ready'
            ) AS ready,

            SUM(
                order_status = 'Completed'
            ) AS completed,

            SUM(
                order_status = 'Cancelled'
            ) AS cancelled,

            SUM(
                DATE(ordered_at) = CURDATE()
            ) AS today

        FROM orders
    ");

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $stats['total'] = (int) ($result['total'] ?? 0);
        $stats['pending'] = (int) ($result['pending'] ?? 0);
        $stats['preparing'] = (int) ($result['preparing'] ?? 0);
        $stats['ready'] = (int) ($result['ready'] ?? 0);
        $stats['completed'] = (int) ($result['completed'] ?? 0);
        $stats['cancelled'] = (int) ($result['cancelled'] ?? 0);
        $stats['today'] = (int) ($result['today'] ?? 0);
    }

} catch (PDOException $e) {

    $message = 'Unable to load order statistics.';
    $messageType = 'danger';
}

/*
|--------------------------------------------------------------------------
| Fetch Orders
|--------------------------------------------------------------------------
*/
$orders = [];

try {

    $sql = "
        SELECT
            o.order_id,
            o.order_number,
            o.user_id,
            o.customer_name,
            o.phone,
            o.email,
            o.address,
            o.order_source,
            o.order_type,
            o.table_id,
            o.subtotal,
            o.discount,
            o.tax,
            o.delivery_charge,
            o.grand_total,
            o.payment_status,
            o.order_status,
            o.payment_method,
            o.notes,
            o.ordered_at,
            o.updated_at,

            ct.table_number,

            (
                SELECT COUNT(*)
                FROM order_items oi
                WHERE oi.order_id = o.order_id
            ) AS item_count

        FROM orders o

        LEFT JOIN cafe_tables ct
            ON ct.table_id = o.table_id

        WHERE 1 = 1
    ";

    $params = [];

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    if ($search !== '') {

        $sql .= "
            AND (
                o.order_number LIKE :search
                OR o.customer_name LIKE :search
                OR o.phone LIKE :search
                OR o.email LIKE :search
            )
        ";

        $params[':search'] = '%' . $search . '%';
    }

    /*
    |--------------------------------------------------------------------------
    | Order Status
    |--------------------------------------------------------------------------
    */
    if (
        $statusFilter !== '' &&
        in_array(
            $statusFilter,
            $allowedOrderStatuses,
            true
        )
    ) {

        $sql .= "
            AND o.order_status = :status
        ";

        $params[':status'] = $statusFilter;
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Status
    |--------------------------------------------------------------------------
    */
    if (
        $paymentFilter !== '' &&
        in_array(
            $paymentFilter,
            $allowedPaymentStatuses,
            true
        )
    ) {

        $sql .= "
            AND o.payment_status = :payment_status
        ";

        $params[':payment_status'] = $paymentFilter;
    }

    /*
    |--------------------------------------------------------------------------
    | Source
    |--------------------------------------------------------------------------
    */
    if (
        $sourceFilter !== '' &&
        in_array(
            $sourceFilter,
            $allowedSources,
            true
        )
    ) {

        $sql .= "
            AND o.order_source = :source
        ";

        $params[':source'] = $sourceFilter;
    }

    /*
    |--------------------------------------------------------------------------
    | Date
    |--------------------------------------------------------------------------
    */
    if ($dateFilter !== '') {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $dateFilter
        );

        if (
            $dateObject &&
            $dateObject->format('Y-m-d') === $dateFilter
        ) {

            $sql .= "
                AND DATE(o.ordered_at) = :order_date
            ";

            $params[':order_date'] = $dateFilter;
        }
    }

    $sql .= "
        ORDER BY
            o.ordered_at DESC,
            o.order_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $message = 'Unable to load orders.';
    $messageType = 'danger';
}

/*
|--------------------------------------------------------------------------
| Page Layout
|--------------------------------------------------------------------------
*/
require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- Page Header -->
        <div class="page-header mb-4">

            <div>

                <h1 class="page-title">
                    <i class="bi bi-receipt me-2"></i>
                    Orders
                </h1>

                <p class="page-subtitle">
                    Manage website, walk-in, Swiggy and Zomato orders.
                </p>

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <a
                    href="offline_orders.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-shop me-1"></i>
                    Offline Orders
                </a>

                <a
                    href="online_orders.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-globe2 me-1"></i>
                    Online Orders
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


        <!-- Statistics -->
        <div class="order-stats mb-4">

            <div class="order-stat-card">

                <div class="order-stat-icon">
                    <i class="bi bi-receipt"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Total Orders
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['total']; ?>
                    </strong>

                </div>

            </div>


            <div class="order-stat-card">

                <div class="order-stat-icon pending">
                    <i class="bi bi-hourglass-split"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Pending
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['pending']; ?>
                    </strong>

                </div>

            </div>


            <div class="order-stat-card">

                <div class="order-stat-icon preparing">
                    <i class="bi bi-fire"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Preparing
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['preparing']; ?>
                    </strong>

                </div>

            </div>


            <div class="order-stat-card">

                <div class="order-stat-icon ready">
                    <i class="bi bi-check2-circle"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Ready
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['ready']; ?>
                    </strong>

                </div>

            </div>


            <div class="order-stat-card">

                <div class="order-stat-icon completed">
                    <i class="bi bi-check-circle-fill"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Completed
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['completed']; ?>
                    </strong>

                </div>

            </div>


            <div class="order-stat-card">

                <div class="order-stat-icon cancelled">
                    <i class="bi bi-x-circle"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Cancelled
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['cancelled']; ?>
                    </strong>

                </div>

            </div>


            <div class="order-stat-card">

                <div class="order-stat-icon today">
                    <i class="bi bi-calendar-day"></i>
                </div>

                <div>

                    <span class="order-stat-label">
                        Today's Orders
                    </span>

                    <strong class="order-stat-value">
                        <?php echo $stats['today']; ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- Filters -->
        <div class="orders-filter-card mb-4">

            <form
                method="GET"
                action="orders.php"
                class="orders-filter"
            >

                <div class="orders-filter-group">

                    <label for="search">
                        Search
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            class="form-control"
                            placeholder="Order number, customer, phone..."
                            value="<?php echo h($search); ?>"
                        >

                    </div>

                </div>


                <div class="orders-filter-group">

                    <label for="status">
                        Order Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >

                        <option value="">
                            All Status
                        </option>

                        <?php foreach ($allowedOrderStatuses as $status): ?>

                            <option
                                value="<?php echo h($status); ?>"
                                <?php echo $statusFilter === $status ? 'selected' : ''; ?>
                            >
                                <?php echo h($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="orders-filter-group">

                    <label for="payment_status">
                        Payment
                    </label>

                    <select
                        id="payment_status"
                        name="payment_status"
                        class="form-select"
                    >

                        <option value="">
                            All Payments
                        </option>

                        <?php foreach ($allowedPaymentStatuses as $status): ?>

                            <option
                                value="<?php echo h($status); ?>"
                                <?php echo $paymentFilter === $status ? 'selected' : ''; ?>
                            >
                                <?php echo h($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="orders-filter-group">

                    <label for="source">
                        Source
                    </label>

                    <select
                        id="source"
                        name="source"
                        class="form-select"
                    >

                        <option value="">
                            All Sources
                        </option>

                        <?php foreach ($allowedSources as $source): ?>

                            <option
                                value="<?php echo h($source); ?>"
                                <?php echo $sourceFilter === $source ? 'selected' : ''; ?>
                            >
                                <?php echo h($source); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="orders-filter-group">

                    <label for="date">
                        Date
                    </label>

                    <input
                        type="date"
                        id="date"
                        name="date"
                        class="form-control"
                        value="<?php echo h($dateFilter); ?>"
                    >

                </div>


                <div class="orders-filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>

                    <a
                        href="orders.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>

                </div>

            </form>

        </div>


        <!-- Orders Table -->
        <div class="orders-table-card">

            <div class="orders-table-header">

                <div>

                    <h3>
                        All Orders
                    </h3>

                    <p>
                        <?php echo count($orders); ?> order(s) found.
                    </p>

                </div>

                <div class="d-flex gap-2">

                    <a
                        href="a-invoice.php"
                        class="btn btn-sm btn-outline-primary"
                    >
                        <i class="bi bi-file-earmark-text me-1"></i>
                        Invoices
                    </a>

                </div>

            </div>


            <?php if (!empty($orders)): ?>

                <div class="orders-table-wrapper">

                    <table class="table orders-management-table align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Source
                                </th>

                                <th>
                                    Type
                                </th>

                                <th>
                                    Items
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Date
                                </th>

                                <th class="text-end">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($orders as $order): ?>

                                <?php
                                $orderStatusClass = strtolower(
                                    str_replace(
                                        ' ',
                                        '-',
                                        (string) $order['order_status']
                                    )
                                );

                                $paymentStatusClass = strtolower(
                                    str_replace(
                                        ' ',
                                        '-',
                                        (string) $order['payment_status']
                                    )
                                );
                                ?>

                                <tr>

                                    <!-- Order -->
                                    <td>

                                        <a
                                            href="order_details.php?id=<?php echo (int) $order['order_id']; ?>"
                                            class="order-number-link"
                                        >
                                            #<?php echo h((string) $order['order_number']); ?>
                                        </a>

                                        <div class="small text-muted">
                                            ID <?php echo (int) $order['order_id']; ?>
                                        </div>

                                    </td>


                                    <!-- Customer -->
                                    <td>

                                        <div class="fw-semibold">
                                            <?php echo h((string) $order['customer_name']); ?>
                                        </div>

                                        <?php if (!empty($order['phone'])): ?>

                                            <div class="small text-muted">
                                                <i class="bi bi-telephone me-1"></i>
                                                <?php echo h((string) $order['phone']); ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Source -->
                                    <td>

                                        <?php
                                        $sourceIcon = match ($order['order_source']) {
                                            'Website' => 'bi-globe2',
                                            'Walk-In' => 'bi-shop',
                                            'Swiggy' => 'bi-bag',
                                            'Zomato' => 'bi-bag-check',
                                            default => 'bi-receipt'
                                        };
                                        ?>

                                        <span class="order-source-badge">

                                            <i class="bi <?php echo h($sourceIcon); ?>"></i>

                                            <?php echo h((string) $order['order_source']); ?>

                                        </span>

                                    </td>


                                    <!-- Order Type -->
                                    <td>

                                        <span class="small">

                                            <?php echo h((string) $order['order_type']); ?>

                                            <?php if (!empty($order['table_number'])): ?>

                                                <br>

                                                <span class="text-muted">
                                                    Table <?php echo h((string) $order['table_number']); ?>
                                                </span>

                                            <?php endif; ?>

                                        </span>

                                    </td>


                                    <!-- Items -->
                                    <td>

                                        <span class="fw-semibold">
                                            <?php echo (int) $order['item_count']; ?>
                                        </span>

                                        <span class="text-muted small">
                                            item(s)
                                        </span>

                                    </td>


                                    <!-- Total -->
                                    <td>

                                        <strong>
                                            ₹<?php echo number_format((float) $order['grand_total'], 2); ?>
                                        </strong>

                                    </td>


                                    <!-- Payment -->
                                    <td>

                                        <span
                                            class="payment-status-badge <?php echo h($paymentStatusClass); ?>"
                                        >
                                            <?php echo h((string) $order['payment_status']); ?>
                                        </span>

                                        <div class="small text-muted mt-1">
                                            <?php echo h((string) $order['payment_method']); ?>
                                        </div>

                                    </td>


                                    <!-- Order Status -->
                                    <td>

                                        <span
                                            class="status-badge <?php echo h($orderStatusClass); ?>"
                                        >
                                            <?php echo h((string) $order['order_status']); ?>
                                        </span>

                                    </td>


                                    <!-- Date -->
                                    <td>

                                        <div class="small fw-semibold">
                                            <?php
                                            echo h(
                                                date(
                                                    'd M Y',
                                                    strtotime((string) $order['ordered_at'])
                                                )
                                            );
                                            ?>
                                        </div>

                                        <div class="small text-muted">
                                            <?php
                                            echo h(
                                                date(
                                                    'h:i A',
                                                    strtotime((string) $order['ordered_at'])
                                                )
                                            );
                                            ?>
                                        </div>

                                    </td>


                                    <!-- Actions -->
                                    <td class="text-end">

                                        <div class="d-flex justify-content-end gap-1">

                                            <a
                                                href="order_details.php?id=<?php echo (int) $order['order_id']; ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="View order"
                                            >
                                                <i class="bi bi-eye"></i>
                                            </a>


                                            <a
                                                href="edit_order.php?id=<?php echo (int) $order['order_id']; ?>"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Edit order"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </a>


                                            <a
                                                href="a-invoice.php?id=<?php echo (int) $order['order_id']; ?>"
                                                class="btn btn-sm btn-outline-success"
                                                title="Invoice"
                                            >
                                                <i class="bi bi-receipt"></i>
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="text-center py-5 px-3">

                    <i
                        class="bi bi-receipt"
                        style="font-size: 3rem; color: #9ca3af;"
                    ></i>

                    <h4 class="mt-3">
                        No Orders Found
                    </h4>

                    <p class="text-muted">
                        No orders match your current filters.
                    </p>

                    <a
                        href="orders.php"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<style>
/* =========================================================
   ORDERS MANAGEMENT
   ========================================================= */

.orders-filter-card {
    background: #fff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    padding: 18px;
}

.orders-filter {
    display: grid;
    grid-template-columns:
        minmax(220px, 2fr)
        minmax(150px, 1fr)
        minmax(150px, 1fr)
        minmax(140px, 1fr)
        minmax(140px, 1fr)
        auto;
    gap: 14px;
    align-items: end;
}

.orders-filter-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.orders-filter-actions {
    display: flex;
    gap: 6px;
}

.orders-table-card {
    width: 100%;
    background: #fff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    overflow: hidden;
}

.orders-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    border-bottom: 1px solid #e8eaee;
}

.orders-table-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
}

.orders-table-header p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.orders-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.orders-management-table {
    min-width: 1250px;
}

.orders-management-table th {
    white-space: nowrap;
    background: #fafafa;
    color: #4b5563;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.orders-management-table td {
    white-space: nowrap;
}

.order-number-link {
    color: #70492f;
    font-weight: 700;
    text-decoration: none;
}

.order-number-link:hover {
    text-decoration: underline;
}

.order-source-badge,
.payment-status-badge,
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 999px;
    padding: 5px 9px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.order-source-badge {
    color: #374151;
    background: #f3f4f6;
}

.payment-status-badge.pending {
    color: #92400e;
    background: #fef3c7;
}

.payment-status-badge.paid {
    color: #166534;
    background: #dcfce7;
}

.payment-status-badge.failed {
    color: #991b1b;
    background: #fee2e2;
}

.payment-status-badge.refunded {
    color: #5b21b6;
    background: #ede9fe;
}

.status-badge.pending {
    color: #92400e;
    background: #fef3c7;
}

.status-badge.accepted {
    color: #1d4ed8;
    background: #dbeafe;
}

.status-badge.preparing {
    color: #9a3412;
    background: #ffedd5;
}

.status-badge.ready {
    color: #166534;
    background: #dcfce7;
}

.status-badge.out-for-delivery {
    color: #075985;
    background: #e0f2fe;
}

.status-badge.completed {
    color: #166534;
    background: #d1fae5;
}

.status-badge.cancelled {
    color: #991b1b;
    background: #fee2e2;
}

@media (max-width: 1200px) {

    .orders-filter {
        grid-template-columns:
            repeat(3, minmax(160px, 1fr));
    }

    .orders-filter-group:first-child {
        grid-column: span 3;
    }

}

@media (max-width: 767px) {

    .orders-filter {
        grid-template-columns: 1fr;
    }

    .orders-filter-group:first-child {
        grid-column: auto;
    }

    .orders-filter-actions {
        width: 100%;
    }

    .orders-filter-actions .btn {
        flex: 1;
    }

    .orders-table-header {
        align-items: flex-start;
        flex-direction: column;
    }

}
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Auto Focus Search
    |--------------------------------------------------------------------------
    */
    const searchInput = document.getElementById('search');

    if (searchInput) {

        document.addEventListener('keydown', function (event) {

            if (
                event.key === '/' &&
                document.activeElement.tagName !== 'INPUT' &&
                document.activeElement.tagName !== 'TEXTAREA' &&
                document.activeElement.tagName !== 'SELECT'
            ) {
                event.preventDefault();
                searchInput.focus();
            }

        });

    }

});
</script>


<?php require_once "includes/a-footer.php"; ?>