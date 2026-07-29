<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Products";

/* =====================================================
   Search & Filters
===================================================== */

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$category = isset($_GET['category']) ? trim($_GET['category']) : "";
$food_type = isset($_GET['food_type']) ? trim($_GET['food_type']) : "";
$status = isset($_GET['status']) ? trim($_GET['status']) : "";

/* =====================================================
   Dashboard Statistics
===================================================== */

$totalProducts = $pdo->query("
SELECT COUNT(*)
FROM products
")->fetchColumn();

$activeProducts = $pdo->query("
SELECT COUNT(*)
FROM products
WHERE status='Active'
")->fetchColumn();

$featuredProducts = $pdo->query("
SELECT COUNT(*)
FROM products
WHERE featured=1
")->fetchColumn();

$outOfStock = $pdo->query("
SELECT COUNT(*)
FROM products
WHERE stock<=0
")->fetchColumn();

/* =====================================================
   Category Dropdown
===================================================== */

$categoryStmt = $pdo->query("
SELECT
category_id,
category_name
FROM categories
WHERE status='Active'
ORDER BY category_name
");

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   Pagination
===================================================== */

$limit = 10;

$page = isset($_GET['page'])
? (int)$_GET['page']
: 1;

if($page < 1){
    $page = 1;
}

$offset = ($page-1)*$limit;

/* =====================================================
   Dynamic WHERE
===================================================== */

$where = [];
$params = [];

if(!empty($search)){

    $where[] =
    "p.product_name LIKE :search";

    $params[':search'] =
    "%{$search}%";

}

if(!empty($category)){

    $where[] =
    "p.category_id=:category";

    $params[':category']=$category;

}

if(!empty($food_type)){

    $where[] =
    "p.food_type=:food_type";

    $params[':food_type']=$food_type;

}

if(!empty($status)){

    $where[] =
    "p.status=:status";

    $params[':status']=$status;

}

$whereSQL="";

if(!empty($where)){

    $whereSQL="WHERE ".implode(" AND ",$where);

}

/* =====================================================
   Total Rows
===================================================== */

$countSQL="

SELECT COUNT(*)

FROM products p

{$whereSQL}

";

$countStmt=$pdo->prepare($countSQL);

$countStmt->execute($params);

$totalRows=$countStmt->fetchColumn();

$totalPages=ceil($totalRows/$limit);

/* =====================================================
   Fetch Products
===================================================== */

$sql="

SELECT

p.*,

c.category_name,

pi.image_name

FROM products p

LEFT JOIN categories c

ON c.category_id=p.category_id

LEFT JOIN product_images pi

ON pi.product_id=p.product_id

AND pi.is_primary=1

{$whereSQL}

ORDER BY p.product_id DESC

LIMIT :offset,:limit

";

$stmt=$pdo->prepare($sql);

foreach($params as $key=>$value){

    $stmt->bindValue($key,$value);

}

$stmt->bindValue(
":offset",
$offset,
PDO::PARAM_INT
);

$stmt->bindValue(
":limit",
$limit,
PDO::PARAM_INT
);

$stmt->execute();

$products=$stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================================
   Includes
===================================================== */

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>

<div class="container-fluid mt-4">