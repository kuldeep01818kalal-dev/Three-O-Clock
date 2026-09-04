<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";

/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['admin_id'])) {
    header("Location: a-dashboard.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(
        random_bytes(32)
    );
}

$csrfToken = $_SESSION['admin_login_csrf'];


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/
$error = "";
$emailValue = "";


/*
|--------------------------------------------------------------------------
| Login Processing
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {

        $error = "Invalid security token. Please refresh the page and try again.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */
        $email = trim(
            (string) ($_POST['email'] ?? '')
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        $emailValue = $email;


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
        if ($email === '') {

            $error = "Please enter your email address.";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error = "Please enter a valid email address.";

        } elseif ($password === '') {

            $error = "Please enter your password.";

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Find Active Admin
                |--------------------------------------------------------------------------
                */
                $sql = "
                    SELECT
                        admin_id,
                        admin_name,
                        email,
                        password,
                        status
                    FROM admins
                    WHERE email = :email
                      AND status = 'Active'
                    LIMIT 1
                ";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    ':email' => $email
                ]);

                $admin = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                /*
                |--------------------------------------------------------------------------
                | Verify Password
                |--------------------------------------------------------------------------
                */
                if (
                    $admin &&
                    password_verify(
                        $password,
                        (string) $admin['password']
                    )
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Regenerate Session ID
                    |--------------------------------------------------------------------------
                    */
                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | Admin Session
                    |--------------------------------------------------------------------------
                    */
                    $_SESSION['admin_id'] =
                        (int) $admin['admin_id'];

                    $_SESSION['admin_name'] =
                        (string) $admin['admin_name'];

                    $_SESSION['admin_email'] =
                        (string) $admin['email'];


                    /*
                    |--------------------------------------------------------------------------
                    | Remove Login CSRF Token
                    |--------------------------------------------------------------------------
                    */
                    unset(
                        $_SESSION['admin_login_csrf']
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Redirect
                    |--------------------------------------------------------------------------
                    */
                    header(
                        "Location: a-dashboard.php"
                    );

                    exit;

                } else {

                    $error =
                        "Invalid email or password.";

                }

            } catch (PDOException $e) {

                $error =
                    "Unable to process login. Please try again.";

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


    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <style>

        :root {
            --coffee-dark: #4b2e2b;
            --coffee: #6f4e37;
            --coffee-light: #8b5e3c;
            --cream: #f8f1e5;
            --caramel: #c68e4b;
            --text-dark: #2e2e2e;
            --text-muted: #6b7280;
        }


        * {
            box-sizing: border-box;
        }


        body {

            min-height: 100vh;

            margin: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;

            font-family: "Poppins", sans-serif;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(198, 142, 75, .18),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #f8f1e5,
                    #efe5d5
                );

            color: var(--text-dark);

        }


        .login-wrapper {

            width: 100%;

            max-width: 1050px;

        }


        .login-card {

            overflow: hidden;

            background: #ffffff;

            border: 0;

            border-radius: 24px;

            box-shadow:
                0 25px 70px rgba(75, 46, 43, .16);

        }


        /*
        |--------------------------------------------------------------------------
        | Brand Section
        |--------------------------------------------------------------------------
        */

        .login-brand {

            min-height: 590px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            padding: 55px;

            background:
                linear-gradient(
                    145deg,
                    #4b2e2b,
                    #6f4e37
                );

            color: #ffffff;

            position: relative;

            overflow: hidden;

        }


        .login-brand::before {

            content: "";

            position: absolute;

            width: 260px;

            height: 260px;

            border-radius: 50%;

            top: -90px;

            right: -80px;

            background: rgba(255,255,255,.07);

        }


        .login-brand::after {

            content: "";

            position: absolute;

            width: 190px;

            height: 190px;

            border-radius: 50%;

            bottom: -70px;

            left: -60px;

            background: rgba(198,142,75,.14);

        }


        .brand-content {

            position: relative;

            z-index: 2;

        }


        .brand-icon {

            width: 76px;

            height: 76px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 25px;

            border-radius: 22px;

            background: rgba(255,255,255,.12);

            border: 1px solid rgba(255,255,255,.16);

            font-size: 34px;

            color: #ffffff;

        }


        .brand-title {

            margin: 0;

            font-size: 32px;

            line-height: 1.2;

            font-weight: 700;

        }


        .brand-title span {

            color: #e8b66d;

        }


        .brand-subtitle {

            margin-top: 15px;

            max-width: 430px;

            color: rgba(255,255,255,.75);

            line-height: 1.7;

            font-size: 14px;

        }


        .brand-features {

            display: flex;

            flex-direction: column;

            gap: 14px;

            margin-top: 30px;

        }


        .brand-feature {

            display: flex;

            align-items: center;

            gap: 11px;

            color: rgba(255,255,255,.88);

            font-size: 13px;

        }


        .brand-feature i {

            color: #e8b66d;

            font-size: 17px;

        }


        /*
        |--------------------------------------------------------------------------
        | Login Section
        |--------------------------------------------------------------------------
        */

        .login-form-section {

            min-height: 590px;

            display: flex;

            align-items: center;

            padding: 50px;

            background: #ffffff;

        }


        .login-form-wrapper {

            width: 100%;

            max-width: 420px;

            margin: 0 auto;

        }


        .login-heading {

            margin-bottom: 7px;

            color: var(--coffee-dark);

            font-size: 27px;

            font-weight: 700;

        }


        .login-description {

            margin-bottom: 30px;

            color: var(--text-muted);

            font-size: 13px;

        }


        .form-label {

            margin-bottom: 8px;

            color: #374151;

            font-size: 13px;

            font-weight: 600;

        }


        .input-wrapper {

            position: relative;

        }


        .input-icon {

            position: absolute;

            top: 50%;

            left: 15px;

            transform: translateY(-50%);

            color: var(--coffee);

            pointer-events: none;

            z-index: 2;

        }


        .form-control {

            height: 52px;

            padding-left: 45px;

            padding-right: 45px;

            border: 1px solid #e1e5ea;

            border-radius: 11px;

            font-size: 13px;

            box-shadow: none;

        }


        .form-control:focus {

            border-color: var(--coffee-light);

            box-shadow:
                0 0 0 3px rgba(139,94,60,.10);

        }


        .password-toggle {

            position: absolute;

            top: 50%;

            right: 10px;

            transform: translateY(-50%);

            width: 34px;

            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            border: 0;

            border-radius: 7px;

            background: transparent;

            color: #6b7280;

            cursor: pointer;

        }


        .password-toggle:hover {

            background: #f3f4f6;

            color: var(--coffee);

        }


        .login-button {

            width: 100%;

            height: 52px;

            border: 0;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #8b5e3c,
                    #6f4e37
                );

            color: #ffffff;

            font-size: 14px;

            font-weight: 600;

            box-shadow:
                0 8px 20px rgba(111,78,55,.20);

            transition:
                transform .2s ease,
                box-shadow .2s ease;

        }


        .login-button:hover {

            transform: translateY(-1px);

            box-shadow:
                0 12px 25px rgba(111,78,55,.27);

            color: #ffffff;

        }


        .login-button:disabled {

            opacity: .75;

            cursor: not-allowed;

            transform: none;

        }


        .login-footer {

            margin-top: 28px;

            padding-top: 20px;

            border-top: 1px solid #eef0f2;

            text-align: center;

            color: #9ca3af;

            font-size: 11px;

        }


        .login-footer strong {

            color: var(--coffee);

        }


        .alert {

            border-radius: 11px;

            font-size: 13px;

        }


        @media (max-width: 991px) {

            .login-brand {

                min-height: auto;

                padding: 40px;

            }


            .login-form-section {

                min-height: auto;

                padding: 40px;

            }

        }


        @media (max-width: 575px) {

            body {

                padding: 15px;

            }


            .login-card {

                border-radius: 18px;

            }


            .login-brand {

                padding: 30px 25px;

            }


            .login-form-section {

                padding: 30px 25px;

            }


            .brand-title {

                font-size: 25px;

            }


            .brand-icon {

                width: 60px;

                height: 60px;

                border-radius: 17px;

                font-size: 27px;

            }


            .login-heading {

                font-size: 23px;

            }

        }

    </style>

