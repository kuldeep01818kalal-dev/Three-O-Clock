<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Edit Category";

$errors = [];


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Get Category ID
|--------------------------------------------------------------------------
*/

$categoryId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (
    !$categoryId
    || $categoryId < 1
) {

    $_SESSION['error'] =
        "Invalid category selected.";

    header(
        "Location: categories.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Fetch Category
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        category_id,
        category_name,
        category_image,
        description,
        status,
        created_at,
        updated_at
    FROM categories
    WHERE category_id = ?
    LIMIT 1
");

$stmt->execute([
    $categoryId
]);

$category =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$category) {

    $_SESSION['error'] =
        "Category not found.";

    header(
        "Location: categories.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

$categoryName =
    trim(
        (string)(
            $_POST['category_name']
            ?? $category['category_name']
        )
    );

$description =
    trim(
        (string)(
            $_POST['description']
            ?? ($category['description'] ?? '')
        )
    );

$status =
    trim(
        (string)(
            $_POST['status']
            ?? $category['status']
        )
    );


/*
|--------------------------------------------------------------------------
| Update Category
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_category'])
) {

    /*
    |--------------------------------------------------------------------------
    | Validate Name
    |--------------------------------------------------------------------------
    */

    if ($categoryName === '') {

        $errors[] =
            "Category name is required.";

    } elseif (
        mb_strlen($categoryName) < 2
    ) {

        $errors[] =
            "Category name must contain at least 2 characters.";

    } elseif (
        mb_strlen($categoryName) > 100
    ) {

        $errors[] =
            "Category name cannot exceed 100 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $status,
            [
                'Active',
                'Inactive'
            ],
            true
        )
    ) {

        $errors[] =
            "Invalid category status.";
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Name
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors)
    ) {

        $duplicateStmt =
            $pdo->prepare("
                SELECT category_id
                FROM categories
                WHERE LOWER(category_name) = LOWER(?)
                AND category_id != ?
                LIMIT 1
            ");

        $duplicateStmt->execute([
            $categoryName,
            $categoryId
        ]);


        if (
            $duplicateStmt->fetch()
        ) {

            $errors[] =
                "Another category with this name already exists.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Image Upload
    |--------------------------------------------------------------------------
    */

    $newImage = null;

    if (
        isset($_FILES['category_image'])
        &&
        $_FILES['category_image']['error']
        !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['category_image']['error']
            !== UPLOAD_ERR_OK
        ) {

            $errors[] =
                "Unable to upload the category image.";

        } else {

            $tmpName =
                $_FILES['category_image']['tmp_name'];

            $originalName =
                $_FILES['category_image']['name'];

            $fileSize =
                (int)(
                    $_FILES['category_image']['size']
                    ?? 0
                );

            $extension =
                strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                );


            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];


            if (
                !in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )
            ) {

                $errors[] =
                    "Only JPG, JPEG, PNG and WEBP images are allowed.";

            } elseif (
                $fileSize > 5 * 1024 * 1024
            ) {

                $errors[] =
                    "Category image must be smaller than 5 MB.";

            } elseif (
                !is_uploaded_file($tmpName)
            ) {

                $errors[] =
                    "Invalid image upload.";

            } elseif (
                @getimagesize($tmpName) === false
            ) {

                $errors[] =
                    "The uploaded file is not a valid image.";

            } else {

                $newImage = [
                    'tmp_name' =>
                        $tmpName,

                    'extension' =>
                        $extension
                ];
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Existing Image
    |--------------------------------------------------------------------------
    */

    $removeImage =
        isset(
            $_POST['remove_image']
        )
        &&
        $_POST['remove_image'] === '1';


    /*
    |--------------------------------------------------------------------------
    | Save Changes
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors)
    ) {

        $newImagePath = null;

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Lock Category
            |--------------------------------------------------------------------------
            */

            $lockStmt =
                $pdo->prepare("
                    SELECT
                        category_id,
                        category_image
                    FROM categories
                    WHERE category_id = ?
                    FOR UPDATE
                ");

            $lockStmt->execute([
                $categoryId
            ]);

            $lockedCategory =
                $lockStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$lockedCategory
            ) {

                throw new RuntimeException(
                    "Category no longer exists."
                );
            }


            $oldImage =
                $lockedCategory['category_image']
                ?? null;


            /*
            |--------------------------------------------------------------------------
            | Upload Directory
            |--------------------------------------------------------------------------
            */

            $uploadDir =
                "../assets/images/categories/";


            if (
                (
                    $newImage !== null
                    ||
                    $removeImage
                )
                &&
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
                        "Unable to create image directory."
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Determine Image
            |--------------------------------------------------------------------------
            */

            $imageName =
                $oldImage;


            /*
            |--------------------------------------------------------------------------
            | New Image
            |--------------------------------------------------------------------------
            */

            if (
                $newImage !== null
            ) {

                $imageName =
                    'category_' .
                    $categoryId .
                    '_' .
                    bin2hex(
                        random_bytes(8)
                    ) .
                    '.' .
                    $newImage['extension'];


                $newImagePath =
                    $uploadDir .
                    $imageName;


                if (
                    !move_uploaded_file(
                        $newImage['tmp_name'],
                        $newImagePath
                    )
                ) {

                    throw new RuntimeException(
                        "Unable to save the new category image."
                    );
                }

            } elseif (
                $removeImage
            ) {

                $imageName = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Update Category
            |--------------------------------------------------------------------------
            */

            $updateStmt =
                $pdo->prepare("
                    UPDATE categories
                    SET
                        category_name = :category_name,
                        category_image = :category_image,
                        description = :description,
                        status = :status
                    WHERE category_id = :category_id
                ");


            $updateStmt->execute([

                ':category_name' =>
                    $categoryName,

                ':category_image' =>
                    $imageName,

                ':description' =>
                    $description !== ''
                        ? $description
                        : null,

                ':status' =>
                    $status,

                ':category_id' =>
                    $categoryId

            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Remove Old Image
            |--------------------------------------------------------------------------
            */

            if (
                (
                    $newImage !== null
                    ||
                    $removeImage
                )
                &&
                !empty($oldImage)
            ) {

                $oldImagePath =
                    $uploadDir .
                    basename(
                        $oldImage
                    );


                if (
                    is_file($oldImagePath)
                ) {

                    @unlink(
                        $oldImagePath
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $_SESSION['success'] =
                "Category updated successfully.";


            header(
                "Location: categories.php"
            );

            exit;

        } catch (
            Throwable $e
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Remove New Image If Database Failed
            |--------------------------------------------------------------------------
            */

            if (
                $newImagePath !== null
                &&
                is_file($newImagePath)
            ) {

                @unlink(
                    $newImagePath
                );
            }


            $errors[] =
                "Unable to update category. Please try again.";
        }
    }
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
| EDIT CATEGORY PAGE
|--------------------------------------------------------------------------
*/

.category-edit-page {
    padding: 24px 28px 40px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.category-edit-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 24px;
}


.category-edit-title {
    display: flex;

    align-items: center;

    gap: 14px;
}


.category-edit-icon {
    width: 52px;
    height: 52px;

    border-radius: 12px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: rgba(
        13,
        110,
        253,
        0.10
    );

    color: #0d6efd;

    font-size: 23px;
}


.category-edit-title h2 {
    margin: 0;

    font-size: 28px;

    font-weight: 700;
}


.category-edit-title p {
    margin: 3px 0 0;

    color: #6c757d;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| FORM CARD
|--------------------------------------------------------------------------
*/

.category-edit-card {
    border: 0;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 2px 12px
        rgba(
            0,
            0,
            0,
            0.06
        );
}


.category-edit-card-header {
    padding: 17px 22px;

    background: #0d6efd;

    color: #fff;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.category-edit-card-header h5 {
    margin: 0;

    font-size: 17px;

    font-weight: 600;
}


.category-id-badge {
    background: rgba(
        255,
        255,
        255,
        0.18
    );

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;
}


.category-edit-body {
    padding: 28px;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.category-edit-body .form-label {
    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 600;
}


.category-edit-body .form-control,
.category-edit-body .form-select {
    min-height: 45px;

    border-radius: 8px;
}


.category-edit-body textarea {
    min-height: 130px;

    resize: vertical;
}


/*
|--------------------------------------------------------------------------
| CURRENT IMAGE
|--------------------------------------------------------------------------
*/

.category-current-image-box {
    border: 1px solid #dee2e6;

    border-radius: 12px;

    padding: 18px;

    text-align: center;

    background: #f8f9fa;
}


.category-current-image {
    width: 100%;

    max-width: 240px;

    height: 160px;

    object-fit: cover;

    border-radius: 10px;

    border: 1px solid #dee2e6;
}


.category-no-image {
    width: 100%;

    max-width: 240px;

    height: 160px;

    margin: auto;

    border-radius: 10px;

    background: #fff;

    border: 1px dashed #ced4da;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-direction: column;

    color: #adb5bd;
}


/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD
|--------------------------------------------------------------------------
*/

.category-edit-upload {
    border: 2px dashed #dee2e6;

    border-radius: 12px;

    padding: 20px;

    text-align: center;

    transition:
        border-color .2s ease,
        background .2s ease;
}


.category-edit-upload:hover {
    border-color: #0d6efd;

    background:
        rgba(
            13,
            110,
            253,
            0.02
        );
}


.category-new-preview {
    display: none;

    width: 100%;

    max-width: 240px;

    height: 150px;

    object-fit: cover;

    border-radius: 10px;

    margin: 15px auto 0;

    border: 1px solid #dee2e6;
}


/*
|--------------------------------------------------------------------------
| INFO CARDS
|--------------------------------------------------------------------------
*/

.category-edit-info-card {
    border: 0;

    border-radius: 12px;

    box-shadow:
        0 2px 12px
        rgba(
            0,
            0,
            0,
            0.06
        );

    margin-bottom: 20px;
}


.category-edit-info-card .card-header {
    background: #fff;

    padding: 16px 20px;

    border-bottom: 1px solid #eee;
}


.category-edit-info-card .card-body {
    padding: 20px;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.category-edit-actions {
    display: flex;

    gap: 10px;

    padding-top: 10px;
}


.category-edit-actions .btn {
    min-height: 44px;

    border-radius: 8px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 991.98px) {

    .category-edit-page {
        padding: 20px 15px 30px;
    }


    .category-edit-header {
        align-items: flex-start;

        flex-direction: column;
    }


    .category-edit-header > a {
        width: 100%;
    }


    .category-edit-body {
        padding: 20px;
    }

}


@media (max-width: 575.98px) {

    .category-edit-title h2 {
        font-size: 23px;
    }


    .category-edit-title p {
        font-size: 13px;
    }


    .category-edit-body {
        padding: 16px;
    }


    .category-edit-actions {
        flex-direction: column;
    }


    .category-edit-actions .btn {
        width: 100%;
    }

}

</style>


<div class="category-edit-page">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="category-edit-header">


        <div class="category-edit-title">

            <div class="category-edit-icon">

                <i class="bi bi-pencil-square"></i>

            </div>


            <div>

                <h2>

                    Edit Category

                </h2>


                <p>

                    Update category information and settings.

                </p>

            </div>

        </div>


        <a
            href="categories.php"
            class="btn btn-outline-secondary"
        >

            <i
                class="bi bi-arrow-left me-1"
            ></i>

            Back to Categories

        </a>

    </div>



    <!-- =========================================================
         ERRORS
    ========================================================== -->

    <?php if (
        !empty($errors)
    ): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
        >

            <div class="fw-semibold mb-2">

                <i
                    class="bi bi-exclamation-triangle-fill me-1"
                ></i>

                Please fix the following:

            </div>


            <ul class="mb-0">

                <?php foreach (
                    $errors
                    as $error
                ): ?>

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
        id="editCategoryForm"
    >

        <div class="row g-4">


            <!-- =====================================================
                 MAIN COLUMN
            ====================================================== -->

            <div class="col-xl-8">


                <div class="card category-edit-card">


                    <div
                        class="category-edit-card-header"
                    >

                        <div
                            class="d-flex align-items-center gap-2"
                        >

                            <i
                                class="bi bi-folder2-open"
                            ></i>


                            <h5>

                                Category Information

                            </h5>

                        </div>


                        <span
                            class="category-id-badge"
                        >

                            ID #<?= (int)$categoryId; ?>

                        </span>

                    </div>



                    <div class="category-edit-body">


                        <div class="row g-4">


                            <!-- CATEGORY NAME -->

                            <div class="col-12">

                                <label
                                    for="category_name"
                                    class="form-label"
                                >

                                    Category Name

                                    <span class="text-danger">*</span>

                                </label>


                                <input
                                    type="text"
                                    name="category_name"
                                    id="category_name"
                                    class="form-control form-control-lg"
                                    maxlength="100"
                                    value="<?= e($categoryName); ?>"
                                    required
                                    autofocus
                                >


                                <div class="form-text">

                                    Maximum 100 characters.

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
                                    maxlength="500"
                                    placeholder="Write a short description..."
                                ><?= e($description); ?></textarea>


                                <div
                                    class="d-flex justify-content-between mt-1"
                                >

                                    <small class="text-muted">

                                        Optional

                                    </small>


                                    <small
                                        class="text-muted"
                                        id="descriptionCount"
                                    >

                                        0 / 500

                                    </small>

                                </div>

                            </div>



                            <!-- STATUS -->

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



                            <!-- CREATED DATE -->

                            <div class="col-md-6">

                                <label
                                    class="form-label"
                                >

                                    Created

                                </label>


                                <div
                                    class="form-control bg-light"
                                >

                                    <i
                                        class="bi bi-calendar3 me-1 text-muted"
                                    ></i>

                                    <?= date(
                                        'd M Y, h:i A',
                                        strtotime(
                                            $category['created_at']
                                        )
                                    ); ?>

                                </div>

                            </div>



                            <!-- CURRENT IMAGE -->

                            <div class="col-md-6">

                                <label
                                    class="form-label"
                                >

                                    Current Image

                                </label>


                                <div
                                    class="category-current-image-box"
                                >

                                    <?php

                                    $currentImagePath =
                                        "../assets/images/categories/" .
                                        basename(
                                            (string)(
                                                $category['category_image']
                                                ?? ''
                                            )
                                        );

                                    $hasCurrentImage =
                                        !empty(
                                            $category['category_image']
                                        )
                                        &&
                                        is_file(
                                            $currentImagePath
                                        );

                                    ?>


                                    <?php if (
                                        $hasCurrentImage
                                    ): ?>

                                        <img
                                            src="<?= e($currentImagePath); ?>"
                                            alt="<?= e($categoryName); ?>"
                                            class="category-current-image"
                                            id="currentImage"
                                        >


                                        <div class="mt-3">

                                            <div
                                                class="form-check d-inline-flex"
                                            >

                                                <input
                                                    type="checkbox"
                                                    name="remove_image"
                                                    value="1"
                                                    id="remove_image"
                                                    class="form-check-input"
                                                >


                                                <label
                                                    for="remove_image"
                                                    class="form-check-label text-danger small"
                                                >

                                                    Remove current image

                                                </label>

                                            </div>

                                        </div>

                                    <?php else: ?>

                                        <div
                                            class="category-no-image"
                                        >

                                            <i
                                                class="bi bi-image fs-2 mb-2"
                                            ></i>


                                            <span class="small">

                                                No image uploaded

                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </div>



                            <!-- NEW IMAGE -->

                            <div class="col-md-6">

                                <label
                                    class="form-label"
                                >

                                    Replace Image

                                </label>


                                <div
                                    class="category-edit-upload"
                                    id="uploadBox"
                                >

                                    <i
                                        class="bi bi-cloud-arrow-up fs-2 text-muted"
                                    ></i>


                                    <div
                                        class="small text-muted mt-2 mb-3"
                                    >

                                        JPG, JPEG, PNG or WEBP
                                        <br>
                                        Maximum 5 MB

                                    </div>


                                    <label
                                        for="category_image"
                                        class="btn btn-outline-primary btn-sm"
                                    >

                                        <i
                                            class="bi bi-image me-1"
                                        ></i>

                                        Choose New Image

                                    </label>


                                    <input
                                        type="file"
                                        name="category_image"
                                        id="category_image"
                                        class="d-none"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >


                                    <div
                                        id="selectedFile"
                                        class="small text-muted mt-2"
                                    ></div>


                                    <img
                                        id="newImagePreview"
                                        class="category-new-preview"
                                        alt="New image preview"
                                    >

                                </div>

                            </div>


                        </div>



                        <!-- ACTIONS -->

                        <div
                            class="category-edit-actions mt-4"
                        >

                            <button
                                type="submit"
                                name="update_category"
                                value="1"
                                class="btn btn-primary px-4"
                                id="updateCategoryBtn"
                            >

                                <i
                                    class="bi bi-check-circle me-1"
                                ></i>

                                Save Changes

                            </button>


                            <a
                                href="categories.php"
                                class="btn btn-outline-secondary px-4"
                            >

                                <i
                                    class="bi bi-x-circle me-1"
                                ></i>

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


                <!-- CATEGORY PREVIEW -->

                <div
                    class="card category-edit-info-card"
                >

                    <div class="card-header">

                        <h6
                            class="mb-0 fw-bold"
                        >

                            <i
                                class="bi bi-eye me-2 text-primary"
                            ></i>

                            Category Preview

                        </h6>

                    </div>


                    <div class="card-body">

                        <div
                            class="text-center mb-3"
                        >

                            <?php if (
                                $hasCurrentImage
                            ): ?>

                                <img
                                    src="<?= e($currentImagePath); ?>"
                                    id="sidePreviewImage"
                                    class="category-current-image"
                                    alt="Category"
                                >

                            <?php else: ?>

                                <div
                                    class="category-no-image"
                                    id="sidePreviewPlaceholder"
                                >

                                    <i
                                        class="bi bi-folder fs-2"
                                    ></i>

                                </div>

                            <?php endif; ?>

                        </div>


                        <h5
                            class="text-center mb-1"
                            id="sideCategoryName"
                        >

                            <?= e($categoryName); ?>

                        </h5>


                        <div
                            class="text-center"
                        >

                            <span
                                id="sideCategoryStatus"
                                class="badge <?= $status === 'Active'
                                    ? 'bg-success'
                                    : 'bg-danger'; ?>"
                            >

                                <?= e($status); ?>

                            </span>

                        </div>

                    </div>

                </div>



                <!-- INFORMATION -->

                <div
                    class="card category-edit-info-card"
                >

                    <div class="card-header">

                        <h6
                            class="mb-0 fw-bold"
                        >

                            <i
                                class="bi bi-info-circle me-2 text-primary"
                            ></i>

                            Category Information

                        </h6>

                    </div>


                    <div class="card-body">


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span class="text-muted">

                                Category ID

                            </span>


                            <strong>

                                #<?= (int)$categoryId; ?>

                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span class="text-muted">

                                Created

                            </span>


                            <strong>

                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $category['created_at']
                                    )
                                ); ?>

                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between"
                        >

                            <span class="text-muted">

                                Last Updated

                            </span>


                            <strong>

                                <?= !empty(
                                    $category['updated_at']
                                )
                                    ? date(
                                        'd M Y',
                                        strtotime(
                                            $category['updated_at']
                                        )
                                    )
                                    : '—'; ?>

                            </strong>

                        </div>

                    </div>

                </div>



                <!-- QUICK LINKS -->

                <div
                    class="card category-edit-info-card"
                >

                    <div class="card-body">

                        <a
                            href="categories.php"
                            class="btn btn-outline-primary w-100 mb-2"
                        >

                            <i
                                class="bi bi-grid me-1"
                            ></i>

                            All Categories

                        </a>


                        <a
                            href="add_category.php"
                            class="btn btn-outline-success w-100"
                        >

                            <i
                                class="bi bi-plus-circle me-1"
                            ></i>

                            Add New Category

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
| Category Name Preview
|--------------------------------------------------------------------------
*/

const categoryName =
    document.getElementById(
        "category_name"
    );

const sideCategoryName =
    document.getElementById(
        "sideCategoryName"
    );


categoryName.addEventListener(
    "input",
    function () {

        const value =
            this.value.trim();


        sideCategoryName.textContent =
            value !== ''
                ? value
                : 'Category Name';

    }
);


/*
|--------------------------------------------------------------------------
| Status Preview
|--------------------------------------------------------------------------
*/

const status =
    document.getElementById(
        "status"
    );

const sideCategoryStatus =
    document.getElementById(
        "sideCategoryStatus"
    );


function updateStatus() {

    sideCategoryStatus.textContent =
        status.value;


    sideCategoryStatus.className =
        status.value === 'Active'
            ? 'badge bg-success'
            : 'badge bg-danger';

}


status.addEventListener(
    "change",
    updateStatus
);


/*
|--------------------------------------------------------------------------
| Description Counter
|--------------------------------------------------------------------------
*/

const description =
    document.getElementById(
        "description"
    );

const descriptionCount =
    document.getElementById(
        "descriptionCount"
    );


function updateDescriptionCount() {

    descriptionCount.textContent =
        description.value.length +
        " / 500";

}


description.addEventListener(
    "input",
    updateDescriptionCount
);

updateDescriptionCount();


/*
|--------------------------------------------------------------------------
| New Image Preview
|--------------------------------------------------------------------------
*/

const imageInput =
    document.getElementById(
        "category_image"
    );

const newImagePreview =
    document.getElementById(
        "newImagePreview"
    );

const selectedFile =
    document.getElementById(
        "selectedFile"
    );


imageInput.addEventListener(
    "change",
    function () {

        const file =
            this.files[0];


        if (!file) {

            newImagePreview.style.display =
                "none";

            selectedFile.textContent =
                "";

            return;
        }


        selectedFile.textContent =
            file.name;


        if (
            !file.type.startsWith(
                "image/"
            )
        ) {

            newImagePreview.style.display =
                "none";

            return;
        }


        const reader =
            new FileReader();


        reader.onload =
            function (event) {

                newImagePreview.src =
                    event.target.result;

                newImagePreview.style.display =
                    "block";


                const sidePreviewImage =
                    document.getElementById(
                        "sidePreviewImage"
                    );


                const sidePlaceholder =
                    document.getElementById(
                        "sidePreviewPlaceholder"
                    );


                if (
                    sidePreviewImage
                ) {

                    sidePreviewImage.src =
                        event.target.result;

                } else if (
                    sidePlaceholder
                ) {

                    sidePlaceholder.outerHTML = `

                        <img
                            src="${event.target.result}"
                            id="sidePreviewImage"
                            class="category-current-image"
                            alt="Category"
                        >

                    `;

                }

            };


        reader.readAsDataURL(
            file
        );

    }
);


/*
|--------------------------------------------------------------------------
| Drag & Drop
|--------------------------------------------------------------------------
*/

const uploadBox =
    document.getElementById(
        "uploadBox"
    );


[
    "dragenter",
    "dragover"
].forEach(
    function (eventName) {

        uploadBox.addEventListener(
            eventName,
            function (event) {

                event.preventDefault();

                uploadBox.style.borderColor =
                    "#0d6efd";

                uploadBox.style.background =
                    "rgba(13,110,253,0.04)";

            }
        );

    }
);


[
    "dragleave",
    "drop"
].forEach(
    function (eventName) {

        uploadBox.addEventListener(
            eventName,
            function (event) {

                event.preventDefault();

                uploadBox.style.borderColor =
                    "#dee2e6";

                uploadBox.style.background =
                    "";

            }
        );

    }
);


uploadBox.addEventListener(
    "drop",
    function (event) {

        const files =
            event.dataTransfer.files;


        if (
            files.length > 0
        ) {

            imageInput.files =
                files;


            imageInput.dispatchEvent(
                new Event(
                    "change"
                )
            );

        }

    }
);


/*
|--------------------------------------------------------------------------
| Remove Image Confirmation
|--------------------------------------------------------------------------
*/

const removeImage =
    document.getElementById(
        "remove_image"
    );


if (
    removeImage
) {

    removeImage.addEventListener(
        "change",
        function () {

            if (
                this.checked
            ) {

                if (
                    !confirm(
                        "Are you sure you want to remove the current category image?"
                    )
                ) {

                    this.checked =
                        false;
                }

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Submit Protection
|--------------------------------------------------------------------------
*/

const editCategoryForm =
    document.getElementById(
        "editCategoryForm"
    );

const updateButton =
    document.getElementById(
        "updateCategoryBtn"
    );


editCategoryForm.addEventListener(
    "submit",
    function (event) {

        if (
            categoryName.value.trim().length < 2
        ) {

            event.preventDefault();

            alert(
                "Category name must contain at least 2 characters."
            );

            categoryName.focus();

            return;
        }


        updateButton.disabled =
            true;


        updateButton.innerHTML = `

            <span
                class="spinner-border spinner-border-sm me-1"
            ></span>

            Saving...

        `;

    }
);

</script>


<?php

require_once "includes/a-footer.php";

?>