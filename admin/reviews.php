<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Reviews";

/* =========================================================
   HELPERS
========================================================= */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function ratingStars(int $rating): string
{
    $rating = max(1, min(5, $rating));

    $html = '';

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="bi bi-star-fill review-star filled"></i>';
        } else {
            $html .= '<i class="bi bi-star review-star"></i>';
        }
    }

    return $html;
}

function statusClass(string $status): string
{
    return match ($status) {
        'Approved' => 'approved',
        'Rejected' => 'rejected',
        default => 'pending'
    };
}

/* =========================================================
   DATABASE CONNECTION
========================================================= */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection is not available.");
}

/* =========================================================
   CSRF TOKEN
========================================================= */

if (empty($_SESSION['reviews_csrf'])) {
    $_SESSION['reviews_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['reviews_csrf'];

/* =========================================================
   HANDLE ACTIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        $_SESSION['review_error'] = "Invalid security token.";
        header("Location: reviews.php");
        exit;
    }

    $action = $_POST['action'] ?? '';
    $reviewId = filter_input(
        INPUT_POST,
        'review_id',
        FILTER_VALIDATE_INT
    );

    if (!$reviewId || $reviewId < 1) {
        $_SESSION['review_error'] = "Invalid review selected.";
        header("Location: reviews.php");
        exit;
    }

    try {

        /* -------------------------------------------------
           UPDATE STATUS
        ------------------------------------------------- */

        if ($action === 'update_status') {

            $status = trim((string)($_POST['status'] ?? ''));

            $allowedStatuses = [
                'Pending',
                'Approved',
                'Rejected'
            ];

            if (!in_array($status, $allowedStatuses, true)) {
                throw new RuntimeException("Invalid review status.");
            }

            $stmt = $pdo->prepare("
                UPDATE reviews
                SET status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE review_id = :review_id
            ");

            $stmt->execute([
                ':status' => $status,
                ':review_id' => $reviewId
            ]);

            $_SESSION['review_success'] =
                "Review status updated successfully.";
        }

        /* -------------------------------------------------
           DELETE REVIEW
        ------------------------------------------------- */

        elseif ($action === 'delete_review') {

            $stmt = $pdo->prepare("
                DELETE FROM reviews
                WHERE review_id = :review_id
            ");

            $stmt->execute([
                ':review_id' => $reviewId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("Review not found.");
            }

            $_SESSION['review_success'] =
                "Review deleted successfully.";
        }

        else {
            throw new RuntimeException("Invalid action.");
        }

    } catch (Throwable $e) {

        $_SESSION['review_error'] =
            $e->getMessage();
    }

    header("Location: reviews.php");
    exit;
}

/* =========================================================
   FILTERS
========================================================= */

$search = trim((string)($_GET['search'] ?? ''));

$statusFilter = trim((string)($_GET['status'] ?? ''));

$ratingFilter = filter_input(
    INPUT_GET,
    'rating',
    FILTER_VALIDATE_INT
);

if (!in_array(
    $statusFilter,
    ['Pending', 'Approved', 'Rejected'],
    true
)) {
    $statusFilter = '';
}

if (
    $ratingFilter === false ||
    $ratingFilter === null ||
    $ratingFilter < 1 ||
    $ratingFilter > 5
) {
    $ratingFilter = null;
}

/* =========================================================
   BUILD REVIEW QUERY
========================================================= */

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            r.review LIKE :search
            OR u.full_name LIKE :search
            OR u.email LIKE :search
            OR p.product_name LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {

    $where[] = "r.status = :status";

    $params[':status'] = $statusFilter;
}

if ($ratingFilter !== null) {

    $where[] = "r.rating = :rating";

    $params[':rating'] = $ratingFilter;
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

/* =========================================================
   FETCH REVIEWS
========================================================= */

$reviews = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            r.review_id,
            r.user_id,
            r.product_id,
            r.rating,
            r.review,
            r.status,
            r.created_at,
            r.updated_at,

            u.full_name,
            u.email,

            p.product_name

        FROM reviews r

        LEFT JOIN users u
            ON u.user_id = r.user_id

        INNER JOIN products p
            ON p.product_id = r.product_id

        $whereSql

        ORDER BY r.created_at DESC,
                 r.review_id DESC
    ");

    $stmt->execute($params);

    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $_SESSION['review_error'] =
        "Unable to load reviews.";
}

/* =========================================================
   REVIEW STATISTICS
========================================================= */

$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'average' => 0
];

try {

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_reviews,

            COALESCE(
                SUM(status = 'Pending'),
                0
            ) AS pending_reviews,

            COALESCE(
                SUM(status = 'Approved'),
                0
            ) AS approved_reviews,

            COALESCE(
                SUM(status = 'Rejected'),
                0
            ) AS rejected_reviews,

            COALESCE(
                AVG(rating),
                0
            ) AS average_rating

        FROM reviews
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {

        $stats['total'] =
            (int)$row['total_reviews'];

        $stats['pending'] =
            (int)$row['pending_reviews'];

        $stats['approved'] =
            (int)$row['approved_reviews'];

        $stats['rejected'] =
            (int)$row['rejected_reviews'];

        $stats['average'] =
            round((float)$row['average_rating'], 1);
    }

} catch (Throwable $e) {
    // Keep default statistics.
}

/* =========================================================
   FLASH MESSAGES
========================================================= */

$successMessage =
    $_SESSION['review_success'] ?? '';

$errorMessage =
    $_SESSION['review_error'] ?? '';

unset(
    $_SESSION['review_success'],
    $_SESSION['review_error']
);

/* =========================================================
   HEADER
========================================================= */

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="reviews-page-header">

            <div>

                <div class="reviews-breadcrumb">
                    <span>Business</span>
                    <i class="bi bi-chevron-right"></i>
                    <strong>Reviews</strong>
                </div>

                <h1 class="reviews-title">
                    Customer Reviews
                </h1>

                <p class="reviews-subtitle">
                    Manage customer feedback and review approvals.
                </p>

            </div>

        </div>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($successMessage !== ''): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                data-auto-hide="true"
                role="alert"
            >
                <i class="bi bi-check-circle-fill me-2"></i>

                <?= e($successMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== ''): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                data-auto-hide="true"
                role="alert"
            >
                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?= e($errorMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>

        <?php endif; ?>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="row g-3 mb-4">

            <!-- TOTAL -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="review-stat-card">

                    <div class="review-stat-icon total">
                        <i class="bi bi-chat-square-text-fill"></i>
                    </div>

                    <div class="review-stat-content">

                        <span>Total Reviews</span>

                        <strong>
                            <?= number_format($stats['total']); ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- PENDING -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="review-stat-card">

                    <div class="review-stat-icon pending">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <div class="review-stat-content">

                        <span>Pending</span>

                        <strong>
                            <?= number_format($stats['pending']); ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- APPROVED -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="review-stat-card">

                    <div class="review-stat-icon approved">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>

                    <div class="review-stat-content">

                        <span>Approved</span>

                        <strong>
                            <?= number_format($stats['approved']); ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- RATING -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="review-stat-card">

                    <div class="review-stat-icon rating">
                        <i class="bi bi-star-fill"></i>
                    </div>

                    <div class="review-stat-content">

                        <span>Average Rating</span>

                        <strong>
                            <?= number_format($stats['average'], 1); ?>
                            <small>/ 5</small>
                        </strong>

                    </div>

                </div>

            </div>

        </div>


        <!-- =================================================
             FILTER CARD
        ================================================== -->

        <div class="reviews-filter-card">

            <form
                method="GET"
                action="reviews.php"
                class="reviews-filter-form"
            >

                <!-- SEARCH -->

                <div class="reviews-search-box">

                    <i class="bi bi-search"></i>

                    <input
                        type="search"
                        name="search"
                        value="<?= e($search); ?>"
                        placeholder="Search customer, email, product or review..."
                        autocomplete="off"
                    >

                </div>


                <!-- STATUS -->

                <div class="reviews-filter-field">

                    <label for="reviewStatus">
                        Status
                    </label>

                    <select
                        name="status"
                        id="reviewStatus"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <option
                            value="Pending"
                            <?= $statusFilter === 'Pending' ? 'selected' : ''; ?>
                        >
                            Pending
                        </option>

                        <option
                            value="Approved"
                            <?= $statusFilter === 'Approved' ? 'selected' : ''; ?>
                        >
                            Approved
                        </option>

                        <option
                            value="Rejected"
                            <?= $statusFilter === 'Rejected' ? 'selected' : ''; ?>
                        >
                            Rejected
                        </option>

                    </select>

                </div>


                <!-- RATING -->

                <div class="reviews-filter-field">

                    <label for="reviewRating">
                        Rating
                    </label>

                    <select
                        name="rating"
                        id="reviewRating"
                    >

                        <option value="">
                            All Ratings
                        </option>

                        <?php for ($i = 5; $i >= 1; $i--): ?>

                            <option
                                value="<?= $i; ?>"
                                <?= $ratingFilter === $i ? 'selected' : ''; ?>
                            >
                                <?= $i; ?> Star<?= $i > 1 ? 's' : ''; ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </div>


                <!-- BUTTON -->

                <button
                    type="submit"
                    class="btn review-filter-btn"
                >
                    <i class="bi bi-funnel-fill me-1"></i>
                    Filter
                </button>


                <?php if (
                    $search !== '' ||
                    $statusFilter !== '' ||
                    $ratingFilter !== null
                ): ?>

                    <a
                        href="reviews.php"
                        class="btn review-clear-btn"
                    >
                        <i class="bi bi-x-lg me-1"></i>
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- =================================================
             REVIEWS TABLE
        ================================================== -->

        <div class="reviews-card">

            <div class="reviews-card-header">

                <div>

                    <h2>
                        Reviews
                    </h2>

                    <span>
                        <?= number_format(count($reviews)); ?>
                        review<?= count($reviews) !== 1 ? 's' : ''; ?>
                        found
                    </span>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table reviews-table">

                    <thead>

                        <tr>

                            <th>
                                Customer
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Rating
                            </th>

                            <th class="review-text-column">
                                Review
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date
                            </th>

                            <th class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($reviews)): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="reviews-empty"
                            >

                                <div class="reviews-empty-icon">

                                    <i class="bi bi-chat-square-text"></i>

                                </div>

                                <strong>
                                    No reviews found
                                </strong>

                                <span>
                                    Try changing your filters or search.
                                </span>

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($reviews as $review): ?>

                            <?php
                            $rating = (int)$review['rating'];
                            $status = (string)$review['status'];

                            $customerName =
                                $review['full_name']
                                ?: 'Guest User';

                            $customerEmail =
                                $review['email']
                                ?: 'No email';

                            $reviewText =
                                trim((string)($review['review'] ?? ''));

                            if ($reviewText === '') {
                                $reviewText = 'No written review.';
                            }
                            ?>

                            <tr data-search-item>

                                <!-- CUSTOMER -->

                                <td>

                                    <div class="review-customer">

                                        <div class="review-avatar">

                                            <i class="bi bi-person-fill"></i>

                                        </div>

                                        <div>

                                            <strong>
                                                <?= e($customerName); ?>
                                            </strong>

                                            <span>
                                                <?= e($customerEmail); ?>
                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <!-- PRODUCT -->

                                <td>

                                    <div class="review-product">

                                        <i class="bi bi-cup-hot-fill"></i>

                                        <span>
                                            <?= e($review['product_name']); ?>
                                        </span>

                                    </div>

                                </td>


                                <!-- RATING -->

                                <td>

                                    <div
                                        class="review-rating"
                                        title="<?= $rating; ?> out of 5"
                                    >

                                        <?= ratingStars($rating); ?>

                                        <strong>
                                            <?= $rating; ?>/5
                                        </strong>

                                    </div>

                                </td>


                                <!-- REVIEW -->

                                <td class="review-text-column">

                                    <div class="review-message">

                                        <?= e($reviewText); ?>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="review-status <?= statusClass($status); ?>"
                                    >

                                        <?php if ($status === 'Approved'): ?>

                                            <i class="bi bi-check-circle-fill"></i>

                                        <?php elseif ($status === 'Rejected'): ?>

                                            <i class="bi bi-x-circle-fill"></i>

                                        <?php else: ?>

                                            <i class="bi bi-clock-fill"></i>

                                        <?php endif; ?>

                                        <?= e($status); ?>

                                    </span>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <div class="review-date">

                                        <strong>
                                            <?= date(
                                                'd M Y',
                                                strtotime((string)$review['created_at'])
                                            ); ?>
                                        </strong>

                                        <span>
                                            <?= date(
                                                'h:i A',
                                                strtotime((string)$review['created_at'])
                                            ); ?>
                                        </span>

                                    </div>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="review-actions">

                                        <!-- STATUS DROPDOWN -->

                                        <div class="dropdown">

                                            <button
                                                type="button"
                                                class="btn review-action-btn"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                                title="Change status"
                                            >
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>

                                            <ul class="dropdown-menu dropdown-menu-end">

                                                <li>
                                                    <h6 class="dropdown-header">
                                                        Change Status
                                                    </h6>
                                                </li>


                                                <?php foreach (
                                                    ['Pending', 'Approved', 'Rejected']
                                                    as $newStatus
                                                ): ?>

                                                    <li>

                                                        <form
                                                            method="POST"
                                                            action="reviews.php"
                                                        >

                                                            <input
                                                                type="hidden"
                                                                name="csrf_token"
                                                                value="<?= e($csrfToken); ?>"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="update_status"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="review_id"
                                                                value="<?= (int)$review['review_id']; ?>"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="status"
                                                                value="<?= e($newStatus); ?>"
                                                            >

                                                            <button
                                                                type="submit"
                                                                class="dropdown-item"
                                                                <?= $status === $newStatus ? 'disabled' : ''; ?>
                                                            >

                                                                <?php if ($newStatus === 'Approved'): ?>

                                                                    <i class="bi bi-check-circle me-2 text-success"></i>

                                                                <?php elseif ($newStatus === 'Rejected'): ?>

                                                                    <i class="bi bi-x-circle me-2 text-danger"></i>

                                                                <?php else: ?>

                                                                    <i class="bi bi-clock me-2 text-warning"></i>

                                                                <?php endif; ?>

                                                                <?= e($newStatus); ?>

                                                            </button>

                                                        </form>

                                                    </li>

                                                <?php endforeach; ?>

                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>

                                                <li>

                                                    <form
                                                        method="POST"
                                                        action="reviews.php"
                                                        onsubmit="return confirm('Are you sure you want to delete this review?');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken); ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete_review"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="review_id"
                                                            value="<?= (int)$review['review_id']; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="dropdown-item text-danger"
                                                        >

                                                            <i class="bi bi-trash3 me-2"></i>

                                                            Delete Review

                                                        </button>

                                                    </form>

                                                </li>

                                            </ul>

                                        </div>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>


<style>

/* =========================================================
   REVIEWS PAGE
========================================================= */

.reviews-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 24px;
}

.reviews-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #9ca3af;
    font-size: 12px;
    margin-bottom: 8px;
}

