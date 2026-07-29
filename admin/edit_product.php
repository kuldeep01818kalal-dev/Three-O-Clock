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
    <!-- ==========================================
     Validation Errors
========================================== -->

<?php if(!empty($errors)): ?>

<div class="alert alert-danger">

    <ul class="mb-0">

        <?php foreach($errors as $error): ?>

            <li><?= htmlspecialchars($error); ?></li>

        <?php endforeach; ?>

    </ul>

</div>

<?php endif; ?>


<div class="card shadow border-0">

    <div class="card-header bg-primary text-white">

        <h4 class="mb-0">

            <i class="bi bi-pencil-square"></i>

            Edit Product

        </h4>

    </div>

    <div class="card-body">

<form method="POST"
      enctype="multipart/form-data">

<div class="row">

<!-- Product Name -->

<div class="col-md-6 mb-3">

<label class="form-label">

Product Name

<span class="text-danger">*</span>

</label>

<input
type="text"
id="product_name"
name="product_name"
class="form-control"
required
value="<?= htmlspecialchars($product_name); ?>">

</div>

<!-- Slug -->

<div class="col-md-6 mb-3">

<label class="form-label">

Slug

</label>

<input
type="text"
id="slug"
class="form-control"
readonly
value="<?= htmlspecialchars($slug); ?>">

</div>

<!-- Category -->

<div class="col-md-6 mb-3">

<label class="form-label">

Category

</label>

<select
name="category_id"
class="form-select"
required>

<option value="">

Select Category

</option>

<?php foreach($categories as $cat): ?>

<option
value="<?= $cat['category_id']; ?>"
<?= ($category_id==$cat['category_id'])?'selected':''; ?>>

<?= htmlspecialchars($cat['category_name']); ?>

</option>

<?php endforeach; ?>

</select>

</div>

<!-- Food Type -->

<div class="col-md-6 mb-3">

<label class="form-label">

Food Type

</label>

<select
name="food_type"
class="form-select">

<option value="Veg"
<?= ($food_type=="Veg")?"selected":""; ?>>

Veg

</option>

<option value="Non-Veg"
<?= ($food_type=="Non-Veg")?"selected":""; ?>>

Non-Veg

</option>

<option value="Egg"
<?= ($food_type=="Egg")?"selected":""; ?>>

Egg

</option>

</select>

</div>

<!-- Short Description -->

<div class="col-12 mb-3">

<label class="form-label">

Short Description

</label>

<textarea
name="short_description"
class="form-control"
rows="2"><?= htmlspecialchars($short_description); ?></textarea>

</div>

<!-- Description -->

<div class="col-12 mb-3">

<label class="form-label">

Description

</label>

<textarea
name="description"
class="form-control"
rows="5"><?= htmlspecialchars($description); ?></textarea>

</div>

<!-- Price -->

<div class="col-md-3 mb-3">

<label class="form-label">

Price

</label>

<input
type="number"
step="0.01"
name="price"
class="form-control"
required
value="<?= htmlspecialchars($price); ?>">

</div>

<!-- Discount Price -->

<div class="col-md-3 mb-3">

<label class="form-label">

Discount Price

</label>

<input
type="number"
step="0.01"
name="discount_price"
class="form-control"
value="<?= htmlspecialchars($discount_price); ?>">

</div>

<!-- Stock -->

<div class="col-md-3 mb-3">

<label class="form-label">

Stock

</label>

<input
type="number"
name="stock"
class="form-control"
value="<?= htmlspecialchars($stock); ?>">

</div>

<!-- Preparation Time -->

<div class="col-md-3 mb-3">

<label class="form-label">

Preparation Time (Min)

</label>

<input
type="number"
name="preparation_time"
class="form-control"
value="<?= htmlspecialchars($preparation_time); ?>">

</div>

<!-- Spice Level -->

<div class="col-md-4 mb-3">

<label class="form-label">

Spice Level

</label>

<select
name="spice_level"
class="form-select">

<option value="">Select</option>

<option value="Mild"
<?= ($spice_level=="Mild")?"selected":""; ?>>

Mild

</option>

<option value="Medium"
<?= ($spice_level=="Medium")?"selected":""; ?>>

Medium

</option>

<option value="Hot"
<?= ($spice_level=="Hot")?"selected":""; ?>>

Hot

</option>

</select>

</div>

<!-- Availability -->

<div class="col-md-4 mb-3">

<label class="form-label">

Availability

</label>

<select
name="availability"
class="form-select">

<option value="Available"
<?= ($availability=="Available")?"selected":""; ?>>

Available

</option>

