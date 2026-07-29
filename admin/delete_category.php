<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

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
   Check Products
========================================== */

$check = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE category_id = :category_id
");

$check->execute([
    ':category_id' => $category_id
]);

$productCount = $check->fetchColumn();

if ($productCount > 0) {

    $_SESSION['error'] =
        "Cannot delete this category because products are assigned to it.";

    header("Location: categories.php");
    exit();

}

/* ==========================================
   Delete Image
========================================== */

$imagePath = "../assets/images/categories/" . $category['category_image'];

if (
    !empty($category['category_image']) &&
    file_exists($imagePath)
) {

    unlink($imagePath);

}

/* ==========================================
   Delete Category
========================================== */

$delete = $pdo->prepare("
    DELETE
    FROM categories
    WHERE category_id = :category_id
");

if (
    $delete->execute([
        ':category_id' => $category_id
    ])
) {

    $_SESSION['success'] =
        "Category deleted successfully.";

} else {

    $_SESSION['error'] =
        "Unable to delete category.";

}

header("Location: categories.php");
exit();