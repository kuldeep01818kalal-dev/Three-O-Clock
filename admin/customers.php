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