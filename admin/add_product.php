<?php

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Add Product";

$errors = [];

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| Generate Slug
|--------------------------------------------------------------------------
*/

function generateSlug($text)
{
    $text = strtolower(trim($text));

    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');
}


/*
|--------------------------------------------------------------------------
| Initial Values
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Load Active Categories
|--------------------------------------------------------------------------
*/

try {

    $categoryStmt = $pdo->query("
        SELECT
            category_id,
            category_name
        FROM categories
        WHERE status = 'Active'
        ORDER BY category_name ASC
    ");

    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $categories = [];

    $errors[] = "Unable to load categories.";
}


/*
|--------------------------------------------------------------------------
| Form Submit
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get Form Values
    |--------------------------------------------------------------------------
    */

    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $discount_price = trim($_POST['discount_price'] ?? '');
    $food_type = trim($_POST['food_type'] ?? '');
    $spice_level = trim($_POST['spice_level'] ?? '');
    $preparation_time = trim($_POST['preparation_time'] ?? '');
    $stock = trim($_POST['stock'] ?? '');

    $featured = isset($_POST['featured']) ? 1 : 0;

    $availability = trim($_POST['availability'] ?? 'Available');
    $status = trim($_POST['status'] ?? 'Active');


    /*
    |--------------------------------------------------------------------------
    | Generate Slug
    |--------------------------------------------------------------------------
    */

    $slug = generateSlug($product_name);


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($product_name === '') {

        $errors[] = "Product name is required.";

    } elseif (mb_strlen($product_name) > 255) {

        $errors[] = "Product name cannot exceed 255 characters.";
    }


    if ($category_id === '') {

        $errors[] = "Category is required.";

    } elseif (
        !ctype_digit($category_id) ||
        (int)$category_id <= 0
    ) {

        $errors[] = "Invalid category.";
    }


    /*
    |--------------------------------------------------------------------------
    | Price Validation
    |--------------------------------------------------------------------------
    */

    if ($price === '') {

        $errors[] = "Price is required.";

    } elseif (
        !is_numeric($price) ||
        (float)$price < 0
    ) {

        $errors[] = "Price must be a valid positive number.";
    }


    /*
    |--------------------------------------------------------------------------
    | Discount Price Validation
    |--------------------------------------------------------------------------
    */

    if ($discount_price !== '') {

        if (
            !is_numeric($discount_price) ||
            (float)$discount_price < 0
        ) {

            $errors[] =
                "Discount price must be a valid positive number.";

        } elseif (
            $price !== '' &&
            is_numeric($price) &&
            (float)$discount_price > (float)$price
        ) {

            $errors[] =
                "Discount price cannot be greater than the product price.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Food Type Validation
    |--------------------------------------------------------------------------
    */

    $allowedFoodTypes = [
        'Veg',
        'Non-Veg',
        'Egg'
    ];

    if (!in_array($food_type, $allowedFoodTypes, true)) {

        $errors[] = "Please select a valid food type.";
    }


    /*
    |--------------------------------------------------------------------------
    | Spice Level Validation
    |--------------------------------------------------------------------------
    */

    $allowedSpiceLevels = [
        'Mild',
        'Medium',
        'Hot'
    ];

    if ($spice_level !== '' &&
        !in_array($spice_level, $allowedSpiceLevels, true)
    ) {

        $errors[] = "Invalid spice level.";
    }


    /*
    |--------------------------------------------------------------------------
    | Preparation Time Validation
    |--------------------------------------------------------------------------
    */

    if ($preparation_time !== '') {

        if (
            !ctype_digit($preparation_time) ||
            (int)$preparation_time < 0
        ) {

            $errors[] =
                "Preparation time must be a valid number.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Stock Validation
    |--------------------------------------------------------------------------
    */

    if ($stock === '') {

        $errors[] = "Stock is required.";

    } elseif (
        !ctype_digit($stock) ||
        (int)$stock < 0
    ) {

        $errors[] =
            "Stock must be a valid non-negative number.";
    }


    /*
    |--------------------------------------------------------------------------
    | Availability Validation
    |--------------------------------------------------------------------------
    */

    $allowedAvailability = [
        'Available',
        'Unavailable'
    ];

    if (
        !in_array(
            $availability,
            $allowedAvailability,
            true
        )
    ) {

        $errors[] = "Invalid availability status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Status Validation
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'Active',
        'Inactive'
    ];

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $errors[] = "Invalid product status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Slug Validation
    |--------------------------------------------------------------------------
    */

    if ($slug === '') {

        $errors[] =
            "A valid product name is required to generate the slug.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Category Exists
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $category_id !== ''
    ) {

        $categoryCheck = $pdo->prepare("
            SELECT category_id
            FROM categories
            WHERE category_id = :category_id
              AND status = 'Active'
            LIMIT 1
        ");

        $categoryCheck->execute([
            ':category_id' => (int)$category_id
        ]);

        if (!$categoryCheck->fetchColumn()) {

            $errors[] =
                "Selected category does not exist or is inactive.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Image Validation
    |--------------------------------------------------------------------------
    */

    $uploadedFiles = [];

    if (
        isset($_FILES['product_images']) &&
        isset($_FILES['product_images']['name']) &&
        is_array($_FILES['product_images']['name'])
    ) {

        $fileCount = count(
            $_FILES['product_images']['name']
        );


        /*
        |--------------------------------------------------------------------------
        | Maximum 10 Images
        |--------------------------------------------------------------------------
        */

        if ($fileCount > 10) {

            $errors[] =
                "You can upload a maximum of 10 product images.";
        }


        /*
        |--------------------------------------------------------------------------
        | Allowed MIME Types
        |--------------------------------------------------------------------------
        */

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];


        /*
        |--------------------------------------------------------------------------
        | Validate Each Image
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $finfo = new finfo(FILEINFO_MIME_TYPE);


            foreach (
                $_FILES['product_images']['tmp_name']
                as $key => $tmpName
            ) {

                $originalName =
                    $_FILES['product_images']['name'][$key] ?? '';

                $errorCode =
                    $_FILES['product_images']['error'][$key] ?? UPLOAD_ERR_NO_FILE;

                $fileSize =
                    $_FILES['product_images']['size'][$key] ?? 0;


                /*
                |--------------------------------------------------------------------------
                | No File
                |--------------------------------------------------------------------------
                */

                if (
                    $errorCode === UPLOAD_ERR_NO_FILE ||
                    $originalName === ''
                ) {

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Upload Error
                |--------------------------------------------------------------------------
                */

                if ($errorCode !== UPLOAD_ERR_OK) {

                    $errors[] =
                        "Unable to upload image: "
                        . $originalName;

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Maximum 5MB
                |--------------------------------------------------------------------------
                */

                if ($fileSize > 5 * 1024 * 1024) {

                    $errors[] =
                        "Image \""
                        . $originalName
                        . "\" exceeds the 5MB limit.";

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | MIME Validation
                |--------------------------------------------------------------------------
                */

                $mimeType = $finfo->file($tmpName);


                if (!isset($allowedMimeTypes[$mimeType])) {

                    $errors[] =
                        "Invalid image type for \""
                        . $originalName
                        . "\".";

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Verify Actual Image
                |--------------------------------------------------------------------------
                */

                if (@getimagesize($tmpName) === false) {

                    $errors[] =
                        "\""
                        . $originalName
                        . "\" is not a valid image.";

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Store Valid Upload
                |--------------------------------------------------------------------------
                */

                $uploadedFiles[] = [
                    'key' => $key,
                    'tmp_name' => $tmpName,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'extension' => $allowedMimeTypes[$mimeType]
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Product
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Slug
            |--------------------------------------------------------------------------
            */

            $baseSlug = $slug;

            $slugCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM products
                WHERE slug = :slug
            ");

            $slugCheck->execute([
                ':slug' => $slug
            ]);

            $slugExists =
                (int)$slugCheck->fetchColumn();


            if ($slugExists > 0) {

                $counter = 2;

                do {

                    $slug = $baseSlug . '-' . $counter;

                    $slugCheck->execute([
                        ':slug' => $slug
                    ]);

                    $slugExists =
                        (int)$slugCheck->fetchColumn();

                    $counter++;

                } while ($slugExists > 0);
            }


            /*
            |--------------------------------------------------------------------------
            | Insert Product
            |--------------------------------------------------------------------------
            */

            $productStmt = $pdo->prepare("
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
                    :category_id,
                    :product_name,
                    :slug,
                    :description,
                    :short_description,
                    :price,
                    :discount_price,
                    :food_type,
                    :spice_level,
                    :preparation_time,
                    :stock,
                    :featured,
                    :availability,
                    :status
                )
            ");


            $productStmt->execute([

                ':category_id' =>
                    (int)$category_id,

                ':product_name' =>
                    $product_name,

                ':slug' =>
                    $slug,

                ':description' =>
                    $description !== ''
                    ? $description
                    : null,

                ':short_description' =>
                    $short_description !== ''
                    ? $short_description
                    : null,

                ':price' =>
                    (float)$price,

                ':discount_price' =>
                    $discount_price !== ''
                    ? (float)$discount_price
                    : 0,

                ':food_type' =>
                    $food_type,

                ':spice_level' =>
                    $spice_level !== ''
                    ? $spice_level
                    : null,

                ':preparation_time' =>
                    $preparation_time !== ''
                    ? (int)$preparation_time
                    : null,

                ':stock' =>
                    (int)$stock,

                ':featured' =>
                    $featured,

                ':availability' =>
                    $availability,

                ':status' =>
                    $status
            ]);


            $productId =
                (int)$pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Upload Directory
            |--------------------------------------------------------------------------
            */

            if (!empty($uploadedFiles)) {

                $uploadDir =
                    __DIR__
                    . "/../assets/images/products/";


                if (!is_dir($uploadDir)) {

                    if (
                        !mkdir(
                            $uploadDir,
                            0755,
                            true
                        )
                    ) {

                        throw new Exception(
                            "Unable to create product image directory."
                        );
                    }
                }


                if (!is_writable($uploadDir)) {

                    throw new Exception(
                        "Product image directory is not writable."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Insert Images
                |--------------------------------------------------------------------------
                */

                $imageStmt = $pdo->prepare("
                    INSERT INTO product_images
                    (
                        product_id,
                        image_name,
                        is_primary,
                        display_order
                    )
                    VALUES
                    (
                        :product_id,
                        :image_name,
                        :is_primary,
                        :display_order
                    )
                ");


                foreach (
                    $uploadedFiles
                    as $index => $file
                ) {

                    $extension =
                        $file['extension'];


                    /*
                    |--------------------------------------------------------------------------
                    | Random Safe Filename
                    |--------------------------------------------------------------------------
                    */

                    $newImageName =
                        bin2hex(
                            random_bytes(16)
                        )
                        . '.'
                        . $extension;


                    $destination =
                        $uploadDir
                        . $newImageName;


                    /*
                    |--------------------------------------------------------------------------
                    | Move Uploaded File
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {

                        throw new Exception(
                            "Unable to save product image."
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Track File For Rollback
                    |--------------------------------------------------------------------------
                    */

                    $uploadedImagePath =
                        $destination;


                    /*
                    |--------------------------------------------------------------------------
                    | First Image = Primary
                    |--------------------------------------------------------------------------
                    */

                    $isPrimary =
                        $index === 0
                        ? 1
                        : 0;


                    $displayOrder =
                        $index + 1;


                    /*
                    |--------------------------------------------------------------------------
                    | Insert Image Record
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $imageStmt->execute([

                            ':product_id' =>
                                $productId,

                            ':image_name' =>
                                $newImageName,

                            ':is_primary' =>
                                $isPrimary,

                            ':display_order' =>
                                $displayOrder
                        ]);

                    } catch (Throwable $imageException) {

                        /*
                        |--------------------------------------------------------------------------
                        | Remove image if DB insert fails
                        |--------------------------------------------------------------------------
                        */

                        if (
                            is_file(
                                $uploadedImagePath
                            )
                        ) {

                            @unlink(
                                $uploadedImagePath
                            );
                        }

                        throw $imageException;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Track Uploaded File
                    |--------------------------------------------------------------------------
                    */

                    $createdImageFiles[] =
                        $uploadedImagePath;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $_SESSION['success'] =
                "Product \""
                . $product_name
                . "\" added successfully.";


            header(
                "Location: products.php"
            );

            exit();


        } catch (Throwable $e) {


            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Remove Uploaded Files
            |--------------------------------------------------------------------------
            */

            if (
                isset($createdImageFiles) &&
                is_array($createdImageFiles)
            ) {

                foreach (
                    $createdImageFiles
                    as $createdFile
                ) {

                    if (is_file($createdFile)) {

                        @unlink($createdFile);
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Error
            |--------------------------------------------------------------------------
            */

            $errors[] =
                "Unable to add product. Please try again.";

        }
    }
}


/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";

?>

<div class="container-fluid mt-4">


    <!-- =====================================================
         Error Messages
    ====================================================== -->

    <?php if (!empty($errors)) : ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert">

            <div class="fw-bold mb-2">

                <i
                    class="bi bi-exclamation-triangle-fill me-2">
                </i>

                Please fix the following:

            </div>

            <ul class="mb-0">

                <?php foreach ($errors as $error) : ?>

                    <li>
                        <?= e($error); ?>
                    </li>

                <?php endforeach; ?>

            </ul>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert">
            </button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Page Header
    ====================================================== -->

    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

        <div>

            <h3 class="fw-bold mb-1">

                <i
                    class="bi bi-plus-circle me-2">
                </i>

                Add New Product

            </h3>

            <p class="text-muted mb-0">

                Add a new food or beverage product to the menu.

            </p>

        </div>


        <a
            href="products.php"
            class="btn btn-secondary">

            <i
                class="bi bi-arrow-left me-1">
            </i>

            Back to Products

        </a>

    </div>


    <!-- =====================================================
         Product Form
    ====================================================== -->

    <div class="card shadow border-0">

        <div class="card-header bg-primary text-white">

            <h5 class="mb-0">

                <i
                    class="bi bi-box-seam me-2">
                </i>

                Product Information

            </h5>

        </div>


        <div class="card-body">

            <form
                method="POST"
                enctype="multipart/form-data"
                id="productForm">


                <!-- =================================================
                     Basic Information
                ================================================== -->

                <div class="row">


                    <!-- Product Name -->

                    <div class="col-md-6 mb-3">

                        <label
                            for="product_name"
                            class="form-label fw-semibold">

                            Product Name

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <input
                            type="text"
                            name="product_name"
                            id="product_name"
                            class="form-control"
                            maxlength="255"
                            required
                            value="<?= e($product_name); ?>"
                            placeholder="e.g. Cappuccino">

                    </div>


                    <!-- Slug -->

                    <div class="col-md-6 mb-3">

                        <label
                            for="slug"
                            class="form-label fw-semibold">

                            Slug

                        </label>

                        <input
                            type="text"
                            id="slug"
                            class="form-control"
                            readonly
                            value="<?= e($slug); ?>">

                        <small class="text-muted">

                            Automatically generated from product name.

                        </small>

                    </div>


                    <!-- Category -->

                    <div class="col-md-6 mb-3">

                        <label
                            for="category_id"
                            class="form-label fw-semibold">

                            Category

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <select
                            name="category_id"
                            id="category_id"
                            class="form-select"
                            required>

                            <option value="">
                                Select Category
                            </option>

                            <?php foreach ($categories as $category) : ?>

                                <option
                                    value="<?= (int)$category['category_id']; ?>"
                                    <?= (string)$category_id ===
                                        (string)$category['category_id']
                                        ? 'selected'
                                        : ''; ?>>

                                    <?= e($category['category_name']); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Food Type -->

                    <div class="col-md-6 mb-3">

                        <label
                            for="food_type"
                            class="form-label fw-semibold">

                            Food Type

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <select
                            name="food_type"
                            id="food_type"
                            class="form-select"
                            required>

                            <option value="">
                                Select Food Type
                            </option>

                            <option
                                value="Veg"
                                <?= $food_type === 'Veg'
                                    ? 'selected'
                                    : ''; ?>>

                                Veg

                            </option>

                            <option
                                value="Non-Veg"
                                <?= $food_type === 'Non-Veg'
                                    ? 'selected'
                                    : ''; ?>>

                                Non-Veg

                            </option>

                            <option
                                value="Egg"
                                <?= $food_type === 'Egg'
                                    ? 'selected'
                                    : ''; ?>>

                                Egg

                            </option>

                        </select>

                    </div>


                    <!-- Short Description -->

                    <div class="col-12 mb-3">

                        <label
                            for="short_description"
                            class="form-label fw-semibold">

                            Short Description

                        </label>

                        <textarea
                            name="short_description"
                            id="short_description"
                            class="form-control"
                            rows="2"
                            maxlength="500"
                            placeholder="Short description for product cards..."><?= e($short_description); ?></textarea>

                    </div>


                    <!-- Description -->

                    <div class="col-12 mb-3">

                        <label
                            for="description"
                            class="form-label fw-semibold">

                            Description

                        </label>

                        <textarea
                            name="description"
                            id="description"
                            class="form-control"
                            rows="5"
                            placeholder="Enter complete product description..."><?= e($description); ?></textarea>

                    </div>

                </div>


                <!-- =================================================
                     Pricing & Inventory
                ================================================== -->

                <hr class="my-4">

                <h6 class="fw-bold mb-3">

                    <i
                        class="bi bi-currency-rupee me-1">
                    </i>

                    Pricing & Inventory

                </h6>


                <div class="row">


                    <!-- Price -->

                    <div class="col-lg-3 col-md-6 mb-3">

                        <label
                            for="price"
                            class="form-label fw-semibold">

                            Price (₹)

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <input
                            type="number"
                            name="price"
                            id="price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            required
                            value="<?= e($price); ?>"
                            placeholder="0.00">

                    </div>


                    <!-- Discount Price -->

                    <div class="col-lg-3 col-md-6 mb-3">

                        <label
                            for="discount_price"
                            class="form-label fw-semibold">

                            Discount Price (₹)

                        </label>

                        <input
                            type="number"
                            name="discount_price"
                            id="discount_price"
                            class="form-control"
                            min="0"
                            step="0.01"
                            value="<?= e($discount_price); ?>"
                            placeholder="Optional">

                        <small class="text-muted">

                            Final selling price after discount.

                        </small>

                    </div>


                    <!-- Stock -->

                    <div class="col-lg-3 col-md-6 mb-3">

                        <label
                            for="stock"
                            class="form-label fw-semibold">

                            Stock

                            <span class="text-danger">
                                *
                            </span>

                        </label>

                        <input
                            type="number"
                            name="stock"
                            id="stock"
                            class="form-control"
                            min="0"
                            step="1"
                            required
                            value="<?= e($stock); ?>"
                            placeholder="0">

                    </div>


                    <!-- Preparation Time -->

                    <div class="col-lg-3 col-md-6 mb-3">

                        <label
                            for="preparation_time"
                            class="form-label fw-semibold">

                            Preparation Time (Min)

                        </label>

                        <input
                            type="number"
                            name="preparation_time"
                            id="preparation_time"
                            class="form-control"
                            min="0"
                            step="1"
                            value="<?= e($preparation_time); ?>"
                            placeholder="e.g. 15">

                    </div>

                </div>


                <!-- =================================================
                     Product Options
                ================================================== -->

                <div class="row">


                    <!-- Spice Level -->

                    <div class="col-md-4 mb-3">

                        <label
                            for="spice_level"
                            class="form-label fw-semibold">

                            Spice Level

                        </label>

                        <select
                            name="spice_level"
                            id="spice_level"
                            class="form-select">

                            <option value="">
                                Select Spice Level
                            </option>

                            <option
                                value="Mild"
                                <?= $spice_level === 'Mild'
                                    ? 'selected'
                                    : ''; ?>>

                                Mild

                            </option>

                            <option
                                value="Medium"
                                <?= $spice_level === 'Medium'
                                    ? 'selected'
                                    : ''; ?>>

                                Medium

                            </option>

                            <option
                                value="Hot"
                                <?= $spice_level === 'Hot'
                                    ? 'selected'
                                    : ''; ?>>

                                Hot

                            </option>

                        </select>

                    </div>


                    <!-- Availability -->

                    <div class="col-md-4 mb-3">

                        <label
                            for="availability"
                            class="form-label fw-semibold">

                            Availability

                        </label>

                        <select
                            name="availability"
                            id="availability"
                            class="form-select">

                            <option
                                value="Available"
                                <?= $availability === 'Available'
                                    ? 'selected'
                                    : ''; ?>>

                                Available

                            </option>

                            <option
                                value="Unavailable"
                                <?= $availability === 'Unavailable'
                                    ? 'selected'
                                    : ''; ?>>

                                Unavailable

                            </option>

                        </select>

                    </div>


                    <!-- Status -->

                    <div class="col-md-4 mb-3">

                        <label
                            for="status"
                            class="form-label fw-semibold">

                            Status

                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-select">

                            <option
                                value="Active"
                                <?= $status === 'Active'
                                    ? 'selected'
                                    : ''; ?>>

                                Active

                            </option>

                            <option
                                value="Inactive"
                                <?= $status === 'Inactive'
                                    ? 'selected'
                                    : ''; ?>>

                                Inactive

                            </option>

                        </select>

                    </div>


                    <!-- Featured -->

                    <div class="col-12 mb-3">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="featured"
                                id="featured"
                                value="1"
                                <?= $featured == 1
                                    ? 'checked'
                                    : ''; ?>>

                            <label
                                class="form-check-label fw-semibold"
                                for="featured">

                                Featured Product

                            </label>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     Product Images
                ================================================== -->

                <hr class="my-4">

                <h6 class="fw-bold mb-3">

                    <i
                        class="bi bi-images me-1">
                    </i>

                    Product Images

                </h6>


                <div class="mb-3">

                    <label
                        for="product_images"
                        class="form-label fw-semibold">

                        Upload Images

                    </label>

                    <input
                        type="file"
                        name="product_images[]"
                        id="product_images"
                        class="form-control"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp">

                    <small class="text-muted">

                        Maximum 10 images.
                        JPG, JPEG, PNG and WEBP.
                        Maximum 5MB per image.
                        First image becomes the primary image.

                    </small>

                </div>


                <!-- Image Preview -->

                <div
                    id="preview"
                    class="row g-3 mb-4">
                </div>


                <!-- =================================================
                     Buttons
                ================================================== -->

                <div
                    class="d-flex flex-wrap gap-2">

                    <button
                        type="submit"
                        class="btn btn-success">

                        <i
                            class="bi bi-check-circle me-1">
                        </i>

                        Save Product

                    </button>


                    <button
                        type="reset"
                        class="btn btn-warning"
                        id="resetForm">

                        <i
                            class="bi bi-arrow-clockwise me-1">
                        </i>

                        Reset

                    </button>


                    <a
                        href="products.php"
                        class="btn btn-secondary">

                        <i
                            class="bi bi-arrow-left me-1">
                        </i>

                        Cancel

                    </a>

                </div>


            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     JavaScript
========================================================== -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const productName =
        document.getElementById("product_name");

    const slug =
        document.getElementById("slug");

    const imageInput =
        document.getElementById("product_images");

    const preview =
        document.getElementById("preview");

    const price =
        document.getElementById("price");

    const discountPrice =
        document.getElementById("discount_price");

    const form =
        document.getElementById("productForm");


    /* =====================================================
       Generate Slug
    ====================================================== */

    function updateSlug() {

        slug.value =
            productName.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "");

    }


    productName.addEventListener(
        "input",
        updateSlug
    );


    /* =====================================================
       Image Preview
    ====================================================== */

    imageInput.addEventListener(
        "change",
        function () {

            preview.innerHTML = "";

            const files =
                Array.from(this.files);


            if (files.length > 10) {

                alert(
                    "You can upload a maximum of 10 images."
                );

                this.value = "";

                return;
            }


            files.forEach(function (file, index) {

                if (!file.type.startsWith("image/")) {

                    return;
                }


                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        const column =
                            document.createElement("div");

                        column.className =
                            "col-xl-2 col-lg-3 col-md-4 col-6";


                        const card =
                            document.createElement("div");

                        card.className =
                            "card h-100 shadow-sm";


                        const image =
                            document.createElement("img");

                        image.src =
                            event.target.result;

                        image.className =
                            "card-img-top";

                        image.style.height =
                            "140px";

                        image.style.objectFit =
                            "cover";


                        const body =
                            document.createElement("div");

                        body.className =
                            "card-body p-2 text-center";


                        const name =
                            document.createElement("div");

                        name.className =
                            "small text-truncate";

                        name.title =
                            file.name;

                        name.textContent =
                            file.name;


                        const primary =
                            document.createElement("span");

                        if (index === 0) {

                            primary.className =
                                "badge bg-success mt-1";

                            primary.textContent =
                                "Primary";
                        }


                        body.appendChild(name);

                        if (index === 0) {

                            body.appendChild(primary);
                        }


                        card.appendChild(image);

                        card.appendChild(body);

                        column.appendChild(card);

                        preview.appendChild(column);

                    };


                reader.readAsDataURL(file);

            });

        }
    );


    /* =====================================================
       Discount Price Validation
    ====================================================== */

    function validateDiscount() {

        const productPrice =
            parseFloat(price.value);

        const discount =
            parseFloat(discountPrice.value);


        if (
            !isNaN(productPrice) &&
            !isNaN(discount) &&
            discount > productPrice
        ) {

            discountPrice.setCustomValidity(
                "Discount price cannot be greater than product price."
            );

        } else {

            discountPrice.setCustomValidity("");

        }

    }


    price.addEventListener(
        "input",
        validateDiscount
    );

    discountPrice.addEventListener(
        "input",
        validateDiscount
    );


    /* =====================================================
       Form Validation
    ====================================================== */

    form.addEventListener(
        "submit",
        function (event) {

            updateSlug();

            validateDiscount();


            if (!productName.value.trim()) {

                event.preventDefault();

                alert(
                    "Please enter the product name."
                );

                productName.focus();

                return;
            }


            if (!price.value) {

                event.preventDefault();

                alert(
                    "Please enter the product price."
                );

                price.focus();

                return;
            }

        }
    );


    /* =====================================================
       Reset
    ====================================================== */

    document
        .getElementById("resetForm")
        .addEventListener(
            "click",
            function () {

                setTimeout(
                    function () {

                        preview.innerHTML = "";

                        slug.value = "";

                        discountPrice.setCustomValidity("");

                    },
                    0
                );

            }
        );

});

</script>


<?php

include "includes/a-footer.php";

?>