.reviews-breadcrumb i {
    font-size: 10px;
}

.reviews-breadcrumb strong {
    color: #6b7280;
}

.reviews-title {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #111827;
}

.reviews-subtitle {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* =========================================================
   STAT CARDS
========================================================= */

.review-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    height: 100%;
    padding: 20px;
    background: #ffffff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.review-stat-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 20px;
}

.review-stat-icon.total {
    background: #f3f4f6;
    color: #6b7280;
}

.review-stat-icon.pending {
    background: #fff7ed;
    color: #ea580c;
}

.review-stat-icon.approved {
    background: #ecfdf5;
    color: #059669;
}

.review-stat-icon.rating {
    background: #fffbeb;
    color: #d97706;
}

.review-stat-content {
    min-width: 0;
}

.review-stat-content span {
    display: block;
    color: #6b7280;
    font-size: 12px;
    margin-bottom: 4px;
}

.review-stat-content strong {
    display: block;
    color: #111827;
    font-size: 22px;
    font-weight: 700;
}

.review-stat-content strong small {
    font-size: 12px;
    font-weight: 500;
    color: #9ca3af;
}


/* =========================================================
   FILTER
========================================================= */

.reviews-filter-card {
    margin-bottom: 20px;
    padding: 18px;
    background: #ffffff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.reviews-filter-form {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    flex-wrap: wrap;
}

