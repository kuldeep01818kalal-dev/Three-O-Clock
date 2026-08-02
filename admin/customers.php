<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Customers";

/*=========================================
SEARCH
=========================================*/

$search = trim($_GET['search'] ?? '');

$sql = "
SELECT

u.user_id,
u.full_name,
u.email,
u.phone,
u.created_at,

COUNT(o.order_id) AS total_orders,

COALESCE(SUM(o.grand_total),0) AS total_spent

FROM users u

LEFT JOIN orders o
ON o.user_id = u.user_id

";

$params = [];

if($search != ""){

    $sql .= "

    WHERE

    u.full_name LIKE ?

    OR u.email LIKE ?

    OR u.phone LIKE ?

    ";

    $keyword = "%".$search."%";

    $params = [
        $keyword,
        $keyword,
        $keyword
    ];

}

$sql .= "

GROUP BY u.user_id

ORDER BY u.user_id DESC

";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
STATISTICS
=========================================*/

$totalCustomers = $pdo->query("
SELECT COUNT(*)
FROM users
")->fetchColumn();

$totalOrders = $pdo->query("
SELECT COUNT(*)
FROM orders
")->fetchColumn();

$totalRevenue = $pdo->query("
SELECT COALESCE(SUM(grand_total),0)
FROM orders
")->fetchColumn();

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>
<div class="row mb-4">

<div class="col-lg-4">

<div class="card shadow-sm border-0">

<div class="card-body">

<h6>Total Customers</h6>

<h2><?= $totalCustomers; ?></h2>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow-sm border-0">

<div class="card-body">

<h6>Total Orders</h6>

<h2><?= $totalOrders; ?></h2>

</div>

</div>

</div>

<div class="col-lg-4">

<div class="card shadow-sm border-0">

<div class="card-body">

<h6>Total Revenue</h6>

<?= number_format((float)$totalRevenue, 2); ?>

</div>

</div>

</div>

</div>
<div class="card shadow-sm border-0 mb-4">

<div class="card-body">

<form method="GET">

<div class="row">

<div class="col-lg-10">

<input
type="text"
name="search"
class="form-control"
placeholder="Search customer..."

value="<?= htmlspecialchars($search); ?>">

</div>

<div class="col-lg-2 d-grid">

<button class="btn btn-primary">

Search

</button>

</div>

</div>

</form>

</div>

</div>
<!-- ==========================================
     CUSTOMERS TABLE
========================================== -->

<div class="card shadow border-0">

    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

        <h5 class="mb-0">

            <i class="bi bi-people-fill me-2"></i>

            Customer Management

        </h5>

        <span class="badge bg-light text-dark">

            <?= count($customers); ?> Customers

        </span>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                <tr>

                    <th width="70">#</th>

                    <th width="80">Avatar</th>

                    <th>Name</th>

                    <th>Email</th>

                    <th>Phone</th>

                    <th>Total Orders</th>

                    <th>Total Spent</th>

                    <th>Joined</th>

                    <th class="text-center" width="150">

                        Actions

                    </th>

                </tr>

                </thead>

                <tbody>

                <?php if(count($customers)>0): ?>

                <?php

                $sr = 1;

                foreach($customers as $customer):

                ?>

                <tr>

                    <td>

                        <?= $sr++; ?>

                    </td>

                    <td>

                        <div class="customer-avatar">

                            <?= strtoupper(substr($customer['full_name'],0,1)); ?>

                        </div>

                    </td>

                    <td>

                        <strong>

                            <?= htmlspecialchars($customer['full_name']); ?>

                        </strong>

                    </td>

                    <td>

                        <?= htmlspecialchars($customer['email']); ?>

                    </td>

                    <td>

                        <?= htmlspecialchars($customer['phone']); ?>

                    </td>

                    <td>

                        <span class="badge bg-primary">

                            <?= (int)$customer['total_orders']; ?>

                        </span>

                    </td>

                    <td>

                        <strong class="text-success">

                            ₹<?= number_format((float)$customer['total_spent'],2); ?>

                        </strong>

                    </td>

                    <td>

                        <?php

                        if(!empty($customer['created_at'])){

                            echo date("d M Y",strtotime($customer['created_at']));

                        }else{

                            echo "-";

                        }

                        ?>

                    </td>

                    <td class="text-center">

                        <a
                        href="customer_details.php?id=<?= $customer['user_id']; ?>"
                        class="btn btn-sm btn-info text-white"
                        title="View">

                            <i class="bi bi-eye-fill"></i>

                        </a>

                        <a
                        href="customer_orders.php?id=<?= $customer['user_id']; ?>"
                        class="btn btn-sm btn-success"
                        title="Orders">

                            <i class="bi bi-bag-check-fill"></i>

                        </a>

                    </td>

                </tr>

                <?php endforeach; ?>

                <?php else: ?>

                <tr>

                    <td colspan="9" class="text-center py-5">

                        <i class="bi bi-people display-5 text-muted"></i>

                        <h5 class="mt-3">

                            No Customers Found

                        </h5>

                    </td>

                </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>