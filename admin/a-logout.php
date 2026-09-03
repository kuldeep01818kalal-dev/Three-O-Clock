<?php
declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| ADMIN LOGOUT
|--------------------------------------------------------------------------
| Destroy only the admin authentication session.
| Other application/session data is preserved.
|--------------------------------------------------------------------------
*/

unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_name'],
    $_SESSION['admin_role']
);

/*
|--------------------------------------------------------------------------
| Regenerate session ID
|--------------------------------------------------------------------------
| Helps prevent session fixation after logout.
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);

/*
|--------------------------------------------------------------------------
| Redirect to Admin Login
|--------------------------------------------------------------------------
*/

header("Location: a-login.php");
exit;