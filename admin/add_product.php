<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Add Product";

/* ==========================================
   Load Active Categories
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
   Initialize Variables
========================================== */

$product_name = "";
$category_id = "";
$slug = "";
$short_description = "";
$description = "";
$price = "";
$discount_price = "";
$food_type = "";
$spice_level = "";
$preparation_time = "";
$stock = "";
$featured = 0;
$availability = "Available";
$status = "Active";

$errors = [];

/* ==========================================
   Generate Slug
========================================== */

function generateSlug($text)
{
    $text = strtolower(trim($text));

    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    $text = trim($text, '-');

    return $text;
}

/* ==========================================
   Form Submit
========================================== */

if ($_SERVER['REQUEST_METHOD'] === "POST") {

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

    if ($status == "") {
        $errors[] = "Status is required.";
    }

    /* ==========================================
       Duplicate Slug Check
    ========================================== */

    if (empty($errors)) {

        $checkSlug = $pdo->prepare("
        SELECT COUNT(*)
        FROM products
        WHERE slug = ?
        ");

        $checkSlug->execute([$slug]);

        if ($checkSlug->fetchColumn() > 0) {

            $slug .= "-" . time();

        }

    }

    /* ==========================================
       Insert Product
    ========================================== */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
            INSERT INTO products
            (
                category_id,
                product_name,
                slug,
                description,
                short_description,
                price,
                discount_price,
                food_type,
                spice_level,
                preparation_time,
                stock,
                featured,
                availability,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
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
                $status
            ]);

            $product_id = $pdo->lastInsertId();

            /*
             * Image upload and product_images insertion
             * will be added in Part 2.
             */
