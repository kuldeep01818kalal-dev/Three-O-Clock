<?php

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

/*
|--------------------------------------------------------------------------
| Delete Category
|--------------------------------------------------------------------------
| This file keeps the existing route:
| delete_category.php?id=CATEGORY_ID
|
| Important:
| A category cannot be deleted if products are assigned to it.
|--------------------------------------------------------------------------
*/

/* =========================================================
   Validate Category ID
========================================================= */

if (
    !isset($_GET['id']) ||
    !ctype_digit((string) $_GET['id']) ||
    (int) $_GET['id'] <= 0
) {

    $_SESSION['error'] = "Invalid category.";

    header("Location: categories.php");
    exit();
}

$category_id = (int) $_GET['id'];


/* =========================================================
   Fetch Category
========================================================= */

try {

    $stmt = $pdo->prepare("
        SELECT
            category_id,
            category_name,
            category_image
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


    /* =====================================================
       Check Assigned Products
    ===================================================== */

    $checkProducts = $pdo->prepare("
        SELECT COUNT(*)
        FROM products
        WHERE category_id = :category_id
    ");

    $checkProducts->execute([
        ':category_id' => $category_id
    ]);

    $productCount = (int) $checkProducts->fetchColumn();


    if ($productCount > 0) {

        $_SESSION['error'] =
            "Cannot delete '{$category['category_name']}'. "
            . $productCount
            . " product(s) are assigned to this category. "
            . "Please move or delete those products first.";

        header("Location: categories.php");
        exit();
    }


    /* =====================================================
       Store Image Path
       Delete it only after successful DB deletion
    ===================================================== */

    $imagePath = null;

    if (!empty($category['category_image'])) {

        $imageFileName = basename($category['category_image']);

        $imagePath = __DIR__
            . "/../assets/images/categories/"
            . $imageFileName;
    }


    /* =====================================================
       Start Transaction
    ===================================================== */

    $pdo->beginTransaction();


    /* =====================================================
       Delete Category
    ===================================================== */

    $delete = $pdo->prepare("
        DELETE FROM categories
        WHERE category_id = :category_id
        LIMIT 1
    ");

    $delete->execute([
        ':category_id' => $category_id
    ]);


    /* =====================================================
       Verify Deletion
    ===================================================== */

    if ($delete->rowCount() !== 1) {

        throw new Exception("Category could not be deleted.");
    }


    /* =====================================================
       Commit Transaction
    ===================================================== */

    $pdo->commit();


    /* =====================================================
       Delete Category Image
       Only after successful database deletion
    ===================================================== */

    if (
        !empty($imagePath) &&
        is_file($imagePath)
    ) {

        @unlink($imagePath);
    }


    /* =====================================================
       Success Message
    ===================================================== */

    $_SESSION['success'] =
        "Category '{$category['category_name']}' deleted successfully.";

} catch (Throwable $e) {

    /* =====================================================
       Rollback
    ===================================================== */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    /* =====================================================
       Error Message
    ===================================================== */

    $_SESSION['error'] =
        "Unable to delete the category. Please try again.";
}


/* =========================================================
   Redirect
========================================================= */

header("Location: categories.php");
exit();