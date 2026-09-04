<?php
declare(strict_types=1);

session_start();

require_once "../config/db.php";
require_once "includes/a-auth.php";

$pageTitle = "Admin Profile";

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Admin ID
|--------------------------------------------------------------------------
*/
$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    header("Location: a-login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Profile Update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {
        $message = "Invalid security token. Please refresh the page and try again.";
        $messageType = "danger";
    } else {

        $action = $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Update Profile
        |--------------------------------------------------------------------------
        */
        if ($action === 'update_profile') {

            $adminName = trim(
                (string) ($_POST['admin_name'] ?? '')
            );

            $email = trim(
                (string) ($_POST['email'] ?? '')
            );

            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */
            if ($adminName === '') {

                $message = "Admin name is required.";
                $messageType = "danger";

            } elseif (mb_strlen($adminName) < 2) {

                $message = "Admin name must contain at least 2 characters.";
                $messageType = "danger";

            } elseif ($email === '') {

                $message = "Email address is required.";
                $messageType = "danger";

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = "Please enter a valid email address.";
                $messageType = "danger";

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Check Duplicate Email
                    |--------------------------------------------------------------------------
                    */
                    $checkEmail = $pdo->prepare("
                        SELECT admin_id
                        FROM admins
                        WHERE email = :email
                          AND admin_id != :admin_id
                        LIMIT 1
                    ");

                    $checkEmail->execute([
                        ':email' => $email,
                        ':admin_id' => $adminId
                    ]);

                    if ($checkEmail->fetch()) {

                        $message = "This email address is already being used.";
                        $messageType = "danger";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Update Admin
                        |--------------------------------------------------------------------------
                        */
                        $update = $pdo->prepare("
                            UPDATE admins
                            SET
                                admin_name = :admin_name,
                                email = :email
                            WHERE admin_id = :admin_id
                        ");

                        $update->execute([
                            ':admin_name' => $adminName,
                            ':email' => $email,
                            ':admin_id' => $adminId
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Update Session
                        |--------------------------------------------------------------------------
                        */
                        $_SESSION['admin_name'] = $adminName;
                        $_SESSION['admin_email'] = $email;

                        $message = "Profile updated successfully.";
                        $messageType = "success";
                    }

                } catch (PDOException $e) {

                    $message = "Unable to update your profile. Please try again.";
                    $messageType = "danger";
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Change Password
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'change_password') {

            $currentPassword = (string) (
                $_POST['current_password'] ?? ''
            );

            $newPassword = (string) (
                $_POST['new_password'] ?? ''
            );

            $confirmPassword = (string) (
                $_POST['confirm_password'] ?? ''
            );


            if ($currentPassword === '') {

                $message = "Current password is required.";
                $messageType = "danger";

            } elseif ($newPassword === '') {

                $message = "New password is required.";
                $messageType = "danger";

            } elseif (strlen($newPassword) < 6) {

                $message = "New password must contain at least 6 characters.";
                $messageType = "danger";

            } elseif ($newPassword !== $confirmPassword) {

                $message = "New password and confirmation password do not match.";
                $messageType = "danger";

            } elseif ($currentPassword === $newPassword) {

                $message = "New password must be different from the current password.";
                $messageType = "danger";

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Fetch Current Password
                    |--------------------------------------------------------------------------
                    */
                    $stmt = $pdo->prepare("
                        SELECT
                            admin_id,
                            password
                        FROM admins
                        WHERE admin_id = :admin_id
                        LIMIT 1
                    ");

                    $stmt->execute([
                        ':admin_id' => $adminId
                    ]);

                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$admin) {

                        $message = "Admin account not found.";
                        $messageType = "danger";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Verify Current Password
                        |--------------------------------------------------------------------------
                        */
                        if (!password_verify(
                            $currentPassword,
                            (string) $admin['password']
                        )) {

                            $message = "Current password is incorrect.";
                            $messageType = "danger";

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Hash New Password
                            |--------------------------------------------------------------------------
                            */
                            $newPasswordHash = password_hash(
                                $newPassword,
                                PASSWORD_DEFAULT
                            );

                            /*
                            |--------------------------------------------------------------------------
                            | Update Password
                            |--------------------------------------------------------------------------
                            */
                            $updatePassword = $pdo->prepare("
                                UPDATE admins
                                SET password = :password
                                WHERE admin_id = :admin_id
                            ");

                            $updatePassword->execute([
                                ':password' => $newPasswordHash,
                                ':admin_id' => $adminId
                            ]);

                            $message = "Password changed successfully.";
                            $messageType = "success";
                        }
                    }

                } catch (PDOException $e) {

                    $message = "Unable to change password. Please try again.";
                    $messageType = "danger";
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Admin
|--------------------------------------------------------------------------
*/
try {

    $stmt = $pdo->prepare("
        SELECT
            admin_id,
            admin_name,
            email,
            created_at
        FROM admins
        WHERE admin_id = :admin_id
        LIMIT 1
    ");

    $stmt->execute([
        ':admin_id' => $adminId
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $admin = null;
}

if (!$admin) {
    session_destroy();
    header("Location: a-login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Admin Initial
|--------------------------------------------------------------------------
*/
$adminName = (string) $admin['admin_name'];

$adminInitial = strtoupper(
    mb_substr(
        trim($adminName),
        0,
        1
    )
);


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
                    <i class="bi bi-person-circle me-2"></i>
                    Admin Profile
                </h1>

                <p class="page-subtitle">
                    Manage your administrator account and security settings.
                </p>

            </div>

        </div>


        <!-- Alert -->
        <?php if ($message !== ''): ?>

            <div
                class="alert alert-<?php echo h($messageType); ?> alert-dismissible fade show"
                role="alert"
                data-auto-hide="true"
            >

                <i class="bi bi-<?php echo $messageType === 'success'
                    ? 'check-circle'
                    : 'exclamation-triangle'; ?> me-2"></i>

                <?php echo h($message); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <div class="row g-4">

            <!-- Profile Information -->
            <div class="col-xl-8">

                <div class="profile-card">

                    <div class="profile-card-header">

                        <div>

                            <h3>
                                <i class="bi bi-person-vcard me-2"></i>
                                Profile Information
                            </h3>

                            <p>
                                Update your administrator account information.
                            </p>

                        </div>

                    </div>


                    <div class="profile-card-body">

                        <!-- Profile Avatar -->
                        <div class="profile-avatar-section">

                            <div class="profile-large-avatar">
                                <?php echo h($adminInitial); ?>
                            </div>

                            <div>

                                <h4>
                                    <?php echo h($adminName); ?>
                                </h4>

                                <span>
                                    Administrator
                                </span>

                            </div>

                        </div>


                        <!-- Profile Form -->
                        <form
                            method="POST"
                            action="profile.php"
                            class="mt-4"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo h($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="update_profile"
                            >


                            <div class="row g-3">

                                <div class="col-md-6">

                                    <label
                                        for="admin_name"
                                        class="form-label"
                                    >
                                        Admin Name
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-person"></i>
                                        </span>

                                        <input
                                            type="text"
                                            id="admin_name"
                                            name="admin_name"
                                            class="form-control"
                                            value="<?php echo h($adminName); ?>"
                                            maxlength="100"
                                            required
                                        >

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label
                                        for="email"
                                        class="form-label"
                                    >
                                        Email Address
                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>

                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            class="form-control"
                                            value="<?php echo h((string) $admin['email']); ?>"
                                            maxlength="150"
                                            required
                                        >

                                    </div>

                                </div>


                                <div class="col-12">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-check-lg me-1"></i>
                                        Save Profile
                                    </button>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>


                <!-- Change Password -->
                <div class="profile-card mt-4">

                    <div class="profile-card-header">

                        <div>

                            <h3>
                                <i class="bi bi-shield-lock me-2"></i>
                                Change Password
                            </h3>

                            <p>
                                Keep your administrator account secure.
                            </p>

                        </div>

                    </div>


                    <div class="profile-card-body">

                        <form
                            method="POST"
                            action="profile.php"
                            id="passwordForm"
                        >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?php echo h($csrfToken); ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="change_password"
                            >


                            <div class="row g-3">

                                <div class="col-12">

                                    <label
                                        for="current_password"
                                        class="form-label"
                                    >
                                        Current Password
                                    </label>

                                    <div class="password-input">

                                        <input
                                            type="password"
                                            id="current_password"
                                            name="current_password"
                                            class="form-control"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="password-toggle"
                                            data-target="current_password"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label
                                        for="new_password"
                                        class="form-label"
                                    >
                                        New Password
                                    </label>

                                    <div class="password-input">

                                        <input
                                            type="password"
                                            id="new_password"
                                            name="new_password"
                                            class="form-control"
                                            minlength="6"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="password-toggle"
                                            data-target="new_password"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                    </div>

                                    <div class="form-text">
                                        Minimum 6 characters.
                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <label
                                        for="confirm_password"
                                        class="form-label"
                                    >
                                        Confirm New Password
                                    </label>

                                    <div class="password-input">

                                        <input
                                            type="password"
                                            id="confirm_password"
                                            name="confirm_password"
                                            class="form-control"
                                            minlength="6"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="password-toggle"
                                            data-target="confirm_password"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                    </div>

                                </div>


                                <div class="col-12">

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        id="changePasswordButton"
                                    >
                                        <i class="bi bi-key me-1"></i>
                                        Change Password
                                    </button>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>


            <!-- Right Column -->
            <div class="col-xl-4">

                <!-- Account Card -->
                <div class="profile-card">

                    <div class="profile-card-header">

                        <h3>
                            <i class="bi bi-person-badge me-2"></i>
                            Account
                        </h3>

                    </div>


                    <div class="profile-card-body">

                        <div class="account-profile">

                            <div class="account-avatar">
                                <?php echo h($adminInitial); ?>
                            </div>

                            <h4>
                                <?php echo h($adminName); ?>
                            </h4>

                            <span>
                                Administrator
                            </span>

                        </div>


                        <div class="account-info-list">

                            <div>

                                <span>
                                    <i class="bi bi-envelope me-1"></i>
                                    Email
                                </span>

                                <strong>
                                    <?php echo h((string) $admin['email']); ?>
                                </strong>

                            </div>


                            <div>

                                <span>
                                    <i class="bi bi-calendar3 me-1"></i>
                                    Account Created
                                </span>

                                <strong>
                                    <?php
                                    echo !empty($admin['created_at'])
                                        ? h(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    (string) $admin['created_at']
                                                )
                                            )
                                        )
                                        : '—';
                                    ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Security Card -->
                <div class="profile-card mt-4">

                    <div class="profile-card-header">

                        <h3>
                            <i class="bi bi-shield-check me-2"></i>
                            Security
                        </h3>

                    </div>


                    <div class="profile-card-body">

                        <div class="security-item">

                            <div class="security-icon">
                                <i class="bi bi-lock"></i>
                            </div>

                            <div>

                                <strong>
                                    Password Protected
                                </strong>

                                <span>
                                    Your password is securely hashed.
                                </span>

                            </div>

                        </div>


                        <div class="security-item">

                            <div class="security-icon">
                                <i class="bi bi-shield-check"></i>
                            </div>

                            <div>

                                <strong>
                                    CSRF Protection
                                </strong>

                                <span>
                                    Profile actions are protected.
                                </span>

                            </div>

                        </div>


                        <div class="security-item">

                            <div class="security-icon">
                                <i class="bi bi-database-check"></i>
                            </div>

                            <div>

                                <strong>
                                    Secure Database Queries
                                </strong>

                                <span>
                                    PDO prepared statements are used.
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- Logout -->
                <div class="profile-logout-card mt-4">

                    <div>

                        <strong>
                            Sign out
                        </strong>

                        <span>
                            End your current administrator session.
                        </span>

                    </div>

                    <a
                        href="a-logout.php"
                        class="btn btn-outline-danger"
                    >
                        <i class="bi bi-box-arrow-right me-1"></i>
                        Logout
                    </a>

                </div>

            </div>

        </div>

    </main>

</div>


<style>
/* =========================================================
   ADMIN PROFILE
   ========================================================= */

.profile-card {
    background: #ffffff;
    border: 1px solid #e8eaee;
    border-radius: 14px;
    overflow: hidden;
}

.profile-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 18px 20px;
    border-bottom: 1px solid #e8eaee;
}

.profile-card-header h3 {
    margin: 0;
    color: #111827;
    font-size: 17px;
    font-weight: 700;
}

.profile-card-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.profile-card-body {
    padding: 20px;
}

.profile-avatar-section {
    display: flex;
    align-items: center;
    gap: 15px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eef0f2;
}

.profile-large-avatar {
    width: 70px;
    height: 70px;
    min-width: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: linear-gradient(
        135deg,
        #8b5e3c,
        #70492f
    );
    color: #ffffff;
    font-size: 28px;
    font-weight: 700;
    box-shadow: 0 8px 20px rgba(112, 73, 47, .18);
}

.profile-avatar-section h4 {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 700;
}

.profile-avatar-section span {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 13px;
}

.input-group-text {
    background: #f8fafc;
    border-color: #dee2e6;
    color: #70492f;
}

.password-input {
    position: relative;
}

.password-input .form-control {
    padding-right: 48px;
}

.password-toggle {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    width: 34px;
    height: 34px;
    border: 0;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    border-radius: 7px;
}

.password-toggle:hover {
    background: #f3f4f6;
    color: #70492f;
}

.account-profile {
    text-align: center;
    padding: 5px 0 20px;
    border-bottom: 1px solid #eef0f2;
}

.account-avatar {
    width: 82px;
    height: 82px;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #8b5e3c;
    color: #ffffff;
    font-size: 30px;
    font-weight: 700;
}

.account-profile h4 {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 700;
}

.account-profile span {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 13px;
}

.account-info-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 18px;
}

.account-info-list > div {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f1f3;
}

.account-info-list > div:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.account-info-list span {
    color: #6b7280;
    font-size: 12px;
}

.account-info-list strong {
    color: #111827;
    font-size: 13px;
    overflow-wrap: anywhere;
}

.security-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 13px 0;
    border-bottom: 1px solid #f0f1f3;
}

.security-item:first-child {
    padding-top: 0;
}

.security-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.security-icon {
    width: 38px;
    height: 38px;
    min-width: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: rgba(112, 73, 47, .10);
    color: #70492f;
}

.security-item strong {
    display: block;
    color: #111827;
    font-size: 13px;
}

.security-item span {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.profile-logout-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 18px;
    background: #fff;
    border: 1px solid #f0d4d4;
    border-radius: 14px;
}

.profile-logout-card strong {
    display: block;
    color: #991b1b;
    font-size: 14px;
}

.profile-logout-card span {
    display: block;
    margin-top: 3px;
    color: #6b7280;
    font-size: 12px;
}

@media (max-width: 767px) {

    .profile-card-header,
    .profile-card-body {
        padding: 15px;
    }

    .profile-avatar-section {
        align-items: flex-start;
    }

    .profile-large-avatar {
        width: 58px;
        height: 58px;
        min-width: 58px;
        font-size: 23px;
        border-radius: 14px;
    }

    .profile-logout-card {
        flex-direction: column;
        align-items: stretch;
    }

    .profile-logout-card .btn {
        width: 100%;
    }

}
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
    |--------------------------------------------------------------------------
    | Password Visibility
    |--------------------------------------------------------------------------
    */
    document.querySelectorAll('.password-toggle').forEach(function (button) {

        button.addEventListener('click', function () {

            const targetId = button.getAttribute('data-target');
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            const icon = button.querySelector('i');

            if (input.type === 'password') {

                input.type = 'text';

                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }

            } else {

                input.type = 'password';

                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }

        });

    });


    /*
    |--------------------------------------------------------------------------
    | Password Confirmation
    |--------------------------------------------------------------------------
    */
    const passwordForm =
        document.getElementById('passwordForm');

    const newPassword =
        document.getElementById('new_password');

    const confirmPassword =
        document.getElementById('confirm_password');

    if (
        passwordForm &&
        newPassword &&
        confirmPassword
    ) {

        passwordForm.addEventListener('submit', function (event) {

            if (
                newPassword.value !==
                confirmPassword.value
            ) {

                event.preventDefault();

                confirmPassword.setCustomValidity(
                    'Passwords do not match.'
                );

                confirmPassword.reportValidity();

            } else {

                confirmPassword.setCustomValidity('');

            }

        });

        confirmPassword.addEventListener('input', function () {

            if (
                newPassword.value !==
                confirmPassword.value
            ) {

                confirmPassword.setCustomValidity(
                    'Passwords do not match.'
                );

            } else {

                confirmPassword.setCustomValidity('');

            }

        });

    }

});
</script>


<?php require_once "includes/a-footer.php"; ?>