<?php
session_start();
require_once "../config/db.php";
require_once __DIR__ . "/includes/a-auth.php";

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

if ($search != "") {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_name LIKE CONCAT('%', ?, '%') ORDER BY category_id DESC");
    $stmt->bind_param("s", $search);
} else {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY category_id DESC");
}

$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "includes/a-header.php";
include "includes/a-sidebar.php";
?>

<div class="content-wrapper">

    <div class="container-fluid mt-4">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>
                <h2 class="fw-bold mb-0">Category Management</h2>
                <small class="text-muted">
                    Manage all food & beverage categories
                </small>
            </div>

            <a href="add_category.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i>
                Add Category
            </a>

        </div>

        <?php if(isset($_GET['success'])){ ?>

            <div class="alert alert-success alert-dismissible fade show">

                <?php

                switch($_GET['success']){

                    case 'added':
                        echo "Category added successfully.";
                        break;

                    case 'updated':
                        echo "Category updated successfully.";
                        break;

                    case 'deleted':
                        echo "Category deleted successfully.";
                        break;

                }

                ?>

                <button class="btn-close" data-bs-dismiss="alert"></button>

            </div>

        <?php } ?>

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <form method="GET" class="row mb-3">

                    <div class="col-md-4">

                        <input
                            type="text"
                            class="form-control"
                            name="search"
                            placeholder="Search category..."
                            value="<?= htmlspecialchars($search) ?>">

                    </div>

                    <div class="col-md-2">

                        <button class="btn btn-dark w-100">

                            Search

                        </button>

                    </div>

                </form>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Image</th>

                            <th>Name</th>

                            <th>Description</th>

                            <th>Status</th>

                            <th>Created</th>

                            <th width="140">Action</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php

                        if(count($result) > 0){

                            while($row=$result->fetch_assoc()){

                        ?>

                        <tr>

                            <td>

                                <?= $row['category_id']; ?>

                            </td>

                            <td>

                                <?php

                                $image="../assets/images/categories/default.png";

                                if(!empty($row['image']) &&
                                   file_exists("../assets/images/categories/".$row['image'])){

                                    $image="../assets/images/categories/".$row['image'];

                                }

                                ?>

                                <img
                                    src="<?= $image; ?>"
                                    width="60"
                                    height="60"
                                    style="object-fit:cover;border-radius:10px;">

                            </td>

                            <td>

                                <strong>

                                    <?= htmlspecialchars($row['category_name']); ?>

                                </strong>

                            </td>

                            <td>

                                <?= htmlspecialchars($row['description']); ?>

                            </td>

                            <td>

                                <?php

                                if($row['status']=="Active"){

                                    echo '<span class="badge bg-success">Active</span>';

                                }else{

                                    echo '<span class="badge bg-danger">Inactive</span>';

                                }

                                ?>

                            </td>

                            <td>

                                <?= date("d M Y",strtotime($row['created_at'])); ?>

                            </td>

                            <td>

                                <a
                                    href="edit_category.php?id=<?= $row['category_id']; ?>"
                                    class="btn btn-warning btn-sm">

                                    <i class="bi bi-pencil"></i>

                                </a>

                                <a
                                    href="delete_category.php?id=<?= $row['category_id']; ?>"
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete this category?');">

                                    <i class="bi bi-trash"></i>

                                </a>

                            </td>

                        </tr>

                        <?php

                            }

                        }else{

                        ?>

                        <tr>

                            <td colspan="7" class="text-center">

                                No categories found.

                            </td>

                        </tr>

                        <?php } ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include "includes/a-footer.php"; ?>