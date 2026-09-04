<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
| This file protects all admin pages.
|
| Session variables created by a-login.php:
| - admin_id
| - admin_name
| - admin_email
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Check Admin Login Session
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['admin_id'])) {
    header("Location: a-login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Validate Admin ID
|--------------------------------------------------------------------------
*/

$adminId = filter_var(
    $_SESSION['admin_id'],
    FILTER_VALIDATE_INT
);

if (!$adminId || $adminId < 1) {

    session_unset();
    session_destroy();

    header("Location: a-login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
| Most admin pages already load db.php before a-auth.php.
| If $pdo is not available, load it here as a fallback.
|--------------------------------------------------------------------------
*/

if (!isset($pdo)) {

    $dbFile = dirname(__DIR__, 2) . '/config/db.php';

    if (file_exists($dbFile)) {
        require_once $dbFile;
    }
}

/*
|--------------------------------------------------------------------------
| Verify Admin Account
|--------------------------------------------------------------------------
| This prevents access if:
| - Admin was deleted
| - Admin account was deactivated
|--------------------------------------------------------------------------
*/

if (isset($pdo)) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                admin_id,
                admin_name,
                email,
                status
            FROM admins
            WHERE admin_id = ?
            LIMIT 1
        ");

        $stmt->execute([$adminId]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | Admin Not Found
        |--------------------------------------------------------------------------
        */

        if (!$admin) {

            session_unset();
            session_destroy();

            header("Location: a-login.php");
            exit();
        }

        /*
        |--------------------------------------------------------------------------
        | Admin Account Not Active
        |--------------------------------------------------------------------------
        */

        if (isset($admin['status']) && $admin['status'] !== 'Active') {

            session_unset();
            session_destroy();

            header("Location: a-login.php");
            exit();
        }

        /*
        |--------------------------------------------------------------------------
        | Refresh Admin Session Information
        |--------------------------------------------------------------------------
        */

        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['admin_name'];
        $_SESSION['admin_email'] = $admin['email'];

        /*
        |--------------------------------------------------------------------------
        | Variables Available To Admin Pages
        |--------------------------------------------------------------------------
        */

        $adminId = (int) $admin['admin_id'];
        $adminName = $admin['admin_name'];
        $adminEmail = $admin['email'];

    } catch (PDOException $e) {

        /*
        |--------------------------------------------------------------------------
        | Database Error
        |--------------------------------------------------------------------------
        | Do not expose database details to the user.
        |--------------------------------------------------------------------------
        */

        http_response_code(500);

        die("Unable to verify administrator account.");
    }
}