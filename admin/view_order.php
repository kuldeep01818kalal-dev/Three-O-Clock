<?php
declare(strict_types=1);

session_start();

require_once "includes/a-auth.php";

/*
|--------------------------------------------------------------------------
| Backward Compatibility
|--------------------------------------------------------------------------
|
| The project now uses order_details.php as the main order-detail page.
| Older links may still point to view_order.php, so redirect them here.
|
*/

$orderId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$orderId || $orderId < 1) {

    header(
        "Location: orders.php?" .
        http_build_query([
            'message_type' => 'danger',
            'message' => 'Invalid order selected.'
        ])
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Redirect to Canonical Order Details Page
|--------------------------------------------------------------------------
*/

header(
    "Location: order_details.php?id=" .
    urlencode((string) $orderId)
);

exit;