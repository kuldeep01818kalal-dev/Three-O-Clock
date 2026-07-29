<?php
session_start();
require_once "../config/db.php";

if (isset($_SESSION['admin_id'])) {
    header("Location:a-dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT * FROM admins
            WHERE email = :email
            AND status='Active'
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email
    ]);

    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {

        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];

        header("Location:a-dashboard.php");
        exit;

    } else {

        $error = "Invalid email or password.";

    }

}
?>

<?php include "includes/a-header.php"; ?>

<div class="container py-5">

    <div class="row justify-content-center">

        <div class="col-lg-5">

            <div class="card shadow-lg border-0 rounded-4">

                <div class="card-body p-5">

                    <h2 class="text-center mb-4">

                        Admin Login

                    </h2>

                    <?php if($error){ ?>

                        <div class="alert alert-danger">

                            <?= $error ?>

                        </div>

                    <?php } ?>

                    <form method="POST">

                        <div class="mb-3">

                            <label>Email</label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                required>

                        </div>

                        <div class="mb-4">

                            <label>Password</label>

                            <input
                                type="password"
                                name="password"
                                class="form-control"
                                required>

                        </div>

                        <button class="btn btn-primary w-100">

                            Login

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php include "includes/a-footer.php"; ?>