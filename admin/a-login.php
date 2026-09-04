<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once "../config/db.php";


/*
|--------------------------------------------------------------------------
| IF ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['admin_id'])) {

    header("Location: a-dashboard.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = "";


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_login_csrf'])) {

    $_SESSION['admin_login_csrf'] =
        bin2hex(random_bytes(32));

}


/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION['admin_login_csrf'],
            $csrfToken
        )
    ) {

        $error =
            "Invalid security token. Please refresh the page.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | INPUT
        |--------------------------------------------------------------------------
        */

        $email = trim(
            (string)($_POST["email"] ?? "")
        );

        $password =
            (string)($_POST["password"] ?? "");


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($email === "" || $password === "") {

            $error =
                "Please enter email address and password.";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                "Please enter a valid email address.";

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | GET ADMIN
                |--------------------------------------------------------------------------
                */

                $sql = "
                    SELECT *
                    FROM admins
                    WHERE email = :email
                    AND status = 'Active'
                    LIMIT 1
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':email' => $email
                ]);

                $admin =
                    $stmt->fetch(PDO::FETCH_ASSOC);


                /*
                |--------------------------------------------------------------------------
                | VERIFY PASSWORD
                |--------------------------------------------------------------------------
                */

                if (
                    !$admin ||
                    empty($admin['password']) ||
                    !password_verify(
                        $password,
                        $admin['password']
                    )
                ) {

                    $error =
                        "Invalid email or password.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | REGENERATE SESSION
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN ID
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['admin_id'] =
                        (int)$admin['admin_id'];


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN NAME
                    |--------------------------------------------------------------------------
                    |
                    | Your existing database uses full_name.
                    |--------------------------------------------------------------------------
                    */

                    if (isset($admin['full_name'])) {

                        $_SESSION['admin_name'] =
                            $admin['full_name'];

                    } elseif (
                        isset($admin['admin_name'])
                    ) {

                        $_SESSION['admin_name'] =
                            $admin['admin_name'];

                    } else {

                        $_SESSION['admin_name'] =
                            "Administrator";

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN EMAIL
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['admin_email'] =
                        $admin['email'] ?? $email;


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN ROLE
                    |--------------------------------------------------------------------------
                    */

                    if (isset($admin['role'])) {

                        $_SESSION['admin_role'] =
                            $admin['role'];

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | LOGIN TIME
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['admin_login_time'] =
                        time();


                    /*
                    |--------------------------------------------------------------------------
                    | NEW CSRF TOKEN
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['admin_login_csrf'] =
                        bin2hex(random_bytes(32));


                    /*
                    |--------------------------------------------------------------------------
                    | DASHBOARD
                    |--------------------------------------------------------------------------
                    */

                    header(
                        "Location: a-dashboard.php"
                    );

                    exit();

                }

            } catch (PDOException $e) {

                /*
                |--------------------------------------------------------------------------
                | DEVELOPMENT ERROR
                |--------------------------------------------------------------------------
                */

                $error =
                    "Unable to process login. Please try again.";

                /*
                | For debugging, temporarily use:
                |
                | $error = $e->getMessage();
                |
                */

            }

        }

    }

}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Login | Three O' Clock Cafe
    </title>


    <!-- =========================================================
         BOOTSTRAP ICONS
    ========================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =========================================================
         LOGIN CSS
    ========================================================== -->

    <link
        rel="stylesheet"
        href="../assets/css/login.css"
    >

</head>


<body>


<!-- =============================================================
     LOGIN PAGE
============================================================== -->

