<?php

/*
|--------------------------------------------------------------------------
| Three O' Clock Cafe
| Common Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('cleanInput')) {

    function cleanInput($data)
    {
        return htmlspecialchars(
            trim($data),
            ENT_QUOTES,
            'UTF-8'
        );
    }

}

if (!function_exists('redirect')) {

    function redirect($url)
    {
        header("Location: {$url}");
        exit();
    }

}

if (!function_exists('isLoggedIn')) {

    function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

}

if (!function_exists('isAdminLoggedIn')) {

    function isAdminLoggedIn()
    {
        return isset($_SESSION['admin_id']);
    }

}

if (!function_exists('formatPrice')) {

    function formatPrice($amount)
    {
        return '₹' . number_format($amount, 2);
    }

}

if (!function_exists('generateOrderNumber')) {

    function generateOrderNumber($id)
    {
        return 'TOC' . str_pad($id, 6, '0', STR_PAD_LEFT);
    }

}

if (!function_exists('setFlash')) {

    function setFlash($type, $message)
    {
        $_SESSION['flash'] = [

            'type' => $type,

            'message' => $message

        ];
    }

}

if (!function_exists('showFlash')) {

    function showFlash()
    {
        if (!isset($_SESSION['flash'])) {

            return;

        }

        $flash = $_SESSION['flash'];

        echo '<div class="alert alert-'
            . $flash['type']
            . ' alert-dismissible fade show">';

        echo cleanInput($flash['message']);

        echo '<button class="btn-close"
                     data-bs-dismiss="alert"></button>';

        echo '</div>';

        unset($_SESSION['flash']);
    }

}

if (!function_exists('currentDateTime')) {

    function currentDateTime()
    {
        return date('Y-m-d H:i:s');
    }

}

if (!function_exists('currentDate')) {

    function currentDate()
    {
        return date('Y-m-d');
    }

}