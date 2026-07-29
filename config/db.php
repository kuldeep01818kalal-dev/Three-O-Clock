<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : config/database.php
 * Purpose : Database Connection (PDO)
 *******************************************************/

declare(strict_types=1);

/*======================================================
=            DATABASE CONFIGURATION
======================================================*/

define('DB_HOST', 'localhost');
define('DB_NAME', 'three_oclock_cafe');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/*======================================================
=            PDO DATABASE CONNECTION
======================================================*/

try {

    $dsn = "mysql:host=" . DB_HOST .
           ";dbname=" . DB_NAME .
           ";charset=" . DB_CHARSET;

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );

} catch (PDOException $e) {

    die(
        "<h2 style='font-family:Arial;color:red;text-align:center;margin-top:100px;'>
        Database Connection Failed
        </h2>
        <p style='text-align:center;font-size:16px;'>
        " . htmlspecialchars($e->getMessage()) . "
        </p>"
    );

}
?>