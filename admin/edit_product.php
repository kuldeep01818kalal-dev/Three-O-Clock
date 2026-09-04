<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Edit Product";

$errors = [];


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Product ID
|--------------------------------------------------------------------------
*/

$productId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    !$productId ||
    $productId < 1
) {

    $_SESSION['error'] =
        "Invalid product selected.";

    header("Location: products.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === ''
) {

    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Fetch Product
|--------------------------------------------------------------------------
*/

$productStmt = $pdo->prepare("
    SELECT
        product_id,
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
        status,
        created_at,
        updated_at
    FROM products
    WHERE product_id = :product_id
    LIMIT 1
");

$productStmt->execute([
    ':product_id' => $productId
]);

$product =
    $productStmt->fetch(PDO::FETCH_ASSOC);


if (!$product) {

    $_SESSION['error'] =
        "Product not found.";

    header("Location: products.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = [];

try {

    $categoryStmt = $pdo->query("
        SELECT
            category_id,
            category_name,
            status
        FROM categories
        ORDER BY category_name ASC
    ");

    $categories =
        $categoryStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $errors[] =
        "Unable to load categories.";
}


/*
|--------------------------------------------------------------------------
| Existing Product Images
|--------------------------------------------------------------------------
*/

$imageStmt = $pdo->prepare("
    SELECT
        image_id,
        product_id,
        image_name,
        is_primary,
        display_order
    FROM product_images
    WHERE product_id = :product_id
    ORDER BY
        is_primary DESC,
        display_order ASC,
        image_id ASC
");

$imageStmt->execute([
    ':product_id' => $productId
]);

$productImages =
    $imageStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$productName =
    trim(
        (string)(
            $_POST['product_name']
            ?? $product['product_name']
        )
    );


$categoryId =
    trim(
        (string)(
            $_POST['category_id']
            ?? $product['category_id']
        )
    );


$slug =
    trim(
        (string)(
            $_POST['slug']
            ?? $product['slug']
        )
    );


$shortDescription =
    trim(
        (string)(
            $_POST['short_description']
            ?? ($product['short_description'] ?? '')
        )
    );


$description =
    trim(
        (string)(
            $_POST['description']
            ?? ($product['description'] ?? '')
        )
    );


$price =
    trim(
        (string)(
            $_POST['price']
            ?? $product['price']
        )
    );


/*
|--------------------------------------------------------------------------
| IMPORTANT:
| Database field is discount_price.
|--------------------------------------------------------------------------
*/

$discountPrice =
    trim(
        (string)(
            $_POST['discount_price']
            ?? ($product['discount_price'] ?? '')
        )
    );


$foodType =
    trim(
        (string)(
            $_POST['food_type']
            ?? $product['food_type']
        )
    );


$spiceLevel =
    trim(
        (string)(
            $_POST['spice_level']
            ?? ($product['spice_level'] ?? '')
        )
    );


$preparationTime =
    trim(
        (string)(
            $_POST['preparation_time']
            ?? ($product['preparation_time'] ?? '')
        )
    );


$stock =
    trim(
        (string)(
            $_POST['stock']
            ?? $product['stock']
        )
    );


$featured =
    isset($_POST['featured'])
        ? 1
        : (int)$product['featured'];


$availability =
    trim(
        (string)(
            $_POST['availability']
            ?? $product['availability']
        )
    );


$status =
    trim(
        (string)(
            $_POST['status']
            ?? $product['status']
        )
    );


/*
|--------------------------------------------------------------------------
| Allowed Values
|--------------------------------------------------------------------------
*/

$allowedFoodTypes = [
    'Veg',
    'Non-Veg',
    'Egg'
];

$allowedSpiceLevels = [
    'Mild',
    'Medium',
    'Hot'
];

$allowedAvailability = [
    'Available',
    'Unavailable'
];

$allowedStatuses = [
    'Active',
    'Inactive'
];


/*
|--------------------------------------------------------------------------
| Deleted Images
|--------------------------------------------------------------------------
*/

$deleteImageIds = [];


/*
|--------------------------------------------------------------------------
| Update Product
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_product'])
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    $submittedToken =
        (string)(
            $_POST['csrf_token']
            ?? ''
        );


    if (
        $submittedToken === '' ||
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $errors[] =
            "Invalid security token. Please refresh the page and try again.";
    }


    /*
    |--------------------------------------------------------------------------
    | Product Name
    |--------------------------------------------------------------------------
    */

    if ($productName === '') {

        $errors[] =
            "Product name is required.";

    } elseif (
        mb_strlen($productName) < 2
    ) {

        $errors[] =
            "Product name must contain at least 2 characters.";

    } elseif (
        mb_strlen($productName) > 150
    ) {

        $errors[] =
            "Product name cannot exceed 150 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Category
    |--------------------------------------------------------------------------
    */

    $categoryIdValue =
        filter_var(
            $categoryId,
            FILTER_VALIDATE_INT
        );


    if (
        $categoryIdValue === false ||
        $categoryIdValue < 1
    ) {

        $errors[] =
            "Please select a valid category.";

    } else {

        $categoryCheck =
            $pdo->prepare("
                SELECT category_id
                FROM categories
                WHERE category_id = :category_id
                LIMIT 1
            ");

        $categoryCheck->execute([
            ':category_id' =>
                $categoryIdValue
        ]);


        if (
            !$categoryCheck->fetchColumn()
        ) {

            $errors[] =
                "Selected category does not exist.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Slug
    |--------------------------------------------------------------------------
    */

    if ($slug === '') {

        $slug =
            strtolower(
                trim(
                    preg_replace(
                        '/[^a-zA-Z0-9]+/',
                        '-',
                        $productName
                    ),
                    '-'
                )
            );
    }


    if ($slug === '') {

        $errors[] =
            "Product slug is required.";
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Slug
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $slug !== ''
    ) {

        $slugStmt =
            $pdo->prepare("
                SELECT product_id
                FROM products
                WHERE slug = :slug
                AND product_id != :product_id
                LIMIT 1
            ");

        $slugStmt->execute([
            ':slug' =>
                $slug,

            ':product_id' =>
                $productId
        ]);


        if (
            $slugStmt->fetch()
        ) {

            $baseSlug =
                $slug;

            $counter = 2;


            do {

                $slug =
                    $baseSlug .
                    '-' .
                    $counter;

                $slugStmt->execute([
                    ':slug' =>
                        $slug,

                    ':product_id' =>
                        $productId
                ]);

                $counter++;

            } while (
                $slugStmt->fetch()
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Price
    |--------------------------------------------------------------------------
    */

    if ($price === '') {

        $errors[] =
            "Price is required.";

    } elseif (!is_numeric($price)) {

        $errors[] =
            "Price must be a valid number.";

    } elseif ((float)$price <= 0) {

        $errors[] =
            "Price must be greater than 0.";
    }


    /*
    |--------------------------------------------------------------------------
    | Discount Price
    |--------------------------------------------------------------------------
    */

    if ($discountPrice !== '') {

        if (!is_numeric($discountPrice)) {

            $errors[] =
                "Discount price must be a valid number.";

        } elseif ((float)$discountPrice < 0) {

            $errors[] =
                "Discount price cannot be negative.";

        } elseif (
            is_numeric($price) &&
            (float)$discountPrice >= (float)$price
        ) {

            $errors[] =
                "Discount price must be lower than the original price.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Food Type
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $foodType,
            $allowedFoodTypes,
            true
        )
    ) {

        $errors[] =
            "Invalid food type.";
    }


    /*
    |--------------------------------------------------------------------------
    | Spice Level
    |--------------------------------------------------------------------------
    */

    if (
        $spiceLevel !== '' &&
        !in_array(
            $spiceLevel,
            $allowedSpiceLevels,
            true
        )
    ) {

        $errors[] =
            "Invalid spice level.";
    }


    /*
    |--------------------------------------------------------------------------
    | Preparation Time
    |--------------------------------------------------------------------------
    */

    if ($preparationTime !== '') {

        if (
            !ctype_digit($preparationTime)
        ) {

            $errors[] =
                "Preparation time must be a whole number.";

        } elseif (
            (int)$preparationTime > 1440
        ) {

            $errors[] =
                "Preparation time cannot exceed 1440 minutes.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Stock
    |--------------------------------------------------------------------------
    */

    if ($stock === '') {

        $errors[] =
            "Stock is required.";

    } elseif (
        !ctype_digit($stock)
    ) {

        $errors[] =
            "Stock must be a whole number.";
    }


    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $availability,
            $allowedAvailability,
            true
        )
    ) {

        $errors[] =
            "Invalid availability status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $errors[] =
            "Invalid product status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Short Description
    |--------------------------------------------------------------------------
    */

    if (
        mb_strlen($shortDescription) > 500
    ) {

        $errors[] =
            "Short description cannot exceed 500 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Image Deletion IDs
    |--------------------------------------------------------------------------
    */

    if (
        isset($_POST['delete_images']) &&
        is_array($_POST['delete_images'])
    ) {

        foreach (
            $_POST['delete_images']
            as $imageId
        ) {

            $validatedImageId =
                filter_var(
                    $imageId,
                    FILTER_VALIDATE_INT
                );


            if (
                $validatedImageId &&
                $validatedImageId > 0
            ) {

                $deleteImageIds[] =
                    (int)$validatedImageId;
            }
        }

        $deleteImageIds =
            array_values(
                array_unique(
                    $deleteImageIds
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | New Image Uploads
    |--------------------------------------------------------------------------
    */

    $newImages = [];

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $maxImageSize =
        5 * 1024 * 1024;


    if (
        isset($_FILES['product_images']) &&
        is_array(
            $_FILES['product_images']['name']
            ?? null
        )
    ) {

        $fileCount =
            count(
                $_FILES['product_images']['name']
            );


        if ($fileCount > 10) {

            $errors[] =
                "You can upload a maximum of 10 images at once.";
        }


        for (
            $i = 0;
            $i < min($fileCount, 10);
            $i++
        ) {

            $fileError =
                (int)(
                    $_FILES['product_images']['error'][$i]
                    ?? UPLOAD_ERR_NO_FILE
                );


            if (
                $fileError === UPLOAD_ERR_NO_FILE
            ) {

                continue;
            }


            if (
                $fileError !== UPLOAD_ERR_OK
            ) {

                $errors[] =
                    "One of the selected images could not be uploaded.";

                continue;
            }


            $tmpName =
                (string)(
                    $_FILES['product_images']['tmp_name'][$i]
                    ?? ''
                );


            $fileSize =
                (int)(
                    $_FILES['product_images']['size'][$i]
                    ?? 0
                );


            if (
                !is_uploaded_file($tmpName)
            ) {

                $errors[] =
                    "Invalid image upload detected.";

                continue;
            }


            if (
                $fileSize <= 0 ||
                $fileSize > $maxImageSize
            ) {

                $errors[] =
                    "Each image must be smaller than 5 MB.";

                continue;
            }


            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mimeType =
                $finfo->file(
                    $tmpName
                );


            if (
                !isset(
                    $allowedMimeTypes[$mimeType]
                )
            ) {

                $errors[] =
                    "Only JPG, PNG and WEBP images are allowed.";

                continue;
            }


            if (
                @getimagesize($tmpName) === false
            ) {

                $errors[] =
                    "One selected file is not a valid image.";

                continue;
            }


            $newImages[] = [

                'tmp_name' =>
                    $tmpName,

                'extension' =>
                    $allowedMimeTypes[$mimeType]

            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $createdFiles = [];

        $oldFilesToDelete = [];

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock Product
            |--------------------------------------------------------------------------
            */

            $lockStmt =
                $pdo->prepare("
                    SELECT
                        product_id,
                        product_name
                    FROM products
                    WHERE product_id = :product_id
                    FOR UPDATE
                ");

            $lockStmt->execute([
                ':product_id' =>
                    $productId
            ]);


            $lockedProduct =
                $lockStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$lockedProduct) {

                throw new RuntimeException(
                    "Product no longer exists."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Selected Images
            |--------------------------------------------------------------------------
            */

            if (!empty($deleteImageIds)) {

                $placeholders =
                    implode(
                        ',',
                        array_fill(
                            0,
                            count($deleteImageIds),
                            '?'
                        )
                    );


                $imageCheck =
                    $pdo->prepare("
                        SELECT
                            image_id,
                            image_name,
                            is_primary
                        FROM product_images
                        WHERE product_id = ?
                        AND image_id IN ($placeholders)
                    ");


                $imageCheck->execute(
                    array_merge(
                        [$productId],
                        $deleteImageIds
                    )
                );


                $imagesToDelete =
                    $imageCheck->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                /*
                |--------------------------------------------------------------------------
                | Prevent deleting every image
                |--------------------------------------------------------------------------
                */

                $existingImageCount =
                    (int)$pdo->prepare("
                        SELECT COUNT(*)
                        FROM product_images
                        WHERE product_id = ?
                    ")
                    ->execute([$productId]);


                $countStmt =
                    $pdo->prepare("
                        SELECT COUNT(*)
                        FROM product_images
                        WHERE product_id = ?
                    ");

                $countStmt->execute([
                    $productId
                ]);

                $currentImageCount =
                    (int)$countStmt->fetchColumn();


                $remainingImages =
                    $currentImageCount -
                    count($imagesToDelete) +
                    count($newImages);


                if (
                    $remainingImages < 0
                ) {

                    $remainingImages = 0;
                }


                if (
                    $remainingImages === 0 &&
                    $currentImageCount > 0 &&
                    empty($newImages)
                ) {

                    throw new RuntimeException(
                        "A product must have at least one image. Upload a new image before deleting all existing images."
                    );
                }


                foreach (
                    $imagesToDelete as $image
                ) {

                    $oldFilesToDelete[] =
                        "../assets/images/products/"
                        . basename(
                            (string)$image['image_name']
                        );
                }


                $deleteStmt =
                    $pdo->prepare("
                        DELETE FROM product_images
                        WHERE product_id = ?
                        AND image_id IN ($placeholders)
                    ");


                $deleteStmt->execute(
                    array_merge(
                        [$productId],
                        $deleteImageIds
                    )
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Upload Directory
            |--------------------------------------------------------------------------
            */

            $uploadDir =
                __DIR__
                . "/../assets/images/products/";


            if (
                !empty($newImages) &&
                !is_dir($uploadDir)
            ) {

                if (
                    !mkdir(
                        $uploadDir,
                        0755,
                        true
                    )
                ) {

                    throw new RuntimeException(
                        "Unable to create product image directory."
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Update Product
            |--------------------------------------------------------------------------
            */

            $updateStmt =
                $pdo->prepare("
                    UPDATE products
                    SET
                        category_id = :category_id,
                        product_name = :product_name,
                        slug = :slug,
                        description = :description,
                        short_description = :short_description,
                        price = :price,
                        discount_price = :discount_price,
                        food_type = :food_type,
                        spice_level = :spice_level,
                        preparation_time = :preparation_time,
                        stock = :stock,
                        featured = :featured,
                        availability = :availability,
                        status = :status
                    WHERE product_id = :product_id
                ");


            $updateStmt->execute([

                ':category_id' =>
                    (int)$categoryIdValue,

                ':product_name' =>
                    $productName,

                ':slug' =>
                    $slug,

                ':description' =>
                    $description !== ''
                        ? $description
                        : null,

                ':short_description' =>
                    $shortDescription !== ''
                        ? $shortDescription
                        : null,

                ':price' =>
                    number_format(
                        (float)$price,
                        2,
                        '.',
                        ''
                    ),

                ':discount_price' =>
                    $discountPrice !== ''
                        ? number_format(
                            (float)$discountPrice,
                            2,
                            '.',
                            ''
                        )
                        : null,

                ':food_type' =>
                    $foodType,

                ':spice_level' =>
                    $spiceLevel !== ''
                        ? $spiceLevel
                        : null,

                ':preparation_time' =>
                    $preparationTime !== ''
                        ? (int)$preparationTime
                        : null,

                ':stock' =>
                    (int)$stock,

                ':featured' =>
                    $featured,

                ':availability' =>
                    $availability,

                ':status' =>
                    $status,

                ':product_id' =>
                    $productId
            ]);


            /*
            |--------------------------------------------------------------------------
            | Existing Image Count
            |--------------------------------------------------------------------------
            */

            $countStmt =
                $pdo->prepare("
                    SELECT COUNT(*)
                    FROM product_images
                    WHERE product_id = ?
                ");

            $countStmt->execute([
                $productId
            ]);

            $imageCount =
                (int)$countStmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Add New Images
            |--------------------------------------------------------------------------
            */

            $imageStmt =
                $pdo->prepare("
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
                $newImages as $position => $image
            ) {

                $newImageName =
                    $productId
                    . "_"
                    . bin2hex(
                        random_bytes(8)
                    )
                    . "."
                    . $image['extension'];


                $destination =
                    $uploadDir
                    . $newImageName;


                if (
                    !move_uploaded_file(
                        $image['tmp_name'],
                        $destination
                    )
                ) {

                    throw new RuntimeException(
                        "Unable to save product image."
                    );
                }


                $createdFiles[] =
                    $destination;


                /*
                |--------------------------------------------------------------------------
                | First Image Becomes Primary
                |--------------------------------------------------------------------------
                */

                $isPrimary =
                    $imageCount === 0 &&
                    $position === 0
                        ? 1
                        : 0;


                $imageStmt->execute([

                    ':product_id' =>
                        $productId,

                    ':image_name' =>
                        $newImageName,

                    ':is_primary' =>
                        $isPrimary,

                    ':display_order' =>
                        $imageCount +
                        $position +
                        1

                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Ensure One Primary Image
            |--------------------------------------------------------------------------
            */

            $primaryCheck =
                $pdo->prepare("
                    SELECT image_id
                    FROM product_images
                    WHERE product_id = ?
                    AND is_primary = 1
                    LIMIT 1
                ");

            $primaryCheck->execute([
                $productId
            ]);


            if (!$primaryCheck->fetchColumn()) {

                $firstImageStmt =
                    $pdo->prepare("
                        SELECT image_id
                        FROM product_images
                        WHERE product_id = ?
                        ORDER BY
                            display_order ASC,
                            image_id ASC
                        LIMIT 1
                    ");

                $firstImageStmt->execute([
                    $productId
                ]);

                $firstImageId =
                    $firstImageStmt->fetchColumn();


                if ($firstImageId) {

                    $makePrimary =
                        $pdo->prepare("
                            UPDATE product_images
                            SET is_primary = 1
                            WHERE image_id = ?
                            AND product_id = ?
                        ");

                    $makePrimary->execute([
                        $firstImageId,
                        $productId
                    ]);
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
            | Delete Old Image Files
            |--------------------------------------------------------------------------
            */

            foreach (
                $oldFilesToDelete as $oldFile
            ) {

                if (
                    is_file($oldFile)
                ) {

                    @unlink($oldFile);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $_SESSION['success'] =
                "Product updated successfully.";

            header(
                "Location: products.php"
            );

            exit;


        } catch (Throwable $e) {


            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Remove Newly Uploaded Files
            |--------------------------------------------------------------------------
            */

            foreach (
                $createdFiles as $createdFile
            ) {

                if (
                    is_file($createdFile)
                ) {

                    @unlink(
                        $createdFile
                    );
                }
            }


            $errors[] =
                "Unable to update the product. Please try again.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Refresh Existing Images After Error
    |--------------------------------------------------------------------------
    */

    $imageStmt->execute([
        ':product_id' => $productId
    ]);

    $productImages =
        $imageStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


/*
|--------------------------------------------------------------------------
| Calculate Discount Percentage
|--------------------------------------------------------------------------
*/

$originalPrice =
    (float)$price;

$currentDiscountPrice =
    $discountPrice !== ''
        ? (float)$discountPrice
        : 0;

$discountPercentage = 0;

if (
    $originalPrice > 0 &&
    $currentDiscountPrice > 0 &&
    $currentDiscountPrice < $originalPrice
) {

    $discountPercentage =
        round(
            (
                (
                    $originalPrice -
                    $currentDiscountPrice
                )
                / $originalPrice
            ) * 100
        );
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
require_once "includes/a-navbar.php";

?>

<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.edit-product-page {
    padding: 24px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.edit-product-header {
    margin-bottom: 24px;
}

.edit-product-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.edit-product-card {
    border: 0;
    overflow: hidden;
}

.edit-product-card-header {
    padding: 18px 22px;
}

.edit-product-card-body {
    padding: 24px;
}


/*
|--------------------------------------------------------------------------
| FORM SECTIONS
|--------------------------------------------------------------------------
*/

.product-form-section {
    padding-bottom: 24px;
    margin-bottom: 24px;
    border-bottom: 1px solid #dee2e6;
}

.product-form-section:last-child {
    border-bottom: 0;
    margin-bottom: 0;
}

.product-section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 18px;
}


/*
|--------------------------------------------------------------------------
| LABELS
|--------------------------------------------------------------------------
*/

.edit-product-page .form-label {
    font-size: .86rem;
    font-weight: 600;
    margin-bottom: 7px;
}

.edit-product-page .form-control,
.edit-product-page .form-select {
    min-height: 43px;
}


/*
|--------------------------------------------------------------------------
| IMAGE GRID
|--------------------------------------------------------------------------
*/

.product-image-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fill,
        minmax(140px, 1fr)
    );
    gap: 14px;
}

.product-image-card {
    position: relative;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    overflow: hidden;
    background: #f8f9fa;
}

.product-existing-image {
    width: 100%;
    height: 130px;
    object-fit: cover;
    display: block;
}

.product-image-card-body {
    padding: 9px;
}

.primary-image-badge {
    position: absolute;
    top: 8px;
    left: 8px;
}

.image-delete-check {
    margin-top: 5px;
}


/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

.product-upload-box {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: .2s ease;
}

.product-upload-box:hover {
    border-color: #0d6efd;
    background: rgba(
        13,
        110,
        253,
        .02
    );
}

.new-image-preview-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fill,
        minmax(120px, 1fr)
    );
    gap: 12px;
    margin-top: 16px;
}

.new-image-preview {
    width: 100%;
    height: 115px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}


/*
|--------------------------------------------------------------------------
| SIDE CARDS
|--------------------------------------------------------------------------
*/

.product-side-card {
    border: 0;
    border-radius: 12px;
    box-shadow:
        0 2px 12px
        rgba(0, 0, 0, .06);
    margin-bottom: 20px;
}

.product-side-card .card-header {
    background: #fff;
    padding: 16px 20px;
}

.product-side-card .card-body {
    padding: 20px;
}

.product-side-preview {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 10px;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.edit-product-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 991.98px) {

    .edit-product-page {
        padding: 18px;
    }

}


@media (max-width: 767.98px) {

    .edit-product-page {
        padding: 14px;
    }

    .edit-product-card-body {
        padding: 18px;
    }

    .edit-product-actions {
        flex-direction: column;
    }

    .edit-product-actions .btn {
        width: 100%;
    }

}


@media (max-width: 420px) {

    .edit-product-page {
        padding: 10px;
    }

    .edit-product-card-body {
        padding: 14px;
    }

}

</style>


<div class="container-fluid edit-product-page">


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 edit-product-header">

        <div class="d-flex align-items-center gap-3">

            <div class="edit-product-icon bg-primary text-white rounded-3 d-flex align-items-center justify-content-center">

                <i class="bi bi-pencil-square fs-4"></i>

            </div>


            <div>

                <h2 class="fw-bold mb-1">
                    Edit Product
                </h2>

                <p class="text-muted mb-0">
                    Update product information, pricing and images.
                </p>

            </div>

        </div>


        <a
            href="products.php"
            class="btn btn-outline-secondary"
        >

            <i class="bi bi-arrow-left me-1"></i>

            Back to Products

        </a>

    </div>



    <!-- =========================================================
         ERRORS
    ========================================================== -->

    <?php if (!empty($errors)): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <div class="fw-semibold mb-2">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                Please fix the following:

            </div>


            <ul class="mb-0">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error); ?>
                    </li>

                <?php endforeach; ?>

            </ul>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>



    <!-- =========================================================
         FORM
    ========================================================== -->

    <form
        method="POST"
        enctype="multipart/form-data"
        id="editProductForm"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($csrfToken); ?>"
        >


        <div class="row g-4">


            <!-- =====================================================
                 MAIN
            ====================================================== -->

            <div class="col-xl-8">

                <div class="card shadow-sm edit-product-card">


                    <div class="card-header bg-primary text-white edit-product-card-header">

                        <div class="d-flex align-items-center justify-content-between gap-3">

                            <h5 class="mb-0">

                                <i class="bi bi-box-seam me-2"></i>

                                Product Information

                            </h5>


                            <span class="badge bg-light text-dark">

                                ID #<?= (int)$productId; ?>

                            </span>

                        </div>

                    </div>



                    <div class="card-body edit-product-card-body">


                        <!-- =================================================
                             BASIC INFORMATION
                        ================================================== -->

                        <div class="product-form-section">

                            <div class="product-section-title">

                                <i class="bi bi-info-circle me-1"></i>

                                Basic Information

                            </div>


                            <div class="row g-3">


                                <!-- PRODUCT NAME -->

                                <div class="col-lg-6">

                                    <label
                                        for="product_name"
                                        class="form-label"
                                    >

                                        Product Name
                                        <span class="text-danger">*</span>

                                    </label>


                                    <input
                                        type="text"
                                        name="product_name"
                                        id="product_name"
                                        class="form-control"
                                        maxlength="150"
                                        required
                                        value="<?= e($productName); ?>"
                                    >

                                </div>



                                <!-- SLUG -->

                                <div class="col-lg-6">

                                    <label
                                        for="slug"
                                        class="form-label"
                                    >

                                        Slug

                                    </label>


                                    <input
                                        type="text"
                                        name="slug"
                                        id="slug"
                                        class="form-control"
                                        value="<?= e($slug); ?>"
                                    >

                                </div>



                                <!-- CATEGORY -->

                                <div class="col-lg-6">

                                    <label
                                        for="category_id"
                                        class="form-label"
                                    >

                                        Category
                                        <span class="text-danger">*</span>

                                    </label>


                                    <select
                                        name="category_id"
                                        id="category_id"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Select Category
                                        </option>


                                        <?php foreach (
                                            $categories as $cat
                                        ): ?>

                                            <option
                                                value="<?= (int)$cat['category_id']; ?>"
                                                <?= (string)$categoryId ===
                                                    (string)$cat['category_id']
                                                        ? 'selected'
                                                        : ''; ?>
                                            >

                                                <?= e(
                                                    $cat['category_name']
                                                ); ?>

                                                <?php if (
                                                    ($cat['status'] ?? '')
                                                    === 'Inactive'
                                                ): ?>

                                                    (Inactive)

                                                <?php endif; ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>



                                <!-- FOOD TYPE -->

                                <div class="col-lg-6">

                                    <label
                                        for="food_type"
                                        class="form-label"
                                    >

                                        Food Type
                                        <span class="text-danger">*</span>

                                    </label>


                                    <select
                                        name="food_type"
                                        id="food_type"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Select Food Type
                                        </option>

                                        <option
                                            value="Veg"
                                            <?= $foodType === 'Veg'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Veg
                                        </option>

                                        <option
                                            value="Non-Veg"
                                            <?= $foodType === 'Non-Veg'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Non-Veg
                                        </option>

                                        <option
                                            value="Egg"
                                            <?= $foodType === 'Egg'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Egg
                                        </option>

                                    </select>

                                </div>



                                <!-- SHORT DESCRIPTION -->

                                <div class="col-12">

                                    <label
                                        for="short_description"
                                        class="form-label"
                                    >

                                        Short Description

                                    </label>


                                    <textarea
                                        name="short_description"
                                        id="short_description"
                                        class="form-control"
                                        rows="2"
                                        maxlength="500"
                                    ><?= e($shortDescription); ?></textarea>


                                    <div
                                        class="form-text text-end"
                                        id="shortDescriptionCount"
                                    >
                                        0 / 500
                                    </div>

                                </div>



                                <!-- DESCRIPTION -->

                                <div class="col-12">

                                    <label
                                        for="description"
                                        class="form-label"
                                    >

                                        Description

                                    </label>


                                    <textarea
                                        name="description"
                                        id="description"
                                        class="form-control"
                                        rows="5"
                                    ><?= e($description); ?></textarea>

                                </div>

                            </div>

                        </div>



                        <!-- =================================================
                             PRICE & INVENTORY
                        ================================================== -->

                        <div class="product-form-section">

                            <div class="product-section-title">

                                <i class="bi bi-currency-rupee me-1"></i>

                                Pricing & Inventory

                            </div>


                            <div class="row g-3">


                                <div class="col-lg-4 col-md-6">

                                    <label
                                        for="price"
                                        class="form-label"
                                    >

                                        Price
                                        <span class="text-danger">*</span>

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ₹
                                        </span>


                                        <input
                                            type="number"
                                            name="price"
                                            id="price"
                                            class="form-control"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            value="<?= e($price); ?>"
                                        >

                                    </div>

                                </div>



                                <div class="col-lg-4 col-md-6">

                                    <label
                                        for="discount_price"
                                        class="form-label"
                                    >

                                        Discount Price

                                    </label>


                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ₹
                                        </span>


                                        <input
                                            type="number"
                                            name="discount_price"
                                            id="discount_price"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            value="<?= e($discountPrice); ?>"
                                        >

                                    </div>


                                    <?php if ($discountPercentage > 0): ?>

                                        <div class="form-text text-success">

                                            <?= $discountPercentage; ?>%
                                            discount

                                        </div>

                                    <?php endif; ?>

                                </div>



                                <div class="col-lg-4 col-md-6">

                                    <label
                                        for="stock"
                                        class="form-label"
                                    >

                                        Stock
                                        <span class="text-danger">*</span>

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
                                    >

                                </div>



                                <div class="col-lg-4 col-md-6">

                                    <label
                                        for="preparation_time"
                                        class="form-label"
                                    >

                                        Preparation Time

                                    </label>


                                    <div class="input-group">

                                        <input
                                            type="number"
                                            name="preparation_time"
                                            id="preparation_time"
                                            class="form-control"
                                            min="0"
                                            max="1440"
                                            step="1"
                                            value="<?= e($preparationTime); ?>"
                                        >

                                        <span class="input-group-text">
                                            min
                                        </span>

                                    </div>

                                </div>



                                <div class="col-lg-4 col-md-6">

                                    <label
                                        for="spice_level"
                                        class="form-label"
                                    >

                                        Spice Level

                                    </label>


                                    <select
                                        name="spice_level"
                                        id="spice_level"
                                        class="form-select"
                                    >

                                        <option value="">
                                            Select Spice Level
                                        </option>

                                        <option
                                            value="Mild"
                                            <?= $spiceLevel === 'Mild'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Mild
                                        </option>

                                        <option
                                            value="Medium"
                                            <?= $spiceLevel === 'Medium'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Medium
                                        </option>

                                        <option
                                            value="Hot"
                                            <?= $spiceLevel === 'Hot'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Hot
                                        </option>

                                    </select>

                                </div>



                                <div class="col-lg-4 col-md-6">

                                    <label
                                        for="availability"
                                        class="form-label"
                                    >

                                        Availability

                                    </label>


                                    <select
                                        name="availability"
                                        id="availability"
                                        class="form-select"
                                    >

                                        <option
                                            value="Available"
                                            <?= $availability === 'Available'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Available
                                        </option>

                                        <option
                                            value="Unavailable"
                                            <?= $availability === 'Unavailable'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Unavailable
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>



                        <!-- =================================================
                             SETTINGS
                        ================================================== -->

                        <div class="product-form-section">

                            <div class="product-section-title">

                                <i class="bi bi-sliders me-1"></i>

                                Product Settings

                            </div>


                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label
                                        for="status"
                                        class="form-label"
                                    >

                                        Status

                                    </label>


                                    <select
                                        name="status"
                                        id="status"
                                        class="form-select"
                                    >

                                        <option
                                            value="Active"
                                            <?= $status === 'Active'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Active
                                        </option>

                                        <option
                                            value="Inactive"
                                            <?= $status === 'Inactive'
                                                ? 'selected'
                                                : ''; ?>
                                        >
                                            Inactive
                                        </option>

                                    </select>

                                </div>



                                <div class="col-md-6 d-flex align-items-end">

                                    <div class="form-check mb-2">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="featured"
                                            id="featured"
                                            value="1"
                                            <?= $featured === 1
                                                ? 'checked'
                                                : ''; ?>
                                        >

                                        <label
                                            class="form-check-label fw-semibold"
                                            for="featured"
                                        >

                                            Featured Product

                                        </label>

                                    </div>

                                </div>

                            </div>

                        </div>



                        <!-- =================================================
                             EXISTING IMAGES
                        ================================================== -->

                        <div class="product-form-section">

                            <div class="product-section-title">

                                <i class="bi bi-images me-1"></i>

                                Existing Product Images

                            </div>


                            <?php if (!empty($productImages)): ?>

                                <div class="product-image-grid">

                                    <?php foreach (
                                        $productImages as $image
                                    ): ?>

                                        <?php

                                        $imageName =
                                            basename(
                                                (string)$image['image_name']
                                            );

                                        $imagePath =
                                            "../assets/images/products/"
                                            . $imageName;

                                        ?>

                                        <div class="product-image-card">

                                            <?php if (
                                                (int)$image['is_primary'] === 1
                                            ): ?>

                                                <span
                                                    class="badge bg-primary primary-image-badge"
                                                >

                                                    <i class="bi bi-star-fill me-1"></i>

                                                    Primary

                                                </span>

                                            <?php endif; ?>


                                            <?php if (
                                                is_file(
                                                    __DIR__
                                                    . "/../assets/images/products/"
                                                    . $imageName
                                                )
                                            ): ?>

                                                <img
                                                    src="<?= e($imagePath); ?>"
                                                    alt="<?= e($productName); ?>"
                                                    class="product-existing-image"
                                                >

                                            <?php else: ?>

                                                <div
                                                    class="product-existing-image d-flex align-items-center justify-content-center text-muted"
                                                >

                                                    <i class="bi bi-image fs-2"></i>

                                                </div>

                                            <?php endif; ?>


                                            <div class="product-image-card-body">

                                                <div class="small text-muted text-truncate">

                                                    <?= e(
                                                        $imageName
                                                    ); ?>

                                                </div>


                                                <div class="form-check image-delete-check">

                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input delete-image-checkbox"
                                                        name="delete_images[]"
                                                        value="<?= (int)$image['image_id']; ?>"
                                                        id="delete_image_<?= (int)$image['image_id']; ?>"
                                                    >


                                                    <label
                                                        class="form-check-label text-danger small"
                                                        for="delete_image_<?= (int)$image['image_id']; ?>"
                                                    >

                                                        Delete image

                                                    </label>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <div class="alert alert-light border mb-0">

                                    <i class="bi bi-image me-1"></i>

                                    No product images uploaded yet.

                                </div>

                            <?php endif; ?>

                        </div>



                        <!-- =================================================
                             NEW IMAGES
                        ================================================== -->

                        <div class="product-form-section">

                            <div class="product-section-title">

                                <i class="bi bi-cloud-arrow-up me-1"></i>

                                Add New Images

                            </div>


                            <div
                                class="product-upload-box"
                                id="uploadBox"
                            >

                                <i class="bi bi-images fs-1 text-muted"></i>


                                <h6 class="fw-bold mt-2">
                                    Add product images
                                </h6>


                                <p class="text-muted small mb-3">

                                    JPG, PNG or WEBP
                                    <br>
                                    Maximum 5 MB per image

                                </p>


                                <label
                                    for="product_images"
                                    class="btn btn-outline-primary"
                                >

                                    <i class="bi bi-folder2-open me-1"></i>

                                    Choose Images

                                </label>


                                <input
                                    type="file"
                                    name="product_images[]"
                                    id="product_images"
                                    class="d-none"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >


                                <div
                                    id="selectedFiles"
                                    class="small text-muted mt-3"
                                ></div>


                                <div
                                    id="newImagePreview"
                                    class="new-image-preview-grid"
                                ></div>

                            </div>

                        </div>



                        <!-- =================================================
                             ACTIONS
                        ================================================== -->

                        <div class="edit-product-actions">

                            <button
                                type="submit"
                                name="update_product"
                                value="1"
                                class="btn btn-primary px-4"
                                id="updateProductBtn"
                            >

                                <i class="bi bi-check-circle me-1"></i>

                                Save Changes

                            </button>


                            <a
                                href="products.php"
                                class="btn btn-outline-secondary px-4"
                            >

                                <i class="bi bi-x-circle me-1"></i>

                                Cancel

                            </a>

                        </div>

                    </div>

                </div>

            </div>



            <!-- =====================================================
                 SIDE COLUMN
            ====================================================== -->

            <div class="col-xl-4">


                <!-- PREVIEW -->

                <div class="card product-side-card">

                    <div class="card-header">

                        <h6 class="fw-bold mb-0">

                            <i class="bi bi-eye me-2 text-primary"></i>

                            Product Preview

                        </h6>

                    </div>


                    <div class="card-body">

                        <?php

                        $previewImage = null;

                        foreach (
                            $productImages as $img
                        ) {

                            if (
                                (int)$img['is_primary'] === 1
                            ) {

                                $previewImage =
                                    basename(
                                        (string)$img['image_name']
                                    );

                                break;
                            }
                        }


                        if (
                            $previewImage === null &&
                            !empty($productImages)
                        ) {

                            $previewImage =
                                basename(
                                    (string)$productImages[0]['image_name']
                                );
                        }

                        ?>


                        <?php if (
                            $previewImage !== null &&
                            is_file(
                                __DIR__
                                . "/../assets/images/products/"
                                . $previewImage
                            )
                        ): ?>

                            <img
                                src="../assets/images/products/<?= e($previewImage); ?>"
                                id="sidePreviewImage"
                                class="product-side-preview"
                                alt="<?= e($productName); ?>"
                            >

                        <?php else: ?>

                            <div
                                id="sidePreviewPlaceholder"
                                class="product-side-preview bg-light d-flex align-items-center justify-content-center text-muted"
                            >

                                <i class="bi bi-image fs-1"></i>

                            </div>

                        <?php endif; ?>


                        <h5
                            class="fw-bold mt-3 mb-1"
                            id="sideProductName"
                        >

                            <?= e($productName); ?>

                        </h5>


                        <div
                            class="text-muted small mb-3"
                            id="sideProductCategory"
                        >

                            Category

                        </div>


                        <div class="d-flex flex-wrap gap-2">

                            <span
                                id="sideFoodType"
                                class="badge bg-success"
                            >

                                <?= e($foodType); ?>

                            </span>


                            <span
                                id="sideStatus"
                                class="badge <?= $status === 'Active'
                                    ? 'bg-success'
                                    : 'bg-danger'; ?>"
                            >

                                <?= e($status); ?>

                            </span>

                        </div>

                    </div>

                </div>



                <!-- PRODUCT INFO -->

                <div class="card product-side-card">

                    <div class="card-header">

                        <h6 class="fw-bold mb-0">

                            <i class="bi bi-info-circle me-2 text-primary"></i>

                            Product Information

                        </h6>

                    </div>


                    <div class="card-body">

                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-muted">
                                Product ID
                            </span>

                            <strong>
                                #<?= (int)$productId; ?>
                            </strong>

                        </div>


                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-muted">
                                Created
                            </span>

                            <strong>
                                <?= !empty($product['created_at'])
                                    ? date(
                                        'd M Y',
                                        strtotime(
                                            $product['created_at']
                                        )
                                    )
                                    : '—'; ?>
                            </strong>

                        </div>


                        <div class="d-flex justify-content-between">

                            <span class="text-muted">
                                Updated
                            </span>

                            <strong>
                                <?= !empty($product['updated_at'])
                                    ? date(
                                        'd M Y',
                                        strtotime(
                                            $product['updated_at']
                                        )
                                    )
                                    : '—'; ?>
                            </strong>

                        </div>

                    </div>

                </div>



                <!-- QUICK LINKS -->

                <div class="card product-side-card">

                    <div class="card-body">

                        <a
                            href="products.php"
                            class="btn btn-outline-primary w-100 mb-2"
                        >

                            <i class="bi bi-grid me-1"></i>

                            All Products

                        </a>


                        <a
                            href="add_product.php"
                            class="btn btn-outline-success w-100"
                        >

                            <i class="bi bi-plus-circle me-1"></i>

                            Add New Product

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </form>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Product Name → Slug
|--------------------------------------------------------------------------
*/

const productName =
    document.getElementById(
        "product_name"
    );

const slug =
    document.getElementById(
        "slug"
    );


function createSlug(value)
{
    return value
        .toLowerCase()
        .trim()
        .replace(
            /[^a-z0-9]+/g,
            "-"
        )
        .replace(
            /^-+|-+$/g,
            ""
        );
}


if (
    productName &&
    slug
) {

    productName.addEventListener(
        "input",
        function () {

            slug.value =
                createSlug(
                    this.value
                );

            const sideName =
                document.getElementById(
                    "sideProductName"
                );

            if (sideName) {

                sideName.textContent =
                    this.value.trim()
                    || "Product Name";
            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Short Description Counter
|--------------------------------------------------------------------------
*/

const shortDescription =
    document.getElementById(
        "short_description"
    );

const shortDescriptionCount =
    document.getElementById(
        "shortDescriptionCount"
    );


function updateShortDescriptionCount()
{
    if (
        shortDescription &&
        shortDescriptionCount
    ) {

        shortDescriptionCount.textContent =
            shortDescription.value.length
            + " / 500";
    }
}


if (shortDescription) {

    shortDescription.addEventListener(
        "input",
        updateShortDescriptionCount
    );

    updateShortDescriptionCount();
}


/*
|--------------------------------------------------------------------------
| Category Preview
|--------------------------------------------------------------------------
*/

const categorySelect =
    document.getElementById(
        "category_id"
    );

const sideProductCategory =
    document.getElementById(
        "sideProductCategory"
    );


function updateCategoryPreview()
{
    if (
        !categorySelect ||
        !sideProductCategory
    ) {

        return;
    }


    const selected =
        categorySelect.options[
            categorySelect.selectedIndex
        ];


    sideProductCategory.textContent =
        selected &&
        selected.value
            ? selected.text
            : "Category";
}


if (categorySelect) {

    categorySelect.addEventListener(
        "change",
        updateCategoryPreview
    );

    updateCategoryPreview();
}


/*
|--------------------------------------------------------------------------
| Food Type Preview
|--------------------------------------------------------------------------
*/

const foodType =
    document.getElementById(
        "food_type"
    );

const sideFoodType =
    document.getElementById(
        "sideFoodType"
    );


function updateFoodType()
{
    if (
        !foodType ||
        !sideFoodType
    ) {

        return;
    }


    sideFoodType.textContent =
        foodType.value
        || "Food Type";


    let className =
        "badge bg-success";


    if (
        foodType.value === "Non-Veg"
    ) {

        className =
            "badge bg-danger";

    } else if (
        foodType.value === "Egg"
    ) {

        className =
            "badge bg-warning text-dark";
    }


    sideFoodType.className =
        className;
}


if (foodType) {

    foodType.addEventListener(
        "change",
        updateFoodType
    );

}


/*
|--------------------------------------------------------------------------
| Status Preview
|--------------------------------------------------------------------------
*/

const status =
    document.getElementById(
        "status"
    );

const sideStatus =
    document.getElementById(
        "sideStatus"
    );


function updateStatus()
{
    if (
        !status ||
        !sideStatus
    ) {

        return;
    }


    sideStatus.textContent =
        status.value;


    sideStatus.className =
        status.value === "Active"
            ? "badge bg-success"
            : "badge bg-danger";
}


if (status) {

    status.addEventListener(
        "change",
        updateStatus
    );

}


/*
|--------------------------------------------------------------------------
| New Image Preview
|--------------------------------------------------------------------------
*/

const imageInput =
    document.getElementById(
        "product_images"
    );

const newImagePreview =
    document.getElementById(
        "newImagePreview"
    );

const selectedFiles =
    document.getElementById(
        "selectedFiles"
    );


if (
    imageInput &&
    newImagePreview
) {

    imageInput.addEventListener(
        "change",
        function () {

            newImagePreview.innerHTML = "";


            if (selectedFiles) {

                selectedFiles.textContent =
                    this.files.length > 0
                        ? this.files.length
                          + " image(s) selected"
                        : "";
            }


            Array.from(this.files)
                .forEach(
                    function (file) {

                        if (
                            !file.type.startsWith(
                                "image/"
                            )
                        ) {

                            return;
                        }


                        const reader =
                            new FileReader();


                        reader.onload =
                            function (event) {

                                const wrapper =
                                    document.createElement(
                                        "div"
                                    );


                                const img =
                                    document.createElement(
                                        "img"
                                    );


                                img.src =
                                    event.target.result;

                                img.alt =
                                    "New product image";

                                img.className =
                                    "new-image-preview";


                                wrapper.appendChild(
                                    img
                                );


                                newImagePreview.appendChild(
                                    wrapper
                                );

                            };


                        reader.readAsDataURL(
                            file
                        );

                    }
                );

        }
    );

}


/*
|--------------------------------------------------------------------------
| Delete Image Warning
|--------------------------------------------------------------------------
*/

const deleteCheckboxes =
    document.querySelectorAll(
        ".delete-image-checkbox"
    );


deleteCheckboxes.forEach(
    function (checkbox) {

        checkbox.addEventListener(
            "change",
            function () {

                const card =
                    this.closest(
                        ".product-image-card"
                    );


                if (!card) {
                    return;
                }


                if (this.checked) {

                    card.style.opacity =
                        "0.55";

                } else {

                    card.style.opacity =
                        "1";
                }

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| Form Submit
|--------------------------------------------------------------------------
*/

const editProductForm =
    document.getElementById(
        "editProductForm"
    );


if (editProductForm) {

    editProductForm.addEventListener(
        "submit",
        function (event) {

            const price =
                parseFloat(
                    document.getElementById(
                        "price"
                    ).value
                );


            const discount =
                parseFloat(
                    document.getElementById(
                        "discount_price"
                    ).value
                );


            if (
                !Number.isNaN(discount) &&
                discount >= price
            ) {

                event.preventDefault();

                alert(
                    "Discount price must be lower than the original price."
                );

                document
                    .getElementById(
                        "discount_price"
                    )
                    .focus();

                return;
            }


            const selectedDeleteImages =
                document.querySelectorAll(
                    ".delete-image-checkbox:checked"
                );


            if (
                selectedDeleteImages.length > 0
            ) {

                const confirmed =
                    confirm(
                        "Are you sure you want to delete the selected product image(s)?"
                    );


                if (!confirmed) {

                    event.preventDefault();

                }

            }

        }
    );

}

</script>


<?php

require_once "includes/a-footer.php";

?>