<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : config/session.php
 * Purpose : Session Management
 *******************************************************/

declare(strict_types=1);

/*======================================================
=            START SESSION
======================================================*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*======================================================
=            SESSION TIMEOUT (30 Minutes)
======================================================*/

$timeout = 1800; // 30 Minutes

if (isset($_SESSION['LAST_ACTIVITY'])) {

    if ((time() - $_SESSION['LAST_ACTIVITY']) > $timeout) {

        session_unset();
        session_destroy();

        header("Location: login.php");
        exit();
    }
}

$_SESSION['LAST_ACTIVITY'] = time();

/*======================================================
=            USER LOGIN CHECK
======================================================*/

function isUserLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/*======================================================
=            ADMIN LOGIN CHECK
======================================================*/

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']);
}

/*======================================================
=            USER AUTHENTICATION
======================================================*/

function requireUserLogin(): void
{
    if (!isUserLoggedIn()) {

        header("Location: login.php");
        exit();
    }
}

/*======================================================
=            ADMIN AUTHENTICATION
======================================================*/

function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {

        header("Location: login.php");
        exit();
    }
}

/*======================================================
=            USER LOGOUT
======================================================*/

function logoutUser(): void
{
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/*======================================================
=            ADMIN LOGOUT
======================================================*/

function logoutAdmin(): void
{
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}
?>