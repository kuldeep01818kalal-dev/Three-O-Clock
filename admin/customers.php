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