<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Manage Categories";

/* ======================================================
   Search & Filter
====================================================== */

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$status = isset($_GET['status']) ? trim($_GET['status']) : "";

/* ======================================================
   Statistics
====================================================== */

$totalCategories = $pdo->query("
    SELECT COUNT(*) 
    FROM categories
")->fetchColumn();

$activeCategories = $pdo->query("
    SELECT COUNT(*)
    FROM categories
    WHERE status='Active'
")->fetchColumn();

$inactiveCategories = $pdo->query("
    SELECT COUNT(*)
    FROM categories
    WHERE status='Inactive'
")->fetchColumn();

/* ======================================================
   Pagination
====================================================== */

$limit = 10;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $limit;

/* ======================================================
   WHERE Conditions
====================================================== */

$where = [];
$params = [];

if (!empty($search)) {

    $where[] = "category_name LIKE :search";

    $params[':search'] = "%{$search}%";

}

if (!empty($status)) {

    $where[] = "status = :status";

    $params[':status'] = $status;

}

$whereSQL = "";

if (!empty($where)) {

    $whereSQL = "WHERE " . implode(" AND ", $where);

}

/* ======================================================
   Total Rows
====================================================== */

$countSQL = "

SELECT COUNT(*)

FROM categories

{$whereSQL}

";

$countStmt = $pdo->prepare($countSQL);

$countStmt->execute($params);

$totalRows = $countStmt->fetchColumn();

$totalPages = ceil($totalRows / $limit);

/* ======================================================
   Fetch Categories
====================================================== */

$sql = "

SELECT *

FROM categories

{$whereSQL}

ORDER BY category_id DESC

LIMIT :offset, :limit

";

$stmt = $pdo->prepare($sql);

/* Bind Search Parameters */

foreach ($params as $key => $value) {

    $stmt->bindValue($key, $value);

}

$stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
$stmt->bindValue(":limit", $limit, PDO::PARAM_INT);

$stmt->execute();

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   Includes
====================================================== */

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>

<div class="container-fluid mt-4">
    <!-- ===========================
     Success Message
=========================== -->

<?php if (isset($_SESSION['success'])) : ?>

<div class="alert alert-success alert-dismissible fade show" role="alert">

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


<!-- ===========================
     Statistics Cards
=========================== -->

<div class="row mb-4">

    <div class="col-lg-4 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted mb-2">

                    Total Categories

                </h6>

                <h3 class="fw-bold">

                    <?= $totalCategories; ?>

                </h3>

            </div>

        </div>

    </div>


    <div class="col-lg-4 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-success mb-2">

                    Active Categories

                </h6>

                <h3 class="fw-bold text-success">

                    <?= $activeCategories; ?>

                </h3>

            </div>

        </div>

    </div>


    <div class="col-lg-4 col-md-6 mb-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-danger mb-2">

                    Inactive Categories

                </h6>

                <h3 class="fw-bold text-danger">

                    <?= $inactiveCategories; ?>

                </h3>

            </div>

        </div>

    </div>

</div>


<!-- ===========================
     Search & Filter Card
=========================== -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body">

        <form method="GET">

            <div class="row align-items-end">

                <div class="col-md-5">

                    <label class="form-label">

                        Search Category

                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search category..."
                        value="<?= htmlspecialchars($search); ?>">

                </div>


                <div class="col-md-3">

                    <label class="form-label">

                        Status

                    </label>

                    <select
                        name="status"
                        class="form-select">

                        <option value="">All</option>

                        <option
                            value="Active"
                            <?= ($status=="Active") ? "selected" : ""; ?>>

                            Active

                        </option>

                        <option
                            value="Inactive"
                            <?= ($status=="Inactive") ? "selected" : ""; ?>>

                            Inactive

                        </option>

                    </select>

                </div>


                <div class="col-md-4 text-end">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        Search

                    </button>

                    <a
                        href="categories.php"
                        class="btn btn-secondary">

                        Reset

                    </a>

                    <a
                        href="add_category.php"
                        class="btn btn-success">

                        <i class="bi bi-plus-circle"></i>

                        Add Category

                    </a>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- ===========================
     Category Table
=========================== -->

<div class="card shadow border-0">

    <div class="card-header bg-primary text-white">

        <h5 class="mb-0">

            <i class="bi bi-grid-fill"></i>

            Category Management

        </h5>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover table-bordered align-middle mb-0">

                <thead class="table-light">

                <tr>

                    <th width="60">#</th>

                    <th width="90">Image</th>

                    <th>Category Name</th>

                    <th>Description</th>

                    <th width="120">Status</th>

                    <th width="170">Created At</th>

                    <th width="170" class="text-center">

                        Actions

                    </th>

                </tr>

                </thead>

                <tbody>
                    <?php if (!empty($categories)) : ?>

<?php
$sr = $offset + 1;

foreach ($categories as $category) :
?>

<tr>

    <td>
        <?= $sr++; ?>
    </td>

    <td class="text-center">

        <?php if (!empty($category['category_image']) && file_exists("../assets/images/categories/" . $category['category_image'])) : ?>

            <img
                src="../assets/images/categories/<?= htmlspecialchars($category['category_image']); ?>"
                alt="<?= htmlspecialchars($category['category_name']); ?>"
                class="img-thumbnail"
                style="width:80px;height:80px;object-fit:cover;border-radius:10px;">

        <?php else : ?>

            <div
                class="bg-light border rounded d-flex align-items-center justify-content-center"
                style="width:80px;height:80px;">

                <small class="text-muted">

                    No Image

                </small>

            </div>

        <?php endif; ?>

    </td>

    <td>

        <strong>

            <?= htmlspecialchars($category['category_name']); ?>

        </strong>

    </td>

    <td>

        <?= !empty($category['description'])
            ? htmlspecialchars(substr($category['description'], 0, 80))
            : "-"; ?>

    </td>

    <td>

        <?php if ($category['status'] == "Active") : ?>

            <span class="badge bg-success">

                Active

            </span>

        <?php else : ?>

            <span class="badge bg-danger">

                Inactive

            </span>

        <?php endif; ?>

    </td>

    <td>

        <?= date("d M Y", strtotime($category['created_at'])); ?>

    </td>

    <td class="text-center">

        <a
            href="edit_category.php?id=<?= $category['category_id']; ?>"
            class="btn btn-warning btn-sm">

            <i class="bi bi-pencil-square"></i>

        </a>

        <a
            href="delete_category.php?id=<?= $category['category_id']; ?>"
            class="btn btn-danger btn-sm"
            onclick="return confirm('Are you sure you want to delete this category?');">

            <i class="bi bi-trash"></i>

        </a>

    </td>

</tr>

<?php endforeach; ?>

<?php else : ?>

<tr>

    <td colspan="7" class="text-center py-5">

        <h5 class="text-muted">

            No Categories Found

        </h5>

    </td>

</tr>

<?php endif; ?>
                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- ===========================
     Pagination
=========================== -->

<?php if ($totalPages > 1) : ?>

<div class="d-flex justify-content-end mt-4">

    <nav>

        <ul class="pagination">

            <!-- Previous -->

            <li class="page-item <?= ($page <= 1) ? 'disabled' : ''; ?>">

                <a
                    class="page-link"
                    href="?page=<?= $page - 1; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status); ?>">

                    Previous

                </a>

            </li>

            <!-- Page Numbers -->

            <?php for ($i = 1; $i <= $totalPages; $i++) : ?>

                <li class="page-item <?= ($page == $i) ? 'active' : ''; ?>">

                    <a
                        class="page-link"
                        href="?page=<?= $i; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status); ?>">

                        <?= $i; ?>

                    </a>

                </li>

            <?php endfor; ?>

            <!-- Next -->

            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : ''; ?>">

                <a
                    class="page-link"
                    href="?page=<?= $page + 1; ?>&search=<?= urlencode($search); ?>&status=<?= urlencode($status); ?>">

                    Next

                </a>

            </li>

        </ul>

    </nav>

</div>

<?php endif; ?>

</div>

<?php include "includes/a-footer.php"; ?>