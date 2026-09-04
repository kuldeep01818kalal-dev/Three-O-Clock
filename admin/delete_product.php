<?php

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

/*
|--------------------------------------------------------------------------
| Delete Product
|--------------------------------------------------------------------------
| Purpose:
| - Validate product ID
| - Check whether product exists
| - Prevent deletion if product has order history/reviews
| - Safely delete product and related image records
| - Delete physical image files only after successful DB commit
| - Preserve existing route: delete_product.php?id=...
|--------------------------------------------------------------------------
*/

$productId = 0;
$imagesToDelete = [];

try {

    /* =========================================================
       1. Validate Product ID
    ========================================================= */

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

        $_SESSION['error'] = "Invalid product ID.";

        header("Location: products.php");
        exit();
    }

    $productId = (int) $_GET['id'];

    if ($productId <= 0) {

        $_SESSION['error'] = "Invalid product ID.";

        header("Location: products.php");
        exit();
    }


    /* =========================================================
       2. Start Transaction
    ========================================================= */

    $pdo->beginTransaction();


    /* =========================================================
       3. Fetch Product
    ========================================================= */

    $productStmt = $pdo->prepare("
        SELECT
            product_id,
            product_name,
            slug
        FROM products
        WHERE product_id = :product_id
        LIMIT 1
        FOR UPDATE
    ");

    $productStmt->execute([
        ':product_id' => $productId
    ]);

    $product = $productStmt->fetch(PDO::FETCH_ASSOC);


    if (!$product) {

        throw new Exception("Product not found.");
    }


    /* =========================================================
       4. Check Order History
    =========================================================
       order_items has ON DELETE CASCADE.

       Therefore, deleting a product could remove historical
       order item records. We prevent that.
    */

    $orderCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM order_items
        WHERE product_id = :product_id
    ");

    $orderCheck->execute([
        ':product_id' => $productId
    ]);

    $orderItemCount = (int) $orderCheck->fetchColumn();


    if ($orderItemCount > 0) {

        throw new Exception(
            "This product cannot be deleted because it has existing order history. "
            . "Set the product to Inactive or Unavailable instead."
        );
    }


    /* =========================================================
       5. Check Product Reviews
    =========================================================
       reviews also uses ON DELETE CASCADE.

       Keep review history safe.
    */

    $reviewCheck = $pdo->prepare("
        SELECT COUNT(*)
        FROM reviews
        WHERE product_id = :product_id
    ");

    $reviewCheck->execute([
        ':product_id' => $productId
    ]);

    $reviewCount = (int) $reviewCheck->fetchColumn();


    if ($reviewCount > 0) {

        throw new Exception(
            "This product cannot be deleted because it has existing reviews. "
            . "Set the product to Inactive instead."
        );
    }


    /* =========================================================
       6. Get Product Images
    =========================================================
       We only store the filenames here.

       Physical files will be deleted AFTER the database
       transaction successfully commits.
    */

    $imageStmt = $pdo->prepare("
        SELECT image_name
        FROM product_images
        WHERE product_id = :product_id
    ");

    $imageStmt->execute([
        ':product_id' => $productId
    ]);

    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);


    /* =========================================================
       7. Delete Product
    =========================================================
       product_images will also be removed automatically
       because of the database ON DELETE CASCADE rule.

       We do not manually delete image records first.
    */

    $deleteProduct = $pdo->prepare("
        DELETE FROM products
        WHERE product_id = :product_id
    ");

    $deleteProduct->execute([
        ':product_id' => $productId
    ]);


    if ($deleteProduct->rowCount() !== 1) {

        throw new Exception("Unable to delete the product.");
    }


    /* =========================================================
       8. Commit Database Transaction
    ========================================================= */

    $pdo->commit();


    /* =========================================================
       9. Delete Physical Image Files
    =========================================================
       This happens ONLY after successful DB commit.

       basename() prevents unexpected directory traversal.
    */

    foreach ($images as $imageName) {

        if (empty($imageName)) {
            continue;
        }

        $safeImageName = basename($imageName);

        $imagePath = "../assets/images/products/" . $safeImageName;

        if (is_file($imagePath)) {

            @unlink($imagePath);
        }
    }


    /* =========================================================
       10. Success Message
    ========================================================= */

    $_SESSION['success'] =
        "Product \"" . $product['product_name'] . "\" deleted successfully.";


} catch (Throwable $e) {

    /* =========================================================
       Rollback Database Transaction
    ========================================================= */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    /* =========================================================
       Error Message
    ========================================================= */

    $_SESSION['error'] = $e->getMessage();
}


/* =============================================================
   11. Redirect
============================================================= */

header("Location: products.php");
exit();