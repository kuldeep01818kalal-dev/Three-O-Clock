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