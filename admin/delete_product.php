<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

/* ==========================================
   Validate Product ID
========================================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    $_SESSION['error'] = "Invalid Product ID.";

    header("Location: a-products.php");
    exit();

}

$product_id = (int)$_GET['id'];

try {

    $pdo->beginTransaction();

    /* ==========================================
       Fetch Product Images
    ========================================== */

    $imageStmt = $pdo->prepare("
        SELECT image_name
        FROM product_images
        WHERE product_id = ?
    ");

    $imageStmt->execute([$product_id]);

    $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

    /* ==========================================
       Delete Image Files
    ========================================== */

    foreach ($images as $image) {

        $file = "../assets/images/products/" . $image['image_name'];

        if (file_exists($file)) {

            unlink($file);

        }

    }

    /* ==========================================
       Delete Image Records
    ========================================== */

    $deleteImages = $pdo->prepare("
        DELETE FROM product_images
        WHERE product_id = ?
    ");

    $deleteImages->execute([$product_id]);

    /* ==========================================
       Delete Product
    ========================================== */

    $deleteProduct = $pdo->prepare("
        DELETE FROM products
        WHERE product_id = ?
    ");

    $deleteProduct->execute([$product_id]);

    if ($deleteProduct->rowCount() == 0) {

        throw new Exception("Product not found.");

    }

    /* ==========================================
       Commit
    ========================================== */

    $pdo->commit();

    $_SESSION['success'] = "Product deleted successfully.";

} catch (Exception $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    $_SESSION['error'] = $e->getMessage();

}

header("Location: a-products.php");
exit();