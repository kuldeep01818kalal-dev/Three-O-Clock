<?php
session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Add Category";

$errors = [];
$success = "";

$category_name = "";
$description = "";
$status = "Active";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $category_name = trim($_POST['category_name']);
    $description   = trim($_POST['description']);
    $status        = $_POST['status'];

    /* ===========================
       Validation
    ============================ */

    if (empty($category_name)) {
        $errors[] = "Category name is required.";
    }

    if (strlen($category_name) > 100) {
        $errors[] = "Category name must be less than 100 characters.";
    }

    /* ===========================
       Duplicate Category Check
    ============================ */

    $check = $pdo->prepare("
        SELECT category_id
        FROM categories
        WHERE category_name = :category_name
        LIMIT 1
    ");

    $check->execute([
        ':category_name' => $category_name
    ]);

    if ($check->fetch()) {
        $errors[] = "Category already exists.";
    }

    /* ===========================
       Image Upload
    ============================ */

    $imageName = "";

    if (!empty($_FILES['category_image']['name'])) {

        $allowed = [
            "jpg",
            "jpeg",
            "png",
            "webp"
        ];

        $extension = strtolower(
            pathinfo(
                $_FILES['category_image']['name'],
                PATHINFO_EXTENSION
            )
        );

        if (!in_array($extension, $allowed)) {

            $errors[] =
                "Only JPG, JPEG, PNG and WEBP files are allowed.";

        } else {

            if ($_FILES['category_image']['size'] > 2 * 1024 * 1024) {

                $errors[] =
                    "Image must be smaller than 2MB.";

            } else {

                $imageName =
                    time() . "_" .
                    preg_replace(
                        "/[^A-Za-z0-9._-]/",
                        "",
                        $_FILES['category_image']['name']
                    );

                $uploadDir =
                    "../assets/images/categories/";

                if (!is_dir($uploadDir)) {

                    mkdir($uploadDir, 0777, true);

                }

            }

        }

    }

    /* ===========================
       Save Category
    ============================ */

    if (empty($errors)) {

        if (!empty($imageName)) {

            move_uploaded_file(

                $_FILES['category_image']['tmp_name'],

                $uploadDir . $imageName

            );

        }

        $stmt = $pdo->prepare("

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

        $saved = $stmt->execute([

            ':category_name'  => $category_name,
            ':category_image' => $imageName,
            ':description'    => $description,
            ':status'         => $status

        ]);

        if ($saved) {

            $_SESSION['success'] =
                "Category added successfully.";

            header("Location: categories.php");

            exit();

        } else {

            $errors[] =
                "Unable to save category.";

        }

    }

}

include "includes/a-header.php";
include "includes/a-sidebar.php";
include "includes/a-navbar.php";
?>

<div class="container-fluid mt-4">

<div class="row">

<div class="col-lg-10 mx-auto">

<div class="card shadow border-0">

<div class="card-header bg-primary text-white">

<h4 class="mb-0">

<i class="bi bi-grid-fill"></i>

Add New Category

</h4>

</div>

<div class="card-body">