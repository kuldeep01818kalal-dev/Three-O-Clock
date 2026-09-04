<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Products";

$errors = [];


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function money(float $amount, string $currency = "₹"): string
{
    return e($currency) . number_format($amount, 2);
}


function paginationUrl(
    int $pageNumber,
    string $search,
    string $category,
    string $foodType,
    string $status
): string {

    $query = [
        'page' => $pageNumber
    ];

    if ($search !== '') {
        $query['search'] = $search;
    }

    if ($category !== '') {
        $query['category'] = $category;
    }

    if ($foodType !== '') {
        $query['food_type'] = $foodType;
    }

    if ($status !== '') {
        $query['status'] = $status;
    }

    return '?' . http_build_query($query);
}


/*
|--------------------------------------------------------------------------
| CURRENCY
|--------------------------------------------------------------------------
*/

$currency = "₹";

try {

    $currencyStmt = $pdo->query("
        SELECT currency
        FROM settings
        ORDER BY setting_id ASC
        LIMIT 1
    ");

    $currencyValue = $currencyStmt->fetchColumn();

    if (
        is_string($currencyValue) &&
        trim($currencyValue) !== ''
    ) {

        $currencyValue = trim($currencyValue);

        /*
        |--------------------------------------------------------------------------
        | Convert database currency code to display symbol
        |--------------------------------------------------------------------------
        */

        $currencyMap = [
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        ];

        $currency = $currencyMap[$currencyValue]
            ?? $currencyValue;
    }

} catch (PDOException $e) {

    $currency = "₹";
}


/*
|--------------------------------------------------------------------------
| SEARCH & FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    (string)($_GET['search'] ?? '')
);

$category = trim(
    (string)($_GET['category'] ?? '')
);

$foodType = trim(
    (string)($_GET['food_type'] ?? '')
);

$status = trim(
    (string)($_GET['status'] ?? '')
);


/*
|--------------------------------------------------------------------------
| VALID FILTER VALUES
|--------------------------------------------------------------------------
*/

$allowedFoodTypes = [
    'Veg',
    'Non-Veg',
    'Egg'
];

$allowedStatuses = [
    'Active',
    'Inactive'
];

if (
    $foodType !== '' &&
    !in_array(
        $foodType,
        $allowedFoodTypes,
        true
    )
) {

    $foodType = '';
}


if (
    $status !== '' &&
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $status = '';
}


/*
|--------------------------------------------------------------------------
| CATEGORY FILTER
|--------------------------------------------------------------------------
*/

$categoryId = null;

if ($category !== '') {

    $validatedCategory = filter_var(
        $category,
        FILTER_VALIDATE_INT
    );

    if (
        $validatedCategory !== false &&
        $validatedCategory > 0
    ) {

        $categoryId = (int)$validatedCategory;

    } else {

        $category = '';
    }
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$activeProducts = 0;
$inactiveProducts = 0;
$featuredProducts = 0;
$outOfStock = 0;
$unavailableProducts = 0;

try {

    $totalProducts = (int)$pdo->query("
        SELECT COUNT(*)
        FROM products
    ")->fetchColumn();


    $activeProducts = (int)$pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE status = 'Active'
    ")->fetchColumn();


    $inactiveProducts = (int)$pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE status = 'Inactive'
    ")->fetchColumn();


    $featuredProducts = (int)$pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE featured = 1
    ")->fetchColumn();


    $outOfStock = (int)$pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE stock <= 0
    ")->fetchColumn();


    $unavailableProducts = (int)$pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE availability = 'Unavailable'
    ")->fetchColumn();

} catch (PDOException $e) {

    $errors[] =
        "Unable to load product statistics.";
}


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
|
| Load all categories so an administrator can find products
| belonging to an inactive category as well.
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $categoryStmt = $pdo->query("
        SELECT
            category_id,
            category_name,
            status
        FROM categories
        ORDER BY category_name ASC
    ");

    $categories = $categoryStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $errors[] =
        "Unable to load categories.";
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$limit = 10;

$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT
);

if (
    $page === false ||
    $page < 1
) {

    $page = 1;
}

$offset = ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| BUILD FILTER CONDITIONS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            p.product_name LIKE :search
            OR p.slug LIKE :search
            OR p.short_description LIKE :search
            OR p.description LIKE :search
        )
    ";

    $params[':search'] =
        '%' . $search . '%';
}


/*
|--------------------------------------------------------------------------
| CATEGORY
|--------------------------------------------------------------------------
*/

if ($categoryId !== null) {

    $where[] =
        "p.category_id = :category_id";

    $params[':category_id'] =
        $categoryId;
}


/*
|--------------------------------------------------------------------------
| FOOD TYPE
|--------------------------------------------------------------------------
*/

if ($foodType !== '') {

    $where[] =
        "p.food_type = :food_type";

    $params[':food_type'] =
        $foodType;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $where[] =
        "p.status = :status";

    $params[':status'] =
        $status;
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSQL = '';

if (!empty($where)) {

    $whereSQL =
        'WHERE ' . implode(
            ' AND ',
            $where
        );
}


/*
|--------------------------------------------------------------------------
| TOTAL FILTERED ROWS
|--------------------------------------------------------------------------
*/

$totalRows = 0;

try {

    $countSQL = "
        SELECT COUNT(*)
        FROM products p
        {$whereSQL}
    ";

    $countStmt =
        $pdo->prepare($countSQL);

    foreach (
        $params as $key => $value
    ) {

        $countStmt->bindValue(
            $key,
            $value
        );
    }

    $countStmt->execute();

    $totalRows =
        (int)$countStmt->fetchColumn();

} catch (PDOException $e) {

    $errors[] =
        "Unable to load filtered product count.";
}


$totalPages = max(
    1,
    (int)ceil(
        $totalRows / $limit
    )
);


/*
|--------------------------------------------------------------------------
| KEEP PAGE WITHIN RANGE
|--------------------------------------------------------------------------
*/

if ($page > $totalPages) {

    $page = $totalPages;

    $offset =
        ($page - 1) * $limit;
}


/*
|--------------------------------------------------------------------------
| FETCH PRODUCTS
|--------------------------------------------------------------------------
*/

$products = [];

try {

    $sql = "
        SELECT

            p.product_id,
            p.category_id,
            p.product_name,
            p.slug,
            p.short_description,
            p.description,
            p.price,
            p.discount_price,
            p.food_type,
            p.spice_level,
            p.preparation_time,
            p.stock,
            p.featured,
            p.availability,
            p.status,
            p.created_at,

            c.category_name,

            (
                SELECT pi.image_name
                FROM product_images pi
                WHERE pi.product_id = p.product_id
                ORDER BY
                    pi.is_primary DESC,
                    pi.display_order ASC,
                    pi.image_id ASC
                LIMIT 1
            ) AS image_name

        FROM products p

        LEFT JOIN categories c
            ON c.category_id = p.category_id

        {$whereSQL}

        ORDER BY p.product_id DESC

        LIMIT :offset, :limit
    ";

    $stmt =
        $pdo->prepare($sql);

    foreach (
        $params as $key => $value
    ) {

        $stmt->bindValue(
            $key,
            $value
        );
    }

    $stmt->bindValue(
        ':offset',
        $offset,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':limit',
        $limit,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $products =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $errors[] =
        "Unable to load products.";
}


/*
|--------------------------------------------------------------------------
| PAGE RANGE
|--------------------------------------------------------------------------
*/

$startItem = $totalRows > 0
    ? $offset + 1
    : 0;

$endItem = min(
    $offset + $limit,
    $totalRows
);


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
require_once "includes/a-navbar.php";

?>

<style>

/*
|--------------------------------------------------------------------------
| PRODUCT PAGE LAYOUT
|--------------------------------------------------------------------------
*/

.product-page {
    padding: 24px;
}

.product-page-header {
    margin-bottom: 24px;
}

.product-title-wrap {
    min-width: 0;
}

.product-title-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
}

.product-title-text h2 {
    font-size: 1.55rem;
}

.product-title-text p {
    font-size: 0.9rem;
}


/*
|--------------------------------------------------------------------------
| STAT CARDS
|--------------------------------------------------------------------------
*/

.product-stat-card {
    min-height: 112px;
    transition: transform 0.2s ease,
                box-shadow 0.2s ease;
}

.product-stat-card:hover {
    transform: translateY(-2px);
}

.product-stat-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
}


/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.product-filter-card {
    position: relative;
    z-index: 2;
}

.product-filter-card .form-label {
    font-size: 0.78rem;
    font-weight: 600;
    margin-bottom: 6px;
}

.product-filter-card .form-control,
.product-filter-card .form-select {
    min-height: 42px;
}


/*
|--------------------------------------------------------------------------
| PRODUCT TABLE
|--------------------------------------------------------------------------
*/

.product-table-card {
    overflow: hidden;
}

.product-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.product-table {
    min-width: 1080px;
    margin-bottom: 0;
    vertical-align: middle;
}

.product-table th {
    white-space: nowrap;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 13px 12px;
}

.product-table td {
    padding: 13px 12px;
}

.product-table tbody tr {
    transition: background-color 0.15s ease;
}

.product-table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.025);
}


