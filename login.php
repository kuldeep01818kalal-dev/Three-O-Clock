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
<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-5 col-md-7">

            <div class="card login-card">

                <div class="card-body p-5">

                    <div class="text-center mb-4">

                        <i class="bi bi-cup-hot-fill text-warning display-4"></i>

                        <h2 class="login-title">

                            Welcome Back

                        </h2>

                        <p class="login-subtitle">

                            Login to your Three O' Clock Cafe account

                        </p>

                    </div>

                    <?php if(isset($_SESSION['registration_success'])): ?>

                        <div class="alert alert-success">

                            <?= $_SESSION['registration_success']; ?>

                        </div>

                        <?php unset($_SESSION['registration_success']); ?>

                    <?php endif; ?>

                    <?php if(!empty($error)): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars($error); ?>

                        </div>

                    <?php endif; ?>

                    <form method="POST">

                        <!-- Email -->

                        <div class="mb-3">

                            <label class="form-label">

                                Email Address

                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= htmlspecialchars($email); ?>"
                                required>

                        </div>

                        <!-- Password -->

                        <div class="mb-3">

                            <label class="form-label">

                                Password

                            </label>

                            <div class="input-group">

                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    required>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    onclick="togglePassword()">

                                    <i id="eyeIcon"
                                       class="bi bi-eye"></i>

                                </button>

                            </div>

                        </div>

                        <!-- Forgot Password -->

                        <div class="text-end mb-3">

                            <a href="forgot_password.php"
                               class="text-decoration-none">

                                Forgot Password?

                            </a>

                        </div>

                        <!-- Login Button -->

                        <div class="d-grid">

                            <button
                                type="submit"
                                class="btn btn-login">

                                <i class="bi bi-box-arrow-in-right me-2"></i>

                                Login

                            </button>

                        </div>

                    </form>

                    <hr>

                    <p class="text-center mb-0">

                        Don't have an account?

                        <a href="register.php"
                           class="text-decoration-none fw-semibold">

                            Register Here

                        </a>

                    </p>

                </div>

            </div>

        </div>

    </div>

</div>

<script>

function togglePassword(){

    const password =
        document.getElementById("password");

    const icon =
        document.getElementById("eyeIcon");

    if(password.type === "password"){

        password.type = "text";

        icon.classList.remove("bi-eye");

        icon.classList.add("bi-eye-slash");

    }else{

        password.type = "password";

        icon.classList.remove("bi-eye-slash");

        icon.classList.add("bi-eye");

    }

}

</script>

<?php include "includes/footer.php"; ?>