.reviews-search-box {
    position: relative;
    flex: 1 1 320px;
    min-width: 220px;
}

.reviews-search-box > i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
}

.reviews-search-box input {
    width: 100%;
    height: 42px;
    padding: 0 14px 0 40px;
    border: 1px solid #dfe3e8;
    border-radius: 9px;
    outline: none;
    font-size: 13px;
    color: #111827;
    background: #ffffff;
}

.reviews-search-box input:focus,
.reviews-filter-field select:focus {
    border-color: #8b5e3c;
    box-shadow: 0 0 0 3px rgba(139, 94, 60, 0.10);
}

.reviews-filter-field {
    min-width: 150px;
}

.reviews-filter-field label {
    display: block;
    margin-bottom: 5px;
    color: #6b7280;
    font-size: 11px;
    font-weight: 600;
}

.reviews-filter-field select {
    width: 100%;
    height: 42px;
    padding: 0 32px 0 12px;
    border: 1px solid #dfe3e8;
    border-radius: 9px;
    background: #ffffff;
    color: #374151;
    font-size: 13px;
    outline: none;
}

.review-filter-btn,
.review-clear-btn {
    height: 42px;
    border-radius: 9px;
    padding: 0 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
}

.review-filter-btn {
    color: #ffffff;
    background: #8b5e3c;
    border: 1px solid #8b5e3c;
}

