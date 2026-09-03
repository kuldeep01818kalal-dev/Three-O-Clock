<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Table Status";

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];

$allowedStatuses = [
    'Available',
    'Reserved',
    'Occupied',
    'Maintenance'
];

$message = '';
$messageType = '';


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage(string $type, string $message): never
{
    header(
        "Location: table_status.php?" .
        http_build_query([
            'message_type' => $type,
            'message' => $message
        ])
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/
if (isset($_GET['message'], $_GET['message_type'])) {
    $message = (string) $_GET['message'];
    $messageType = $_GET['message_type'] === 'success'
        ? 'success'
        : 'danger';
}


/*
|--------------------------------------------------------------------------
| Update Table Status
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, (string) $_POST['csrf_token'])
    ) {
        redirectWithMessage(
            'danger',
            'Invalid security token. Please try again.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update Status
    |--------------------------------------------------------------------------
    */
    if ($action === 'update_status') {

        $tableId = filter_input(
            INPUT_POST,
            'table_id',
            FILTER_VALIDATE_INT
        );

        $newStatus = trim((string) ($_POST['status'] ?? ''));

        if (!$tableId || $tableId < 1) {
            redirectWithMessage(
                'danger',
                'Invalid table selected.'
            );
        }

        if (!in_array($newStatus, $allowedStatuses, true)) {
            redirectWithMessage(
                'danger',
                'Invalid table status selected.'
            );
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Fetch Table
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT
                    table_id,
                    table_number,
                    capacity,
                    location,
                    status
                FROM cafe_tables
                WHERE table_id = :table_id
                LIMIT 1
            ");

            $stmt->execute([
                ':table_id' => $tableId
            ]);

            $table = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$table) {
                redirectWithMessage(
                    'danger',
                    'Table not found.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Update Table
            |--------------------------------------------------------------------------
            */
            $update = $pdo->prepare("
                UPDATE cafe_tables
                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE table_id = :table_id
            ");

            $update->execute([
                ':status' => $newStatus,
                ':table_id' => $tableId
            ]);

            redirectWithMessage(
                'success',
                'Table ' . $table['table_number'] .
                ' status updated to ' . $newStatus . '.'
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'danger',
                'Unable to update table status. Please try again.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Bulk Status Update
    |--------------------------------------------------------------------------
    */
    if ($action === 'bulk_update') {

        $selectedTables = $_POST['table_ids'] ?? [];
        $newStatus = trim((string) ($_POST['bulk_status'] ?? ''));

        if (!is_array($selectedTables)) {
            redirectWithMessage(
                'danger',
                'Invalid table selection.'
            );
        }

        if (empty($selectedTables)) {
            redirectWithMessage(
                'danger',
                'Please select at least one table.'
            );
        }

        if (!in_array($newStatus, $allowedStatuses, true)) {
            redirectWithMessage(
                'danger',
                'Invalid status selected.'
            );
        }

        $tableIds = [];

        foreach ($selectedTables as $tableId) {

            $tableId = filter_var(
                $tableId,
                FILTER_VALIDATE_INT
            );

            if ($tableId && $tableId > 0) {
                $tableIds[] = $tableId;
            }
        }

        $tableIds = array_values(
            array_unique($tableIds)
        );

        if (empty($tableIds)) {
            redirectWithMessage(
                'danger',
                'No valid tables were selected.'
            );
        }

        try {

            $pdo->beginTransaction();

            $update = $pdo->prepare("
                UPDATE cafe_tables
                SET
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE table_id = :table_id
            ");

            $updatedCount = 0;

            foreach ($tableIds as $tableId) {

                $update->execute([
                    ':status' => $newStatus,
                    ':table_id' => $tableId
                ]);

                $updatedCount += $update->rowCount();
            }

            $pdo->commit();

            redirectWithMessage(
                'success',
                $updatedCount . ' table(s) updated to ' . $newStatus . '.'
            );

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            redirectWithMessage(
                'danger',
                'Unable to update selected tables.'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$locationFilter = trim((string) ($_GET['location'] ?? ''));


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/
$stats = [
    'total' => 0,
    'available' => 0,
    'reserved' => 0,
    'occupied' => 0,
    'maintenance' => 0
];

try {

    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Available') AS available,
            SUM(status = 'Reserved') AS reserved,
            SUM(status = 'Occupied') AS occupied,
            SUM(status = 'Maintenance') AS maintenance
        FROM cafe_tables
    ");

    $statsResult = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if ($statsResult) {
        $stats['total'] = (int) ($statsResult['total'] ?? 0);
        $stats['available'] = (int) ($statsResult['available'] ?? 0);
        $stats['reserved'] = (int) ($statsResult['reserved'] ?? 0);
        $stats['occupied'] = (int) ($statsResult['occupied'] ?? 0);
        $stats['maintenance'] = (int) ($statsResult['maintenance'] ?? 0);
    }

} catch (PDOException $e) {
    $message = 'Unable to load table statistics.';
    $messageType = 'danger';
}


/*
|--------------------------------------------------------------------------
| Fetch Tables
|--------------------------------------------------------------------------
*/
$tables = [];

try {

    $sql = "
        SELECT
            ct.table_id,
            ct.table_number,
            ct.capacity,
            ct.location,
            ct.status,
            ct.description,
            ct.created_at,
            ct.updated_at,

            (
                SELECT COUNT(*)
                FROM table_bookings tb
                WHERE tb.table_id = ct.table_id
                  AND tb.booking_status = 'Confirmed'
                  AND tb.booking_date >= CURDATE()
            ) AS confirmed_bookings,

            (
                SELECT MIN(
                    CONCAT(
                        tb.booking_date,
                        ' ',
                        tb.booking_time
                    )
                )
                FROM table_bookings tb
                WHERE tb.table_id = ct.table_id
                  AND tb.booking_status = 'Confirmed'
                  AND tb.booking_date >= CURDATE()
            ) AS next_booking

        FROM cafe_tables ct
        WHERE 1 = 1
    ";

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    if ($search !== '') {

        $sql .= "
            AND (
                CAST(ct.table_number AS CHAR) LIKE :search
                OR ct.location LIKE :search
                OR ct.description LIKE :search
            )
        ";

        $params[':search'] = '%' . $search . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */
    if (
        $statusFilter !== '' &&
        in_array($statusFilter, $allowedStatuses, true)
    ) {

        $sql .= "
            AND ct.status = :status
        ";

        $params[':status'] = $statusFilter;
    }


    /*
    |--------------------------------------------------------------------------
    | Location Filter
    |--------------------------------------------------------------------------
    */
    if (
        $locationFilter !== '' &&
        in_array(
            $locationFilter,
            ['Indoor', 'Outdoor', 'VIP'],
            true
        )
    ) {

        $sql .= "
            AND ct.location = :location
        ";

        $params[':location'] = $locationFilter;
    }


    $sql .= "
        ORDER BY
            CASE ct.status
                WHEN 'Available' THEN 1
                WHEN 'Reserved' THEN 2
                WHEN 'Occupied' THEN 3
                WHEN 'Maintenance' THEN 4
                ELSE 5
            END,
            CAST(ct.table_number AS UNSIGNED),
            ct.table_number
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $message = 'Unable to load tables.';
    $messageType = 'danger';
}


/*
|--------------------------------------------------------------------------
| Page Layout
|--------------------------------------------------------------------------
*/
require_once "includes/a-header.php";
require_once "includes/a-sidebar.php";
?>

<div class="admin-main">

    <?php require_once "includes/a-navbar.php"; ?>

    <main class="admin-content">

        <!-- Page Header -->
        <div class="page-header mb-4">

            <div>
                <h1 class="page-title">
                    <i class="bi bi-grid-3x3-gap me-2"></i>
                    Table Status
                </h1>

                <p class="page-subtitle">
                    Monitor and update the current status of cafe tables.
                </p>
            </div>

            <div class="d-flex gap-2 flex-wrap">

                <a
                    href="tables.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-table me-1"></i>
                    Manage Tables
                </a>

                <a
                    href="table_booking.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-calendar-check me-1"></i>
                    Bookings
                </a>

            </div>

        </div>


        <!-- Flash Message -->
        <?php if ($message !== ''): ?>

            <div
                class="alert alert-<?php echo h($messageType); ?> alert-dismissible fade show"
                data-auto-hide="true"
                role="alert"
            >
                <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>

                <?php echo h($message); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>

        <?php endif; ?>


        <!-- Statistics -->
        <div class="table-stats mb-4">

            <div class="table-stat-card">

                <div class="table-stat-icon">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Total Tables
                    </span>

                    <span class="table-stat-value">
                        <?php echo $stats['total']; ?>
                    </span>

                </div>

            </div>


            <div class="table-stat-card">

                <div class="table-stat-icon available">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Available
                    </span>

                    <span class="table-stat-value">
                        <?php echo $stats['available']; ?>
                    </span>

                </div>

            </div>


            <div class="table-stat-card">

                <div class="table-stat-icon reserved">
                    <i class="bi bi-calendar-check"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Reserved
                    </span>

                    <span class="table-stat-value">
                        <?php echo $stats['reserved']; ?>
                    </span>

                </div>

            </div>


            <div class="table-stat-card">

                <div class="table-stat-icon occupied">
                    <i class="bi bi-people"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Occupied
                    </span>

                    <span class="table-stat-value">
                        <?php echo $stats['occupied']; ?>
                    </span>

                </div>

            </div>


            <div class="table-stat-card">

                <div class="table-stat-icon maintenance">
                    <i class="bi bi-tools"></i>
                </div>

                <div class="table-stat-content">

                    <span class="table-stat-label">
                        Maintenance
                    </span>

                    <span class="table-stat-value">
                        <?php echo $stats['maintenance']; ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- Filters -->
        <div class="table-filter-card mb-4">

            <form
                method="GET"
                action="table_status.php"
                class="table-filter"
            >

                <div class="table-filter-group">

                    <label for="search">
                        Search
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            class="form-control"
                            placeholder="Table number, location..."
                            value="<?php echo h($search); ?>"
                        >

                    </div>

                </div>


                <div class="table-filter-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-select"
                    >

                        <option value="">
                            All Status
                        </option>

                        <?php foreach ($allowedStatuses as $status): ?>

                            <option
                                value="<?php echo h($status); ?>"
                                <?php echo $statusFilter === $status ? 'selected' : ''; ?>
                            >
                                <?php echo h($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="table-filter-group">

                    <label for="location">
                        Location
                    </label>

                    <select
                        id="location"
                        name="location"
                        class="form-select"
                    >

                        <option value="">
                            All Locations
                        </option>

                        <option
                            value="Indoor"
                            <?php echo $locationFilter === 'Indoor' ? 'selected' : ''; ?>
                        >
                            Indoor
                        </option>

                        <option
                            value="Outdoor"
                            <?php echo $locationFilter === 'Outdoor' ? 'selected' : ''; ?>
                        >
                            Outdoor
                        </option>

                        <option
                            value="VIP"
                            <?php echo $locationFilter === 'VIP' ? 'selected' : ''; ?>
                        >
                            VIP
                        </option>

                    </select>

                </div>


                <div class="table-filter-group table-filter-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>

                    <a
                        href="table_status.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- Bulk Action -->
        <form
            method="POST"
            action="table_status.php"
            id="bulkStatusForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo h($csrfToken); ?>"
            >

            <input
                type="hidden"
                name="action"
                value="bulk_update"
            >


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

                <div>

                    <strong>
                        <?php echo count($tables); ?>
                    </strong>

                    table(s) found

                </div>


                <div class="d-flex gap-2 flex-wrap">

                    <select
                        name="bulk_status"
                        class="form-select form-select-sm"
                        style="min-width: 160px;"
                        required
                    >

                        <option value="">
                            Bulk Status
                        </option>

                        <?php foreach ($allowedStatuses as $status): ?>

                            <option value="<?php echo h($status); ?>">
                                <?php echo h($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>


                    <button
                        type="submit"
                        class="btn btn-sm btn-primary"
                        data-confirm="Update status for all selected tables?"
                    >
                        <i class="bi bi-check2-all me-1"></i>
                        Apply
                    </button>

                </div>

            </div>


            <!-- Table Grid -->
            <?php if (!empty($tables)): ?>

                <div class="cafe-table-grid">

                    <?php foreach ($tables as $table): ?>

                        <?php
                        $tableStatus = $table['status'];

                        $statusClass = strtolower(
                            str_replace(' ', '-', $tableStatus)
                        );

                        $statusIcon = match ($tableStatus) {
                            'Available' => 'bi-check-circle',
                            'Reserved' => 'bi-calendar-check',
                            'Occupied' => 'bi-people-fill',
                            'Maintenance' => 'bi-tools',
                            default => 'bi-question-circle'
                        };
                        ?>

                        <div
                            class="cafe-table-card"
                            data-search-item
                            data-status="<?php echo h($tableStatus); ?>"
                        >

                            <!-- Card Top -->
                            <div class="cafe-table-top">

                                <div class="d-flex align-items-center gap-2">

                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="table_ids[]"
                                        value="<?php echo (int) $table['table_id']; ?>"
                                        aria-label="Select table <?php echo h((string) $table['table_number']); ?>"
                                    >

                                    <div class="cafe-table-number">
                                        Table <?php echo h((string) $table['table_number']); ?>
                                    </div>

                                </div>


                                <span class="cafe-table-status <?php echo h($statusClass); ?>">

                                    <i class="bi <?php echo h($statusIcon); ?>"></i>

                                    <?php echo h($tableStatus); ?>

                                </span>

                            </div>


                            <!-- Table Visual -->
                            <div class="cafe-table-visual">

                                <div class="cafe-table-shape <?php echo h($statusClass); ?>">

                                    <span>
                                        <?php echo h((string) $table['table_number']); ?>
                                    </span>

                                </div>

                            </div>


                            <!-- Table Information -->
                            <div class="cafe-table-info">

                                <div class="cafe-table-info-item">

                                    <span class="cafe-table-info-label">
                                        <i class="bi bi-people me-1"></i>
                                        Capacity
                                    </span>

                                    <span class="cafe-table-info-value">
                                        <?php echo (int) $table['capacity']; ?> Guests
                                    </span>

                                </div>


                                <div class="cafe-table-info-item">

                                    <span class="cafe-table-info-label">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        Location
                                    </span>

                                    <span class="cafe-table-info-value">
                                        <?php echo h((string) $table['location']); ?>
                                    </span>

                                </div>


                                <div class="cafe-table-info-item">

                                    <span class="cafe-table-info-label">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        Confirmed Bookings
                                    </span>

                                    <span class="cafe-table-info-value">
                                        <?php echo (int) $table['confirmed_bookings']; ?>
                                    </span>

                                </div>


                                <?php if (!empty($table['next_booking'])): ?>

                                    <div class="cafe-table-info-item">

                                        <span class="cafe-table-info-label">
                                            <i class="bi bi-clock me-1"></i>
                                            Next Booking
                                        </span>

                                        <span class="cafe-table-info-value">
                                            <?php
                                            echo h(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime((string) $table['next_booking'])
                                                )
                                            );
                                            ?>
                                        </span>

                                    </div>

                                <?php endif; ?>


                                <?php if (!empty($table['description'])): ?>

                                    <div class="cafe-table-info-item">

                                        <span class="cafe-table-info-label">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Description
                                        </span>

                                        <span class="cafe-table-info-value">
                                            <?php echo h((string) $table['description']); ?>
                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- Status Update -->
                            <div class="mt-3">

                                <form
                                    method="POST"
                                    action="table_status.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo h($csrfToken); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update_status"
                                    >

                                    <input
                                        type="hidden"
                                        name="table_id"
                                        value="<?php echo (int) $table['table_id']; ?>"
                                    >


                                    <label
                                        class="form-label small fw-semibold"
                                        for="status_<?php echo (int) $table['table_id']; ?>"
                                    >
                                        Change Status
                                    </label>

                                    <div class="input-group">

                                        <select
                                            id="status_<?php echo (int) $table['table_id']; ?>"
                                            name="status"
                                            class="form-select form-select-sm"
                                            required
                                        >

                                            <?php foreach ($allowedStatuses as $status): ?>

                                                <option
                                                    value="<?php echo h($status); ?>"
                                                    <?php echo $tableStatus === $status ? 'selected' : ''; ?>
                                                >
                                                    <?php echo h($status); ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>


                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-primary"
                                        >
                                            <i class="bi bi-check-lg"></i>
                                            Update
                                        </button>

                                    </div>

                                </form>

                            </div>


                            <!-- Quick Actions -->
                            <div class="cafe-table-actions mt-3">

                                <?php if ($tableStatus !== 'Available'): ?>

                                    <form
                                        method="POST"
                                        action="table_status.php"
                                        class="d-inline"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo h($csrfToken); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="table_id"
                                            value="<?php echo (int) $table['table_id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="Available"
                                        >

                                        <button
                                            type="submit"
                                            class="cafe-table-action available"
                                        >
                                            <i class="bi bi-check-circle"></i>
                                            Available
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if ($tableStatus !== 'Occupied'): ?>

                                    <form
                                        method="POST"
                                        action="table_status.php"
                                        class="d-inline"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo h($csrfToken); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="table_id"
                                            value="<?php echo (int) $table['table_id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="Occupied"
                                        >

                                        <button
                                            type="submit"
                                            class="cafe-table-action occupied"
                                        >
                                            <i class="bi bi-people"></i>
                                            Occupied
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if ($tableStatus !== 'Reserved'): ?>

                                    <form
                                        method="POST"
                                        action="table_status.php"
                                        class="d-inline"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo h($csrfToken); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="table_id"
                                            value="<?php echo (int) $table['table_id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="Reserved"
                                        >

                                        <button
                                            type="submit"
                                            class="cafe-table-action reserved"
                                        >
                                            <i class="bi bi-calendar-check"></i>
                                            Reserved
                                        </button>

                                    </form>

                                <?php endif; ?>


                                <?php if ($tableStatus !== 'Maintenance'): ?>

                                    <form
                                        method="POST"
                                        action="table_status.php"
                                        class="d-inline"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo h($csrfToken); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="table_id"
                                            value="<?php echo (int) $table['table_id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="status"
                                            value="Maintenance"
                                        >

                                        <button
                                            type="submit"
                                            class="cafe-table-action maintenance"
                                            data-confirm="Set this table to Maintenance?"
                                        >
                                            <i class="bi bi-tools"></i>
                                            Maintenance
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <!-- Empty State -->
                <div class="text-center py-5">

                    <div class="mb-3">

                        <i
                            class="bi bi-grid-3x3-gap"
                            style="font-size: 3rem; color: #9ca3af;"
                        ></i>

                    </div>

                    <h4>
                        No Tables Found
                    </h4>

                    <p class="text-muted mb-3">
                        No cafe tables match the selected filters.
                    </p>

                    <a
                        href="table_status.php"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </form>

    </main>

</div>


<style>
/* =========================================================
   Table Status Page
   ========================================================= */

.table-stat-icon.available {
    color: #198754;
    background: rgba(25, 135, 84, 0.10);
}

.table-stat-icon.reserved {
    color: #0d6efd;
    background: rgba(13, 110, 253, 0.10);
}

.table-stat-icon.occupied {
    color: #fd7e14;
    background: rgba(253, 126, 20, 0.10);
}

.table-stat-icon.maintenance {
    color: #dc3545;
    background: rgba(220, 53, 69, 0.10);
}

.cafe-table-card {
    min-width: 0;
}

.cafe-table-card .form-select {
    min-width: 0;
}

.cafe-table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.cafe-table-actions form {
    margin: 0;
}

.cafe-table-action {
    border: 1px solid var(--border-color, #e8eaee);
    background: #fff;
    border-radius: 8px;
    padding: 7px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s ease;
}

.cafe-table-action:hover {
    transform: translateY(-1px);
}

.cafe-table-action.available {
    color: #198754;
}

.cafe-table-action.reserved {
    color: #0d6efd;
}

.cafe-table-action.occupied {
    color: #fd7e14;
}

.cafe-table-action.maintenance {
    color: #dc3545;
}

.cafe-table-action.available:hover {
    background: rgba(25, 135, 84, .08);
}

.cafe-table-action.reserved:hover {
    background: rgba(13, 110, 253, .08);
}

.cafe-table-action.occupied:hover {
    background: rgba(253, 126, 20, .08);
}

.cafe-table-action.maintenance:hover {
    background: rgba(220, 53, 69, .08);
}

@media (max-width: 767px) {

    .table-filter {
        flex-direction: column;
    }

    .table-filter-group,
    .table-filter-actions {
        width: 100%;
    }

    .table-filter-actions {
        display: flex;
    }

    .table-filter-actions .btn {
        flex: 1;
    }

    .cafe-table-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cafe-table-action {
        width: 100%;
    }

}

@media (max-width: 480px) {

    .cafe-table-actions {
        grid-template-columns: 1fr;
    }

    #bulkStatusForm > .d-flex {
        align-items: stretch !important;
    }

    #bulkStatusForm > .d-flex > .d-flex {
        width: 100%;
    }

    #bulkStatusForm > .d-flex > .d-flex .form-select,
    #bulkStatusForm > .d-flex > .d-flex .btn {
        flex: 1;
    }

}
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const bulkForm = document.getElementById('bulkStatusForm');

    if (!bulkForm) {
        return;
    }

    bulkForm.addEventListener('submit', function (event) {

        const selected = bulkForm.querySelectorAll(
            'input[name="table_ids[]"]:checked'
        );

        const status = bulkForm.querySelector(
            'select[name="bulk_status"]'
        );

        if (!selected.length) {

            event.preventDefault();

            alert('Please select at least one table.');

            return;
        }

        if (!status || !status.value) {

            event.preventDefault();

            alert('Please select a status.');

            return;
        }

    });

});
</script>

<?php require_once "includes/a-footer.php"; ?>