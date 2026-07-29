<?php
session_start();

require_once "config/db.php";

$pageTitle = "Menu";

/* ==========================================
   Search & Filters
========================================== */

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$category = isset($_GET['category']) ? trim($_GET['category']) : "";
$food_type = isset($_GET['food_type']) ? trim($_GET['food_type']) : "";

/* ==========================================
   Fetch Categories
========================================== */

$categoryStmt = $pdo->query("
SELECT
    category_id,
    category_name
FROM categories
WHERE status='Active'
ORDER BY category_name ASC
");

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   Pagination
========================================== */

$limit = 12;

$page = isset($_GET['page'])
    ? max((int)$_GET['page'], 1)
    : 1;

$offset = ($page - 1) * $limit;

/* ==========================================
   Dynamic WHERE Clause
========================================== */

$where = [
    "p.status='Active'",
    "p.availability='Available'"
];

$params = [];

if (!empty($search)) {

    $where[] = "p.product_name LIKE :search";

    $params[':search'] = "%{$search}%";

}

if (!empty($category)) {

    $where[] = "p.category_id = :category";

    $params[':category'] = $category;

}

if (!empty($food_type)) {

    $where[] = "p.food_type = :food_type";

    $params[':food_type'] = $food_type;

}

$whereSQL = "WHERE " . implode(" AND ", $where);

/* ==========================================
   Count Products
========================================== */

$countSQL = "
SELECT COUNT(*)
FROM products p
{$whereSQL}
";

$countStmt = $pdo->prepare($countSQL);

$countStmt->execute($params);

$totalProducts = $countStmt->fetchColumn();

$totalPages = ceil($totalProducts / $limit);

/* ==========================================
   Fetch Products
========================================== */

$sql = "
SELECT

p.*,

c.category_name,

pi.image_name

FROM products p

LEFT JOIN categories c
ON c.category_id = p.category_id

LEFT JOIN product_images pi
ON pi.product_id = p.product_id
AND pi.is_primary = 1

{$whereSQL}

ORDER BY

p.featured DESC,

p.product_name ASC

LIMIT :offset,:limit
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {

    $stmt->bindValue($key, $value);

}

$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);

$stmt->bindValue(":limit", $limit, PDO::PARAM_INT);

$stmt->execute();

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   Includes
========================================== */

include "includes/header.php";
?>

<div class="container py-5">