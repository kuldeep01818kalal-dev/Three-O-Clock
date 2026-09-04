<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Products";

$errors = [];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
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

    $currencyValue =
        $currencyStmt->fetchColumn();

    if (
        is_string($currencyValue) &&
        trim($currencyValue) !== ''
    ) {

        $currency = trim($currencyValue);

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
| CATEGORY FILTER VALIDATION
|--------------------------------------------------------------------------
*/

$categoryId = null;

if ($category !== '') {

    if (
        filter_var(
            $category,
            FILTER_VALIDATE_INT
        ) !== false
    ) {

        $categoryId =
            (int)$category;

    } else {

        $category = '';

    }

}


/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$totalProducts = 0;
$activeProducts = 0;
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
| CATEGORY DROPDOWN
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $categoryStmt = $pdo->query("
        SELECT
            category_id,
            category_name
        FROM categories
        WHERE status = 'Active'
        ORDER BY category_name ASC
    ");

    $categories =
        $categoryStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $categories = [];

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

$offset =
    ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| BUILD WHERE CLAUSE
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
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );

}


/*
|--------------------------------------------------------------------------
| TOTAL FILTERED PRODUCTS
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
        $params
        as $key => $value
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
        "Unable to load product count.";

}


$totalPages =
    max(
        1,
        (int)ceil(
            $totalRows / $limit
        )
    );


/*
|--------------------------------------------------------------------------
| KEEP PAGE IN RANGE
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
|
| IMPORTANT:
| discount_price is the actual database field.
|
| The primary image is fetched using a correlated subquery
| so the same product cannot appear multiple times because
| of multiple product_images rows.
|
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
        $params
        as $key => $value
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
| BUILD PAGINATION URL
|--------------------------------------------------------------------------
*/

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

        $query['search'] =
            $search;

    }


    if ($category !== '') {

        $query['category'] =
            $category;

    }


    if ($foodType !== '') {

        $query['food_type'] =
            $foodType;

    }


    if ($status !== '') {

        $query['status'] =
            $status;

    }


    return '?' .
        http_build_query(
            $query
        );
}


/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

require_once "includes/a-header.php";

require_once "includes/a-sidebar.php";

require_once "includes/a-navbar.php";

?>


