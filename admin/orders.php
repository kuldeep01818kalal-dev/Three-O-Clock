<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";

$pageTitle = "Manage Orders";

/*=========================================
ADMIN LOGIN CHECK
=========================================*/

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");

    exit();

}

/*=========================================
SEARCH & FILTER
=========================================*/

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
SELECT

    order_id,
    order_number,

    customer_name,
    phone,

    order_source,
    order_type,

    grand_total,

    payment_method,
    payment_status,

    order_status,

    ordered_at

FROM orders

WHERE 1=1
";

$params = [];

/* Search */

if ($search !== '') {

    $sql .= "
    AND
    (
        order_number LIKE ?
        OR customer_name LIKE ?
        OR phone LIKE ?
    )
    ";

    $keyword = "%{$search}%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

}

/* Status Filter */

if ($status !== '') {

    $sql .= "
    AND order_status = ?
    ";

    $params[] = $status;

}

/* Latest First */

$sql .= "
ORDER BY ordered_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
LAYOUT
=========================================*/

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-content">

<div class="container-fluid">

<div class="page-header">

<h2>

<i class="bi bi-bag-check-fill me-2"></i>

Manage Orders

</h2>

<p>

Manage customer orders and update their status.

</p>

</div>
<!-- =========================================
     SEARCH & FILTER
========================================= -->

<div class="card shadow-sm border-0 rounded-4 mb-4">

    <div class="card-body">

        <form method="GET">

            <div class="row g-3 align-items-end">

                <!-- Search -->

                <div class="col-lg-5">

                    <label class="form-label fw-semibold">

                        Search Order

                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Order No., Customer Name or Phone"
                        value="<?= htmlspecialchars($search); ?>">

                </div>

                <!-- Status -->

                <div class="col-lg-3">

                    <label class="form-label fw-semibold">

                        Status

                    </label>

                    <select
                        name="status"
                        class="form-select">

                        <option value="">All Status</option>

                        <option value="Pending"
                            <?= $status=="Pending"?"selected":""; ?>>

                            Pending

                        </option>

                        <option value="Preparing"
                            <?= $status=="Preparing"?"selected":""; ?>>

                            Preparing

                        </option>

                        <option value="Ready"
                            <?= $status=="Ready"?"selected":""; ?>>

                            Ready

                        </option>

                        <option value="Out for Delivery"
                            <?= $status=="Out for Delivery"?"selected":""; ?>>

                            Out for Delivery

                        </option>

                        <option value="Completed"
                            <?= $status=="Completed"?"selected":""; ?>>

                            Completed

                        </option>

                        <option value="Cancelled"
                            <?= $status=="Cancelled"?"selected":""; ?>>

                            Cancelled

                        </option>

                    </select>

                </div>

                <!-- Search Button -->

                <div class="col-lg-2 d-grid">

                    <button
                        class="btn btn-primary">

                        <i class="bi bi-search me-2"></i>

                        Search

                    </button>

                </div>

                <!-- Reset Button -->

                <div class="col-lg-2 d-grid">

                    <a
                        href="orders.php"
                        class="btn btn-outline-secondary">

                        <i class="bi bi-arrow-clockwise me-2"></i>

                        Reset

                    </a>

                </div>

            </div>

        </form>

    </div>

</div>
<!-- =========================================
     ORDERS TABLE
========================================= -->

<div class="table-card">

    <div class="d-flex justify-content-between align-items-center p-4">

        <h4 class="mb-0">

            <i class="bi bi-list-check me-2"></i>

            All Orders

        </h4>

        <span class="badge bg-primary fs-6">

            <?= count($orders); ?> Orders

        </span>

    </div>

    <div class="table-responsive">

        <table class="table align-middle table-hover">

            <thead>

                <tr>

                    <th>Order No.</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Source</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="text-center">Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if(empty($orders)): ?>

                <tr>

                    <td colspan="10" class="text-center py-5">

                        <i class="bi bi-inbox display-6 text-muted"></i>

                        <p class="mt-3 mb-0">

                            No orders found.

                        </p>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($orders as $order): ?>

                <?php

                $statusColor = "secondary";

                switch($order['order_status']){

                    case "Pending":
                        $statusColor="warning";
                        break;

                    case "Preparing":
                        $statusColor="info";
                        break;

                    case "Ready":
                        $statusColor="primary";
                        break;

                    case "Out for Delivery":
                        $statusColor="dark";
                        break;

                    case "Completed":
                        $statusColor="success";
                        break;

                    case "Cancelled":
                        $statusColor="danger";
                        break;

                }

                ?>

                <tr>

                    <td>

                        <strong>

                            <?= htmlspecialchars($order['order_number']); ?>

                        </strong>

                    </td>

                    <td>

                        <?= htmlspecialchars($order['customer_name']); ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($order['phone']); ?>

                    </td>
                     <td>

<?php

switch($order['order_source']){

    case "Website":

        echo '<span class="badge bg-primary-subtle text-primary border border-primary">
        🌐 Website
        </span>';

        break;

    case "Walk-In":

        echo '<span class="badge bg-success-subtle text-success border border-success">
        🚶 Walk-In
        </span>';

        break;

    case "Swiggy":

        echo '<span class="badge bg-warning-subtle text-dark border border-warning">
        🟠 Swiggy
        </span>';

        break;

    case "Zomato":

        echo '<span class="badge bg-danger-subtle text-danger border border-danger">
        🟥 Zomato
        </span>';

        break;

    default:

        echo '<span class="badge bg-secondary">
        Unknown
        </span>';

}

?>

</td>       
<td>

<?php

switch($order['order_type']){

    case "Delivery":

        echo '<span class="badge bg-info">
        🚚 Delivery
        </span>';

        break;

    case "Takeaway":

        echo '<span class="badge bg-dark">
        🥡 Takeaway
        </span>';

        break;

    case "Dine-In":

        echo '<span class="badge bg-success">
        🍽 Dine-In
        </span>';

        break;

    default:

        echo '<span class="badge bg-secondary">
        -
        </span>';

}

?>

</td>
                    <td>

                        <strong class="text-success">

                            ₹<?= number_format((float)$order['grand_total'],2); ?>

                        </strong>

                    </td>

                    <td>

                        <?php if($order['payment_method']=="Cash"): ?>

                            <span class="badge bg-success">

                                <i class="bi bi-cash-stack me-1"></i>

                                Cash

                            </span>

                        <?php elseif($order['payment_method']=="Razorpay"): ?>

                            <span class="badge bg-primary">

                                <i class="bi bi-credit-card me-1"></i>

                                Razorpay

                            </span>

                        <?php else: ?>

                            <span class="badge bg-secondary">

                                <?= htmlspecialchars($order['payment_method']); ?>

                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <span class="badge bg-<?= $statusColor; ?>">

                            <?= htmlspecialchars($order['order_status']); ?>

                        </span>

                    </td>

                    <td>

                        <?= date("d M Y", strtotime($order['ordered_at'])); ?>

                    </td>

                    <td class="text-center">

                        <a
                            href="view_order.php?id=<?= $order['order_id']; ?>"
                            class="btn btn-sm btn-dark"
                            title="View">

                            <i class="bi bi-eye-fill"></i>

                        </a>

                        <a
                            href="edit_order.php?id=<?= $order['order_id']; ?>"
                            class="btn btn-sm btn-warning"
                            title="Update">

                            <i class="bi bi-pencil-fill"></i>

                        </a>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>