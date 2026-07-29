<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Add Category";

$errors = [];
$success = "";

$category_name = "";
$description = "";
$status = "Active";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $category_name = trim($_POST['category_name']);
    $description   = trim($_POST['description']);
    $status        = $_POST['status'];

    /* ===========================
       Validation
    ============================ */

    if (empty($category_name)) {
        $errors[] = "Category name is required.";
    }

    if (strlen($category_name) > 100) {
        $errors[] = "Category name must be less than 100 characters.";
    }

    /* ===========================
       Duplicate Category Check
    ============================ */

    $check = $pdo->prepare("
        SELECT category_id
        FROM categories
        WHERE category_name = :category_name
        LIMIT 1
    ");

    $check->execute([
        ':category_name' => $category_name
    ]);

    if ($check->fetch()) {
        $errors[] = "Category already exists.";
    }

    /* ===========================
       Image Upload
    ============================ */

    $imageName = "";

    if (!empty($_FILES['category_image']['name'])) {

        $allowed = [
            "jpg",
            "jpeg",
            "png",
            "webp"
        ];

        $extension = strtolower(
            pathinfo(
                $_FILES['category_image']['name'],
                PATHINFO_EXTENSION
            )
        );

        if (!in_array($extension, $allowed)) {

            $errors[] =
                "Only JPG, JPEG, PNG and WEBP files are allowed.";

        } else {

            if ($_FILES['category_image']['size'] > 2 * 1024 * 1024) {

                $errors[] =
                    "Image must be smaller than 2MB.";

            } else {

                $imageName =
                    time() . "_" .
                    preg_replace(
                        "/[^A-Za-z0-9._-]/",
                        "",
                        $_FILES['category_image']['name']
                    );

                $uploadDir =
                    "../assets/images/categories/";

                if (!is_dir($uploadDir)) {

                    mkdir($uploadDir, 0777, true);

                }

            }

        }

    }

    /* ===========================
       Save Category
    ============================ */

    if (empty($errors)) {

        if (!empty($imageName)) {

            move_uploaded_file(

                $_FILES['category_image']['tmp_name'],

                $uploadDir . $imageName

            );

        }

        $stmt = $pdo->prepare("

            INSERT INTO categories

            (
                category_name,
                category_image,
                description,
                status
            )

            VALUES

            (
                :category_name,
                :category_image,
                :description,
                :status
            )

        ");

        $saved = $stmt->execute([

            ':category_name'  => $category_name,
            ':category_image' => $imageName,
            ':description'    => $description,
            ':status'         => $status

        ]);

        if ($saved) {

            $_SESSION['success'] =
                "Category added successfully.";

            header("Location: categories.php");

            exit();

        } else {

            $errors[] =
                "Unable to save category.";

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

<div class="card shadow border-0">

<div class="card-header bg-primary text-white">

<h4 class="mb-0">

<i class="bi bi-grid-fill"></i>

Add New Category

</h4>

</div>

<div class="card-body">
    <?php if (!empty($errors)) { ?>

<div class="alert alert-danger">

    <ul class="mb-0">

        <?php foreach ($errors as $error) { ?>

            <li><?= htmlspecialchars($error); ?></li>

        <?php } ?>

    </ul>

</div>

<?php } ?>

<form method="POST" enctype="multipart/form-data">

<div class="row">

    <!-- Category Name -->

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Category Name
            <span class="text-danger">*</span>

        </label>

        <input
            type="text"
            name="category_name"
            class="form-control"
            maxlength="100"
            value="<?= htmlspecialchars($category_name); ?>"
            placeholder="Enter category name"
            required>

    </div>

    <!-- Status -->

    <div class="col-md-6 mb-3">

        <label class="form-label">

            Status

        </label>

        <select
            name="status"
            class="form-select">

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

    <!-- Description -->

    <div class="col-12 mb-3">

        <label class="form-label">

            Description

        </label>

        <textarea
            name="description"
            rows="4"
            class="form-control"
            placeholder="Category description"><?= htmlspecialchars($description); ?></textarea>

    </div>

    <!-- Image -->

    <div class="col-md-12 mb-4">

        <label class="form-label">

            Category Image

        </label>

        <input
            type="file"
            name="category_image"
            class="form-control"
            accept=".jpg,.jpeg,.png,.webp">

        <small class="text-muted">

            Allowed:
            JPG, JPEG, PNG, WEBP

            <br>

            Maximum Size:
            2 MB

        </small>

    </div>

</div>

<div class="text-end">

    <a
        href="categories.php"
        class="btn btn-secondary">

        <i class="bi bi-arrow-left-circle"></i>

        Back

    </a>

    <button
        type="reset"
        class="btn btn-warning">

        <i class="bi bi-arrow-clockwise"></i>

        Reset

    </button>

    <button
        type="submit"
        class="btn btn-success">

        <i class="bi bi-check-circle"></i>

        Save Category

    </button>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

<?php include "includes/a-footer.php"; ?>