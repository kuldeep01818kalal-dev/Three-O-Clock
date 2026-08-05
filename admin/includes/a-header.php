<?php
if(session_status()===PHP_SESSION_NONE){
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title><?= $pageTitle ?? 'Admin Panel'; ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/admin/a-dashboard.css">
<?php

$currentPage = basename($_SERVER['PHP_SELF']);

if(
    in_array($currentPage,[
        'a-dashboard.php',
        'customer_details.php',
        'kitchen.php',
        'orders.php',
        'view_order.php',
        'edit_order.php',
        'a-billing.php'
    ])
){

?>

<link rel="stylesheet" href="../assets/css/dashboard.css">

<?php } ?>
</head>

<body>