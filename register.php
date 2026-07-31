<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : register.php
 * Purpose : User Registration
 *******************************************************/

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';

// require_once 'config/mail.php';

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
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>User Registration | Three O' Clock Cafe</title>

    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
          rel="stylesheet">

    <!-- Custom CSS -->

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body class="bg-light">

<div class="container">

    <div class="row justify-content-center">

        <div class="col-lg-6 col-md-8">

            <div class="card shadow-lg border-0 mt-5 mb-5">

                <div class="card-body p-5">

                    <h2 class="text-center fw-bold mb-2">
                        Create Account
                    </h2>

                    <p class="text-center text-muted mb-4">
                        Three O' Clock Cafe
                    </p>

                    <!-- Success Message -->

                    <?php
                    if (!empty($success)) {
                    ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars($success) ?>

                    </div>

                    <?php
                    }
                    ?>

                    <!-- Error Messages -->

                    <?php
                    if (!empty($errors)) {
                    ?>

                    <div class="alert alert-danger">

                        <ul class="mb-0">

                            <?php
                            foreach ($errors as $error) {
                            ?>

                                <li><?= htmlspecialchars($error) ?></li>

                            <?php
                            }
                            ?>

                        </ul>

                    </div>

                    <?php
                    }
                    ?>

                    <form method="POST"
                          action=""
                          id="registerForm">

                        <!-- Full Name -->

                        <div class="mb-3">

                            <label class="form-label">

                                Full Name

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="full_name"
                                id="full_name"
                                value="<?= htmlspecialchars($full_name) ?>"
                                required>

                        </div>

                        <!-- Email -->

                        <div class="mb-3">

                            <label class="form-label">

                                Email Address

                            </label>

                            <input
                                type="email"
                                class="form-control"
                                name="email"
                                id="email"
                                value="<?= htmlspecialchars($email) ?>"
                                required>

                        </div>

                        <!-- Phone -->

                        <div class="mb-3">

                            <label class="form-label">

                                Mobile Number

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="phone"
                                id="phone"
                                maxlength="10"
                                value="<?= htmlspecialchars($phone) ?>"
                                required>

                        </div>

                        <!-- Password -->

                        <div class="mb-3">

                            <label class="form-label">

                                Password

                            </label>

                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>

                        </div>

                        <!-- Confirm Password -->

                        <div class="mb-4">

                            <label class="form-label">

                                Confirm Password

                            </label>

                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm password" name="confirm password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm password', this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                            </div>

                        </div>

                        <!-- Register Button -->

                        <div class="d-grid">

                            <button
                                type="submit"
                                class="btn btn-dark btn-lg">

                                Register

                            </button>

                        </div>

                    </form>

                    <hr>

                    <p class="text-center mb-0">

                        Already have an account?

                        <a href="login.php"
                           class="text-decoration-none fw-semibold">

                            Login Here

                        </a>

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- Bootstrap JS -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>