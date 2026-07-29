<?php
session_start();
require_once "../config/db.php";
require_once __DIR__ . "/includes/a-auth.php";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $category_name = trim($_POST['category_name']);
    $description   = trim($_POST['description']);
    $status        = $_POST['status'];

    if (empty($category_name)) {
        $errors[] = "Category name is required.";
    }

    // Check duplicate category
    $check = $conn->prepare("SELECT category_id FROM categories WHERE category_name=? LIMIT 1");
    $check->bind_param("s", $category_name);
    $check->execute();
    $checkResult = $check->get_result();

    if ($checkResult->num_rows > 0) {
        $errors[] = "Category already exists.";
    }

    $imageName = "";

    if (!empty($_FILES['image']['name'])) {

        $allowed = ['jpg','jpeg','png','webp'];

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {

            $errors[] = "Only JPG, JPEG, PNG and WEBP images are allowed.";

        } else {

            if ($_FILES['image']['size'] > 2 * 1024 * 1024) {

                $errors[] = "Image size must be less than 2MB.";

            } else {

                $imageName = time() . "_" . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $_FILES['image']['name']);

                $uploadPath = "../assets/images/categories/" . $imageName;

            }

        }

    }

    if (empty($errors)) {

        if (!empty($imageName)) {
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath);
        }

        $stmt = $conn->prepare("INSERT INTO categories(category_name,description,image,status) VALUES(?,?,?,?)");

        $stmt->bind_param(
            "ssss",
            $category_name,
            $description,
            $imageName,
            $status
        );

        if ($stmt->execute()) {

            header("Location: categories.php?success=added");
            exit;

        } else {

            $errors[] = "Something went wrong while saving.";

        }

    }

}

include "includes/a-header.php";
include "includes/a-sidebar.php";
?>

<div class="content-wrapper">

<div class="container-fluid mt-4">

<div class="row justify-content-center">

<div class="col-lg-8">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h4 class="mb-0">

Add Category

</h4>

</div>

<div class="card-body">

<?php if(!empty($errors)){ ?>

<div class="alert alert-danger">

<ul class="mb-0">

<?php foreach($errors as $error){ ?>

<li><?= $error ?></li>

<?php } ?>

</ul>

</div>

<?php } ?>

<form method="POST" enctype="multipart/form-data">

<div class="mb-3">

<label class="form-label">

Category Name <span class="text-danger">*</span>

</label>

<input
type="text"
name="category_name"
class="form-control"
required
value="<?= isset($category_name) ? htmlspecialchars($category_name) : '' ?>">

</div>

<div class="mb-3">

<label class="form-label">

Description

</label>

<textarea
name="description"
class="form-control"
rows="4"><?= isset($description) ? htmlspecialchars($description) : '' ?></textarea>

</div>

<div class="mb-3">

<label class="form-label">

Category Image

</label>

<input
type="file"
name="image"
class="form-control"
accept=".jpg,.jpeg,.png,.webp">

<small class="text-muted">

Maximum size: 2 MB

</small>

</div>

<div class="mb-3">

<label class="form-label">

Status

</label>

<select
name="status"
class="form-select">

<option value="Active">Active</option>

<option value="Inactive">Inactive</option>

</select>

</div>

<div class="d-flex gap-2">

<button
type="submit"
class="btn btn-success">

<i class="bi bi-check-circle"></i>

Save Category

</button>

<a
href="categories.php"
class="btn btn-secondary">

Cancel

</a>

</div>

</form>

</div>

</div>

</div>

</div>

</div>

</div>

<?php include "includes/a-footer.php"; ?>