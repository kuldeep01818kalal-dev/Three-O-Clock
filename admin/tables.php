<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Table Management";

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];

$allowedLocations = [
    'Indoor',
    'Outdoor',
    'VIP'
];

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
        "Location: tables.php?" .
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
| POST Actions
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {
        redirectWithMessage(
            'danger',
            'Invalid security token. Please try again.'
        );
    }

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Add Table
    |--------------------------------------------------------------------------
    */
    if ($action === 'add_table') {

        $tableNumber = trim((string) ($_POST['table_number'] ?? ''));
        $capacity = filter_var(
            $_POST['capacity'] ?? null,
            FILTER_VALIDATE_INT
        );
        $location = trim((string) ($_POST['location'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'Available'));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($tableNumber === '') {
            redirectWithMessage(
                'danger',
                'Table number is required.'
            );
        }

        if ($capacity === false || $capacity < 1 || $capacity > 100) {
            redirectWithMessage(
                'danger',
                'Capacity must be between 1 and 100.'
            );
        }

        if (!in_array($location, $allowedLocations, true)) {
            redirectWithMessage(
                'danger',
                'Invalid table location.'
            );
        }

        if (!in_array($status, $allowedStatuses, true)) {
            redirectWithMessage(
                'danger',
                'Invalid table status.'
            );
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Table Number
            |--------------------------------------------------------------------------
            */
            $check = $pdo->prepare("
                SELECT table_id
                FROM cafe_tables
                WHERE table_number = :table_number
                LIMIT 1
            ");

            $check->execute([
                ':table_number' => $tableNumber
            ]);

            if ($check->fetch()) {
                redirectWithMessage(
                    'danger',
                    'Table number ' . $tableNumber . ' already exists.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Insert Table
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                INSERT INTO cafe_tables (
                    table_number,
                    capacity,
                    location,
                    status,
                    description,
                    created_at,
                    updated_at
                )
                VALUES (
                    :table_number,
                    :capacity,
                    :location,
                    :status,
                    :description,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");

            $stmt->execute([
                ':table_number' => $tableNumber,
                ':capacity' => $capacity,
                ':location' => $location,
                ':status' => $status,
                ':description' => $description !== ''
                    ? $description
                    : null
            ]);

            redirectWithMessage(
                'success',
                'Table ' . $tableNumber . ' added successfully.'
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'danger',
                'Unable to add table. Please try again.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Table
    |--------------------------------------------------------------------------
    */
    if ($action === 'update_table') {

        $tableId = filter_var(
            $_POST['table_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $tableNumber = trim((string) ($_POST['table_number'] ?? ''));
        $capacity = filter_var(
            $_POST['capacity'] ?? null,
            FILTER_VALIDATE_INT
        );
        $location = trim((string) ($_POST['location'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if (!$tableId || $tableId < 1) {
            redirectWithMessage(
                'danger',
                'Invalid table selected.'
            );
        }

        if ($tableNumber === '') {
            redirectWithMessage(
                'danger',
                'Table number is required.'
            );
        }

        if ($capacity === false || $capacity < 1 || $capacity > 100) {
            redirectWithMessage(
                'danger',
                'Capacity must be between 1 and 100.'
            );
        }

        if (!in_array($location, $allowedLocations, true)) {
            redirectWithMessage(
                'danger',
                'Invalid table location.'
            );
        }

        if (!in_array($status, $allowedStatuses, true)) {
            redirectWithMessage(
                'danger',
                'Invalid table status.'
            );
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Table Exists
            |--------------------------------------------------------------------------
            */
            $exists = $pdo->prepare("
                SELECT table_id
                FROM cafe_tables
                WHERE table_id = :table_id
                LIMIT 1
            ");

            $exists->execute([
                ':table_id' => $tableId
            ]);

            if (!$exists->fetch()) {
                redirectWithMessage(
                    'danger',
                    'Table not found.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate Number Check
            |--------------------------------------------------------------------------
            */
            $check = $pdo->prepare("
                SELECT table_id
                FROM cafe_tables
                WHERE table_number = :table_number
                  AND table_id != :table_id
                LIMIT 1
            ");

            $check->execute([
                ':table_number' => $tableNumber,
                ':table_id' => $tableId
            ]);

            if ($check->fetch()) {
                redirectWithMessage(
                    'danger',
                    'Another table already uses table number ' . $tableNumber . '.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Update
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                UPDATE cafe_tables
                SET
                    table_number = :table_number,
                    capacity = :capacity,
                    location = :location,
                    status = :status,
                    description = :description,
                    updated_at = CURRENT_TIMESTAMP
                WHERE table_id = :table_id
            ");

            $stmt->execute([
                ':table_number' => $tableNumber,
                ':capacity' => $capacity,
                ':location' => $location,
                ':status' => $status,
                ':description' => $description !== ''
                    ? $description
                    : null,
                ':table_id' => $tableId
            ]);

            redirectWithMessage(
                'success',
                'Table ' . $tableNumber . ' updated successfully.'
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'danger',
                'Unable to update table.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Table
    |--------------------------------------------------------------------------
    */
    if ($action === 'delete_table') {

        $tableId = filter_var(
            $_POST['table_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$tableId || $tableId < 1) {
            redirectWithMessage(
                'danger',
                'Invalid table selected.'
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
                    table_number
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
            | Check Existing Bookings
            |--------------------------------------------------------------------------
            */
            $bookingCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM table_bookings
                WHERE table_id = :table_id
                  AND booking_status IN ('Pending', 'Confirmed')
            ");

            $bookingCheck->execute([
                ':table_id' => $tableId
            ]);

            $activeBookings = (int) $bookingCheck->fetchColumn();

            if ($activeBookings > 0) {
                redirectWithMessage(
                    'danger',
                    'Table ' . $table['table_number'] .
                    ' cannot be deleted because it has pending or confirmed bookings.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Check Orders
            |--------------------------------------------------------------------------
            */
            $orderCheck = $pdo->prepare("
                SELECT COUNT(*)
                FROM orders
                WHERE table_id = :table_id
                  AND order_status NOT IN ('Completed', 'Cancelled')
            ");

            $orderCheck->execute([
                ':table_id' => $tableId
            ]);

            $activeOrders = (int) $orderCheck->fetchColumn();

            if ($activeOrders > 0) {
                redirectWithMessage(
                    'danger',
                    'Table ' . $table['table_number'] .
                    ' cannot be deleted because it has active orders.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Delete
            |--------------------------------------------------------------------------
            */
            $delete = $pdo->prepare("
                DELETE FROM cafe_tables
                WHERE table_id = :table_id
            ");

            $delete->execute([
                ':table_id' => $tableId
            ]);

            redirectWithMessage(
                'success',
                'Table ' . $table['table_number'] . ' deleted successfully.'
            );

        } catch (PDOException $e) {

            redirectWithMessage(
                'danger',
                'Unable to delete table. It may still be referenced by existing records.'
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

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Available') AS available,
            SUM(status = 'Reserved') AS reserved,
            SUM(status = 'Occupied') AS occupied,
            SUM(status = 'Maintenance') AS maintenance
        FROM cafe_tables
    ");

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $stats['total'] = (int) ($result['total'] ?? 0);
        $stats['available'] = (int) ($result['available'] ?? 0);
        $stats['reserved'] = (int) ($result['reserved'] ?? 0);
        $stats['occupied'] = (int) ($result['occupied'] ?? 0);
        $stats['maintenance'] = (int) ($result['maintenance'] ?? 0);
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

    if (
        $statusFilter !== '' &&
        in_array($statusFilter, $allowedStatuses, true)
    ) {

        $sql .= "
            AND ct.status = :status
        ";

        $params[':status'] = $statusFilter;
    }

    if (
        $locationFilter !== '' &&
        in_array($locationFilter, $allowedLocations, true)
    ) {

        $sql .= "
            AND ct.location = :location
        ";

        $params[':location'] = $locationFilter;
    }

    $sql .= "
        ORDER BY
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
| Edit Table
|--------------------------------------------------------------------------
*/
$editTable = null;

if (isset($_GET['edit'])) {

    $editId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT
    );

    if ($editId && $editId > 0) {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    table_id,
                    table_number,
                    capacity,
                    location,
                    status,
                    description
                FROM cafe_tables
                WHERE table_id = :table_id
                LIMIT 1
            ");

            $stmt->execute([
                ':table_id' => $editId
            ]);

            $editTable = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $editTable = null;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Header + Sidebar
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
                    <i class="bi bi-table me-2"></i>
                    Table Management
                </h1>

                <p class="page-subtitle">
                    Create, edit and manage your cafe tables.
                </p>

            </div>

            <div class="d-flex gap-2 flex-wrap">

                <a
                    href="table_status.php"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-grid-3x3-gap me-1"></i>
                    Table Status
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


        <!-- Add / Edit Table -->
        <div class="table-list-card mb-4">

            <div class="table-list-header">

                <div>

                    <h3>
                        <?php echo $editTable ? 'Edit Table' : 'Add New Table'; ?>
                    </h3>

                    <p>
                        <?php
                        echo $editTable
                            ? 'Update the selected table information.'
                            : 'Create a new cafe table.';
                        ?>
                    </p>

                </div>

            </div>


            <div class="p-4">

                <form
                    method="POST"
                    action="tables.php<?php echo $editTable ? '?edit=' . (int) $editTable['table_id'] : ''; ?>"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo h($csrfToken); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="<?php echo $editTable ? 'update_table' : 'add_table'; ?>"
                    >

                    <?php if ($editTable): ?>

                        <input
                            type="hidden"
                            name="table_id"
                            value="<?php echo (int) $editTable['table_id']; ?>"
                        >

                    <?php endif; ?>


                    <div class="row g-3">

                        <!-- Table Number -->
                        <div class="col-md-6 col-lg-3">

                            <label
                                for="table_number"
                                class="form-label fw-semibold"
                            >
                                Table Number
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="table_number"
                                name="table_number"
                                class="form-control"
                                maxlength="50"
                                placeholder="Example: T01"
                                value="<?php echo h($editTable['table_number'] ?? ''); ?>"
                                required
                            >

                        </div>


                        <!-- Capacity -->
                        <div class="col-md-6 col-lg-3">

                            <label
                                for="capacity"
                                class="form-label fw-semibold"
                            >
                                Capacity
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="number"
                                id="capacity"
                                name="capacity"
                                class="form-control"
                                min="1"
                                max="100"
                                placeholder="Example: 4"
                                value="<?php echo h(isset($editTable['capacity']) ? (string) $editTable['capacity'] : '4'); ?>"
                                required
                            >

                        </div>


                        <!-- Location -->
                        <div class="col-md-6 col-lg-3">

                            <label
                                for="location"
                                class="form-label fw-semibold"
                            >
                                Location
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                id="location"
                                name="location"
                                class="form-select"
                                required
                            >

                                <?php foreach ($allowedLocations as $location): ?>

                                    <option
                                        value="<?php echo h($location); ?>"
                                        <?php
                                        echo (
                                            ($editTable['location'] ?? 'Indoor') === $location
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        <?php echo h($location); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Status -->
                        <div class="col-md-6 col-lg-3">

                            <label
                                for="status"
                                class="form-label fw-semibold"
                            >
                                Status
                            </label>

                            <select
                                id="status"
                                name="status"
                                class="form-select"
                            >

                                <?php foreach ($allowedStatuses as $status): ?>

                                    <option
                                        value="<?php echo h($status); ?>"
                                        <?php
                                        echo (
                                            ($editTable['status'] ?? 'Available') === $status
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        <?php echo h($status); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Description -->
                        <div class="col-12">

                            <label
                                for="description"
                                class="form-label fw-semibold"
                            >
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                class="form-control"
                                rows="3"
                                maxlength="500"
                                placeholder="Example: Corner table near window"
                            ><?php echo h($editTable['description'] ?? ''); ?></textarea>

                        </div>


                        <!-- Buttons -->
                        <div class="col-12">

                            <div class="d-flex gap-2 flex-wrap">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    <i class="bi bi-<?php echo $editTable ? 'save' : 'plus-circle'; ?> me-1"></i>

                                    <?php
                                    echo $editTable
                                        ? 'Update Table'
                                        : 'Add Table';
                                    ?>

                                </button>


                                <?php if ($editTable): ?>

                                    <a
                                        href="tables.php"
                                        class="btn btn-outline-secondary"
                                    >
                                        <i class="bi bi-x-circle me-1"></i>
                                        Cancel Edit
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="reset"
                                        class="btn btn-outline-secondary"
                                    >
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                                        Reset
                                    </button>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- Filters -->
        <div class="table-filter-card mb-4">

            <form
                method="GET"
                action="tables.php"
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

                    <label for="status_filter">
                        Status
                    </label>

                    <select
                        id="status_filter"
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

                    <label for="location_filter">
                        Location
                    </label>

                    <select
                        id="location_filter"
                        name="location"
                        class="form-select"
                    >

                        <option value="">
                            All Locations
                        </option>

                        <?php foreach ($allowedLocations as $location): ?>

                            <option
                                value="<?php echo h($location); ?>"
                                <?php echo $locationFilter === $location ? 'selected' : ''; ?>
                            >
                                <?php echo h($location); ?>
                            </option>

                        <?php endforeach; ?>

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
                        href="tables.php"
                        class="btn btn-outline-secondary"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- Table List -->
        <div class="table-list-card">

            <div class="table-list-header">

                <div>

                    <h3>
                        Cafe Tables
                    </h3>

                    <p>
                        <?php echo count($tables); ?> table(s) found.
                    </p>

                </div>

                <a
                    href="table_status.php"
                    class="btn btn-sm btn-outline-primary"
                >
                    <i class="bi bi-grid-3x3-gap me-1"></i>
                    View Status
                </a>

            </div>


            <?php if (!empty($tables)): ?>

                <div class="table-responsive">

                    <table class="table management-table align-middle mb-0">

                        <thead>

                            <tr>

                                <th>
                                    Table
                                </th>

                                <th>
                                    Capacity
                                </th>

                                <th>
                                    Location
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Bookings
                                </th>

                                <th>
                                    Description
                                </th>

                                <th class="text-end">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($tables as $table): ?>

                                <?php
                                $statusClass = strtolower(
                                    str_replace(
                                        ' ',
                                        '-',
                                        (string) $table['status']
                                    )
                                );
                                ?>

                                <tr>

                                    <!-- Table -->
                                    <td>

                                        <div class="d-flex align-items-center gap-3">

                                            <div
                                                class="table-mini-icon <?php echo h($statusClass); ?>"
                                            >
                                                <i class="bi bi-grid-3x3-gap"></i>
                                            </div>

                                            <div>

                                                <strong>
                                                    Table <?php echo h((string) $table['table_number']); ?>
                                                </strong>

                                                <div class="small text-muted">
                                                    ID #<?php echo (int) $table['table_id']; ?>
                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- Capacity -->
                                    <td>

                                        <span class="fw-semibold">
                                            <?php echo (int) $table['capacity']; ?>
                                        </span>

                                        <span class="text-muted">
                                            guests
                                        </span>

                                    </td>


                                    <!-- Location -->
                                    <td>

                                        <span class="badge bg-light text-dark border">

                                            <i class="bi bi-geo-alt me-1"></i>

                                            <?php echo h((string) $table['location']); ?>

                                        </span>

                                    </td>


                                    <!-- Status -->
                                    <td>

                                        <span
                                            class="cafe-table-status <?php echo h($statusClass); ?>"
                                        >

                                            <?php if ($table['status'] === 'Available'): ?>

                                                <i class="bi bi-check-circle"></i>

                                            <?php elseif ($table['status'] === 'Reserved'): ?>

                                                <i class="bi bi-calendar-check"></i>

                                            <?php elseif ($table['status'] === 'Occupied'): ?>

                                                <i class="bi bi-people-fill"></i>

                                            <?php else: ?>

                                                <i class="bi bi-tools"></i>

                                            <?php endif; ?>

                                            <?php echo h((string) $table['status']); ?>

                                        </span>

                                    </td>


                                    <!-- Bookings -->
                                    <td>

                                        <span class="fw-semibold">
                                            <?php echo (int) $table['confirmed_bookings']; ?>
                                        </span>

                                        <span class="text-muted small">
                                            confirmed
                                        </span>

                                    </td>


                                    <!-- Description -->
                                    <td>

                                        <span
                                            class="text-muted"
                                            title="<?php echo h((string) $table['description']); ?>"
                                        >
                                            <?php
                                            $description = trim(
                                                (string) $table['description']
                                            );

                                            echo $description !== ''
                                                ? h(
                                                    mb_strimwidth(
                                                        $description,
                                                        0,
                                                        45,
                                                        '...'
                                                    )
                                                )
                                                : '—';
                                            ?>
                                        </span>

                                    </td>


                                    <!-- Actions -->
                                    <td class="text-end">

                                        <div class="d-flex justify-content-end gap-1 flex-wrap">

                                            <a
                                                href="tables.php?edit=<?php echo (int) $table['table_id']; ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit table"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </a>


                                            <a
                                                href="table_status.php"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Manage status"
                                            >
                                                <i class="bi bi-grid-3x3-gap"></i>
                                            </a>


                                            <form
                                                method="POST"
                                                action="tables.php"
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
                                                    value="delete_table"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="table_id"
                                                    value="<?php echo (int) $table['table_id']; ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Delete table"
                                                    data-confirm="Are you sure you want to delete Table <?php echo h((string) $table['table_number']); ?>?"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="text-center py-5 px-3">

                    <i
                        class="bi bi-table"
                        style="font-size: 3rem; color: #9ca3af;"
                    ></i>

                    <h4 class="mt-3">
                        No Tables Found
                    </h4>

                    <p class="text-muted">
                        No tables match your current filters.
                    </p>

                    <a
                        href="tables.php"
                        class="btn btn-outline-primary"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>


<style>
/* =========================================================
   TABLE MANAGEMENT
   ========================================================= */

.table-mini-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.table-mini-icon.available {
    color: #198754;
    background: rgba(25, 135, 84, .10);
}

.table-mini-icon.reserved {
    color: #0d6efd;
    background: rgba(13, 110, 253, .10);
}

.table-mini-icon.occupied {
    color: #fd7e14;
    background: rgba(253, 126, 20, .10);
}

.table-mini-icon.maintenance {
    color: #dc3545;
    background: rgba(220, 53, 69, .10);
}

.management-table {
    min-width: 980px;
}

.management-table th {
    white-space: nowrap;
}

.management-table td {
    vertical-align: middle;
}

.table-list-card .table-responsive {
    border-radius: 0 0 12px 12px;
}

@media (max-width: 767px) {

    .management-table {
        min-width: 900px;
    }

}
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Capacity Validation
    |--------------------------------------------------------------------------
    */
    const capacityInput = document.getElementById('capacity');

    if (capacityInput) {

        capacityInput.addEventListener('input', function () {

            let value = parseInt(this.value || '0', 10);

            if (value < 1) {
                this.setCustomValidity(
                    'Capacity must be at least 1.'
                );
            } else if (value > 100) {
                this.setCustomValidity(
                    'Capacity cannot be greater than 100.'
                );
            } else {
                this.setCustomValidity('');
            }

        });
    }


    /*
    |--------------------------------------------------------------------------
    | Table Number Trim
    |--------------------------------------------------------------------------
    */
    const tableNumber = document.getElementById('table_number');

    if (tableNumber) {

        tableNumber.addEventListener('blur', function () {
            this.value = this.value.trim();
        });

    }

});
</script>


<?php require_once "includes/a-footer.php"; ?>