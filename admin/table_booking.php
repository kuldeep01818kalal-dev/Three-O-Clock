<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Table Bookings";

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bookingStatusClass(string $status): string
{
    return match ($status) {
        'Pending'   => 'pending',
        'Confirmed' => 'confirmed',
        'Cancelled' => 'cancelled',
        'Completed' => 'completed',
        default     => 'pending',
    };
}

function formatDate(string $date): string
{
    $timestamp = strtotime($date);

    return $timestamp
        ? date('d M Y', $timestamp)
        : $date;
}

function formatTime(string $time): string
{
    $timestamp = strtotime($time);

    return $timestamp
        ? date('h:i A', $timestamp)
        : $time;
}

function redirectWithMessage(string $type, string $message): never
{
    $query = http_build_query([
        'message_type' => $type,
        'message'      => $message,
    ]);

    header("Location: table_booking.php?" . $query);
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];

/*
|--------------------------------------------------------------------------
| Handle POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals($csrfToken, $postedToken)
    ) {
        redirectWithMessage('danger', 'Invalid security token. Please try again.');
    }

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Update Booking Status
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_status') {

        $bookingId = filter_input(
            INPUT_POST,
            'booking_id',
            FILTER_VALIDATE_INT
        );

        $newStatus = trim((string)($_POST['booking_status'] ?? ''));

        $allowedStatuses = [
            'Pending',
            'Confirmed',
            'Cancelled',
            'Completed',
        ];

        if (!$bookingId || !in_array($newStatus, $allowedStatuses, true)) {
            redirectWithMessage(
                'danger',
                'Invalid booking information.'
            );
        }

        try {

            $pdo->beginTransaction();

            /*
             * Get booking and associated table.
             */
            $stmt = $pdo->prepare("
                SELECT
                    tb.booking_id,
                    tb.table_id,
                    tb.booking_status,
                    tb.booking_date,
                    tb.booking_time,
                    tb.number_of_guests,
                    ct.capacity,
                    ct.status AS table_status
                FROM table_bookings tb
                INNER JOIN cafe_tables ct
                    ON ct.table_id = tb.table_id
                WHERE tb.booking_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([$bookingId]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $pdo->rollBack();

                redirectWithMessage(
                    'danger',
                    'Booking not found.'
                );
            }

            /*
             * Validate guest count against table capacity.
             */
            if (
                (int)$booking['number_of_guests'] >
                (int)$booking['capacity']
            ) {
                $pdo->rollBack();

                redirectWithMessage(
                    'danger',
                    'The selected table does not have enough capacity for this booking.'
                );
            }

            /*
             * When confirming a booking, make sure the same table
             * is not already reserved at the same date/time.
             */
            if ($newStatus === 'Confirmed') {

                $conflictStmt = $pdo->prepare("
                    SELECT booking_id
                    FROM table_bookings
                    WHERE table_id = ?
                      AND booking_date = ?
                      AND booking_time = ?
                      AND booking_id <> ?
                      AND booking_status = 'Confirmed'
                    LIMIT 1
                ");

                $conflictStmt->execute([
                    $booking['table_id'],
                    $booking['booking_date'],
                    $booking['booking_time'],
                    $bookingId
                ]);

                if ($conflictStmt->fetch(PDO::FETCH_ASSOC)) {

                    $pdo->rollBack();

                    redirectWithMessage(
                        'warning',
                        'This table is already confirmed for the selected date and time.'
                    );
                }
            }

            /*
             * Update booking status.
             */
            $updateStmt = $pdo->prepare("
                UPDATE table_bookings
                SET
                    booking_status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE booking_id = ?
            ");

            $updateStmt->execute([
                $newStatus,
                $bookingId
            ]);

            /*
             * Synchronize table status.
             *
             * Confirmed -> Reserved
             * Cancelled/Completed -> Available
             *
             * Occupied/Maintenance are not overwritten.
             */
            if ($newStatus === 'Confirmed') {

                $tableStmt = $pdo->prepare("
                    UPDATE cafe_tables
                    SET
                        status = 'Reserved',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE table_id = ?
                      AND status <> 'Occupied'
                      AND status <> 'Maintenance'
                ");

                $tableStmt->execute([
                    $booking['table_id']
                ]);

            } elseif (
                $newStatus === 'Cancelled' ||
                $newStatus === 'Completed'
            ) {

                /*
                 * Only release the table if there is no other
                 * confirmed booking for the same table.
                 */
                $otherBookingStmt = $pdo->prepare("
                    SELECT booking_id
                    FROM table_bookings
                    WHERE table_id = ?
                      AND booking_status = 'Confirmed'
                      AND booking_id <> ?
                    LIMIT 1
                ");

                $otherBookingStmt->execute([
                    $booking['table_id'],
                    $bookingId
                ]);

                $otherConfirmedBooking =
                    $otherBookingStmt->fetch(PDO::FETCH_ASSOC);

                if (!$otherConfirmedBooking) {

                    $releaseTableStmt = $pdo->prepare("
                        UPDATE cafe_tables
                        SET
                            status = 'Available',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE table_id = ?
                          AND status = 'Reserved'
                    ");

                    $releaseTableStmt->execute([
                        $booking['table_id']
                    ]);
                }
            }

            $pdo->commit();

            redirectWithMessage(
                'success',
                "Booking #{$bookingId} status updated to {$newStatus}."
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            redirectWithMessage(
                'danger',
                'Unable to update booking. Please try again.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Booking
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete_booking') {

        $bookingId = filter_input(
            INPUT_POST,
            'booking_id',
            FILTER_VALIDATE_INT
        );

        if (!$bookingId) {
            redirectWithMessage(
                'danger',
                'Invalid booking ID.'
            );
        }

        try {

            $pdo->beginTransaction();

            /*
             * Get table before deleting booking.
             */
            $stmt = $pdo->prepare("
                SELECT
                    booking_id,
                    table_id,
                    booking_status
                FROM table_bookings
                WHERE booking_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([$bookingId]);

            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {

                $pdo->rollBack();

                redirectWithMessage(
                    'danger',
                    'Booking not found.'
                );
            }

            $deleteStmt = $pdo->prepare("
                DELETE FROM table_bookings
                WHERE booking_id = ?
            ");

            $deleteStmt->execute([
                $bookingId
            ]);

            /*
             * Release reserved table if no other confirmed booking
             * exists for that table.
             */
            if (!empty($booking['table_id'])) {

                $confirmedStmt = $pdo->prepare("
                    SELECT booking_id
                    FROM table_bookings
                    WHERE table_id = ?
                      AND booking_status = 'Confirmed'
                    LIMIT 1
                ");

                $confirmedStmt->execute([
                    $booking['table_id']
                ]);

                if (!$confirmedStmt->fetch(PDO::FETCH_ASSOC)) {

                    $releaseStmt = $pdo->prepare("
                        UPDATE cafe_tables
                        SET
                            status = 'Available',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE table_id = ?
                          AND status = 'Reserved'
                    ");

                    $releaseStmt->execute([
                        $booking['table_id']
                    ]);
                }
            }

            $pdo->commit();

            redirectWithMessage(
                'success',
                'Booking deleted successfully.'
            );

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            redirectWithMessage(
                'danger',
                'Unable to delete booking.'
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim((string)($_GET['search'] ?? ''));

$statusFilter = trim(
    (string)($_GET['status'] ?? '')
);

$dateFilter = trim(
    (string)($_GET['booking_date'] ?? '')
);

$tableFilter = filter_input(
    INPUT_GET,
    'table_id',
    FILTER_VALIDATE_INT
);

$allowedStatuses = [
    'Pending',
    'Confirmed',
    'Cancelled',
    'Completed',
];

/*
|--------------------------------------------------------------------------
| Build Booking Query
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "
        (
            tb.customer_name LIKE ?
            OR tb.phone LIKE ?
            OR tb.email LIKE ?
            OR CAST(tb.booking_id AS CHAR) LIKE ?
            OR ct.table_number LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if (
    $statusFilter !== '' &&
    in_array($statusFilter, $allowedStatuses, true)
) {

    $where[] = "tb.booking_status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $dateFilter
    );

    if (
        $dateObject &&
        $dateObject->format('Y-m-d') === $dateFilter
    ) {

        $where[] = "tb.booking_date = ?";
        $params[] = $dateFilter;
    }
}

if ($tableFilter) {

    $where[] = "tb.table_id = ?";
    $params[] = $tableFilter;
}

$whereSql = '';

if ($where) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

/*
|--------------------------------------------------------------------------
| Booking Statistics
|--------------------------------------------------------------------------
*/

try {

    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total_bookings,

            SUM(
                CASE
                    WHEN booking_status = 'Pending'
                    THEN 1 ELSE 0
                END
            ) AS pending_bookings,

            SUM(
                CASE
                    WHEN booking_status = 'Confirmed'
                    THEN 1 ELSE 0
                END
            ) AS confirmed_bookings,

            SUM(
                CASE
                    WHEN booking_status = 'Completed'
                    THEN 1 ELSE 0
                END
            ) AS completed_bookings,

            SUM(
                CASE
                    WHEN booking_status = 'Cancelled'
                    THEN 1 ELSE 0
                END
            ) AS cancelled_bookings

        FROM table_bookings
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $stats = [
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'confirmed_bookings' => 0,
        'completed_bookings' => 0,
        'cancelled_bookings' => 0,
    ];
}

/*
|--------------------------------------------------------------------------
| Fetch Tables
|--------------------------------------------------------------------------
*/

try {

    $tablesStmt = $pdo->query("
        SELECT
            table_id,
            table_number,
            capacity,
            location,
            status
        FROM cafe_tables
        ORDER BY
            CAST(table_number AS UNSIGNED),
            table_number
    ");

    $tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $tables = [];
}

/*
|--------------------------------------------------------------------------
| Fetch Bookings
|--------------------------------------------------------------------------
*/

try {

    $bookingSql = "
        SELECT
            tb.booking_id,
            tb.user_id,
            tb.table_id,
            tb.booking_date,
            tb.booking_time,
            tb.number_of_guests,
            tb.customer_name,
            tb.phone,
            tb.email,
            tb.special_request,
            tb.booking_status,
            tb.created_at,
            tb.updated_at,

            ct.table_number,
            ct.capacity,
            ct.location AS table_location,
            ct.status AS table_status,

            u.full_name AS registered_user_name

        FROM table_bookings tb

        INNER JOIN cafe_tables ct
            ON ct.table_id = tb.table_id

        LEFT JOIN users u
            ON u.user_id = tb.user_id

        {$whereSql}

        ORDER BY
            tb.booking_date DESC,
            tb.booking_time DESC,
            tb.booking_id DESC
    ";

    $bookingStmt = $pdo->prepare($bookingSql);

    $bookingStmt->execute($params);

    $bookings = $bookingStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $bookings = [];
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$messageType = trim(
    (string)($_GET['message_type'] ?? '')
);

$message = trim(
    (string)($_GET['message'] ?? '')
);

$allowedMessageTypes = [
    'success',
    'danger',
    'warning',
    'info',
];

if (!in_array($messageType, $allowedMessageTypes, true)) {
    $messageType = '';
}

/*
|--------------------------------------------------------------------------
| Admin Layout
|--------------------------------------------------------------------------
*/

require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">

            <div>
                <h1 class="mb-1 fw-bold">
                    Table Bookings
                </h1>

                <p class="text-muted mb-0">
                    Manage cafe table reservations and booking status.
                </p>
            </div>

            <a
                href="tables.php"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-grid-3x3-gap me-1"></i>
                Manage Tables
            </a>

        </div>

        <!-- =====================================================
             ALERT
        ====================================================== -->

        <?php if ($message !== '' && $messageType !== ''): ?>

            <div
                class="alert alert-<?= e($messageType) ?> alert-dismissible fade show"
                role="alert"
                data-auto-hide="true"
            >
                <i class="bi bi-info-circle me-2"></i>
                <?= e($message) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

        <?php endif; ?>

        <!-- =====================================================
             STATISTICS
        ====================================================== -->

        <div class="table-stats">

            <div class="table-stat-card">

                <div class="table-stat-icon">
                    <i class="bi bi-calendar3"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Total Bookings
                    </span>

                    <div class="table-stat-value">
                        <?= (int)($stats['total_bookings'] ?? 0) ?>
                    </div>

                </div>

            </div>


            <div class="table-stat-card reserved">

                <div class="table-stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Pending
                    </span>

                    <div class="table-stat-value">
                        <?= (int)($stats['pending_bookings'] ?? 0) ?>
                    </div>

                </div>

            </div>


            <div class="table-stat-card available">

                <div class="table-stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Confirmed
                    </span>

                    <div class="table-stat-value">
                        <?= (int)($stats['confirmed_bookings'] ?? 0) ?>
                    </div>

                </div>

            </div>


            <div class="table-stat-card occupied">

                <div class="table-stat-icon">
                    <i class="bi bi-check2-all"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Completed
                    </span>

                    <div class="table-stat-value">
                        <?= (int)($stats['completed_bookings'] ?? 0) ?>
                    </div>

                </div>

            </div>

        </div>

        <!-- =====================================================
             FILTERS
        ====================================================== -->

        <div class="table-filter-card">

            <form
                method="GET"
                action="table_booking.php"
                class="table-filter"
            >

                <div class="table-filter-group">

                    <label for="bookingSearch">
                        Search
                    </label>

                    <input
                        type="search"
                        id="bookingSearch"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Name, phone, email, table..."
                    >

                </div>


                <div class="table-filter-group">

                    <label for="bookingStatus">
                        Booking Status
                    </label>

                    <select
                        id="bookingStatus"
                        name="status"
                    >

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach ($allowedStatuses as $status): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= $statusFilter === $status ? 'selected' : '' ?>
                            >
                                <?= e($status) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="table-filter-group">

                    <label for="bookingDate">
                        Booking Date
                    </label>

                    <input
                        type="date"
                        id="bookingDate"
                        name="booking_date"
                        value="<?= e($dateFilter) ?>"
                    >

                </div>


                <div class="table-filter-group">

                    <label for="bookingTable">
                        Table
                    </label>

                    <select
                        id="bookingTable"
                        name="table_id"
                    >

                        <option value="">
                            All Tables
                        </option>

                        <?php foreach ($tables as $table): ?>

                            <option
                                value="<?= (int)$table['table_id'] ?>"
                                <?= $tableFilter === (int)$table['table_id'] ? 'selected' : '' ?>
                            >
                                Table <?= e($table['table_number']) ?>
                                -
                                <?= (int)$table['capacity'] ?> Seats
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="table-filter-btn"
                    >
                        <i class="bi bi-search me-1"></i>
                        Filter
                    </button>

                    <a
                        href="table_booking.php"
                        class="btn btn-light border"
                        style="min-height:42px;display:inline-flex;align-items:center;"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>

                </div>

            </form>

        </div>

        <!-- =====================================================
             BOOKINGS LIST
        ====================================================== -->

        <div class="table-list-card">

            <div class="table-list-header">

                <div>

                    <h2>
                        Reservation List
                    </h2>

                    <small class="text-muted">
                        <?= count($bookings) ?> booking(s) found
                    </small>

                </div>

                <span class="badge bg-light text-dark border">
                    <i class="bi bi-calendar-check me-1"></i>
                    Reservations
                </span>

            </div>


            <?php if (empty($bookings)): ?>

                <div class="text-center py-5 px-3">

                    <div
                        class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                        style="
                            width:64px;
                            height:64px;
                            background:#f5eee9;
                            color:#8b5e3c;
                            font-size:25px;
                        "
                    >
                        <i class="bi bi-calendar-x"></i>
                    </div>

                    <h5 class="fw-bold mb-1">
                        No bookings found
                    </h5>

                    <p class="text-muted mb-0">
                        Try changing your filters or check again later.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-management-wrapper">

                    <table class="table-management">

                        <thead>

                            <tr>

                                <th>
                                    Booking
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Table
                                </th>

                                <th>
                                    Date & Time
                                </th>

                                <th>
                                    Guests
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($bookings as $booking): ?>

                                <?php
                                $status = (string)$booking['booking_status'];
                                $statusClass = bookingStatusClass($status);

                                $bookingDate = (string)$booking['booking_date'];
                                $bookingTime = (string)$booking['booking_time'];

                                $isPast =
                                    strtotime(
                                        $bookingDate . ' ' . $bookingTime
                                    ) < time();
                                ?>

                                <tr>

                                    <!-- Booking -->

                                    <td>

                                        <div class="fw-bold">
                                            #<?= (int)$booking['booking_id'] ?>
                                        </div>

                                        <small class="text-muted">
                                            <?= e(formatDate((string)$booking['created_at'])) ?>
                                        </small>

                                    </td>


                                    <!-- Customer -->

                                    <td>

                                        <div class="fw-semibold">
                                            <?= e($booking['customer_name']) ?>
                                        </div>

                                        <div class="small text-muted">
                                            <i class="bi bi-telephone me-1"></i>
                                            <?= e($booking['phone']) ?>
                                        </div>

                                        <?php if (!empty($booking['email'])): ?>

                                            <div class="small text-muted">
                                                <i class="bi bi-envelope me-1"></i>
                                                <?= e($booking['email']) ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Table -->

                                    <td>

                                        <div class="fw-semibold">
                                            Table <?= e($booking['table_number']) ?>
                                        </div>

                                        <div class="small text-muted">

                                            <?= e($booking['table_location']) ?>

                                            ·

                                            <?= (int)$booking['capacity'] ?>
                                            seats

                                        </div>

                                    </td>


                                    <!-- Date -->

                                    <td>

                                        <div class="fw-semibold">
                                            <?= e(formatDate($bookingDate)) ?>
                                        </div>

                                        <div class="small text-muted">

                                            <i class="bi bi-clock me-1"></i>

                                            <?= e(formatTime($bookingTime)) ?>

                                        </div>

                                        <?php if ($isPast && $status === 'Pending'): ?>

                                            <span class="small text-warning">
                                                Past booking
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Guests -->

                                    <td>

                                        <span class="fw-semibold">

                                            <i class="bi bi-people me-1"></i>

                                            <?= (int)$booking['number_of_guests'] ?>

                                        </span>

                                    </td>


                                    <!-- Status -->

                                    <td>

                                        <span
                                            class="table-status-badge <?= e($statusClass) ?>"
                                        >
                                            <?= e($status) ?>
                                        </span>

                                    </td>


                                    <!-- Actions -->

                                    <td>

                                        <div
                                            class="d-flex flex-wrap gap-1"
                                            style="min-width:180px;"
                                        >

                                            <?php if ($status === 'Pending'): ?>

                                                <form
                                                    method="POST"
                                                    class="d-inline"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($csrfToken) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="update_status"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_id"
                                                        value="<?= (int)$booking['booking_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_status"
                                                        value="Confirmed"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="table-action-btn success"
                                                        title="Confirm booking"
                                                    >
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (
                                                $status === 'Pending' ||
                                                $status === 'Confirmed'
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    class="d-inline"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($csrfToken) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="update_status"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_id"
                                                        value="<?= (int)$booking['booking_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_status"
                                                        value="Cancelled"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="table-action-btn danger"
                                                        title="Cancel booking"
                                                        data-confirm="Are you sure you want to cancel this booking?"
                                                    >
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if ($status === 'Confirmed'): ?>

                                                <form
                                                    method="POST"
                                                    class="d-inline"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($csrfToken) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="update_status"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_id"
                                                        value="<?= (int)$booking['booking_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="booking_status"
                                                        value="Completed"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="table-action-btn primary"
                                                        title="Mark completed"
                                                    >
                                                        <i class="bi bi-check2-all"></i>
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <form
                                                method="POST"
                                                class="d-inline"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete_booking"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="booking_id"
                                                    value="<?= (int)$booking['booking_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="table-action-btn danger"
                                                    title="Delete booking"
                                                    data-confirm="Delete this booking permanently?"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                                <?php if (!empty($booking['special_request'])): ?>

                                    <tr>

                                        <td colspan="7">

                                            <div
                                                class="small"
                                                style="
                                                    padding:9px 12px;
                                                    background:#fcfaf8;
                                                    border-radius:7px;
                                                    color:#6b7280;
                                                "
                                            >
                                                <strong>
                                                    <i class="bi bi-chat-left-text me-1"></i>
                                                    Special Request:
                                                </strong>

                                                <?= e($booking['special_request']) ?>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

<style>

/* =========================================================
   BOOKING STATUS BADGES
========================================================= */

.table-status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 27px;

    padding: 4px 10px;

    border-radius: 20px;

    font-size: 10px;
    font-weight: 700;

    white-space: nowrap;
}

.table-status-badge.pending {
    background: #fff7ed;
    color: #c2410c;
}

.table-status-badge.confirmed {
    background: #ecfdf5;
    color: #15803d;
}

.table-status-badge.cancelled {
    background: #fef2f2;
    color: #dc2626;
}

.table-status-badge.completed {
    background: #eff6ff;
    color: #2563eb;
}


/* =========================================================
   ACTION BUTTONS
========================================================= */

.table-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 31px;
    height: 31px;

    padding: 0;

    border: 1px solid #e5e7eb;
    border-radius: 7px;

    background: #ffffff;
    color: #6b7280;

    font-size: 12px;

    cursor: pointer;

    transition:
        background-color .2s ease,
        color .2s ease,
        border-color .2s ease;
}

.table-action-btn:hover {
    background: #f9fafb;
    border-color: #d8c4b5;
    color: #8b5e3c;
}

.table-action-btn.success:hover {
    background: #ecfdf5;
    border-color: #bbf7d0;
    color: #15803d;
}

.table-action-btn.primary:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #2563eb;
}

.table-action-btn.danger:hover {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991px) {

    .table-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .table-filter {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 575px) {

    .table-stats {
        grid-template-columns: 1fr;
    }

    .table-filter {
        grid-template-columns: 1fr;
    }

    .table-filter > div:last-child {
        width: 100%;
    }

    .table-filter-btn {
        flex: 1;
    }

}

</style>

<?php require_once "includes/a-footer.php"; ?>