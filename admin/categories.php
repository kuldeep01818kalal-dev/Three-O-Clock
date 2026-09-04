<?php

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Manage Categories";


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : "";

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : "";


/*
|--------------------------------------------------------------------------
| Validate Status Filter
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    "Active",
    "Inactive"
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = "";
}


/*
|--------------------------------------------------------------------------
| Pagination Settings
|--------------------------------------------------------------------------
*/

$limit = 10;

$page = isset($_GET['page'])
    ? (int)$_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}


/*
|--------------------------------------------------------------------------
| WHERE Conditions
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $where[] = "
        (
            category_name LIKE :search
            OR description LIKE :search_description
        )
    ";

    $params[':search'] = "%" . $search . "%";
    $params[':search_description'] = "%" . $search . "%";
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

if ($status !== "") {

    $where[] = "status = :status";

    $params[':status'] = $status;
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSQL = "";

if (!empty($where)) {

    $whereSQL = "WHERE " . implode(" AND ", $where);
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

try {

    $totalCategories = (int)$pdo->query("
        SELECT COUNT(*)
        FROM categories
    ")->fetchColumn();


    $activeCategories = (int)$pdo->query("
        SELECT COUNT(*)
        FROM categories
        WHERE status = 'Active'
    ")->fetchColumn();


    $inactiveCategories = (int)$pdo->query("
        SELECT COUNT(*)
        FROM categories
        WHERE status = 'Inactive'
    ")->fetchColumn();


} catch (Throwable $e) {

    $totalCategories = 0;
    $activeCategories = 0;
    $inactiveCategories = 0;

    $_SESSION['error'] = "Unable to load category statistics.";
}


/*
|--------------------------------------------------------------------------
| Total Filtered Rows
|--------------------------------------------------------------------------
*/

try {

    $countSQL = "
        SELECT COUNT(*)
        FROM categories
        {$whereSQL}
    ";

    $countStmt = $pdo->prepare($countSQL);

    foreach ($params as $key => $value) {

        $countStmt->bindValue($key, $value);
    }

    $countStmt->execute();

    $totalRows = (int)$countStmt->fetchColumn();


} catch (Throwable $e) {

    $totalRows = 0;

    $_SESSION['error'] = "Unable to load categories.";
}


/*
|--------------------------------------------------------------------------
| Calculate Total Pages
|--------------------------------------------------------------------------
*/

$totalPages = $totalRows > 0
    ? (int)ceil($totalRows / $limit)
    : 1;


/*
|--------------------------------------------------------------------------
| Correct Invalid Page
|--------------------------------------------------------------------------
*/

if ($page > $totalPages) {
    $page = $totalPages;
}


/*
|--------------------------------------------------------------------------
| Offset
|--------------------------------------------------------------------------
*/

$offset = ($page - 1) * $limit;


/*
|--------------------------------------------------------------------------
| Fetch Categories
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $sql = "
        SELECT
            category_id,
            category_name,
            category_image,
            description,
            status,
            created_at,
            updated_at
        FROM categories
        {$whereSQL}
        ORDER BY category_id DESC
        LIMIT :offset, :limit
    ";

    $stmt = $pdo->prepare($sql);


    foreach ($params as $key => $value) {

        $stmt->bindValue($key, $value);
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

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    $_SESSION['error'] = "Unable to load category list.";

    $categories = [];
}


/*
|--------------------------------------------------------------------------
| Pagination URL Helper
|--------------------------------------------------------------------------
*/

function paginationUrl($page, $search, $status)
{
    return "?page=" . (int)$page
        . "&search=" . urlencode($search)
        . "&status=" . urlencode($status);
}


/*
|--------------------------------------------------------------------------
| Pagination Window
|--------------------------------------------------------------------------
*/

$paginationStart = max(1, $page - 2);
$paginationEnd = min($totalPages, $page + 2);


/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";

?>

