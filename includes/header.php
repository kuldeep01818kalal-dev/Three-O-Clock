<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? "Three O' Clock Cafe";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($page_title) ?></title>

    <meta name="description"
          content="Three O' Clock Cafe - Delicious Food, Coffee, Desserts, Online Ordering and Table Reservation.">

    <meta name="keywords"
          content="Cafe, Restaurant, Coffee, Pizza, Burger, Reservation, Food Delivery">

    <meta name="author"
          content="Three O' Clock Cafe">

    <meta name="theme-color"
          content="#212529">

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet">

    <!-- Google Font -->

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- Website CSS -->

    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Favicon -->

   <!-- <link rel="icon" href="assets/images/favicon.png"> -->

</head>

<body>

<div class="website-wrapper">