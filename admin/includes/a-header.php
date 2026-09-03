<?php

declare(strict_types=1);


/* =========================================================
   SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CURRENT PAGE
========================================================= */

$currentPage = basename($_SERVER['PHP_SELF']);


/* =========================================================
   PAGE TITLE
========================================================= */

$pageTitle = $pageTitle ?? "Three O' Clock Cafe";


/* =========================================================
   PAGE SPECIFIC CSS
========================================================= */

$pageStyles = [

    /* Dashboard */

    'a-dashboard.php' => [
        '../assets/css/dashboard.css'
    ],


    /* Orders */

    'orders.php' => [
        '../assets/css/order.css'
    ],

    'view_order.php' => [
        '../assets/css/order_details.css'
    ],

    'order_details.php' => [
        '../assets/css/order_details.css'
    ],

    'edit_order.php' => [
        '../assets/css/order_details.css'
    ],


    /* Products */

    'a-products.php' => [
        '../assets/css/product.css'
    ],

    'products.php' => [
        '../assets/css/product.css'
    ],

    'add_product.php' => [
        '../assets/css/product.css'
    ],

    'edit_product.php' => [
        '../assets/css/product.css'
    ],


    /* Categories */

    'categories.php' => [],

    'add_category.php' => [],

    'edit_category.php' => [],


    /* Customers */

    'customers.php' => [],

    'customer_details.php' => [],


    /* Billing */

    'a-billing.php' => [],


    /* Kitchen */

    'kitchen.php' => [],


    /* Tables */

    'tables.php' => [
        '../assets/css/table.css'
    ],

    'table_status.php' => [
        '../assets/css/table.css'
    ],


    /* Reservations */

    'table_booking.php' => [
        '../assets/css/table.css'
    ],


    /* Login */

    'a-login.php' => [
        '../assets/css/login.css'
    ],


    /* Profile */

    'profile.php' => [
        '../assets/css/profile.css'
    ]

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#171311"
    >

    <meta
        name="description"
        content="Three O' Clock Cafe Restaurant Management System"
    >

    <title>
        <?= htmlspecialchars($pageTitle); ?>
        | Three O' Clock Cafe
    </title>


    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         CORE ADMIN CSS
    ====================================================== -->

    <link
        rel="stylesheet"
        href="../assets/css/layout.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/admin.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/navbar.css"
    >


    <!-- =====================================================
         PAGE SPECIFIC CSS
    ====================================================== -->

    <?php

    if (isset($pageStyles[$currentPage])) {

        foreach ($pageStyles[$currentPage] as $cssFile) {

            if (!empty($cssFile)) {

    ?>

                <link
                    rel="stylesheet"
                    href="<?= htmlspecialchars($cssFile); ?>"
                >

    <?php

            }

        }

    }

    ?>


    <!-- =====================================================
         EXTRA CSS
    ====================================================== -->

    <?php if (!empty($extraCSS) && is_array($extraCSS)): ?>

        <?php foreach ($extraCSS as $css): ?>

            <link
                rel="stylesheet"
                href="<?= htmlspecialchars($css); ?>"
            >

        <?php endforeach; ?>

    <?php endif; ?>


</head>


<body>

<div class="admin-layout">