<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Edit Category";

/* ==========================================
   Validate Category ID
========================================== */

if (
    !isset($_GET['id']) ||
    !is_numeric($_GET['id'])
) {

    $_SESSION['error'] = "Invalid category.";

    header("Location: categories.php");
    exit();

}

$category_id = (int) $_GET['id'];

/* ==========================================
   Fetch Category
========================================== */

$stmt = $pdo->prepare("
    SELECT *
    FROM categories
    WHERE category_id = :category_id
    LIMIT 1
");

$stmt->execute([
    ':category_id' => $category_id
]);

$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {

    $_SESSION['error'] = "Category not found.";

    header("Location: categories.php");
    exit();

}

/* ==========================================
   Existing Values
========================================== */

$category_name = $category['category_name'];
$description   = $category['description'];
$status        = $category['status'];
$oldImage      = $category['category_image'];

$errors = [];

/* ==========================================
   Update Category
========================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $category_name = trim($_POST['category_name']);
    $description   = trim($_POST['description']);
    $status        = trim($_POST['status']);

    /* ------------------------------
       Validation
    ------------------------------ */

    if (empty($category_name)) {

        $errors[] = "Category name is required.";

    }

    if (strlen($category_name) > 100) {

        $errors[] = "Category name must be less than 100 characters.";

    }

    /* ------------------------------
       Duplicate Category Check
    ------------------------------ */

    $check = $pdo->prepare("
        SELECT category_id
        FROM categories
        WHERE category_name = :category_name
        AND category_id != :category_id
        LIMIT 1
    ");

    $check->execute([
        ':category_name' => $category_name,
        ':category_id'   => $category_id
    ]);

    if ($check->fetch()) {

        $errors[] = "Category name already exists.";

    }

    /* ------------------------------
       Image Upload
    ------------------------------ */

    $imageName = $oldImage;

    if (
        isset($_FILES['category_image']) &&
        $_FILES['category_image']['error'] == 0
    ) {

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        $extension = strtolower(
            pathinfo(
                $_FILES['category_image']['name'],
                PATHINFO_EXTENSION
            )
        );

        if (!in_array($extension, $allowed)) {

            $errors[] = "Only JPG, JPEG, PNG and WEBP images are allowed.";

        } elseif ($_FILES['category_image']['size'] > (2 * 1024 * 1024)) {

            $errors[] = "Image size must be less than 2 MB.";

        } else {

            $imageName = time() . "_" .
                preg_replace(
                    "/[^A-Za-z0-9._-]/",
                    "",
                    $_FILES['category_image']['name']
                );

            $uploadDir = "../assets/images/categories/";

            if (!is_dir($uploadDir)) {

                mkdir($uploadDir, 0777, true);

            }

            if (
                move_uploaded_file(
                    $_FILES['category_image']['tmp_name'],
                    $uploadDir . $imageName
                )
            ) {

                if (
                    !empty($oldImage) &&
                    file_exists($uploadDir . $oldImage)
                ) {

                    unlink($uploadDir . $oldImage);

                }

            } else {

                $errors[] = "Failed to upload image.";

            }

        }

    }

    /* ------------------------------
       Update Category
    ------------------------------ */

    if (empty($errors)) {

        $update = $pdo->prepare("
            UPDATE categories
            SET
                category_name = :category_name,
                category_image = :category_image,
                description = :description,
                status = :status,
                updated_at = NOW()
            WHERE category_id = :category_id
        ");

        $updated = $update->execute([

            ':category_name'  => $category_name,
            ':category_image' => $imageName,
            ':description'    => $description,
            ':status'         => $status,
            ':category_id'    => $category_id

        ]);

        if ($updated) {

            $_SESSION['success'] =
                "Category updated successfully.";

            header("Location: categories.php");

            exit();

        } else {

            $errors[] =
                "Unable to update category.";

        }

    }

}
include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>

<div class="container-fluid mt-4">
    <div class="row">
    <div class="col-lg-10 mx-auto">

        <?php if (!empty($errors)) : ?>

            <div class="alert alert-danger">
                <ul class="mb-0">

                    <?php foreach ($errors as $error) : ?>

                        <li><?= htmlspecialchars($error); ?></li>

                    <?php endforeach; ?>

                </ul>
            </div>

        <?php endif; ?>

        <div class="card shadow border-0">

            <div class="card-header bg-primary text-white">

                <h4 class="mb-0">

                    <i class="bi bi-pencil-square"></i>

                    Edit Category

                </h4>

            </div>

            <div class="card-body">

                <form method="POST" enctype="multipart/form-data">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Category Name

                            </label>

                            <input
                                type="text"
                                name="category_name"
                                class="form-control"
                                value="<?= htmlspecialchars($category_name); ?>"
                                maxlength="100"
                                required>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Status

                            </label>

                            <select
                                name="status"
                                class="form-select">

                                <option value="Active"
                                    <?= ($status=="Active") ? "selected" : ""; ?>>

                                    Active

                                </option>

                                <option value="Inactive"
                                    <?= ($status=="Inactive") ? "selected" : ""; ?>>

                                    Inactive

                                </option>

                            </select>

                        </div>

                        <div class="col-12 mb-3">

                            <label class="form-label">

                                Description

                            </label>

                            <textarea
                                name="description"
                                rows="4"
                                class="form-control"><?= htmlspecialchars($description); ?></textarea>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Current Image

                            </label>

                            <br>

                            <?php if (!empty($oldImage) && file_exists("../assets/images/categories/" . $oldImage)) : ?>

                                <img
                                    src="../assets/images/categories/<?= htmlspecialchars($oldImage); ?>"
                                    class="img-thumbnail"
                                    style="width:150px;height:150px;object-fit:cover;">

                            <?php else : ?>

                                <div
                                    class="border rounded d-flex justify-content-center align-items-center bg-light"
                                    style="width:150px;height:150px;">

                                    <span class="text-muted">

                                        No Image

                                    </span>

                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Change Image

                            </label>

                            <input
                                type="file"
                                name="category_image"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.webp">

                            <small class="text-muted">

                                Leave empty to keep current image.

                            </small>

                        </div>

                    </div>

                    <div class="text-end mt-4">

                        <a
                            href="categories.php"
                            class="btn btn-secondary">

                            <i class="bi bi-arrow-left-circle"></i>

                            Cancel

                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="bi bi-save"></i>

                            Update Category

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>
</div>

</div>

<?php include "includes/a-footer.php"; ?>