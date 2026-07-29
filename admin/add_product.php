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
$discount_percent = "";
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
    $discount_percent = trim($_POST['discount_percent']);
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

    if ($discount_percent != "" && !is_numeric($discount_percent)) {
        if($discount_percent < 0 || $discount_percent > 100){
            $errors[] = "Discount must be between 0 and 100.";

        }
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
                discount_percent,
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
                $discount_percent == "" ? null : $discount_percent,
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
            /* ==========================================
               Upload Product Images
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

                    if (move_uploaded_file(
                        $tmpName,
                        $uploadDir . $newImageName
                    )) {

                        $isPrimary = ($key == 0) ? 1 : 0;

                        $displayOrder = $key + 1;

                        $imgStmt = $pdo->prepare("
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

                        $imgStmt->execute([
                            $product_id,
                            $newImageName,
                            $isPrimary,
                            $displayOrder
                        ]);

                    }

                }

            }

            /* ==========================================
               Commit Transaction
            ========================================== */

            $pdo->commit();

            $_SESSION['success'] =
                "Product added successfully.";

            header("Location: products.php");

            exit();

        } catch (Exception $e) {

            $pdo->rollBack();

            $_SESSION['error'] =
                "Something went wrong. " .
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

            <i class="bi bi-plus-circle"></i>

            Add New Product

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
name="product_name"
id="product_name"
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
name="slug"
id="slug"
class="form-control"
readonly
value="<?= htmlspecialchars($slug); ?>">

</div>

<!-- Category -->

<div class="col-md-6 mb-3">

<label class="form-label">

Category

<span class="text-danger">*</span>

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

<option value="">

Select Food Type

</option>

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

<span class="text-danger">*</span>

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
Discount (%)
</label>

<input
type="number"
name="discount_percent"
class="form-control"
min="0"
max="100"
step="0.01"
value="<?= htmlspecialchars($discount_percent); ?>">

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

<option value="">

Select

</option>

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

<div class="col-md-12 mb-3">

<div class="form-check">

<input
class="form-check-input"
type="checkbox"
name="featured"
id="featured"
value="1"
<?= ($featured==1)?'checked':''; ?>>

<label
class="form-check-label"
for="featured">

Featured Product

</label>

</div>

</div>

<!-- Product Images -->

<div class="col-12 mb-4">

<label class="form-label">

Product Images

</label>

<input
type="file"
name="product_images[]"
id="product_images"
class="form-control"
multiple
accept=".jpg,.jpeg,.png,.webp">

<small class="text-muted">

You can upload multiple images.
The first uploaded image will be the primary image.

</small>

</div>
<!-- Image Preview -->

<div class="row mb-4">

    <div class="col-12">

        <div
            id="preview"
            class="d-flex flex-wrap gap-3">

        </div>

    </div>

</div>

<!-- Buttons -->

<div class="row">

    <div class="col-12">

        <button
            type="submit"
            class="btn btn-success">

            <i class="bi bi-check-circle"></i>

            Save Product

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

const productName =
document.getElementById("product_name");

const slug =
document.getElementById("slug");

productName.addEventListener("keyup",function(){

    slug.value=this.value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g,"-")
    .replace(/^-|-$/g,"");

});

const imageInput =
document.getElementById("product_images");

const preview =
document.getElementById("preview");

imageInput.addEventListener("change",function(){

    preview.innerHTML="";

    Array.from(this.files).forEach(function(file){

        if(!file.type.startsWith("image/")){
            return;
        }

        const reader =
        new FileReader();

        reader.onload=function(e){

            const card=
            document.createElement("div");

            card.className="text-center";

            card.innerHTML=`
                <img
                    src="${e.target.result}"
                    class="img-thumbnail"
                    style="
                        width:120px;
                        height:120px;
                        object-fit:cover;
                    ">

                <div class="small mt-2">

                    ${file.name}

                </div>
            `;

            preview.appendChild(card);

        };

        reader.readAsDataURL(file);

    });

});

document.querySelector("form")
.addEventListener("submit",function(e){

    if(productName.value.trim()==""){

        alert("Please enter Product Name");

        productName.focus();

        e.preventDefault();

        return;

    }

});

</script>

<?php include "includes/a-footer.php"; ?>