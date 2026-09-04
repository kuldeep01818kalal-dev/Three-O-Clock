<?php

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

/*
|--------------------------------------------------------------------------
| Delete Category
|--------------------------------------------------------------------------
| Existing route:
| delete_category.php?id=CATEGORY_ID
|
| Important:
| A category MUST NOT be deleted when products are assigned to it.
| This prevents the categories -> products ON DELETE CASCADE from
| accidentally deleting products.
|--------------------------------------------------------------------------
*/


/* =========================================================
   1. Validate Category ID
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

$categoryId = (int) $_GET['id'];


/* =========================================================
   2. Variables
========================================================= */

$categoryName = "";
$imagePath = null;


/* =========================================================
   3. Start Transaction
========================================================= */

try {

    $pdo->beginTransaction();


    /* =====================================================
       4. Fetch & Lock Category
    ===================================================== */

    $categoryStmt = $pdo->prepare("
        SELECT
            category_id,
            category_name,
            category_image
        FROM categories
        WHERE category_id = :category_id
        LIMIT 1
        FOR UPDATE
    ");

    $categoryStmt->execute([
        ':category_id' => $categoryId
    ]);

    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);


    if (!$category) {

        throw new Exception("Category not found.");
    }


    $categoryName = $category['category_name'];


    /* =====================================================
       5. Check Assigned Products
    ===================================================== */

    $productCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM products
        WHERE category_id = :category_id
    ");

    $productCheck->execute([
        ':category_id' => $categoryId
    ]);

    $productCount = (int) $productCheck->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | Never delete a category that contains products.
    |--------------------------------------------------------------------------
    */

    if ($productCount > 0) {

        throw new Exception(
            "Cannot delete this category because "
            . $productCount
            . " product(s) are assigned to it. "
            . "Move the products to another category first."
        );
    }


    /* =====================================================
       6. Prepare Category Image Path
    ===================================================== */

    if (!empty($category['category_image'])) {

        $safeImageName = basename(
            $category['category_image']
        );

        if ($safeImageName !== "") {

            $imagePath =
                __DIR__
                . "/../assets/images/categories/"
                . $safeImageName;
        }
    }


    /* =====================================================
       7. Delete Category
    ===================================================== */

    $deleteStmt = $pdo->prepare("
        DELETE FROM categories
        WHERE category_id = :category_id
        LIMIT 1
    ");

    $deleteStmt->execute([
        ':category_id' => $categoryId
    ]);


    /* =====================================================
       8. Verify Database Deletion
    ===================================================== */

    if ($deleteStmt->rowCount() !== 1) {

        throw new Exception(
            "Category could not be deleted."
        );
    }


    /* =====================================================
       9. Commit Transaction
    ===================================================== */

    $pdo->commit();


    /* =====================================================
       10. Delete Physical Image
       Only after successful DB commit
    ===================================================== */

    if (
        !empty($imagePath) &&
        is_file($imagePath)
    ) {

        @unlink($imagePath);
    }


    /* =====================================================
       11. Success Message
    ===================================================== */

    $_SESSION['success'] =
        "Category \""
        . $categoryName
        . "\" deleted successfully.";


} catch (Throwable $e) {


    /* =====================================================
       12. Rollback
    ===================================================== */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    /* =====================================================
       13. User-Friendly Error
    ===================================================== */

    $errorMessage = $e->getMessage();


    /*
    |--------------------------------------------------------------------------
    | Expected business-rule errors can be shown directly.
    |--------------------------------------------------------------------------
    */

    if (
        str_contains(
            $errorMessage,
            "product(s) are assigned"
        )
        ||
        $errorMessage === "Category not found."
    ) {

        $_SESSION['error'] = $errorMessage;

    } else {

        /*
        |--------------------------------------------------------------------------
        | Do not expose database/internal errors to the admin.
        |--------------------------------------------------------------------------
        */

        $_SESSION['error'] =
            "Unable to delete the category. Please try again.";
    }
}


/* =========================================================
   14. Redirect
========================================================= */

header("Location: categories.php");
exit();