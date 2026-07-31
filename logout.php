<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : logout.php
 * Purpose : Customer Logout
 *******************************************************/

declare(strict_types=1);

session_start();

/*=========================================
=            Clear Session
=========================================*/

$_SESSION = [];

/*=========================================
=            Destroy Session
=========================================*/

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );

}

session_destroy();

/*=========================================
=            Redirect
=========================================*/

header("Location: index.php");

exit();