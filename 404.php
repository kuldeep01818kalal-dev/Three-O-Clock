<?php
/**
 * Three-O-Clock Cafe
 * 404 - Page Not Found
 */

http_response_code(404);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#8b5e3c"
    >

    <title>
        Page Not Found | Three O' Clock Cafe
    </title>

    <!-- Google Font -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #8b5e3c;
            --primary-dark: #70492f;
            --dark: #171717;
            --muted: #6b7280;
            --light: #f8f6f3;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(139, 94, 60, 0.10),
                    transparent 35%
                ),
                var(--light);
            color: var(--dark);
            font-family: "Poppins", sans-serif;
        }

        .error-page {
            width: 100%;
            max-width: 850px;
            text-align: center;
        }

        .error-card {
            padding: 55px 40px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.08);
        }

        .error-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(139, 94, 60, 0.10);
            color: var(--primary);
            font-size: 42px;
        }

        .error-code {
            margin: 0;
            font-size: clamp(80px, 15vw, 150px);
            line-height: 0.9;
            font-weight: 700;
            letter-spacing: -6px;
            color: var(--primary);
        }

        .error-title {
            margin-top: 28px;
            margin-bottom: 12px;
            font-size: clamp(24px, 4vw, 36px);
            font-weight: 700;
        }

        .error-text {
            max-width: 570px;
            margin: 0 auto 30px;
            color: var(--muted);
            line-height: 1.8;
        }

        .error-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
        }

        .btn-cafe {
            min-width: 150px;
            padding: 12px 22px;
            border-radius: 10px;
            font-weight: 600;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .btn-cafe-primary {
            color: #ffffff;
            background: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-cafe-primary:hover {
            color: #ffffff;
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow:
                0 8px 20px rgba(139, 94, 60, 0.25);
        }

        .btn-cafe-outline {
            color: var(--primary);
            background: #ffffff;
            border: 1px solid var(--primary);
        }

        .btn-cafe-outline:hover {
            color: #ffffff;
            background: var(--primary);
            transform: translateY(-2px);
        }

        .brand {
            margin-top: 28px;
            color: var(--muted);
            font-size: 14px;
        }

        .brand strong {
            color: var(--primary);
        }

        @media (max-width: 576px) {

            body {
                padding: 15px;
            }

            .error-card {
                padding: 40px 20px;
                border-radius: 18px;
            }

            .error-code {
                letter-spacing: -3px;
            }

            .error-icon {
                width: 80px;
                height: 80px;
                font-size: 34px;
            }

            .error-actions {
                flex-direction: column;
            }

            .btn-cafe {
                width: 100%;
            }

        }

    </style>

</head>

<body>

    <main class="error-page">

        <div class="error-card">

            <!-- Error Icon -->
            <div class="error-icon">

                <i class="bi bi-cup-hot-fill"></i>

            </div>


            <!-- Error Code -->
            <h1 class="error-code">
                404
            </h1>


            <!-- Title -->
            <h2 class="error-title">
                Oops! Page Not Found
            </h2>


            <!-- Description -->
            <p class="error-text">

                Looks like this page has wandered off for a coffee.
                The page you're looking for doesn't exist or may
                have been moved.

            </p>


            <!-- Actions -->
            <div class="error-actions">

                <a
                    href="index.php"
                    class="btn btn-cafe btn-cafe-primary"
                >
                    <i class="bi bi-house-door-fill me-2"></i>
                    Back to Home
                </a>


                <a
                    href="menu.php"
                    class="btn btn-cafe btn-cafe-outline"
                >
                    <i class="bi bi-cup-hot me-2"></i>
                    View Menu
                </a>

            </div>


            <!-- Brand -->
            <div class="brand">

                <strong>Three O' Clock Cafe</strong>

                <br>

                Good Food. Great Coffee. Good Times.

            </div>

        </div>

    </main>

</body>

</html>