<option value="Unavailable"
<?= ($availability=="Unavailable")?"selected":""; ?>>

Unavailable

</option>

</select>

</div>

<!-- Status -->

<div class="col-md-4 mb-3">

<label class="form-label">

Status

</label>

<select
name="status"
class="form-select">

<option value="Active"
<?= ($status=="Active")?"selected":""; ?>>

Active

</option>

<option value="Inactive"
<?= ($status=="Inactive")?"selected":""; ?>>

Inactive

</option>

</select>

</div>

<!-- Featured -->

<div class="col-12 mb-4">

<div class="form-check">

<input
type="checkbox"
name="featured"
id="featured"
class="form-check-input"
value="1"
<?= ($featured==1)?'checked':''; ?>>

<label
class="form-check-label"
for="featured">

Featured Product

</label>

</div>

</div>

<hr>

<h5 class="mb-3">

Existing Images

</h5>

<div class="row">

<?php if(count($productImages)>0): ?>

<?php foreach($productImages as $img): ?>

<div class="col-lg-3 col-md-4 col-sm-6 mb-4">

<div class="card">

<img
src="../assets/images/products/<?= htmlspecialchars($img['image_name']); ?>"
class="card-img-top"
style="height:180px;object-fit:cover;">

<div class="card-body">

<div class="form-check mb-2">

<input
class="form-check-input"
type="radio"
name="primary_image"
value="<?= $img['image_id']; ?>"
<?= ($img['is_primary']==1)?'checked':''; ?>>

<label class="form-check-label">

Primary Image

</label>

</div>

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="delete_images[]"
value="<?= $img['image_id']; ?>">

<label class="form-check-label text-danger">

Delete Image

</label>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="col-12">

<div class="alert alert-warning">

No images uploaded.

</div>

</div>

<?php endif; ?>

</div>

<hr>

<div class="mb-4">

<label class="form-label">

Upload New Images

</label>

<input
type="file"
id="product_images"
name="product_images[]"
class="form-control"
multiple
accept=".jpg,.jpeg,.png,.webp">

<small class="text-muted">

Select one or more new images to add.

</small>

</div>

<div id="preview"
class="row mb-4">

</div>
<!-- ==========================================
     Action Buttons
========================================== -->

<div class="row">

    <div class="col-12">

        <button
            type="submit"
            class="btn btn-success">

            <i class="bi bi-check-circle"></i>

            Update Product

        </button>

        <button
            type="reset"
            class="btn btn-warning">

            <i class="bi bi-arrow-clockwise"></i>

            Reset

        </button>

        <a
            href="products.php"
            class="btn btn-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

</div>

</form>

    </div>

</div>

</div>

<!-- ==========================================
     JavaScript
========================================== -->

<script>

// ======================================
// Auto Slug Generator
// ======================================

const productName =
document.getElementById("product_name");

const slug =
document.getElementById("slug");

productName.addEventListener("keyup", function () {

    slug.value = this.value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "");

});

// ======================================
// Image Preview
// ======================================

const imageInput =
document.getElementById("product_images");

const preview =
document.getElementById("preview");

imageInput.addEventListener("change", function () {

    preview.innerHTML = "";

    Array.from(this.files).forEach(function (file) {

        if (!file.type.startsWith("image/")) {

            return;

        }

        const reader = new FileReader();

        reader.onload = function (e) {

            const col = document.createElement("div");

            col.className = "col-md-3 mb-3";

            col.innerHTML = `
                <div class="card">

                    <img
                        src="${e.target.result}"
                        class="card-img-top"
                        style="
                            height:180px;
                            object-fit:cover;
                        ">

                    <div class="card-body text-center">

                        <small>${file.name}</small>

                    </div>

                </div>
            `;

            preview.appendChild(col);

        };

        reader.readAsDataURL(file);

    });

});

// ======================================
// Form Validation
// ======================================

document.querySelector("form")
.addEventListener("submit", function(e){

    if(productName.value.trim() == ""){

        alert("Please enter Product Name.");

        productName.focus();

        e.preventDefault();

        return;

    }

    const price =
    document.querySelector("[name='price']");

    if(price.value == ""){

        alert("Please enter Product Price.");

        price.focus();

        e.preventDefault();

        return;

    }

});

// ======================================
// Delete Confirmation
// ======================================

document.querySelector("form")
.addEventListener("submit", function(){

    const checked =
    document.querySelectorAll(
        "input[name='delete_images[]']:checked"
    );

    if(checked.length > 0){

        return confirm(
            "Selected images will be permanently deleted. Continue?"
        );

    }

});

</script>

<?php include "includes/a-footer.php"; ?>