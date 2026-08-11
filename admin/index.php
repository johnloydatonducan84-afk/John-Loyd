<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];

/*
|--------------------------------------------------------------------------
| ADMIN LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Please enter your username and password.';
    } else {

        try {

            /*
             * Get admin from USERS table.
             * The admins table only contains the admin profile.
             */
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    username,
                    email,
                    password,
                    role,
                    status
                FROM users
                WHERE username = :username
                  AND role = 'admin'
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                ':username' => $username
            ]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {

                $errors[] = 'Invalid admin username or password.';

            } elseif (!password_verify($password, $admin['password'])) {

                $errors[] = 'Invalid admin username or password.';

            } else {

                /*
                 * Login successful
                 */

                session_regenerate_id(true);

                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['email'] = $admin['email'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_logged_in'] = true;

                header('Location: dashboard.php');
                exit;
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $errors[] = 'Database error. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | CareSched</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        rel="stylesheet"
    >

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(13, 110, 253, .15), transparent 35%),
                radial-gradient(circle at bottom right, rgba(25, 135, 84, .12), transparent 35%),
                #f4f7fb;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 25px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1050px;
            min-height: 620px;

            background: #fff;

            border-radius: 28px;

            overflow: hidden;

            box-shadow:
                0 25px 70px rgba(15, 35, 65, .14);

            display: grid;

            grid-template-columns: 1fr 1fr;
        }

        /* LEFT SIDE */

        .login-info {
            position: relative;

            padding: 60px;

            color: #fff;

            background:
                linear-gradient(
                    145deg,
                    #0758c9,
                    #0d6efd 55%,
                    #198754
                );

            display: flex;
            flex-direction: column;
            justify-content: center;

            overflow: hidden;
        }

        .login-info::before {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            border-radius: 50%;

            background: rgba(255,255,255,.08);

            top: -120px;
            right: -100px;
        }

        .login-info::after {
            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            border-radius: 50%;

            background: rgba(255,255,255,.07);

            bottom: -90px;
            left: -70px;
        }

        .brand {
            position: relative;
            z-index: 2;

            font-size: 28px;
            font-weight: 800;

            margin-bottom: 45px;
        }

        .brand i {
            margin-right: 8px;
        }

        .info-content {
            position: relative;
            z-index: 2;
        }

        .info-content h1 {
            font-size: 42px;
            line-height: 1.15;

            font-weight: 800;

            margin-bottom: 20px;
        }

        .info-content p {
            font-size: 16px;
            line-height: 1.8;

            opacity: .9;

            max-width: 430px;
        }

        .feature {
            display: flex;
            align-items: center;

            gap: 14px;

            margin-top: 30px;
        }

        .feature-icon {
            width: 45px;
            height: 45px;

            border-radius: 13px;

            background: rgba(255,255,255,.15);

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .feature span {
            font-size: 14px;
            font-weight: 500;
        }

        /* RIGHT SIDE */

        .login-form-area {
            padding: 60px;

            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 30px;
        }

        .admin-icon {
            width: 65px;
            height: 65px;

            border-radius: 18px;

            background: #eaf2ff;

            color: #0d6efd;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 25px;

            margin-bottom: 22px;
        }

        .login-header h2 {
            font-size: 30px;

            font-weight: 800;

            color: #182433;

            margin-bottom: 8px;
        }

        .login-header p {
            color: #7a8795;

            margin: 0;

            font-size: 14px;
        }

        /* ERROR */

        .alert-danger {
            border: none;

            border-radius: 12px;

            background: #fff0f0;

            color: #b42318;

            font-size: 14px;

            padding: 14px 16px;
        }

        /* INPUT */

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;

            font-weight: 600;

            color: #344054;

            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper > i {
            position: absolute;

            left: 16px;
            top: 50%;

            transform: translateY(-50%);

            color: #98a2b3;

            z-index: 2;
        }

        .form-control {
            height: 54px;

            border: 1px solid #d9e1eb;

            border-radius: 13px;

            padding-left: 45px;

            padding-right: 45px;

            font-size: 14px;

            background: #f9fbfd;

            transition: .25s ease;
        }

        .form-control:focus {
            background: #fff;

            border-color: #0d6efd;

            box-shadow: 0 0 0 4px rgba(13,110,253,.10);
        }

        .toggle-password {
            position: absolute;

            right: 16px;
            top: 50%;

            transform: translateY(-50%);

            color: #98a2b3;

            cursor: pointer;

            z-index: 3;
        }

        .toggle-password:hover {
            color: #0d6efd;
        }

        /* BUTTON */

        .login-button {
            width: 100%;

            height: 54px;

            border: none;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #0758c9
                );

            color: #fff;

            font-weight: 700;

            font-size: 15px;

            box-shadow:
                0 10px 25px rgba(13,110,253,.22);

            transition: .25s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 14px 30px rgba(13,110,253,.30);
        }

        .secure-note {
            display: flex;

            align-items: center;

            gap: 9px;

            margin-top: 20px;

            padding: 12px 14px;

            background: #f5f9ff;

            border-radius: 11px;

            color: #667085;

            font-size: 12px;
        }

        .secure-note i {
            color: #0d6efd;
        }

        .back-link {
            display: block;

            text-align: center;

            margin-top: 25px;

            color: #667085;

            text-decoration: none;

            font-size: 13px;

            font-weight: 500;
        }

        .back-link:hover {
            color: #0d6efd;
        }

        .copyright {
            text-align: center;

            color: #98a2b3;

            font-size: 11px;

            margin-top: 25px;
        }

        /* RESPONSIVE */

        @media (max-width: 850px) {

            .login-wrapper {
                grid-template-columns: 1fr;

                max-width: 500px;
            }

            .login-info {
                display: none;
            }

            .login-form-area {
                padding: 45px 30px;
            }
        }

        @media (max-width: 480px) {

            body {
                padding: 15px;
            }

            .login-wrapper {
                border-radius: 20px;
            }

            .login-form-area {
                padding: 35px 22px;
            }

            .login-header h2 {
                font-size: 26px;
            }
        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <!-- LEFT INFORMATION PANEL -->

    <div class="login-info">

        <div class="brand">
            <i class="fa-solid fa-heart-pulse"></i>
            CareSched
        </div>

        <div class="info-content">

            <h1>
                Admin<br>
                Portal
            </h1>

            <p>
                Manage patient appointments, healthcare services,
                schedules, notifications, and other RHU operations
                in one secure platform.
            </p>

            <div class="feature">

                <div class="feature-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <span>
                    Manage appointments efficiently
                </span>

            </div>

            <div class="feature">

                <div class="feature-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <span>
                    Monitor patient information
                </span>

            </div>

            <div class="feature">

                <div class="feature-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>

                <span>
                    Manage email notifications
                </span>

            </div>

        </div>

    </div>


    <!-- LOGIN FORM -->

    <div class="login-form-area">

        <div class="login-header">

            <div class="admin-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>

            <h2>Welcome back!</h2>

            <p>
                Sign in to access the CareSched administration panel.
            </p>

        </div>


        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger mb-4">

                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= e($errors[0]) ?>

            </div>

        <?php endif; ?>


        <form method="POST" action="" autocomplete="off">

            <div class="form-group">

                <label class="form-label">
                    Username
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-user"></i>

                    <input
                        type="text"
                        name="username"
                        class="form-control"
                        placeholder="Enter admin username"
                        value="<?= e($_POST['username'] ?? '') ?>"
                        required
                        autofocus
                    >

                </div>

            </div>


            <div class="form-group">

                <label class="form-label">
                    Password
                </label>

                <div class="input-wrapper">

                    <i class="fa-solid fa-lock"></i>

                    <input
                        type="password"
                        name="password"
                        id="adminPassword"
                        class="form-control"
                        placeholder="Enter your password"
                        required
                    >

                    <span
                        class="toggle-password"
                        id="togglePassword"
                        title="Show password"
                    >
                        <i class="fa-solid fa-eye"></i>
                    </span>

                </div>

            </div>


            <button
                type="submit"
                class="login-button"
            >
                <i class="fa-solid fa-right-to-bracket me-2"></i>
                Sign In
            </button>


            <div class="secure-note">

                <i class="fa-solid fa-shield-halved"></i>

                <span>
                    Your administrator account is protected with
                    secure password authentication.
                </span>

            </div>

        </form>


        <a
            href="/caresched/"
            class="back-link"
        >
            <i class="fa-solid fa-arrow-left me-1"></i>
            Back to CareSched
        </a>


        <div class="copyright">

            © <?= date('Y') ?> CareSched —
            Rural Health Unit, Arakan, Cotabato

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const password =
        document.getElementById('adminPassword');

    const toggle =
        document.getElementById('togglePassword');

    if (password && toggle) {

        toggle.addEventListener('click', function () {

            const icon =
                toggle.querySelector('i');

            if (password.type === 'password') {

                password.type = 'text';

                icon.classList.remove('fa-eye');

                icon.classList.add('fa-eye-slash');

                toggle.title = 'Hide password';

            } else {

                password.type = 'password';

                icon.classList.remove('fa-eye-slash');

                icon.classList.add('fa-eye');

                toggle.title = 'Show password';

            }

        });

    }

});

</script>

</body>
</html>