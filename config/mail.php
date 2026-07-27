<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : config/mail.php
 * Purpose : PHPMailer Configuration
 *******************************************************/

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

/*======================================================
=            MAIL CONFIGURATION
======================================================*/

define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'your_email@gmail.com');
define('MAIL_PASSWORD', 'your_16_character_app_password');
define('MAIL_ENCRYPTION', 'tls');

define('MAIL_FROM_EMAIL', 'your_email@gmail.com');
define('MAIL_FROM_NAME', "Three O' Clock Cafe");

/*======================================================
=            SEND EMAIL FUNCTION
======================================================*/

function sendEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $body
): bool {

    $mail = new PHPMailer(true);

    try {

        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        // UTF-8 Support
        $mail->CharSet = "UTF-8";

        // Sender
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Receiver
        $mail->addAddress($toEmail, $toName);

        // Email Format
        $mail->isHTML(true);

        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Mail Error: " . $mail->ErrorInfo);

        return false;
    }
}