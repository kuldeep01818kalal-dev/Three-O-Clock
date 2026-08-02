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

require_once "includes/header.php";
require_once "includes/sidebar.php";
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