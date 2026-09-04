<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| CHECK LOGIN SESSION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['admin_id'])) {

    header("Location: a-login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| VALIDATE ADMIN ID
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
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

if (!isset($pdo)) {

    $dbFile = dirname(__DIR__, 2) . "/config/db.php";

    if (!file_exists($dbFile)) {

        die("Database configuration file not found.");

    }

    require_once $dbFile;
}


/*
|--------------------------------------------------------------------------
| VERIFY ADMIN ACCOUNT
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | SELECT * is intentionally used here because your existing admins
    | table uses the original project structure.
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM admins
        WHERE admin_id = ?
        LIMIT 1
    ");

    $stmt->execute([$adminId]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | ADMIN DOES NOT EXIST
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
    | CHECK ADMIN STATUS
    |--------------------------------------------------------------------------
    */

    if (
        isset($admin['status']) &&
        strtolower((string)$admin['status']) !== 'active'
    ) {

        session_unset();
        session_destroy();

        header("Location: a-login.php");
        exit();

    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN NAME
    |--------------------------------------------------------------------------
    |
    | Your original project uses full_name.
    | admin_name is kept as a fallback.
    |--------------------------------------------------------------------------
    */

    if (isset($admin['full_name'])) {

        $adminName = $admin['full_name'];

    } elseif (isset($admin['admin_name'])) {

        $adminName = $admin['admin_name'];

    } else {

        $adminName = "Administrator";

    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN EMAIL
    |--------------------------------------------------------------------------
    */

    $adminEmail = $admin['email'] ?? "";


    /*
    |--------------------------------------------------------------------------
    | REFRESH SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION['admin_id'] = (int)$admin['admin_id'];

    $_SESSION['admin_name'] = $adminName;

    $_SESSION['admin_email'] = $adminEmail;


    /*
    |--------------------------------------------------------------------------
    | OPTIONAL ROLE
    |--------------------------------------------------------------------------
    | Keep compatibility with your original project.
    |--------------------------------------------------------------------------
    */

    if (isset($admin['role'])) {

        $_SESSION['admin_role'] = $admin['role'];

    }

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT ERROR
    |--------------------------------------------------------------------------
    | Show the actual error temporarily so we can identify DB problems.
    |--------------------------------------------------------------------------
    */

    die(
        "Admin authentication database error: "
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );

}