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
    <!-- ==========================================
     Success / Error Messages
========================================== -->

<?php if(isset($_SESSION['success'])): ?>

<div class="alert alert-success alert-dismissible fade show">

    <i class="bi bi-check-circle-fill"></i>

    <?= $_SESSION['success']; ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert">
    </button>

</div>

<?php unset($_SESSION['success']); ?>

<?php endif; ?>


<?php if(isset($_SESSION['error'])): ?>

<div class="alert alert-danger alert-dismissible fade show">

    <i class="bi bi-exclamation-circle-fill"></i>

    <?= $_SESSION['error']; ?>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="alert">
    </button>

</div>

<?php unset($_SESSION['error']); ?>

<?php endif; ?>



<!-- ==========================================
     Statistics Cards
========================================== -->

<div class="row mb-4">

    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <h6 class="text-muted">

                    Total Products

                </h6>

                <h2 class="fw-bold">

                    <?= $totalProducts; ?>

                </h2>

            </div>

        </div>

    </div>


    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <h6 class="text-success">

                    Active Products

                </h6>

                <h2 class="fw-bold text-success">

                    <?= $activeProducts; ?>

                </h2>

            </div>

        </div>

    </div>


    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <h6 class="text-warning">

                    Featured Products

                </h6>

                <h2 class="fw-bold text-warning">

                    <?= $featuredProducts; ?>

                </h2>

            </div>

        </div>

    </div>


    <div class="col-lg-3 col-md-6 mb-3">

        <div class="card border-0 shadow-sm">

            <div class="card-body">

                <h6 class="text-danger">

                    Out Of Stock

                </h6>

                <h2 class="fw-bold text-danger">

                    <?= $outOfStock; ?>

                </h2>

            </div>

        </div>

    </div>

</div>



<!-- ==========================================
     Search & Filters
========================================== -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body">

        <form method="GET">

            <div class="row g-3 align-items-end">

                <div class="col-lg-3">

                    <label class="form-label">

                        Search Product

                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search product..."
                        value="<?= htmlspecialchars($search); ?>">

                </div>


                <div class="col-lg-2">

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
                            <?= ($category==$cat['category_id']) ? 'selected' : ''; ?>>

                            <?= htmlspecialchars($cat['category_name']); ?>

                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-2">

                    <label class="form-label">

                        Food Type

                    </label>

                    <select
                        name="food_type"
                        class="form-select">

                        <option value="">

                            All

                        </option>

                        <option value="Veg"
                            <?= ($food_type=="Veg") ? "selected":""; ?>>

                            Veg

                        </option>

                        <option value="Non-Veg"
                            <?= ($food_type=="Non-Veg") ? "selected":""; ?>>

                            Non-Veg

                        </option>

                        <option value="Egg"
                            <?= ($food_type=="Egg") ? "selected":""; ?>>

                            Egg

                        </option>

                    </select>

                </div>


                <div class="col-lg-2">

                    <label class="form-label">

                        Status

                    </label>

                    <select
                        name="status"
                        class="form-select">

                        <option value="">

                            All

                        </option>

                        <option value="Active"
                            <?= ($status=="Active") ? "selected":""; ?>>

                            Active

                        </option>

                        <option value="Inactive"
                            <?= ($status=="Inactive") ? "selected":""; ?>>

                            Inactive

                        </option>

                    </select>

                </div>


                <div class="col-lg-3 text-end">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        Search

                    </button>

                    <a
                        href="products.php"
                        class="btn btn-secondary">

                        Reset

                    </a>

                    <a
                        href="add_product.php"
                        class="btn btn-success">

                        <i class="bi bi-plus-circle"></i>

                        Add Product

                    </a>

                </div>

            </div>

        </form>

    </div>

</div>



<!-- ==========================================
     Products Table
========================================== -->

<div class="card shadow border-0">

    <div class="card-header bg-primary text-white">

        <h5 class="mb-0">

            <i class="bi bi-box-seam"></i>

            Product Management

        </h5>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover table-bordered align-middle mb-0">

                <thead class="table-light">

                <tr>

                    <th width="50">#</th>

                    <th width="90">Image</th>

                    <th>Product</th>

                    <th>Category</th>

                    <th width="110">Price</th>

                    <th width="90">Stock</th>

                    <th width="120">Food Type</th>

                    <th width="100">Featured</th>

                    <th width="100">Status</th>

                    <th width="140" class="text-center">

                        Actions

                    </th>

                </tr>

                </thead>

                <tbody>