<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Edit Product";

/* ==========================================
   Validate Product ID
========================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    $_SESSION['error'] = "Invalid Product ID.";

    header("Location: products.php");
    exit();

}

$product_id = (int) $_GET['id'];

/* ==========================================
   Fetch Product Details
========================================== */

$productStmt = $pdo->prepare("
SELECT *
FROM products
WHERE product_id = ?
LIMIT 1
");

$productStmt->execute([$product_id]);

$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {

    $_SESSION['error'] = "Product not found.";

    header("Location: products.php");
    exit();

}

/* ==========================================
   Fetch Categories
========================================== */

$categoryStmt = $pdo->query("
SELECT
category_id,
category_name
FROM categories
WHERE status='Active'
ORDER BY category_name ASC
");

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   Fetch Product Images
========================================== */

$imageStmt = $pdo->prepare("
SELECT *
FROM product_images
WHERE product_id=?
ORDER BY
is_primary DESC,
display_order ASC
");

$imageStmt->execute([$product_id]);

$productImages = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================
   Initialize Variables
========================================== */

$product_name = $product['product_name'];
$category_id = $product['category_id'];
$slug = $product['slug'];
$short_description = $product['short_description'];
$description = $product['description'];
$price = $product['price'];
$discount_price = $product['discount_price'];
$food_type = $product['food_type'];
$spice_level = $product['spice_level'];
$preparation_time = $product['preparation_time'];
$stock = $product['stock'];
$featured = $product['featured'];
$availability = $product['availability'];
$status = $product['status'];

$errors = [];

/* ==========================================
   Generate Slug
========================================== */

function generateSlug($text)
{

    $text = strtolower(trim($text));

    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');

}

/* ==========================================
   Update Product
========================================== */

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $product_name = trim($_POST['product_name']);
    $category_id = trim($_POST['category_id']);
    $short_description = trim($_POST['short_description']);
    $description = trim($_POST['description']);
    $price = trim($_POST['price']);
    $discount_price = trim($_POST['discount_price']);
    $food_type = trim($_POST['food_type']);
    $spice_level = trim($_POST['spice_level']);
    $preparation_time = trim($_POST['preparation_time']);
    $stock = trim($_POST['stock']);

    $featured = isset($_POST['featured']) ? 1 : 0;

    $availability = trim($_POST['availability']);

    $status = trim($_POST['status']);

    $slug = generateSlug($product_name);

    /* ==========================================
       Validation
    ========================================== */

    if ($product_name == "") {

        $errors[] = "Product Name is required.";

    }

    if ($category_id == "") {

        $errors[] = "Category is required.";

    }

    if ($price == "") {

        $errors[] = "Price is required.";

    }

    if (!is_numeric($price)) {

        $errors[] = "Price must be numeric.";

    }

    if ($discount_price != "" && !is_numeric($discount_price)) {

        $errors[] = "Discount Price must be numeric.";

    }

    if ($stock == "") {

        $errors[] = "Stock is required.";

    }

    if (!is_numeric($stock)) {

        $errors[] = "Stock must be numeric.";

    }

    if ($food_type == "") {

        $errors[] = "Food Type is required.";

    }

    /* ==========================================
       Duplicate Slug Check
    ========================================== */

    if (empty($errors)) {

        $checkSlug = $pdo->prepare("
        SELECT COUNT(*)
        FROM products
        WHERE slug=?
        AND product_id!=?
        ");

        $checkSlug->execute([
            $slug,
            $product_id
        ]);

        if ($checkSlug->fetchColumn() > 0) {

            $slug .= "-" . time();

        }

    }

    /* ==========================================
       Update Database
    ========================================== */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
            UPDATE products
            SET
                category_id=?,
                product_name=?,
                slug=?,
                description=?,
                short_description=?,
                price=?,
                discount_price=?,
                food_type=?,
                spice_level=?,
                preparation_time=?,
                stock=?,
                featured=?,
                availability=?,
                status=?
            WHERE
                product_id=?
            ");

            $stmt->execute([
                $category_id,
                $product_name,
                $slug,
                $description,
                $short_description,
                $price,
                $discount_price == "" ? null : $discount_price,
                $food_type,
                $spice_level,
                $preparation_time,
                $stock,
                $featured,
                $availability,
                $status,
                $product_id
            ]);

            /*
             * Image upload, image deletion,
             * and primary image update
             * will be added in Part 2.
             */