<div class="container-fluid mt-4">


    <!-- =====================================================
         Flash Messages
    ====================================================== -->

    <?php if (isset($_SESSION['success'])) : ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert">

            <i class="bi bi-check-circle-fill me-2"></i>

            <?= e($_SESSION['success']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>


    <?php if (isset($_SESSION['error'])) : ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert">

            <i class="bi bi-exclamation-triangle-fill me-2"></i>

            <?= e($_SESSION['error']); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

        <?php unset($_SESSION['error']); ?>

    <?php endif; ?>


    <!-- =====================================================
         Page Header
    ====================================================== -->

    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>

            <h3 class="fw-bold mb-1">
                <i class="bi bi-grid-fill me-2"></i>
                Category Management
            </h3>

            <p class="text-muted mb-0">
                Manage cafe food and menu categories.
            </p>

        </div>

        <div>

            <a
                href="add_category.php"
                class="btn btn-success">

                <i class="bi bi-plus-circle me-1"></i>

                Add Category

            </a>

        </div>

    </div>


    <!-- =====================================================
         Statistics Cards
    ====================================================== -->

    <div class="row mb-4">


        <!-- Total -->

        <div class="col-lg-4 col-md-6 mb-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-muted mb-2">
                                Total Categories
                            </h6>

                            <h3 class="fw-bold mb-0">
                                <?= $totalCategories; ?>
                            </h3>

                        </div>

                        <div class="fs-1 text-primary">
                            <i class="bi bi-grid"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Active -->

        <div class="col-lg-4 col-md-6 mb-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-success mb-2">
                                Active Categories
                            </h6>

                            <h3 class="fw-bold text-success mb-0">
                                <?= $activeCategories; ?>
                            </h3>

                        </div>

                        <div class="fs-1 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Inactive -->

        <div class="col-lg-4 col-md-6 mb-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body">

                    <div
                        class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-danger mb-2">
                                Inactive Categories
                            </h6>

                            <h3 class="fw-bold text-danger mb-0">
                                <?= $inactiveCategories; ?>
                            </h3>

                        </div>

                        <div class="fs-1 text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         Search & Filter
    ====================================================== -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <form
                method="GET"
                action="categories.php">

                <div class="row align-items-end g-3">


                    <!-- Search -->

                    <div class="col-lg-5 col-md-6">

                        <label
                            for="search"
                            class="form-label fw-semibold">

                            Search Category

                        </label>

                        <div class="input-group">

                            <span class="input-group-text">
                                <i class="bi bi-search"></i>
                            </span>

                            <input
                                type="text"
                                id="search"
                                name="search"
                                class="form-control"
                                placeholder="Search category or description..."
                                value="<?= e($search); ?>">

                        </div>

                    </div>


                    <!-- Status -->

                    <div class="col-lg-3 col-md-6">

                        <label
                            for="status"
                            class="form-label fw-semibold">

                            Status

                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-select">

                            <option value="">
                                All Status
                            </option>

                            <option
                                value="Active"
                                <?= $status === "Active" ? "selected" : ""; ?>>

                                Active

                            </option>

                            <option
                                value="Inactive"
                                <?= $status === "Inactive" ? "selected" : ""; ?>>

                                Inactive

                            </option>

                        </select>

                    </div>


                    <!-- Buttons -->

                    <div class="col-lg-4 col-md-12">

                        <div
                            class="d-flex flex-wrap gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-search me-1"></i>

                                Search

                            </button>


                            <a
                                href="categories.php"
                                class="btn btn-secondary">

                                <i class="bi bi-arrow-clockwise me-1"></i>

                                Reset

                            </a>


                            <a
                                href="add_category.php"
                                class="btn btn-success">

                                <i class="bi bi-plus-circle me-1"></i>

                                Add

                            </a>

                        </div>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- =====================================================
         Category Table
    ====================================================== -->

    <div class="card shadow border-0">

        <div
            class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

            <h5 class="mb-0">

                <i class="bi bi-grid-fill me-2"></i>

                Category List

            </h5>

            <span class="badge bg-light text-dark">

                <?= $totalRows; ?> result<?= $totalRows == 1 ? "" : "s"; ?>

            </span>

        </div>


        <div class="card-body p-0">

            <div class="table-responsive">

                <table
                    class="table table-hover table-bordered align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th
                                width="60"
                                class="text-center">

                                #

                            </th>

                            <th
                                width="100"
                                class="text-center">

                                Image

                            </th>

                            <th>

                                Category Name

                            </th>

                            <th>

                                Description

                            </th>

                            <th
                                width="120"
                                class="text-center">

                                Status

                            </th>

                            <th
                                width="140">

                                Created

                            </th>

                            <th
                                width="150"
                                class="text-center">

                                Actions

                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (!empty($categories)) : ?>

                            <?php

                            $sr = $offset + 1;

                            foreach ($categories as $category) :

                                $categoryId = (int)$category['category_id'];

                                $categoryName =
                                    $category['category_name'];

                                $description =
                                    trim((string)$category['description']);

                                $imageName =
                                    basename(
                                        (string)$category['category_image']
                                    );

                                $imagePath =
                                    "../assets/images/categories/"
                                    . $imageName;

                                $hasImage =
                                    $imageName !== ""
                                    && is_file($imagePath);

                                if (mb_strlen($description) > 80) {

                                    $description =
                                        mb_substr($description, 0, 80)
                                        . "...";
                                }

                            ?>

                                <tr>


                                    <!-- Serial -->

                                    <td class="text-center">

                                        <?= $sr++; ?>

                                    </td>


                                    <!-- Image -->

                                    <td class="text-center">

                                        <?php if ($hasImage) : ?>

                                            <img
                                                src="<?= e($imagePath); ?>"
                                                alt="<?= e($categoryName); ?>"
                                                class="img-thumbnail"
                                                style="
                                                    width:80px;
                                                    height:80px;
                                                    object-fit:cover;
                                                    border-radius:10px;
                                                ">

                                        <?php else : ?>

                                            <div
                                                class="bg-light border rounded d-flex align-items-center justify-content-center mx-auto"
                                                style="
                                                    width:80px;
                                                    height:80px;
                                                ">

                                                <i
                                                    class="bi bi-image text-muted fs-4">
                                                </i>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Category Name -->

                                    <td>

                                        <strong>

                                            <?= e($categoryName); ?>

                                        </strong>

                                    </td>


                                    <!-- Description -->

                                    <td>

                                        <?php if ($description !== "") : ?>

                                            <span
                                                title="<?= e($category['description']); ?>">

                                                <?= e($description); ?>

                                            </span>

                                        <?php else : ?>

                                            <span class="text-muted">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Status -->

                                    <td class="text-center">

                                        <?php if ($category['status'] === "Active") : ?>

                                            <span
                                                class="badge bg-success">

                                                <i
                                                    class="bi bi-check-circle me-1">
                                                </i>

                                                Active

                                            </span>

                                        <?php else : ?>

                                            <span
                                                class="badge bg-danger">

                                                <i
                                                    class="bi bi-x-circle me-1">
                                                </i>

                                                Inactive

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Created -->

                                    <td>

                                        <?php

                                        if (
                                            !empty($category['created_at'])
                                        ) {

                                            echo e(
                                                date(
                                                    "d M Y",
                                                    strtotime(
                                                        $category['created_at']
                                                    )
                                                )
                                            );

                                        } else {

                                            echo "-";

                                        }

                                        ?>

                                    </td>


                                    <!-- Actions -->

                                    <td class="text-center">

                                        <div
                                            class="d-flex justify-content-center gap-1">


                                            <!-- Edit -->

                                            <a
                                                href="edit_category.php?id=<?= $categoryId; ?>"
                                                class="btn btn-warning btn-sm"
                                                title="Edit Category">

                                                <i
                                                    class="bi bi-pencil-square">
                                                </i>

                                            </a>


                                            <!-- Delete -->

                                            <a
                                                href="delete_category.php?id=<?= $categoryId; ?>"
                                                class="btn btn-danger btn-sm"
                                                title="Delete Category"
                                                onclick="return confirm('Are you sure you want to delete <?= e($categoryName); ?>? If products are assigned to this category, deletion will be blocked.');">

                                                <i
                                                    class="bi bi-trash">
                                                </i>

                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                        <?php else : ?>


                            <!-- Empty State -->

                            <tr>

                                <td
                                    colspan="7"
                                    class="text-center py-5">

                                    <div class="py-4">

                                        <i
                                            class="bi bi-grid text-muted"
                                            style="font-size:50px;">
                                        </i>

                                        <h5
                                            class="text-muted mt-3">

                                            No Categories Found

                                        </h5>

                                        <?php if ($search !== "" || $status !== "") : ?>

                                            <p class="text-muted mb-3">

                                                Try changing your search
                                                or filter.

                                            </p>

                                            <a
                                                href="categories.php"
                                                class="btn btn-secondary">

                                                <i
                                                    class="bi bi-arrow-clockwise me-1">
                                                </i>

                                                Clear Filters

                                            </a>

                                        <?php else : ?>

                                            <p class="text-muted mb-3">

                                                No categories have been
                                                added yet.

                                            </p>

                                            <a
                                                href="add_category.php"
                                                class="btn btn-success">

                                                <i
                                                    class="bi bi-plus-circle me-1">
                                                </i>

                                                Add First Category

                                            </a>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- =====================================================
         Pagination
    ====================================================== -->

    <?php if ($totalPages > 1) : ?>

        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">


            <!-- Page Information -->

            <div class="text-muted">

                Showing

                <strong>
                    <?= $totalRows > 0 ? $offset + 1 : 0; ?>
                </strong>

                to

                <strong>
                    <?= min($offset + $limit, $totalRows); ?>
                </strong>

                of

                <strong>
                    <?= $totalRows; ?>
                </strong>

                categories

            </div>


            <!-- Pagination -->

            <nav aria-label="Category pagination">

                <ul class="pagination mb-0">


                    <!-- Previous -->

                    <li
                        class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">

                        <?php if ($page > 1) : ?>

                            <a
                                class="page-link"
                                href="<?= e(paginationUrl($page - 1, $search, $status)); ?>">

                                <i
                                    class="bi bi-chevron-left">
                                </i>

                                Previous

                            </a>

                        <?php else : ?>

                            <span class="page-link">

                                <i
                                    class="bi bi-chevron-left">
                                </i>

                                Previous

                            </span>

                        <?php endif; ?>

                    </li>


                    <!-- First Page -->

                    <?php if ($paginationStart > 1) : ?>

                        <li class="page-item">

                            <a
                                class="page-link"
                                href="<?= e(paginationUrl(1, $search, $status)); ?>">

                                1

                            </a>

                        </li>

                        <?php if ($paginationStart > 2) : ?>

                            <li
                                class="page-item disabled">

                                <span class="page-link">
                                    ...
                                </span>

                            </li>

                        <?php endif; ?>

                    <?php endif; ?>


                    <!-- Page Numbers -->

                    <?php for (
                        $i = $paginationStart;
                        $i <= $paginationEnd;
                        $i++
                    ) : ?>

                        <li
                            class="page-item <?= $page == $i ? 'active' : ''; ?>">

                            <a
                                class="page-link"
                                href="<?= e(paginationUrl($i, $search, $status)); ?>">

                                <?= $i; ?>

                            </a>

                        </li>

                    <?php endfor; ?>


                    <!-- Last Page -->

                    <?php if ($paginationEnd < $totalPages) : ?>

                        <?php if ($paginationEnd < $totalPages - 1) : ?>

                            <li
                                class="page-item disabled">

                                <span class="page-link">
                                    ...
                                </span>

                            </li>

                        <?php endif; ?>

                        <li class="page-item">

                            <a
                                class="page-link"
                                href="<?= e(paginationUrl($totalPages, $search, $status)); ?>">

                                <?= $totalPages; ?>

                            </a>

                        </li>

                    <?php endif; ?>


                    <!-- Next -->

                    <li
                        class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">

                        <?php if ($page < $totalPages) : ?>

                            <a
                                class="page-link"
                                href="<?= e(paginationUrl($page + 1, $search, $status)); ?>">

                                Next

                                <i
                                    class="bi bi-chevron-right">
                                </i>

                            </a>

                        <?php else : ?>

                            <span class="page-link">

                                Next

                                <i
                                    class="bi bi-chevron-right">
                                </i>

                            </span>

                        <?php endif; ?>

                    </li>

                </ul>

            </nav>

        </div>

    <?php endif; ?>


</div>


<?php

include "includes/a-footer.php";

?>