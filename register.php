<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : register.php
 * Purpose : User Registration
 *******************************************************/

declare(strict_types=1);

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/mail.php';

// If already logged in
if (isUserLoggedIn()) {
    header("Location: index.php");
    exit();
}

/*======================================================
=            INITIAL VARIABLES
======================================================*/

$full_name = "";
$email = "";
$phone = "";

$errors = [];
$success = "";

/*======================================================
=            HANDLE FORM SUBMISSION
======================================================*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get Form Data
    $full_name       = trim($_POST['full_name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    /*==================================================
    =            VALIDATION
    ==================================================*/

    // Full Name
    if ($full_name === "") {
        $errors[] = "Full name is required.";
    } elseif (strlen($full_name) < 3) {
        $errors[] = "Full name must contain at least 3 characters.";
    }

    // Email
    if ($email === "") {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    }

    // Phone
    if ($phone === "") {
        $errors[] = "Mobile number is required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Enter a valid 10-digit mobile number.";
    }

    // Password
    if ($password === "") {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    // Confirm Password
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    /*==================================================
    =            DUPLICATE EMAIL CHECK
    ==================================================*/

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = "Email already registered.";
        }

    }

    /*==================================================
    =            DUPLICATE PHONE CHECK
    ==================================================*/

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE phone = ?
        ");

        $stmt->execute([$phone]);

        if ($stmt->fetch()) {
            $errors[] = "Mobile number already registered.";
        }

    }

    /*==================================================
    =            REGISTER USER
    ==================================================*/

    if (empty($errors)) {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO users
            (
                full_name,
                email,
                password,
                phone
            )
            VALUES
            (
                ?, ?, ?, ?
            )
        ");

        if (
            $stmt->execute([
                $full_name,
                $email,
                $hashedPassword,
                $phone
            ])
        ) {

            /*
            ==================================================
            Welcome Email
            ==================================================

            Uncomment this after configuring Gmail
            credentials in config/mail.php.

            sendEmail(
                $email,
                $full_name,
                "Welcome to Three O' Clock Cafe",
                "<h2>Welcome {$full_name}</h2>
                <p>Your account has been created successfully.</p>"
            );

            */

            $_SESSION['registration_success'] =
                "Registration completed successfully. Please login.";

            header("Location: login.php");
            exit();

        } else {

            $errors[] =
                "Registration failed. Please try again.";

        }

    }

}

/*******************************************************
 * Part 1 Ends Here
 * Part 2 will start with <!DOCTYPE html>
 *******************************************************/