</head>


<body>


<div class="login-wrapper">

    <div class="card login-card">

        <div class="row g-0">


            <!-- =====================================================
                 BRAND
                 ===================================================== -->

            <div class="col-lg-6">

                <div class="login-brand">

                    <div class="brand-content">

                        <div class="brand-icon">

                            <i class="bi bi-cup-hot-fill"></i>

                        </div>


                        <h1 class="brand-title">

                            Three O' Clock
                            <span>Cafe</span>

                        </h1>


                        <p class="brand-subtitle">

                            Welcome to the Three O' Clock Cafe
                            administration panel. Manage orders,
                            tables, bookings, products and cafe
                            operations from one place.

                        </p>


                        <div class="brand-features">

                            <div class="brand-feature">

                                <i class="bi bi-check-circle-fill"></i>

                                <span>
                                    Manage cafe orders
                                </span>

                            </div>


                            <div class="brand-feature">

                                <i class="bi bi-check-circle-fill"></i>

                                <span>
                                    Manage tables and reservations
                                </span>

                            </div>


                            <div class="brand-feature">

                                <i class="bi bi-check-circle-fill"></i>

                                <span>
                                    Track payments and reports
                                </span>

                            </div>


                            <div class="brand-feature">

                                <i class="bi bi-check-circle-fill"></i>

                                <span>
                                    Manage products and reviews
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 LOGIN FORM
                 ===================================================== -->

            <div class="col-lg-6">

                <div class="login-form-section">

                    <div class="login-form-wrapper">


                        <h2 class="login-heading">

                            Admin Login

                        </h2>


                        <p class="login-description">

                            Sign in to access your administration dashboard.

                        </p>


                        <?php if ($error !== ''): ?>

                            <div
                                class="alert alert-danger d-flex align-items-start gap-2"
                                role="alert"
                            >

                                <i class="bi bi-exclamation-triangle-fill"></i>

                                <span>
                                    <?php echo htmlspecialchars(
                                        $error,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </span>

                            </div>

                        <?php endif; ?>


                        <form
                            method="POST"
                            action="a-login.php"
                            id="adminLoginForm"
                            autocomplete="on"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo htmlspecialchars(
                                    $csrfToken,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                            >


                            <!-- Email -->

                            <div class="mb-3">

                                <label
                                    for="email"
                                    class="form-label"
                                >
                                    Email Address
                                </label>

                                <div class="input-wrapper">

                                    <i class="bi bi-envelope input-icon"></i>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        class="form-control"
                                        placeholder="Enter your email"
                                        value="<?php echo htmlspecialchars(
                                            $emailValue,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>"
                                        autocomplete="username"
                                        required
                                        autofocus
                                    >

                                </div>

                            </div>


                            <!-- Password -->

                            <div class="mb-4">

                                <label
                                    for="password"
                                    class="form-label"
                                >
                                    Password
                                </label>

                                <div class="input-wrapper">

                                    <i class="bi bi-lock input-icon"></i>

                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        class="form-control"
                                        placeholder="Enter your password"
                                        autocomplete="current-password"
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="passwordToggle"
                                        aria-label="Show password"
                                    >

                                        <i class="bi bi-eye"></i>

                                    </button>

                                </div>

                            </div>


                            <!-- Login Button -->

                            <button
                                type="submit"
                                class="login-button"
                                id="loginButton"
                            >

                                <i class="bi bi-box-arrow-in-right me-1"></i>

                                Login

                            </button>

                        </form>


                        <div class="login-footer">

                            <div>

                                <strong>
                                    Three O' Clock Cafe
                                </strong>

                                &nbsp;•&nbsp;

                                Admin Panel

                            </div>

                            <div class="mt-1">

                                Secure administrator access

                            </div>

                        </div>


                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Password Toggle
        |--------------------------------------------------------------------------
        */

        const password =
            document.getElementById('password');

        const passwordToggle =
            document.getElementById('passwordToggle');


        if (
            password &&
            passwordToggle
        ) {

            passwordToggle.addEventListener(
                'click',
                function () {

                    const icon =
                        passwordToggle.querySelector('i');


                    if (
                        password.type === 'password'
                    ) {

                        password.type = 'text';

                        passwordToggle.setAttribute(
                            'aria-label',
                            'Hide password'
                        );

                        if (icon) {

                            icon.classList.remove(
                                'bi-eye'
                            );

                            icon.classList.add(
                                'bi-eye-slash'
                            );

                        }

                    } else {

                        password.type = 'password';

                        passwordToggle.setAttribute(
                            'aria-label',
                            'Show password'
                        );

                        if (icon) {

                            icon.classList.remove(
                                'bi-eye-slash'
                            );

                            icon.classList.add(
                                'bi-eye'
                            );

                        }

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Prevent Double Login Submission
        |--------------------------------------------------------------------------
        */

        const form =
            document.getElementById('adminLoginForm');

        const button =
            document.getElementById('loginButton');


        if (
            form &&
            button
        ) {

            form.addEventListener(
                'submit',
                function () {

                    if (
                        form.dataset.submitted === 'true'
                    ) {

                        return;

                    }


                    form.dataset.submitted = 'true';

                    button.disabled = true;

                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2"></span>' +
                        'Signing in...';

                }
            );

        }

    }
);

</script>


</body>

</html>