/*
|--------------------------------------------------------------------------
| PRODUCT IMAGE
|--------------------------------------------------------------------------
*/

.product-image {
    width: 58px;
    height: 58px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
}

.product-image-placeholder {
    width: 58px;
    height: 58px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    background: #f8f9fa;
}


/*
|--------------------------------------------------------------------------
| PRODUCT NAME
|--------------------------------------------------------------------------
*/

.product-name {
    max-width: 250px;
}

.product-name-title {
    font-weight: 600;
    line-height: 1.25;
}

.product-short-description {
    max-width: 250px;
    font-size: 0.78rem;
}


/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

.product-price-current {
    font-weight: 700;
    white-space: nowrap;
}

.product-price-old {
    font-size: 0.76rem;
    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| STOCK
|--------------------------------------------------------------------------
*/

.stock-number {
    font-weight: 700;
}

.stock-low {
    color: #dc3545;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.product-actions {
    white-space: nowrap;
}

.product-actions .btn {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}


/*
|--------------------------------------------------------------------------
| EMPTY STATE
|--------------------------------------------------------------------------
*/

.product-empty-state {
    padding: 55px 20px;
}

.product-empty-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 15px;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.product-pagination {
    gap: 4px;
}

.product-pagination .page-link {
    min-width: 38px;
    text-align: center;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 991.98px) {

    .product-page {
        padding: 18px;
    }

    .product-page-header {
        align-items: flex-start !important;
    }

    .product-title-text h2 {
        font-size: 1.35rem;
    }

}


@media (max-width: 767.98px) {

    .product-page {
        padding: 14px;
    }

    .product-page-header {
        flex-direction: column;
        align-items: stretch !important;
    }

    .product-add-button {
        width: 100%;
    }

    .product-add-button .btn {
        width: 100%;
    }

    .product-title-icon {
        width: 42px;
        height: 42px;
        flex-basis: 42px;
    }

    .product-stat-card {
        min-height: auto;
    }

    .product-filter-actions {
        width: 100%;
    }

    .product-filter-actions .btn {
        width: 100%;
    }

}


/*
|--------------------------------------------------------------------------
| VERY SMALL DEVICES
|--------------------------------------------------------------------------
*/

@media (max-width: 420px) {

    .product-page {
        padding: 10px;
    }

    .product-title-text h2 {
        font-size: 1.2rem;
    }

    .product-title-text p {
        font-size: 0.8rem;
    }

}

</style>


<div class="container-fluid product-page">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div
        class="product-page-header d-flex flex-wrap justify-content-between align-items-center gap-3"
    >

        <div class="product-title-wrap d-flex align-items-center gap-3">

            <div
                class="product-title-icon bg-primary text-white rounded-3 d-flex align-items-center justify-content-center"
            >

                <i class="bi bi-cup-straw fs-4"></i>

            </div>


            <div class="product-title-text">

                <h2 class="fw-bold mb-1">
                    Products
                </h2>

                <p class="text-muted mb-0">
                    Manage your cafe menu and inventory.
                </p>

            </div>

        </div>


        <div class="product-add-button">

            <a
                href="add_product.php"
                class="btn btn-success"
            >

                <i class="bi bi-plus-circle me-1"></i>

                Add Product

            </a>

        </div>

    </div>



    <!-- =========================================================
         ALERTS
    ========================================================== -->

    <?php if (!empty($errors)): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <div class="fw-bold mb-1">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                Product Module Warning

            </div>


            <?php foreach ($errors as $error): ?>

                <div>
                    <?= e($error); ?>
                </div>

            <?php endforeach; ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if (
        isset($_SESSION['success']) &&
        trim((string)$_SESSION['success']) !== ''
    ): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-check-circle-fill me-1"></i>

            <?= e($_SESSION['success']); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>


        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <?php if (
        isset($_SESSION['error']) &&
        trim((string)$_SESSION['error']) !== ''
    ): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <i class="bi bi-exclamation-circle-fill me-1"></i>

            <?= e($_SESSION['error']); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>


        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>



    <!-- =========================================================
         STATISTICS
    ========================================================== -->

    <div class="row g-3 mb-4">


        <!-- TOTAL -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100 product-stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center gap-3">

                        <div>

                            <div class="text-muted small fw-semibold">
                                TOTAL PRODUCTS
                            </div>

                            <div class="fs-3 fw-bold mt-1">
                                <?= $totalProducts; ?>
                            </div>

                        </div>


                        <div
                            class="product-stat-icon bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center"
                        >

                            <i class="bi bi-box-seam fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- ACTIVE -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100 product-stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center gap-3">

                        <div>

                            <div class="text-muted small fw-semibold">
                                ACTIVE PRODUCTS
                            </div>

                            <div class="fs-3 fw-bold text-success mt-1">
                                <?= $activeProducts; ?>
                            </div>

                        </div>


                        <div
                            class="product-stat-icon bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center"
                        >

                            <i class="bi bi-check-circle-fill fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- FEATURED -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100 product-stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center gap-3">

                        <div>

                            <div class="text-muted small fw-semibold">
                                FEATURED PRODUCTS
                            </div>

                            <div class="fs-3 fw-bold text-warning mt-1">
                                <?= $featuredProducts; ?>
                            </div>

                        </div>


                        <div
                            class="product-stat-icon bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center"
                        >

                            <i class="bi bi-star-fill fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- OUT OF STOCK -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100 product-stat-card">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center gap-3">

                        <div>

                            <div class="text-muted small fw-semibold">
                                OUT OF STOCK
                            </div>

                            <div class="fs-3 fw-bold text-danger mt-1">
                                <?= $outOfStock; ?>
                            </div>

                        </div>


                        <div
                            class="product-stat-icon bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center justify-content-center"
                        >

                            <i class="bi bi-exclamation-octagon-fill fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



    <!-- =========================================================
         FILTERS
    ========================================================== -->

    <div class="card border-0 shadow-sm mb-4 product-filter-card">

        <div class="card-body p-3 p-lg-4">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-funnel me-1"></i>
                        Search & Filter
                    </h5>

                    <small class="text-muted">
                        Find products quickly using the available filters.
                    </small>

                </div>


                <?php if (
                    $search !== '' ||
                    $category !== '' ||
                    $foodType !== '' ||
                    $status !== ''
                ): ?>

                    <a
                        href="products.php"
                        class="btn btn-sm btn-outline-secondary"
                    >

                        <i class="bi bi-x-circle me-1"></i>

                        Clear

                    </a>

                <?php endif; ?>

            </div>


            <form
                method="GET"
                action="products.php"
            >

                <div class="row g-3 align-items-end">


                    <!-- SEARCH -->

                    <div class="col-xl-4 col-lg-6">

                        <label
                            for="search"
                            class="form-label"
                        >
                            Search Product
                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="search"
                                name="search"
                                id="search"
                                class="form-control"
                                value="<?= e($search); ?>"
                                placeholder="Product name, slug or description..."
                            >

                        </div>

                    </div>



                    <!-- CATEGORY -->

                    <div class="col-xl-2 col-lg-3 col-md-6">

                        <label
                            for="category"
                            class="form-label"
                        >
                            Category
                        </label>

                        <select
                            name="category"
                            id="category"
                            class="form-select"
                        >

                            <option value="">
                                All Categories
                            </option>


                            <?php foreach (
                                $categories as $cat
                            ): ?>

                                <option
                                    value="<?= (int)$cat['category_id']; ?>"
                                    <?= $categoryId === (int)$cat['category_id']
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= e($cat['category_name']); ?>

                                    <?php if (
                                        ($cat['status'] ?? '') === 'Inactive'
                                    ): ?>

                                        (Inactive)

                                    <?php endif; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <!-- FOOD TYPE -->

                    <div class="col-xl-2 col-lg-3 col-md-6">

                        <label
                            for="food_type"
                            class="form-label"
                        >
                            Food Type
                        </label>

                        <select
                            name="food_type"
                            id="food_type"
                            class="form-select"
                        >

                            <option value="">
                                All Food Types
                            </option>

                            <option
                                value="Veg"
                                <?= $foodType === 'Veg'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Veg
                            </option>

                            <option
                                value="Non-Veg"
                                <?= $foodType === 'Non-Veg'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Non-Veg
                            </option>

                            <option
                                value="Egg"
                                <?= $foodType === 'Egg'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Egg
                            </option>

                        </select>

                    </div>



                    <!-- STATUS -->

                    <div class="col-xl-2 col-lg-3 col-md-6">

                        <label
                            for="status"
                            class="form-label"
                        >
                            Status
                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-select"
                        >

                            <option value="">
                                All Status
                            </option>

                            <option
                                value="Active"
                                <?= $status === 'Active'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Active
                            </option>

                            <option
                                value="Inactive"
                                <?= $status === 'Inactive'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>



                    <!-- BUTTON -->

                    <div class="col-xl-2 col-lg-3 col-md-6 product-filter-actions">

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >

                            <i class="bi bi-search me-1"></i>

                            Apply Filters

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>



    <!-- =========================================================
         PRODUCT LIST HEADER
    ========================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

        <div>

            <h5 class="fw-bold mb-1">
                Product List
            </h5>

            <div class="text-muted small">

                <?php if ($totalRows > 0): ?>

                    Showing
                    <strong><?= $startItem; ?></strong>
                    -
                    <strong><?= $endItem; ?></strong>
                    of
                    <strong><?= $totalRows; ?></strong>
                    products

                <?php else: ?>

                    No products found

                <?php endif; ?>

            </div>

        </div>


        <div class="text-muted small">

            Page
            <strong><?= $page; ?></strong>
            of
            <strong><?= $totalPages; ?></strong>

        </div>

    </div>



    <!-- =========================================================
         PRODUCT TABLE
    ========================================================== -->

    <div class="card border-0 shadow-sm product-table-card">

        <div class="product-table-wrapper">

            <table class="table table-hover product-table">

                <thead class="table-light">

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Category
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Food Type
                        </th>

                        <th>
                            Spice
                        </th>

                        <th>
                            Stock
                        </th>

                        <th>
                            Availability
                        </th>

                        <th>
                            Featured
                        </th>

                        <th>
                            Status
                        </th>

                        <th class="text-center">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($products)): ?>

                    <tr>

                        <td
                            colspan="10"
                            class="text-center"
                        >

                            <div class="product-empty-state">

                                <div
                                    class="product-empty-icon bg-light text-muted rounded-circle d-flex align-items-center justify-content-center"
                                >

                                    <i class="bi bi-box-seam fs-2"></i>

                                </div>

                                <h5 class="fw-bold">
                                    No Products Found
                                </h5>

                                <p class="text-muted mb-3">

                                    <?php if (
                                        $search !== '' ||
                                        $category !== '' ||
                                        $foodType !== '' ||
                                        $status !== ''
                                    ): ?>

                                        Try changing your filters or search term.

                                    <?php else: ?>

                                        Start by adding your first product.

                                    <?php endif; ?>

                                </p>


                                <?php if (
                                    $search !== '' ||
                                    $category !== '' ||
                                    $foodType !== '' ||
                                    $status !== ''
                                ): ?>

                                    <a
                                        href="products.php"
                                        class="btn btn-outline-secondary me-1"
                                    >

                                        <i class="bi bi-arrow-counterclockwise me-1"></i>

                                        Clear Filters

                                    </a>

                                <?php endif; ?>


                                <a
                                    href="add_product.php"
                                    class="btn btn-success"
                                >

                                    <i class="bi bi-plus-circle me-1"></i>

                                    Add Product

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $products as $product
                    ): ?>

                        <?php

                        $productPrice =
                            (float)($product['price'] ?? 0);

                        $discountPrice =
                            (float)($product['discount_price'] ?? 0);

                        $hasDiscount =
                            $discountPrice > 0 &&
                            $discountPrice < $productPrice;

                        $finalPrice =
                            $hasDiscount
                                ? $discountPrice
                                : $productPrice;


                        $imageName =
                            trim(
                                (string)(
                                    $product['image_name']
                                    ?? ''
                                )
                            );

                        $safeImageName =
                            $imageName !== ''
                                ? basename($imageName)
                                : '';

                        $imagePath =
                            "../assets/images/products/"
                            . $safeImageName;


                        $stock =
                            (int)(
                                $product['stock']
                                ?? 0
                            );


                        $foodClass = match (
                            $product['food_type']
                            ?? ''
                        ) {

                            'Veg' =>
                                'success',

                            'Non-Veg' =>
                                'danger',

                            'Egg' =>
                                'warning',

                            default =>
                                'secondary'
                        };

                        ?>


                        <tr>


                            <!-- PRODUCT -->

                            <td>

                                <div class="d-flex align-items-center gap-3 product-name">

                                    <?php if (
                                        $safeImageName !== '' &&
                                        is_file(
                                            __DIR__
                                            . "/../assets/images/products/"
                                            . $safeImageName
                                        )
                                    ): ?>

                                        <img
                                            src="<?= e($imagePath); ?>"
                                            alt="<?= e($product['product_name']); ?>"
                                            class="product-image"
                                            loading="lazy"
                                        >

                                    <?php else: ?>

                                        <div
                                            class="product-image-placeholder d-flex align-items-center justify-content-center text-muted"
                                        >

                                            <i class="bi bi-image fs-4"></i>

                                        </div>

                                    <?php endif; ?>


                                    <div class="min-width-0">

                                        <div class="product-name-title">

                                            <?= e(
                                                $product['product_name']
                                            ); ?>

                                        </div>


                                        <?php if (
                                            !empty(
                                                $product['short_description']
                                            )
                                        ): ?>

                                            <div
                                                class="product-short-description text-muted text-truncate mt-1"
                                                title="<?= e(
                                                    $product['short_description']
                                                ); ?>"
                                            >

                                                <?= e(
                                                    $product['short_description']
                                                ); ?>

                                            </div>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $product['slug']
                                            )
                                        ): ?>

                                            <div class="small text-muted mt-1">

                                                <i class="bi bi-link-45deg"></i>

                                                <?= e(
                                                    $product['slug']
                                                ); ?>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </td>



                            <!-- CATEGORY -->

                            <td>

                                <?php if (
                                    !empty(
                                        $product['category_name']
                                    )
                                ): ?>

                                    <span class="badge bg-light text-dark border">

                                        <i class="bi bi-tag me-1"></i>

                                        <?= e(
                                            $product['category_name']
                                        ); ?>

                                    </span>

                                <?php else: ?>

                                    <span class="text-muted">
                                        Uncategorized
                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- PRICE -->

                            <td>

                                <div class="product-price-current">

                                    <?= money(
                                        $finalPrice,
                                        $currency
                                    ); ?>

                                </div>


                                <?php if ($hasDiscount): ?>

                                    <div
                                        class="product-price-old text-muted text-decoration-line-through"
                                    >

                                        <?= money(
                                            $productPrice,
                                            $currency
                                        ); ?>

                                    </div>


                                    <span class="badge bg-success mt-1">

                                        Sale

                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- FOOD TYPE -->

                            <td>

                                <span
                                    class="badge bg-<?= e($foodClass); ?> <?= $foodClass === 'warning'
                                        ? 'text-dark'
                                        : ''; ?>"
                                >

                                    <?= e(
                                        $product['food_type']
                                    ); ?>

                                </span>

                            </td>



                            <!-- SPICE -->

                            <td>

                                <?php

                                $spiceClass = match (
                                    $product['spice_level']
                                    ?? ''
                                ) {

                                    'Mild' =>
                                        'success',

                                    'Medium' =>
                                        'warning',

                                    'Hot' =>
                                        'danger',

                                    default =>
                                        'secondary'
                                };

                                ?>

                                <span
                                    class="badge bg-<?= e($spiceClass); ?> <?= $spiceClass === 'warning'
                                        ? 'text-dark'
                                        : ''; ?>"
                                >

                                    <?= e(
                                        $product['spice_level']
                                    ); ?>

                                </span>

                            </td>



                            <!-- STOCK -->

                            <td>

                                <span
                                    class="stock-number <?= $stock <= 0
                                        ? 'stock-low'
                                        : ''; ?>"
                                >

                                    <?= $stock; ?>

                                </span>


                                <?php if ($stock <= 0): ?>

                                    <div class="small text-danger">
                                        Out of stock
                                    </div>

                                <?php elseif ($stock <= 10): ?>

                                    <div class="small text-warning">
                                        Low stock
                                    </div>

                                <?php endif; ?>

                            </td>



                            <!-- AVAILABILITY -->

                            <td>

                                <?php if (
                                    ($product['availability'] ?? '')
                                    === 'Available'
                                ): ?>

                                    <span class="badge bg-success">

                                        <i class="bi bi-check-circle me-1"></i>

                                        Available

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">

                                        <i class="bi bi-dash-circle me-1"></i>

                                        Unavailable

                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- FEATURED -->

                            <td>

                                <?php if (
                                    (int)(
                                        $product['featured']
                                        ?? 0
                                    ) === 1
                                ): ?>

                                    <span class="badge bg-warning text-dark">

                                        <i class="bi bi-star-fill me-1"></i>

                                        Yes

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-light text-dark border">

                                        No

                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- STATUS -->

                            <td>

                                <?php if (
                                    ($product['status'] ?? '')
                                    === 'Active'
                                ): ?>

                                    <span class="badge bg-success">

                                        Active

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-danger">

                                        Inactive

                                    </span>

                                <?php endif; ?>

                            </td>



                            <!-- ACTIONS -->

                            <td class="text-center product-actions">

                                <a
                                    href="edit_product.php?id=<?= (int)$product['product_id']; ?>"
                                    class="btn btn-sm btn-outline-warning"
                                    title="Edit Product"
                                    aria-label="Edit Product"
                                >

                                    <i class="bi bi-pencil-square"></i>

                                </a>


                                <a
                                    href="delete_product.php?id=<?= (int)$product['product_id']; ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Delete Product"
                                    aria-label="Delete Product"
                                    onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');"
                                >

                                    <i class="bi bi-trash3"></i>

                                </a>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>



    <!-- =========================================================
         PAGINATION
    ========================================================== -->

    <?php if ($totalPages > 1): ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">

            <div class="text-muted small">

                Showing
                <strong><?= $startItem; ?></strong>
                -
                <strong><?= $endItem; ?></strong>
                of
                <strong><?= $totalRows; ?></strong>

            </div>


            <nav
                aria-label="Product pagination"
            >

                <ul class="pagination product-pagination mb-0">


                    <!-- PREVIOUS -->

                    <li
                        class="page-item <?= $page <= 1
                            ? 'disabled'
                            : ''; ?>"
                    >

                        <?php if ($page > 1): ?>

                            <a
                                class="page-link"
                                href="<?= e(
                                    paginationUrl(
                                        $page - 1,
                                        $search,
                                        $category,
                                        $foodType,
                                        $status
                                    )
                                ); ?>"
                                aria-label="Previous"
                            >

                                <i class="bi bi-chevron-left"></i>

                            </a>

                        <?php else: ?>

                            <span class="page-link">

                                <i class="bi bi-chevron-left"></i>

                            </span>

                        <?php endif; ?>

                    </li>



                    <?php

                    $startPage =
                        max(
                            1,
                            $page - 2
                        );

                    $endPage =
                        min(
                            $totalPages,
                            $page + 2
                        );

                    ?>


                    <!-- FIRST PAGE -->

                    <?php if ($startPage > 1): ?>

                        <li class="page-item">

                            <a
                                class="page-link"
                                href="<?= e(
                                    paginationUrl(
                                        1,
                                        $search,
                                        $category,
                                        $foodType,
                                        $status
                                    )
                                ); ?>"
                            >
                                1
                            </a>

                        </li>


                        <?php if ($startPage > 2): ?>

                            <li class="page-item disabled">

                                <span class="page-link">
                                    ...
                                </span>

                            </li>

                        <?php endif; ?>

                    <?php endif; ?>



                    <!-- PAGE NUMBERS -->

                    <?php for (
                        $i = $startPage;
                        $i <= $endPage;
                        $i++
                    ): ?>

                        <li
                            class="page-item <?= $page === $i
                                ? 'active'
                                : ''; ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= e(
                                    paginationUrl(
                                        $i,
                                        $search,
                                        $category,
                                        $foodType,
                                        $status
                                    )
                                ); ?>"
                            >

                                <?= $i; ?>

                            </a>

                        </li>

                    <?php endfor; ?>



                    <!-- LAST PAGE -->

                    <?php if ($endPage < $totalPages): ?>

                        <?php if (
                            $endPage < $totalPages - 1
                        ): ?>

                            <li class="page-item disabled">

                                <span class="page-link">
                                    ...
                                </span>

                            </li>

                        <?php endif; ?>


                        <li class="page-item">

                            <a
                                class="page-link"
                                href="<?= e(
                                    paginationUrl(
                                        $totalPages,
                                        $search,
                                        $category,
                                        $foodType,
                                        $status
                                    )
                                ); ?>"
                            >

                                <?= $totalPages; ?>

                            </a>

                        </li>

                    <?php endif; ?>



                    <!-- NEXT -->

                    <li
                        class="page-item <?= $page >= $totalPages
                            ? 'disabled'
                            : ''; ?>"
                    >

                        <?php if ($page < $totalPages): ?>

                            <a
                                class="page-link"
                                href="<?= e(
                                    paginationUrl(
                                        $page + 1,
                                        $search,
                                        $category,
                                        $foodType,
                                        $status
                                    )
                                ); ?>"
                                aria-label="Next"
                            >

                                <i class="bi bi-chevron-right"></i>

                            </a>

                        <?php else: ?>

                            <span class="page-link">

                                <i class="bi bi-chevron-right"></i>

                            </span>

                        <?php endif; ?>

                    </li>


                </ul>

            </nav>

        </div>

    <?php endif; ?>


</div>


<?php require_once "includes/a-footer.php"; ?>