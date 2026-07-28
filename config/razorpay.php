<?php
/*******************************************************
 * Project : Three O' Clock Cafe Management System
 * File    : config/razorpay.php
 * Purpose : Razorpay Configuration
 *******************************************************/

declare(strict_types=1);

/*======================================================
=            RAZORPAY CONFIGURATION
======================================================*/

define('RAZORPAY_KEY_ID', 'rzp_test_your_key_id');
define('RAZORPAY_KEY_SECRET', 'your_key_secret');

/*======================================================
=            CREATE PAYMENT ORDER
======================================================*/

function createRazorpayOrder(
    float $amount,
    string $receipt,
    string $currency = 'INR'
): array {

    $url = "https://api.razorpay.com/v1/orders";

    $data = [
        "amount" => (int)($amount * 100), // Convert ₹ to paise
        "currency" => $currency,
        "receipt" => $receipt,
        "payment_capture" => 1
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {

        curl_close($ch);

        return [
            "success" => false,
            "message" => curl_error($ch)
        ];
    }

    curl_close($ch);

    return json_decode($response, true);
}

/*======================================================
=            VERIFY PAYMENT SIGNATURE
======================================================*/

function verifyPaymentSignature(
    string $orderId,
    string $paymentId,
    string $signature
): bool {

    $generated = hash_hmac(
        'sha256',
        $orderId . "|" . $paymentId,
        RAZORPAY_KEY_SECRET
    );

    return hash_equals($generated, $signature);
}
?>