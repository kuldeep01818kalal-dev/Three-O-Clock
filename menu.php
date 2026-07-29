<?php
session_start();

require_once "config/db.php";
require_once "includes/navbar.php";

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
    <!-- ==========================================
     Hero Section
========================================== -->

<div class="text-center mb-5">

    <h1 class="fw-bold display-5">

        Our Menu

    </h1>

    <p class="text-muted">

        Freshly prepared food and beverages made with quality ingredients.

    </p>

</div>

<!-- ==========================================
     Search & Filters
========================================== -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body">

        <form method="GET">

            <div class="row g-3 align-items-end">

                <!-- Search -->

                <div class="col-lg-4">

                    <label class="form-label">

                        Search Menu

                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search food..."
                        value="<?= htmlspecialchars($search); ?>">

                </div>

                <!-- Category -->

                <div class="col-lg-3">

                    <label class="form-label">

                        Category

                    </label>

                    <select
                        name="category"
                        class="form-select">

                        <option value="">

                            All Categories

                        </option>

                        <?php foreach($categories as $cat): ?>

                        <option
                            value="<?= $cat['category_id']; ?>"
                            <?= ($category==$cat['category_id']) ? "selected" : ""; ?>>

                            <?= htmlspecialchars($cat['category_name']); ?>

                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Food Type -->

                <div class="col-lg-2">

                    <label class="form-label">

                        Food Type

                    </label>

                    <select
                        name="food_type"
                        class="form-select">

                        <option value="">All</option>

                        <option value="Veg"
                            <?= ($food_type=="Veg") ? "selected" : ""; ?>>

                            Veg

                        </option>

                        <option value="Non-Veg"
                            <?= ($food_type=="Non-Veg") ? "selected" : ""; ?>>

                            Non-Veg

                        </option>

                        <option value="Egg"
                            <?= ($food_type=="Egg") ? "selected" : ""; ?>>

                            Egg

                        </option>

                    </select>

                </div>

                <!-- Buttons -->

                <div class="col-lg-3 text-end">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        Search

                    </button>

                    <a
                        href="menu.php"
                        class="btn btn-secondary">

                        Reset

                    </a>

                </div>

            </div>

        </form>

    </div>

</div>

<!-- ==========================================
     Product Count
========================================== -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <h4 class="mb-0">

        Menu Items

    </h4>

    <span class="badge bg-primary fs-6">

        <?= $totalProducts; ?> Items Found

    </span>

</div>

<!-- ==========================================
     Products Grid
========================================== -->

<div class="row">
    <?php if(count($products) > 0): ?>

<?php foreach($products as $row): ?>

<?php

$image = !empty($row['image_name'])
    ? "assets/images/products/".$row['image_name']
    : "assets/images/no-image.png";

$price = (float)$row['price'];
$discount = (float)$row['discount_percent'];

$finalPrice = $price;

if($discount > 0){

    $finalPrice = $price - (($price * $discount) / 100);

}

?>

<div class="col-xl-3 col-lg-4 col-md-6 mb-4">

    <div class="card h-100 border-0 shadow-sm">

        <div class="position-relative">

            <img
                src="<?= htmlspecialchars($image); ?>"
                class="card-img-top"
                alt="<?= htmlspecialchars($row['product_name']); ?>"
                style="height:230px;object-fit:cover;">

            <?php if($row['featured']==1): ?>

            <span class="badge bg-warning text-dark position-absolute top-0 start-0 m-2">

                ⭐ Featured

            </span>

            <?php endif; ?>

            <?php if($discount>0): ?>

            <span class="badge bg-danger position-absolute top-0 end-0 m-2">

                <?= rtrim(rtrim(number_format($discount,2),'0'),'.'); ?>% OFF

            </span>

            <?php endif; ?>

        </div>

        <div class="card-body d-flex flex-column">

            <div class="mb-2">

                <?php

                switch($row['food_type']){

                    case "Veg":

                        echo '<span class="badge bg-success">Veg</span>';

                    break;

                    case "Non-Veg":

                        echo '<span class="badge bg-danger">Non-Veg</span>';

                    break;

                    case "Egg":

                        echo '<span class="badge bg-warning text-dark">Egg</span>';

                    break;

                    default:

                        echo '<span class="badge bg-secondary">N/A</span>';

                }

                ?>

            </div>

            <h5 class="fw-bold">

                <?= htmlspecialchars($row['product_name']); ?>

            </h5>

            <p class="text-muted small flex-grow-1">

                <?= htmlspecialchars($row['short_description']); ?>

            </p>

            <div class="mb-2">

                <?php if($discount>0): ?>

                    <span class="fs-5 fw-bold text-success">

                        ₹<?= number_format($finalPrice,2); ?>

                    </span>

                    <br>

                    <small class="text-decoration-line-through text-muted">

                        ₹<?= number_format($price,2); ?>

                    </small>

                <?php else: ?>

                    <span class="fs-5 fw-bold">

                        ₹<?= number_format($price,2); ?>

                    </span>

                <?php endif; ?>

            </div>

            <p class="text-muted mb-3">

                <i class="bi bi-clock"></i>

                <?= (int)$row['preparation_time']; ?> Min

            </p>

            <div class="d-grid gap-2 mt-auto">

                <a
                    href="product_details.php?id=<?= $row['product_id']; ?>"
                    class="btn btn-outline-primary">

                    <i class="bi bi-eye"></i>

                    View Details

                </a>

                <a
                    href="cart.php?action=add&id=<?= $row['product_id']; ?>"
                    class="btn btn-primary">

                    <i class="bi bi-cart-plus"></i>

                    Add to Cart

                </a>

            </div>

        </div>

    </div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="col-12">

    <div class="text-center py-5">

        <i class="bi bi-search fs-1 text-muted"></i>

        <h3 class="mt-3">

            No Products Found

        </h3>

        <p class="text-muted">

            Try changing your search or filter.

        </p>

    </div>

</div>

<?php endif; ?>