<div class="container-fluid mt-4 mb-5">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">


        <div>

            <div class="d-flex align-items-center gap-2">

                <div
                    class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center"
                    style="width:46px;height:46px;"
                >

                    <i class="bi bi-cup-straw fs-4"></i>

                </div>


                <div>

                    <h2 class="fw-bold mb-0">

                        Products

                    </h2>


                    <p class="text-muted mb-0">

                        Manage your cafe menu and inventory.

                    </p>

                </div>

            </div>

        </div>


        <div>

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
         ERROR MESSAGES
    ========================================================== -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <div class="fw-bold mb-1">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                Product module warning

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



    <!-- =========================================================
         SUCCESS MESSAGE
    ========================================================== -->

    <?php if (
        isset($_SESSION['success']) &&
        $_SESSION['success'] !== ''
    ): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle-fill me-1"></i>

            <?= e(
                (string)$_SESSION['success']
            ); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>


        <?php unset(
            $_SESSION['success']
        ); ?>

    <?php endif; ?>



    <!-- =========================================================
         ERROR FROM OTHER PRODUCT FILES
    ========================================================== -->

    <?php if (
        isset($_SESSION['error']) &&
        $_SESSION['error'] !== ''
    ): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-circle-fill me-1"></i>

            <?= e(
                (string)$_SESSION['error']
            ); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>


        <?php unset(
            $_SESSION['error']
        ); ?>

    <?php endif; ?>



    <!-- =========================================================
         STATISTICS
    ========================================================== -->

    <div class="row g-3 mb-4">


        <!-- TOTAL -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">


                        <div>

                            <span class="text-muted small">

                                TOTAL PRODUCTS

                            </span>


                            <h2 class="fw-bold mb-0 mt-1">

                                <?= $totalProducts; ?>

                            </h2>

                        </div>


                        <div
                            class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center"
                            style="width:50px;height:50px;"
                        >

                            <i class="bi bi-box-seam fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- ACTIVE -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">


                        <div>

                            <span class="text-muted small">

                                ACTIVE PRODUCTS

                            </span>


                            <h2 class="fw-bold text-success mb-0 mt-1">

                                <?= $activeProducts; ?>

                            </h2>

                        </div>


                        <div
                            class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center"
                            style="width:50px;height:50px;"
                        >

                            <i class="bi bi-check-circle-fill fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- FEATURED -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">


                        <div>

                            <span class="text-muted small">

                                FEATURED PRODUCTS

                            </span>


                            <h2 class="fw-bold text-warning mb-0 mt-1">

                                <?= $featuredProducts; ?>

                            </h2>

                        </div>


                        <div
                            class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center"
                            style="width:50px;height:50px;"
                        >

                            <i class="bi bi-star-fill fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>



        <!-- STOCK -->

        <div class="col-xl-3 col-md-6">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">


                        <div>

                            <span class="text-muted small">

                                OUT OF STOCK

                            </span>


                            <h2 class="fw-bold text-danger mb-0 mt-1">

                                <?= $outOfStock; ?>

                            </h2>

                        </div>


                        <div
                            class="bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center justify-content-center"
                            style="width:50px;height:50px;"
                        >

                            <i class="bi bi-box2-fill fs-4"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


    </div>



    <!-- =========================================================
         ADDITIONAL INVENTORY INFO
    ========================================================== -->

    <div class="d-flex flex-wrap gap-2 mb-4">

        <span class="badge bg-light text-dark border px-3 py-2">

            <i class="bi bi-eye-slash me-1"></i>

            Unavailable:
            <?= $unavailableProducts; ?>

        </span>


        <span class="badge bg-light text-dark border px-3 py-2">

            <i class="bi bi-list-check me-1"></i>

            Showing:
            <?= count($products); ?>
            of
            <?= $totalRows; ?>

        </span>

    </div>



    <!-- =========================================================
         SEARCH & FILTERS
    ========================================================== -->

    <div class="card shadow-sm border-0 mb-4">


        <div class="card-header bg-white py-3">


            <div class="d-flex align-items-center gap-2">

                <i class="bi bi-funnel-fill text-primary"></i>


                <h5 class="mb-0 fw-bold">

                    Search & Filters

                </h5>

            </div>


        </div>


        <div class="card-body">


            <form
                method="GET"
                action="a-products.php"
            >


                <div class="row g-3 align-items-end">


                    <!-- SEARCH -->

                    <div class="col-xl-3 col-lg-4 col-md-6">

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
                                type="text"
                                name="search"
                                id="search"
                                class="form-control"
                                placeholder="Product name..."
                                value="<?= e($search); ?>"
                            >

                        </div>

                    </div>



                    <!-- CATEGORY -->

                    <div class="col-xl-2 col-lg-4 col-md-6">

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
                                $categories
                                as $cat
                            ): ?>

                                <option
                                    value="<?= (int)$cat['category_id']; ?>"
                                    <?= (
                                        $categoryId ===
                                        (int)$cat['category_id']
                                    )
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= e(
                                        $cat['category_name']
                                    ); ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>



                    <!-- FOOD TYPE -->

                    <div class="col-xl-2 col-lg-4 col-md-6">

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

                                All

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

                    <div class="col-xl-2 col-lg-4 col-md-6">

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

                                All

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



                    <!-- BUTTONS -->

                    <div class="col-xl-3 col-lg-4 col-md-12">


                        <div class="d-flex flex-wrap gap-2">


                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="bi bi-search me-1"></i>

                                Search

                            </button>


                            <a
                                href="a-products.php"
                                class="btn btn-outline-secondary"
                            >

                                <i class="bi bi-arrow-counterclockwise me-1"></i>

                                Reset

                            </a>


                            <a
                                href="add_product.php"
                                class="btn btn-success"
                            >

                                <i class="bi bi-plus-circle me-1"></i>

                                Add

                            </a>


                        </div>

                    </div>


                </div>


            </form>


        </div>

    </div>



    <!-- =========================================================
         PRODUCT TABLE
    ========================================================== -->

    <div class="card shadow-sm border-0">


        <div class="card-header bg-primary text-white py-3">


            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">


                <h5 class="mb-0">

                    <i class="bi bi-box-seam me-2"></i>

                    Product Management

                </h5>


                <span class="badge bg-white text-primary">

                    <?= $totalRows; ?>
                    Products

                </span>


            </div>


        </div>


        <div class="card-body p-0">


            <div class="table-responsive">


                <table class="table table-hover align-middle mb-0">


                    <thead class="table-light">

                        <tr>

                            <th class="ps-3">
                                #
                            </th>

                            <th>
                                Image
                            </th>

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
                                Stock
                            </th>

                            <th>
                                Food
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


                    <?php if (
                        empty($products)
                    ): ?>


                        <tr>

                            <td
                                colspan="11"
                                class="text-center py-5"
                            >

                                <div class="text-muted">


                                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>


                                    <h5>

                                        No Products Found

                                    </h5>


                                    <p class="mb-3">

                                        Try changing your search or filters.

                                    </p>


                                    <a
                                        href="a-products.php"
                                        class="btn btn-outline-primary btn-sm"
                                    >

                                        Clear Filters

                                    </a>


                                </div>

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php

                        $serial =
                            $offset + 1;

                        ?>


                        <?php foreach (
                            $products
                            as $product
                        ): ?>


                            <?php

                            $regularPrice =
                                (float)$product['price'];

                            $discountPrice =
                                $product['discount_price'] !== null
                                    ? (float)$product['discount_price']
                                    : null;


                            $finalPrice =
                                $regularPrice;


                            $hasDiscount =
                                $discountPrice !== null &&
                                $discountPrice > 0 &&
                                $discountPrice < $regularPrice;


                            if ($hasDiscount) {

                                $finalPrice =
                                    $discountPrice;

                            }


                            $stock =
                                (int)$product['stock'];


                            $imageName =
                                trim(
                                    (string)(
                                        $product['image_name']
                                        ?? ''
                                    )
                                );


                            $imagePath =
                                $imageName !== ''
                                    ? "../assets/images/products/" .
                                      $imageName
                                    : "../assets/images/no-image.png";


                            ?>


                            <tr>


                                <!-- SERIAL -->

                                <td class="ps-3">

                                    <?= $serial++; ?>

                                </td>



                                <!-- IMAGE -->

                                <td>

                                    <img
                                        src="<?= e($imagePath); ?>"
                                        alt="<?= e(
                                            $product['product_name']
                                        ); ?>"
                                        class="img-thumbnail"
                                        style="
                                            width:70px;
                                            height:70px;
                                            object-fit:cover;
                                        "
                                        onerror="this.onerror=null;this.src='../assets/images/no-image.png';"
                                    >

                                </td>



                                <!-- PRODUCT -->

                                <td>


                                    <div class="fw-semibold">

                                        <?= e(
                                            $product['product_name']
                                        ); ?>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $product[
                                                'short_description'
                                            ]
                                        )
                                    ): ?>

                                        <small class="text-muted">

                                            <?= e(
                                                mb_strimwidth(
                                                    (string)$product[
                                                        'short_description'
                                                    ],
                                                    0,
                                                    55,
                                                    '...'
                                                )
                                            ); ?>

                                        </small>

                                    <?php endif; ?>


                                    <div>

                                        <small class="text-muted">

                                            <?= e(
                                                $product['slug']
                                            ); ?>

                                        </small>

                                    </div>


                                </td>



                                <!-- CATEGORY -->

                                <td>

                                    <?= e(
                                        $product[
                                            'category_name'
                                        ] ??
                                        'N/A'
                                    ); ?>

                                </td>



                                <!-- PRICE -->

                                <td>


                                    <?php if (
                                        $hasDiscount
                                    ): ?>


                                        <div class="fw-bold text-success">

                                            <?= e($currency); ?><?= number_format(
                                                $finalPrice,
                                                2
                                            ); ?>

                                        </div>


                                        <small
                                            class="text-muted text-decoration-line-through"
                                        >

                                            <?= e($currency); ?><?= number_format(
                                                $regularPrice,
                                                2
                                            ); ?>

                                        </small>


                                        <div>

                                            <span class="badge bg-danger">

                                                <?= e($currency); ?><?= number_format(
                                                    $regularPrice -
                                                    $discountPrice,
                                                    2
                                                ); ?>

                                                OFF

                                            </span>

                                        </div>


                                    <?php else: ?>


                                        <span class="fw-bold">

                                            <?= e($currency); ?><?= number_format(
                                                $regularPrice,
                                                2
                                            ); ?>

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- STOCK -->

                                <td>


                                    <?php if (
                                        $stock <= 0
                                    ): ?>


                                        <span class="badge bg-danger">

                                            Out of Stock

                                        </span>


                                    <?php elseif (
                                        $stock <= 10
                                    ): ?>


                                        <span class="badge bg-warning text-dark">

                                            <?= $stock; ?>

                                            Low

                                        </span>


                                    <?php else: ?>


                                        <span class="badge bg-success">

                                            <?= $stock; ?>

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- FOOD TYPE -->

                                <td>


                                    <?php

                                    $foodClass =
                                        match (
                                            $product[
                                                'food_type'
                                            ]
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


                                    <span
                                        class="badge bg-<?= e(
                                            $foodClass
                                        ); ?> <?= $foodClass === 'warning'
                                            ? 'text-dark'
                                            : ''; ?>"
                                    >

                                        <?= e(
                                            $product[
                                                'food_type'
                                            ]
                                        ); ?>

                                    </span>


                                </td>



                                <!-- AVAILABILITY -->

                                <td>


                                    <?php if (
                                        $product[
                                            'availability'
                                        ] === 'Available'
                                    ): ?>


                                        <span class="badge bg-success">

                                            Available

                                        </span>


                                    <?php else: ?>


                                        <span class="badge bg-secondary">

                                            Unavailable

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- FEATURED -->

                                <td>


                                    <?php if (
                                        (int)$product[
                                            'featured'
                                        ] === 1
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
                                        $product[
                                            'status'
                                        ] === 'Active'
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

                                <td class="text-center">


                                    <div class="d-flex justify-content-center gap-1">


                                        <a
                                            href="edit_product.php?id=<?= (int)$product['product_id']; ?>"
                                            class="btn btn-sm btn-outline-warning"
                                            title="Edit Product"
                                        >

                                            <i class="bi bi-pencil-square"></i>

                                        </a>


                                        <a
                                            href="delete_product.php?id=<?= (int)$product['product_id']; ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete Product"
                                            onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');"
                                        >

                                            <i class="bi bi-trash3"></i>

                                        </a>


                                    </div>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </div>


    </div>



    <!-- =========================================================
         PAGINATION
    ========================================================== -->

    <?php if (
        $totalPages > 1
    ): ?>


        <nav
            class="mt-4"
            aria-label="Product pagination"
        >


            <ul class="pagination justify-content-center flex-wrap">


                <!-- PREVIOUS -->

                <li
                    class="page-item <?= $page <= 1
                        ? 'disabled'
                        : ''; ?>"
                >

                    <?php if (
                        $page > 1
                    ): ?>

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
                        >

                            <i class="bi bi-chevron-left"></i>

                            Previous

                        </a>

                    <?php else: ?>

                        <span class="page-link">

                            <i class="bi bi-chevron-left"></i>

                            Previous

                        </span>

                    <?php endif; ?>

                </li>



                <!-- PAGE NUMBERS -->

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



                <!-- NEXT -->

                <li
                    class="page-item <?= $page >= $totalPages
                        ? 'disabled'
                        : ''; ?>"
                >

                    <?php if (
                        $page < $totalPages
                    ): ?>

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
                        >

                            Next

                            <i class="bi bi-chevron-right"></i>

                        </a>

                    <?php else: ?>

                        <span class="page-link">

                            Next

                            <i class="bi bi-chevron-right"></i>

                        </span>

                    <?php endif; ?>

                </li>


            </ul>


        </nav>


    <?php endif; ?>


</div>


<?php require_once "includes/a-footer.php"; ?>