<?php
session_start();

require_once "config/db.php";

$pageTitle = "Checkout";

/*=========================================
LOGIN CHECK
=========================================*/

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = "checkout.php";

    header("Location: login.php");

    exit();

}

$user_id = $_SESSION['user_id'];

/*=========================================
GET CUSTOMER DETAILS
=========================================*/

$stmt = $pdo->prepare("
SELECT
user_id,
full_name,
email,
phone,
address,
city,
state,
pincode
FROM users
WHERE user_id = ?
LIMIT 1
");

$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

/*=========================================
GET CART ITEMS
=========================================*/

$stmt = $pdo->prepare("
SELECT

c.cart_id,
c.quantity,

p.product_id,
p.product_name,
p.price,
p.discount_percent,
p.stock,

pi.image_name

FROM cart c

INNER JOIN products p

ON p.product_id = c.product_id

LEFT JOIN product_images pi

ON pi.product_id = p.product_id
AND pi.is_primary = 1

WHERE c.user_id = ?
");

$stmt->execute([$user_id]);

$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
EMPTY CART
=========================================*/

if (count($cartItems) == 0) {

    $_SESSION['cart_error'] = "Your cart is empty.";

    header("Location: cart.php");

    exit();

}

/*=========================================
CALCULATE TOTAL
=========================================*/

$subtotal = 0;

foreach ($cartItems as &$item) {

    $price = (float)$item['price'];

    $discount = (float)$item['discount_percent'];

    $finalPrice = $price;

    if ($discount > 0) {

        $finalPrice = $price - (($price * $discount) / 100);

    }

    $item['final_price'] = $finalPrice;

    $item['item_total'] =

        $finalPrice * $item['quantity'];

    $subtotal += $item['item_total'];

}

$gst = round($subtotal * 0.05, 2);

$delivery = ($subtotal >= 500) ? 0 : 40;

$grandTotal =

$subtotal +

$gst +

$delivery;

/*=========================================
LAYOUT
=========================================*/

require_once "includes/header.php";
require_once "includes/navbar.php";
?>
<<section class="checkout-section">

<div class="container">

<form action="place_order.php" method="POST">

<div class="row g-4">

    <!-- =========================================
         LEFT COLUMN
    ========================================== -->

    <div class="col-lg-8">

        <div class="checkout-card">

            <h3 class="section-title">

                <i class="bi bi-person-circle me-2"></i>

                Customer Details

            </h3>

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Full Name

                    </label>

                    <input
                        type="text"
                        name="full_name"
                        class="form-control"
                        value="<?= htmlspecialchars($user['full_name'] ?? ''); ?>"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Email Address

                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= htmlspecialchars($user['email'] ?? ''); ?>"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Mobile Number

                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control"
                        value="<?= htmlspecialchars($user['phone'] ?? ''); ?>"
                        required>

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Pincode

                    </label>

                    <input
                        type="text"
                        name="pincode"
                        class="form-control"
                        value="<?= htmlspecialchars($user['pincode'] ?? ''); ?>">

                </div>

            </div>

            <hr class="my-4">

            <h3 class="section-title">

                <i class="bi bi-geo-alt-fill me-2"></i>

                Delivery Address

            </h3>

            <div class="mb-3">

                <label class="form-label">

                    Complete Address

                </label>

                <textarea
                    name="address"
                    class="form-control"
                    rows="4"
                    required><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>

            </div>

            <div class="row">

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        City

                    </label>

                    <input
                        type="text"
                        name="city"
                        class="form-control"
                        value="<?= htmlspecialchars($user['city'] ?? ''); ?>">

                </div>

                <div class="col-md-6 mb-3">

                    <label class="form-label">

                        Landmark

                    </label>

                    <input
                        type="text"
                        name="landmark"
                        class="form-control"
                        placeholder="Near School, Temple, Mall, etc.">

                </div>

            </div>

            <hr class="my-4">

            <h3 class="section-title">

                <i class="bi bi-credit-card me-2"></i>

                Payment Method

            </h3>

            <div class="payment-option">

                <label>

                    <input
                        type="radio"
                        name="payment_method"
                        value="COD"
                        checked>

                    Cash On Delivery

                </label>

            </div>

            <div class="payment-option">

                <label>

                    <input
                        type="radio"
                        name="payment_method"
                        value="ONLINE">

                    Online Payment (Razorpay)

                </label>

            </div>

        </div>

    </div>

    <!-- =========================================
         RIGHT COLUMN
    ========================================== -->

    <div class="col-lg-4">

        <div class="summary-card">

            <h3>

                Order Summary

            </h3>

            <?php foreach($cartItems as $item): ?>

            <div class="summary-item">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <strong>

                            <?= htmlspecialchars($item['product_name']); ?>

                        </strong>

                        <br>

                        <small>

                            Qty : <?= $item['quantity']; ?>

                        </small>

                    </div>

                    <div class="price">

                        ₹<?= number_format($item['item_total'],2); ?>

                    </div>

                </div>

            </div>

            <?php endforeach; ?>

            <div class="summary-row">

                <span>Subtotal</span>

                <span>

                    ₹<?= number_format($subtotal,2); ?>

                </span>

            </div>

            <div class="summary-row">

                <span>GST (5%)</span>

                <span>

                    ₹<?= number_format($gst,2); ?>

                </span>

            </div>

            <div class="summary-row">

                <span>Delivery</span>

                <span class="free-delivery">

                    <?= $delivery == 0 ? "FREE" : "₹".number_format($delivery,2); ?>

                </span>

            </div>

            <hr>

            <div class="summary-total">

                <h4>

                    Total

                </h4>

                <h4>

                    ₹<?= number_format($grandTotal,2); ?>

                </h4>

            </div>

            <button
                type="submit"
                class="btn-place-order">

                <i class="bi bi-bag-check-fill me-2"></i>

                Place Order

            </button>

        </div>

    </div>

</div>

</form>

</div>

</section>

<?php require_once "includes/footer.php"; ?>