<div class="login-page">


    <!-- =========================================================
         LOGIN CONTAINER
    ========================================================== -->

    <div class="login-container">


        <!-- =====================================================
             LOGIN CARD
        ====================================================== -->

        <div class="login-card">


            <!-- =================================================
                 BRAND
            ================================================== -->

            <div class="login-brand">


                <!-- LOGO -->

                <div class="login-brand-logo">

                    <i class="bi bi-cup-hot-fill"></i>

                </div>


                <!-- BRAND NAME -->

                <h1>
                    Three O' Clock Cafe
                </h1>


                <!-- DESCRIPTION -->

                <p>
                    Welcome to the Three O' Clock Cafe
                    administration panel.
                </p>

            </div>



            <!-- =================================================
                 LOGIN TITLE
            ================================================== -->

            <div class="login-title">

                <h2>
                    Admin Login
                </h2>

                <p>
                    Sign in to access your administration dashboard.
                </p>

            </div>



            <!-- =================================================
                 ERROR MESSAGE
            ================================================== -->

            <?php if ($error !== ""): ?>

                <div class="login-alert">

                    <i class="bi bi-exclamation-triangle-fill"></i>

                    <span>

                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </span>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 LOGIN FORM
            ================================================== -->

            <form
                method="POST"
                class="login-form"
                id="adminLoginForm"
                autocomplete="on"
            >


                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['admin_login_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                >



                <!-- =================================================
                     EMAIL
                ================================================== -->

                <div class="login-form-group">

                    <label for="email">
                        Email Address
                    </label>


                    <div class="login-input-wrapper">


                        <!-- ICON -->

                        <i
                            class="bi bi-envelope login-input-icon"
                        ></i>


                        <!-- INPUT -->

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="login-input"
                            value="<?= htmlspecialchars(
                                $_POST['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            placeholder="admin@threeoclock.com"
                            autocomplete="username"
                            required
                        >

                    </div>

                </div>



                <!-- =================================================
                     PASSWORD
                ================================================== -->

                <div class="login-form-group">

                    <label for="password">
                        Password
                    </label>


                    <div class="login-input-wrapper password-wrapper">


                        <!-- ICON -->

                        <i
                            class="bi bi-lock login-input-icon"
                        ></i>


                        <!-- PASSWORD -->

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="login-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <!-- TOGGLE -->

                        <button
                            type="button"
                            class="password-toggle"
                            id="togglePassword"
                            aria-label="Show password"
                        >

                            <i class="bi bi-eye"></i>

                        </button>

                    </div>

                </div>



                <!-- =================================================
                     LOGIN BUTTON
                ================================================== -->

                <button
                    type="submit"
                    class="login-button"
                    id="loginButton"
                >

                    <i class="bi bi-box-arrow-in-right"></i>

                    <span>
                        Login
                    </span>

                </button>


            </form>



            <!-- =================================================
                 SECURITY
            ================================================== -->

            <div class="login-security">

                <i class="bi bi-shield-check"></i>

                <span>
                    Secure administrator access
                </span>

            </div>



            <!-- =================================================
                 FOOTER
            ================================================== -->

            <div class="login-footer">

                <p>

                    <strong>
                        Three O' Clock Cafe
                    </strong>

                    &nbsp;•&nbsp;

                    Admin Panel

                </p>

            </div>


        </div>

    </div>

</div>



<!-- =============================================================
     LOGIN JAVASCRIPT
============================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {


        /*
        |--------------------------------------------------------------------------
        | PASSWORD TOGGLE
        |--------------------------------------------------------------------------
        */

        const password =
            document.getElementById("password");

        const toggle =
            document.getElementById("togglePassword");


        if (password && toggle) {

            toggle.addEventListener(
                "click",
                function () {

                    if (
                        password.type === "password"
                    ) {

                        password.type = "text";

                        toggle.innerHTML =
                            '<i class="bi bi-eye-slash"></i>';

                        toggle.setAttribute(
                            "aria-label",
                            "Hide password"
                        );

                    } else {

                        password.type = "password";

                        toggle.innerHTML =
                            '<i class="bi bi-eye"></i>';

                        toggle.setAttribute(
                            "aria-label",
                            "Show password"
                        );

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT DOUBLE SUBMIT
        |--------------------------------------------------------------------------
        */

        const form =
            document.getElementById(
                "adminLoginForm"
            );

        const button =
            document.getElementById(
                "loginButton"
            );


        if (form && button) {

            form.addEventListener(
                "submit",
                function () {

                    button.disabled = true;

                    button.classList.add(
                        "loading"
                    );

                    button.innerHTML =
                        "Logging in...";

                }
            );

        }

    }

);

</script>


</body>

</html>