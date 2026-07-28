<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : config/whatsapp.php
 * Purpose : WhatsApp Cloud API Configuration
 *******************************************************/

declare(strict_types=1);

/*======================================================
=            WHATSAPP CONFIGURATION
======================================================*/

// Meta WhatsApp Cloud API

define('WA_ACCESS_TOKEN', 'YOUR_ACCESS_TOKEN');
define('WA_PHONE_NUMBER_ID', 'YOUR_PHONE_NUMBER_ID');
define('WA_API_VERSION', 'v23.0');

define(
    'WA_API_URL',
    'https://graph.facebook.com/' .
    WA_API_VERSION .
    '/' .
    WA_PHONE_NUMBER_ID .
    '/messages'
);

/*======================================================
=            SEND TEXT MESSAGE
======================================================*/

function sendWhatsAppMessage(
    string $phone,
    string $message
): bool {

    $payload = [

        "messaging_product" => "whatsapp",

        "to" => $phone,

        "type" => "text",

        "text" => [

            "preview_url" => false,

            "body" => $message

        ]

    ];

    $headers = [

        "Authorization: Bearer " . WA_ACCESS_TOKEN,

        "Content-Type: application/json"

    ];

    $ch = curl_init(WA_API_URL);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {

        error_log(curl_error($ch));

        curl_close($ch);

        return false;

    }

    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {

        return true;

    }

    error_log($response);

    return false;

}

/*======================================================
=            READY MADE MESSAGES
======================================================*/

function sendWelcomeMessage(
    string $phone,
    string $name
): bool {

    $message =
"Hello {$name},

Welcome to Three O' Clock Cafe ☕

Thank you for registering with us.

We look forward to serving you!

-Team Three O' Clock Cafe";

    return sendWhatsAppMessage($phone, $message);

}

function sendOrderReceivedMessage(
    string $phone,
    string $orderNumber
): bool {

    $message =
"Your order #{$orderNumber} has been received.

We are preparing your delicious meal 🍔☕

Thank you for choosing Three O' Clock Cafe.";

    return sendWhatsAppMessage($phone, $message);

}

function sendOrderReadyMessage(
    string $phone,
    string $orderNumber
): bool {

    $message =
"Great News!

Your Order #{$orderNumber} is ready.

Please collect your order.

Thank you.";

    return sendWhatsAppMessage($phone, $message);

}

function sendOrderDeliveredMessage(
    string $phone,
    string $orderNumber
): bool {

    $message =
"Your Order #{$orderNumber} has been delivered.

Thank you for ordering from Three O' Clock Cafe.

We hope to serve you again ❤️";

    return sendWhatsAppMessage($phone, $message);

}

function sendBookingConfirmation(
    string $phone,
    string $customer,
    string $date,
    string $time,
    string $table
): bool {

    $message =
"Hello {$customer},

Your table booking has been confirmed.

Table : {$table}
Date : {$date}
Time : {$time}

See you soon ☕";

    return sendWhatsAppMessage($phone, $message);

}