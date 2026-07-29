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
                        /* ==========================================
               Upload New Images
            ========================================== */

            if (
                isset($_FILES['product_images']) &&
                !empty($_FILES['product_images']['name'][0])
            ) {

                $uploadDir = "../assets/images/products/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $allowed = [
                    "jpg",
                    "jpeg",
                    "png",
                    "webp"
                ];

                $displayStmt = $pdo->prepare("
                    SELECT COALESCE(MAX(display_order),0)
                    FROM product_images
                    WHERE product_id=?
                ");

                $displayStmt->execute([$product_id]);

                $displayOrder = (int)$displayStmt->fetchColumn();

                foreach ($_FILES['product_images']['name'] as $key => $imageName) {

                    if ($_FILES['product_images']['error'][$key] != 0) {
                        continue;
                    }

                    $tmpName = $_FILES['product_images']['tmp_name'][$key];

                    $extension = strtolower(
                        pathinfo($imageName, PATHINFO_EXTENSION)
                    );

                    if (!in_array($extension, $allowed)) {
                        continue;
                    }

                    $newImageName =
                        time() .
                        "_" .
                        uniqid() .
                        "." .
                        $extension;

                    if (
                        move_uploaded_file(
                            $tmpName,
                            $uploadDir . $newImageName
                        )
                    ) {

                        $displayOrder++;

                        $primaryCheck = $pdo->prepare("
                            SELECT COUNT(*)
                            FROM product_images
                            WHERE product_id=?
                            AND is_primary=1
                        ");

                        $primaryCheck->execute([$product_id]);

                        $isPrimary =
                            ($primaryCheck->fetchColumn() == 0)
                            ? 1
                            : 0;

                        $insertImage = $pdo->prepare("
                            INSERT INTO product_images
                            (
                                product_id,
                                image_name,
                                is_primary,
                                display_order
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?
                            )
                        ");

                        $insertImage->execute([
                            $product_id,
                            $newImageName,
                            $isPrimary,
                            $displayOrder
                        ]);

                    }

                }

            }

            /* ==========================================
               Delete Selected Images
            ========================================== */

            if (!empty($_POST['delete_images'])) {

                foreach ($_POST['delete_images'] as $image_id) {

                    $imageStmt = $pdo->prepare("
                        SELECT *
                        FROM product_images
                        WHERE image_id=?
                        AND product_id=?
                    ");

                    $imageStmt->execute([
                        $image_id,
                        $product_id
                    ]);

                    $image = $imageStmt->fetch(PDO::FETCH_ASSOC);

                    if ($image) {

                        $file =
                            "../assets/images/products/" .
                            $image['image_name'];

                        if (file_exists($file)) {
                            unlink($file);
                        }

                        $deleteStmt = $pdo->prepare("
                            DELETE
                            FROM product_images
                            WHERE image_id=?
                        ");

                        $deleteStmt->execute([
                            $image_id
                        ]);

                    }

                }

            }

            /* ==========================================
               Change Primary Image
            ========================================== */

            if (!empty($_POST['primary_image'])) {

                $primaryImage =
                    (int)$_POST['primary_image'];

                $pdo->prepare("
                    UPDATE product_images
                    SET is_primary=0
                    WHERE product_id=?
                ")->execute([$product_id]);

                $pdo->prepare("
                    UPDATE product_images
                    SET is_primary=1
                    WHERE image_id=?
                    AND product_id=?
                ")->execute([
                    $primaryImage,
                    $product_id
                ]);

            }

            /* ==========================================
               Ensure One Primary Image Exists
            ========================================== */

            $primaryCount = $pdo->prepare("
                SELECT COUNT(*)
                FROM product_images
                WHERE product_id=?
                AND is_primary=1
            ");

            $primaryCount->execute([$product_id]);

            if ($primaryCount->fetchColumn() == 0) {

                $firstImage = $pdo->prepare("
                    SELECT image_id
                    FROM product_images
                    WHERE product_id=?
                    ORDER BY display_order ASC
                    LIMIT 1
                ");

                $firstImage->execute([$product_id]);

                $first = $firstImage->fetch(PDO::FETCH_ASSOC);

                if ($first) {

                    $pdo->prepare("
                        UPDATE product_images
                        SET is_primary=1
                        WHERE image_id=?
                    ")->execute([
                        $first['image_id']
                    ]);

                }

            }

            /* ==========================================
               Commit Transaction
            ========================================== */

            $pdo->commit();

            $_SESSION['success'] =
                "Product updated successfully.";

            header("Location: products.php");

            exit();

        } catch (Exception $e) {

            $pdo->rollBack();

            $_SESSION['error'] =
                "Update failed : " .
                $e->getMessage();

        }

    }

}

/* ==========================================
   Includes
========================================== */

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>

<div class="container-fluid mt-4">