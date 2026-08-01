<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . "/config/db.php";

$pageTitle = "My Orders";

/*=========================================
LOGIN CHECK
=========================================*/

if (!isset($_SESSION['user_id'])) {

    $_SESSION['redirect_after_login'] = "my_orders.php";

    header("Location: login.php");

    exit();

}

$user_id = (int)$_SESSION['user_id'];

/*=========================================
FETCH ORDERS
=========================================*/

$stmt = $pdo->prepare("
SELECT
    order_id,
    order_number,
    ordered_at,
    grand_total,
    payment_method,
    payment_status,
    order_status
FROM orders
WHERE user_id = ?
ORDER BY ordered_at DESC
");

$stmt->execute([$user_id]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================
LAYOUT
=========================================*/

require_once "includes/header.php";
require_once "includes/navbar.php";
?>

<section class="orders-section py-5">

<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="orders-title">

                <i class="bi bi-bag-check-fill me-2"></i>

                My Orders

            </h2>

            <p class="text-muted">

                View all your previous orders.

            </p>

        </div>

        <a href="menu.php" class="btn btn-success">

            <i class="bi bi-plus-circle me-2"></i>

            Order More

        </a>

    </div>

    <?php if(empty($orders)): ?>

    <div class="empty-orders">

        <i class="bi bi-cart-x"></i>

        <h4>No Orders Yet</h4>

        <p>

            You haven't placed any orders yet.

        </p>

        <a href="menu.php" class="btn btn-success">

            Browse Menu

        </a>

    </div>

    <?php else: ?>

    <div class="row g-4">

        <?php foreach($orders as $order): ?>

        <?php

        $badge = "secondary";

        switch($order['order_status']){

            case "Pending":
                $badge="warning";
                break;

            case "Preparing":
                $badge="info";
                break;

            case "Out for Delivery":
                $badge="primary";
                break;

            case "Delivered":
                $badge="success";
                break;

            case "Cancelled":
                $badge="danger";
                break;

        }

        ?>

        <div class="col-lg-6">

            <div class="order-card">

                <div class="order-header">

                    <div>

                        <h5>

                            Order #<?= htmlspecialchars($order['order_number']); ?>

                        </h5>

                        <small>

                            <?= date("d M Y, h:i A", strtotime($order['ordered_at'])); ?>

                        </small>

                    </div>

                    <span class="badge bg-<?= $badge; ?>">

                        <?= htmlspecialchars($order['order_status']); ?>

                    </span>

                </div>

                <hr>

                <div class="order-info">

                    <div>

                        <strong>Total</strong>

                        <p>

                            ₹<?= number_format($order['grand_total'],2); ?>

                        </p>

                    </div>

                    <div>

                        <strong>Payment</strong>

                        <p>

                            <?= htmlspecialchars($order['payment_method']); ?>

                        </p>

                    </div>

                    <div>

                        <strong>Status</strong>

                        <p>

                            <?= htmlspecialchars($order['payment_status']); ?>

                        </p>

                    </div>

                </div>

                <div class="mt-3 d-flex gap-2">

                    <a
                        href="order_details.php?id=<?= $order['order_id']; ?>"
                        class="btn btn-dark">

                        <i class="bi bi-eye me-2"></i>

                        View Details

                    </a>

                    <?php if($order['order_status']=="Pending"): ?>

                    <a
                        href="cancel_order.php?id=<?= $order['order_id']; ?>"
                        class="btn btn-outline-danger"
                        onclick="return confirm('Cancel this order?');">

                        Cancel

                    </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <?php endforeach; ?>

    </div>

    <?php endif; ?>

</div>

</section>

<?php require_once "includes/footer.php"; ?>