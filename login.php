<?php
session_start();

require_once "config/db.php";

/* ==========================================
   Already Logged In
========================================== */

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

/* ==========================================
   Variables
========================================== */

$error = "";
$email = "";

/* ==========================================
   Login
========================================== */

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($email) || empty($password)) {

        $error = "Please enter email and password.";

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {

            $error = "Email does not exist.";

        } elseif ($user['status'] != 'Active') {

            $error = "Your account has been blocked.";

        } elseif (!password_verify($password, $user['password'])) {

            $error = "Incorrect password.";

        } else {

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];

            header("Location: index.php");
            exit();

        }

    }

}

$pageTitle = "Customer Login";

include "includes/header.php";
include "includes/navbar.php";
?>