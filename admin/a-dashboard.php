<?php
session_start();

if (!isset($_SESSION['admin_id'])) {

    header("Location: login.php");
    exit;

}

echo "<h1>Welcome " . $_SESSION['admin_name'] . "</h1>";