.review-filter-btn:hover {
    color: #ffffff;
    background: #70492f;
    border-color: #70492f;
}

.review-clear-btn {
    color: #6b7280;
    background: #ffffff;
    border: 1px solid #dfe3e8;
}

.review-clear-btn:hover {
    color: #111827;
    background: #f9fafb;
}


/* =========================================================
   REVIEW TABLE
========================================================= */

.reviews-card {
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.reviews-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 22px;
    border-bottom: 1px solid #eef0f2;
}

.reviews-card-header h2 {
    margin: 0 0 3px;
    font-size: 17px;
    font-weight: 700;
    color: #111827;
}

.reviews-card-header span {
    color: #9ca3af;
    font-size: 12px;
}

.reviews-table {
    min-width: 1050px;
    margin: 0;
    vertical-align: middle;
}

.reviews-table thead th {
    padding: 14px 16px;
    background: #fafafa;
    border-bottom: 1px solid #e8eaee;
    color: #6b7280;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}

.reviews-table tbody td {
    padding: 16px;
    border-bottom: 1px solid #f0f1f3;
    color: #374151;
    font-size: 13px;
}

.reviews-table tbody tr:last-child td {
    border-bottom: 0;
}

.reviews-table tbody tr:hover {
    background: #fcfcfc;
}


/* =========================================================
   CUSTOMER
========================================================= */

.review-customer {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 190px;
}

.review-avatar {
    width: 38px;
    height: 38px;
    min-width: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f3f4f6;
    color: #6b7280;
}

.review-customer strong {
    display: block;
    max-width: 145px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #111827;
    font-size: 13px;
}

.review-customer span {
    display: block;
    max-width: 145px;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #9ca3af;
    font-size: 10px;
}


/* =========================================================
   PRODUCT
========================================================= */

.review-product {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 145px;
}

.review-product i {
    color: #8b5e3c;
}

.review-product span {
    color: #374151;
    font-weight: 500;
}


/* =========================================================
   RATING
========================================================= */

.review-rating {
    white-space: nowrap;
}

.review-star {
    color: #d1d5db;
    font-size: 12px;
}

.review-star.filled {
    color: #f59e0b;
}

.review-rating strong {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 10px;
    font-weight: 600;
}


/* =========================================================
   REVIEW TEXT
========================================================= */

.review-text-column {
    min-width: 250px;
    max-width: 330px;
}

.review-message {
    max-width: 320px;
    color: #4b5563;
    font-size: 12px;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}


/* =========================================================
   STATUS
========================================================= */

.review-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.review-status.pending {
    color: #b45309;
    background: #fffbeb;
}

.review-status.approved {
    color: #047857;
    background: #ecfdf5;
}

.review-status.rejected {
    color: #b91c1c;
    background: #fef2f2;
}


/* =========================================================
   DATE
========================================================= */

.review-date strong {
    display: block;
    color: #374151;
    font-size: 11px;
    white-space: nowrap;
}

.review-date span {
    display: block;
    margin-top: 3px;
    color: #9ca3af;
    font-size: 10px;
    white-space: nowrap;
}


/* =========================================================
   ACTIONS
========================================================= */

.review-actions {
    display: flex;
    justify-content: flex-end;
}

.review-action-btn {
    width: 34px;
    height: 34px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.review-action-btn:hover {
    color: #8b5e3c;
    border-color: #d6c2b2;
    background: #faf7f4;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.reviews-empty {
    padding: 65px 20px !important;
    text-align: center;
}

.reviews-empty-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: #f5f6f8;
    color: #9ca3af;
    font-size: 24px;
}

.reviews-empty strong {
    display: block;
    color: #374151;
    font-size: 14px;
}

.reviews-empty span {
    display: block;
    margin-top: 5px;
    color: #9ca3af;
    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991px) {

    .reviews-title {
        font-size: 24px;
    }

    .reviews-filter-form {
        align-items: stretch;
    }

    .reviews-search-box {
        flex-basis: 100%;
    }

    .reviews-filter-field {
        flex: 1;
    }

    .review-filter-btn,
    .review-clear-btn {
        flex: 1;
    }
}


@media (max-width: 575px) {

    .reviews-page-header {
        margin-bottom: 18px;
    }

    .reviews-title {
        font-size: 21px;
    }

    .reviews-subtitle {
        font-size: 12px;
    }

    .reviews-filter-card {
        padding: 14px;
    }

    .reviews-filter-field {
        width: 100%;
        flex-basis: 100%;
    }

    .review-filter-btn,
    .review-clear-btn {
        width: 100%;
        flex-basis: 100%;
    }

    .reviews-card-header {
        padding: 16px;
    }

    .reviews-table thead th,
    .reviews-table tbody td {
        padding: 12px;
    }

}

</style>

<?php require_once "includes/a-footer.php"; ?>