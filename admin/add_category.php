<?php

declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Add Category";

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
| Form Values
|--------------------------------------------------------------------------
*/

$categoryName = trim(
    (string)(
        $_POST['category_name'] ?? ''
    )
);

$description = trim(
    (string)(
        $_POST['description'] ?? ''
    )
);

$status = trim(
    (string)(
        $_POST['status'] ?? 'Active'
    )
);


/*
|--------------------------------------------------------------------------
| Add Category
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['add_category'])
) {

    /*
    |--------------------------------------------------------------------------
    | Validate Category Name
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
    | Check Duplicate Category
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
                LIMIT 1
            ");

        $duplicateStmt->execute([
            $categoryName
        ]);

        if (
            $duplicateStmt->fetch()
        ) {

            $errors[] =
                "A category with this name already exists.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Image Validation
    |--------------------------------------------------------------------------
    */

    $imageFile = null;

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


            /*
            |--------------------------------------------------------------
            | Allowed Extensions
            |--------------------------------------------------------------
            */

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

            } else {

                /*
                |----------------------------------------------------------
                | Verify Actual Image
                |----------------------------------------------------------
                */

                $imageInfo =
                    @getimagesize(
                        $tmpName
                    );

                if (
                    $imageInfo === false
                ) {

                    $errors[] =
                        "The uploaded file is not a valid image.";

                } else {

                    $imageFile = [
                        'tmp_name' =>
                            $tmpName,

                        'extension' =>
                            $extension
                    ];
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Category
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors)
    ) {

        $savedImagePath = null;

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Category
            |--------------------------------------------------------------------------
            */

            $insertStmt =
                $pdo->prepare("
                    INSERT INTO categories
                    (
                        category_name,
                        category_image,
                        description,
                        status
                    )
                    VALUES
                    (
                        :category_name,
                        :category_image,
                        :description,
                        :status
                    )
                ");


            /*
            |--------------------------------------------------------------------------
            | Temporary Image Name
            |--------------------------------------------------------------------------
            */

            $categoryImageName = null;


            /*
            |--------------------------------------------------------------------------
            | Create Upload Directory
            |--------------------------------------------------------------------------
            */

            if (
                $imageFile !== null
            ) {

                $uploadDir =
                    "../assets/images/categories/";


                if (
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
                            "Unable to create category image directory."
                        );
                    }
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Insert Without Image First
            |--------------------------------------------------------------------------
            */

            $insertStmt->execute([

                ':category_name' =>
                    $categoryName,

                ':category_image' =>
                    null,

                ':description' =>
                    $description !== ''
                        ? $description
                        : null,

                ':status' =>
                    $status

            ]);


            /*
            |--------------------------------------------------------------------------
            | Get New Category ID
            |--------------------------------------------------------------------------
            */

            $categoryId =
                (int)$pdo->lastInsertId();


            if (
                $categoryId < 1
            ) {

                throw new RuntimeException(
                    "Unable to create category."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Save Image
            |--------------------------------------------------------------------------
            */

            if (
                $imageFile !== null
            ) {

                $categoryImageName =
                    'category_' .
                    $categoryId .
                    '_' .
                    bin2hex(
                        random_bytes(8)
                    ) .
                    '.' .
                    $imageFile['extension'];


                $destination =
                    $uploadDir .
                    $categoryImageName;


                if (
                    !move_uploaded_file(
                        $imageFile['tmp_name'],
                        $destination
                    )
                ) {

                    throw new RuntimeException(
                        "Unable to save category image."
                    );
                }


                $savedImagePath =
                    $destination;


                /*
                |----------------------------------------------------------
                | Update Image Name
                |----------------------------------------------------------
                */

                $imageUpdate =
                    $pdo->prepare("
                        UPDATE categories
                        SET category_image = ?
                        WHERE category_id = ?
                    ");


                $imageUpdate->execute([
                    $categoryImageName,
                    $categoryId
                ]);
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
                "Category added successfully.";


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
            | Remove Uploaded File If Database Failed
            |--------------------------------------------------------------------------
            */

            if (
                $savedImagePath !== null
                &&
                is_file($savedImagePath)
            ) {

                @unlink(
                    $savedImagePath
                );
            }


            $errors[] =
                "Unable to add category. Please try again.";
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
| ADD CATEGORY PAGE
|--------------------------------------------------------------------------
*/

.category-add-page {
    padding: 24px 28px 40px;
}


/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

.category-add-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 24px;
}


.category-add-title {
    display: flex;

    align-items: center;

    gap: 14px;
}


.category-add-icon {
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


.category-add-title h2 {
    margin: 0;

    font-size: 28px;

    font-weight: 700;
}


.category-add-title p {
    margin: 3px 0 0;

    color: #6c757d;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| FORM CARD
|--------------------------------------------------------------------------
*/

.category-form-card {
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

    overflow: hidden;
}


.category-form-header {
    padding: 17px 22px;

    background: #0d6efd;

    color: #fff;

    display: flex;

    align-items: center;

    gap: 10px;
}


.category-form-header h5 {
    margin: 0;

    font-size: 17px;

    font-weight: 600;
}


.category-form-body {
    padding: 28px;
}


/*
|--------------------------------------------------------------------------
| FORM LABELS
|--------------------------------------------------------------------------
*/

.category-form-body .form-label {
    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 600;

    color: #343a40;
}


.category-form-body .form-control,
.category-form-body .form-select {
    min-height: 45px;

    border-radius: 8px;
}


.category-form-body textarea.form-control {
    min-height: 130px;

    resize: vertical;
}


/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD
|--------------------------------------------------------------------------
*/

.category-upload-box {
    border: 2px dashed #dee2e6;

    border-radius: 12px;

    padding: 25px;

    text-align: center;

    transition:
        border-color 0.2s ease,
        background 0.2s ease;
}


.category-upload-box:hover {
    border-color: #0d6efd;

    background: rgba(
        13,
        110,
        253,
        0.02
    );
}


.category-upload-icon {
    width: 55px;
    height: 55px;

    margin: 0 auto 12px;

    border-radius: 12px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #f8f9fa;

    color: #6c757d;

    font-size: 24px;
}


.category-image-preview {
    display: none;

    max-width: 220px;

    width: 100%;

    height: 150px;

    object-fit: cover;

    margin: 15px auto 0;

    border-radius: 10px;

    border: 1px solid #dee2e6;
}


/*
|--------------------------------------------------------------------------
| SIDE INFO
|--------------------------------------------------------------------------
*/

.category-info-card {
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


.category-info-card .card-header {
    background: #fff;

    padding: 16px 20px;

    border-bottom: 1px solid #eee;
}


.category-info-card .card-body {
    padding: 20px;
}


.category-info-list {
    list-style: none;

    margin: 0;

    padding: 0;
}


.category-info-list li {
    display: flex;

    align-items: flex-start;

    gap: 10px;

    padding: 9px 0;

    font-size: 13px;

    color: #6c757d;
}


.category-info-list li i {
    color: #198754;

    margin-top: 2px;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.category-form-actions {
    display: flex;

    gap: 10px;

    padding-top: 8px;
}


.category-form-actions .btn {
    min-height: 44px;

    border-radius: 8px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 991.98px) {

    .category-add-page {
        padding: 20px 15px 30px;
    }


    .category-add-header {
        align-items: flex-start;

        flex-direction: column;
    }


    .category-add-header > a {
        width: 100%;
    }


    .category-form-body {
        padding: 20px;
    }

}


@media (max-width: 575.98px) {

    .category-add-title h2 {
        font-size: 23px;
    }


    .category-add-title p {
        font-size: 13px;
    }


    .category-form-body {
        padding: 16px;
    }


    .category-upload-box {
        padding: 20px 15px;
    }


    .category-form-actions {
        flex-direction: column;
    }


    .category-form-actions .btn {
        width: 100%;
    }

}

</style>


<div class="category-add-page">


    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <div class="category-add-header">


        <div class="category-add-title">

            <div class="category-add-icon">

                <i class="bi bi-folder-plus"></i>

            </div>


            <div>

                <h2>

                    Add Category

                </h2>


                <p>

                    Create a new category for your cafe menu.

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
            role="alert"
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
        id="categoryForm"
    >

        <div class="row g-4">


            <!-- =====================================================
                 LEFT COLUMN
            ====================================================== -->

            <div class="col-xl-8">


                <div class="card category-form-card">


                    <div class="category-form-header">

                        <i
                            class="bi bi-folder-plus"
                        ></i>


                        <h5>

                            Category Information

                        </h5>

                    </div>


                    <div class="category-form-body">


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
                                    placeholder="e.g. Pizza, Coffee, Desserts"
                                    maxlength="100"
                                    value="<?= e($categoryName); ?>"
                                    required
                                    autofocus
                                >


                                <div class="form-text">

                                    Use a short and clear name that
                                    customers can easily understand.

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
                                    placeholder="Write a short description about this category..."
                                ><?= e($description); ?></textarea>


                                <div
                                    class="d-flex justify-content-between mt-1"
                                >

                                    <small
                                        class="text-muted"
                                    >

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



                            <!-- IMAGE -->

                            <div class="col-12">

                                <label
                                    class="form-label"
                                >

                                    Category Image

                                </label>


                                <div
                                    class="category-upload-box"
                                    id="uploadBox"
                                >

                                    <div
                                        class="category-upload-icon"
                                    >

                                        <i
                                            class="bi bi-cloud-arrow-up"
                                        ></i>

                                    </div>


                                    <h6
                                        class="fw-semibold mb-1"
                                    >

                                        Upload Category Image

                                    </h6>


                                    <p
                                        class="text-muted small mb-3"
                                    >

                                        JPG, JPEG, PNG or WEBP
                                        · Maximum 5 MB

                                    </p>


                                    <label
                                        for="category_image"
                                        class="btn btn-outline-primary"
                                    >

                                        <i
                                            class="bi bi-image me-1"
                                        ></i>

                                        Choose Image

                                    </label>


                                    <input
                                        type="file"
                                        name="category_image"
                                        id="category_image"
                                        class="d-none"
                                        accept=".jpg,.jpeg,.png,.webp"
                                    >


                                    <img
                                        id="imagePreview"
                                        class="category-image-preview"
                                        alt="Category preview"
                                    >


                                    <div
                                        id="selectedFile"
                                        class="small text-muted mt-2"
                                    ></div>

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


                                <div
                                    class="form-text"
                                >

                                    Inactive categories will not
                                    be available for normal menu use.

                                </div>

                            </div>



                            <!-- CATEGORY PREVIEW -->

                            <div class="col-md-6">

                                <div
                                    class="border rounded-3 p-3 h-100"
                                >

                                    <div
                                        class="small text-muted mb-2"
                                    >

                                        Category Preview

                                    </div>


                                    <div
                                        class="d-flex align-items-center gap-3"
                                    >

                                        <div
                                            id="previewIcon"
                                            class="category-upload-icon m-0"
                                            style="
                                                width:48px;
                                                height:48px;
                                                flex-shrink:0;
                                            "
                                        >

                                            <i
                                                class="bi bi-folder"
                                            ></i>

                                        </div>


                                        <div>

                                            <div
                                                id="categoryPreviewName"
                                                class="fw-semibold"
                                            >

                                                Category Name

                                            </div>


                                            <div
                                                id="categoryPreviewStatus"
                                                class="small text-success"
                                            >

                                                Active

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                        </div>


                        <!-- ACTIONS -->

                        <div
                            class="category-form-actions mt-4"
                        >

                            <button
                                type="submit"
                                name="add_category"
                                value="1"
                                class="btn btn-success px-4"
                                id="saveCategoryBtn"
                            >

                                <i
                                    class="bi bi-check-circle me-1"
                                ></i>

                                Save Category

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
                 RIGHT COLUMN
            ====================================================== -->

            <div class="col-xl-4">


                <!-- CATEGORY GUIDE -->

                <div
                    class="card category-info-card"
                >

                    <div class="card-header">

                        <h6
                            class="mb-0 fw-bold"
                        >

                            <i
                                class="bi bi-info-circle me-2 text-primary"
                            ></i>

                            Category Guidelines

                        </h6>

                    </div>


                    <div class="card-body">

                        <ul
                            class="category-info-list"
                        >

                            <li>

                                <i
                                    class="bi bi-check-circle-fill"
                                ></i>

                                Use a simple and recognizable
                                category name.

                            </li>


                            <li>

                                <i
                                    class="bi bi-check-circle-fill"
                                ></i>

                                Keep the description short and useful.

                            </li>


                            <li>

                                <i
                                    class="bi bi-check-circle-fill"
                                ></i>

                                Use a clear category image.

                            </li>


                            <li>

                                <i
                                    class="bi bi-check-circle-fill"
                                ></i>

                                Recommended image formats are
                                JPG, PNG and WEBP.

                            </li>


                            <li>

                                <i
                                    class="bi bi-check-circle-fill"
                                ></i>

                                Keep inactive categories when you
                                temporarily don't want to show them.

                            </li>

                        </ul>

                    </div>

                </div>



                <!-- IMAGE REQUIREMENTS -->

                <div
                    class="card category-info-card"
                >

                    <div class="card-header">

                        <h6
                            class="mb-0 fw-bold"
                        >

                            <i
                                class="bi bi-image me-2 text-warning"
                            ></i>

                            Image Requirements

                        </h6>

                    </div>


                    <div class="card-body">


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span class="text-muted">

                                Formats

                            </span>


                            <strong>

                                JPG / PNG / WEBP

                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between mb-3"
                        >

                            <span class="text-muted">

                                Maximum Size

                            </span>


                            <strong>

                                5 MB

                            </strong>

                        </div>


                        <div
                            class="d-flex justify-content-between"
                        >

                            <span class="text-muted">

                                Storage

                            </span>


                            <strong>

                                Category Images

                            </strong>

                        </div>

                    </div>

                </div>



                <!-- QUICK NAVIGATION -->

                <div
                    class="card category-info-card"
                >

                    <div class="card-body">

                        <a
                            href="categories.php"
                            class="btn btn-outline-primary w-100 mb-2"
                        >

                            <i
                                class="bi bi-grid me-1"
                            ></i>

                            Manage Categories

                        </a>


                        <a
                            href="a-products.php"
                            class="btn btn-outline-secondary w-100"
                        >

                            <i
                                class="bi bi-box-seam me-1"
                            ></i>

                            Manage Products

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

const categoryNameInput =
    document.getElementById(
        "category_name"
    );

const categoryPreviewName =
    document.getElementById(
        "categoryPreviewName"
    );


function updateCategoryName() {

    const value =
        categoryNameInput.value.trim();


    categoryPreviewName.textContent =
        value !== ''
            ? value
            : 'Category Name';

}


categoryNameInput.addEventListener(
    "input",
    updateCategoryName
);


/*
|--------------------------------------------------------------------------
| Status Preview
|--------------------------------------------------------------------------
*/

const statusInput =
    document.getElementById(
        "status"
    );

const categoryPreviewStatus =
    document.getElementById(
        "categoryPreviewStatus"
    );


function updateStatusPreview() {

    const value =
        statusInput.value;


    categoryPreviewStatus.textContent =
        value;


    categoryPreviewStatus.className =
        value === 'Active'
            ? 'small text-success'
            : 'small text-danger';

}


statusInput.addEventListener(
    "change",
    updateStatusPreview
);


/*
|--------------------------------------------------------------------------
| Description Counter
|--------------------------------------------------------------------------
*/

const descriptionInput =
    document.getElementById(
        "description"
    );

const descriptionCount =
    document.getElementById(
        "descriptionCount"
    );


function updateDescriptionCount() {

    descriptionCount.textContent =
        descriptionInput.value.length +
        " / 500";

}


descriptionInput.addEventListener(
    "input",
    updateDescriptionCount
);


updateDescriptionCount();


/*
|--------------------------------------------------------------------------
| Image Preview
|--------------------------------------------------------------------------
*/

const imageInput =
    document.getElementById(
        "category_image"
    );

const imagePreview =
    document.getElementById(
        "imagePreview"
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

            imagePreview.style.display =
                "none";

            imagePreview.removeAttribute(
                "src"
            );

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

            imagePreview.style.display =
                "none";

            return;
        }


        const reader =
            new FileReader();


        reader.onload =
            function (event) {

                imagePreview.src =
                    event.target.result;

                imagePreview.style.display =
                    "block";

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
    function(eventName) {

        uploadBox.addEventListener(
            eventName,
            function(event) {

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
    function(eventName) {

        uploadBox.addEventListener(
            eventName,
            function(event) {

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
    function(event) {

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
| Submit Protection
|--------------------------------------------------------------------------
*/

const categoryForm =
    document.getElementById(
        "categoryForm"
    );

const saveButton =
    document.getElementById(
        "saveCategoryBtn"
    );


categoryForm.addEventListener(
    "submit",
    function(event) {

        const name =
            categoryNameInput.value.trim();


        if (
            name.length < 2
        ) {

            event.preventDefault();

            alert(
                "Category name must contain at least 2 characters."
            );

            categoryNameInput.focus();

            return;
        }


        saveButton.disabled =
            true;


        saveButton.innerHTML = `

            <span
                class="spinner-border spinner-border-sm me-1"
            ></span>

            Saving...

        `;

    }
);


/*
|--------------------------------------------------------------------------
| Initial Preview
|--------------------------------------------------------------------------
*/

updateCategoryName();

updateStatusPreview();

</script>


<?php

require_once "includes/a-footer.php";

?>