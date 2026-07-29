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
}