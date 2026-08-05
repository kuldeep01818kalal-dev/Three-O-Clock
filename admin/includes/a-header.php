<?php
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title><?= $pageTitle ?? 'Three O\' Clock Cafe'; ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/admin/layout.css">

<link rel="stylesheet" href="../assets/css/admin/sidebar.css">

<link rel="stylesheet" href="../assets/css/admin/navbar.css">

<link rel="stylesheet" href="../assets/css/admin/footer.css">

<link rel="stylesheet" href="../assets/css/admin/dashboard.css">

<link rel="stylesheet" href="../assets/css/admin/responsive.css">

</head>

<body>

<div class="admin-layout">