<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| GET ADMIN ACCOUNT
|--------------------------------------------------------------------------
*/

$user_stmt = $pdo->prepare("
    SELECT id, username, email, password
    FROM users
    WHERE id = :id
    LIMIT 1
");

$user_stmt->execute([
    'id' => $user_id
]);

$admin = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('Account not found.');
}

/*
|--------------------------------------------------------------------------
| HANDLE FORMS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_profile') {

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {

            $error = 'Username and email are required.';

        } elseif (strlen($username) < 3) {

            $error = 'Username must be at least 3 characters.';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';

        } else {

            /*
            | Check duplicate username
            */

            $check_username = $pdo->prepare("
                SELECT id
                FROM users
                WHERE username = :username
                AND id != :id
                LIMIT 1
            ");

            $check_username->execute([
                'username' => $username,
                'id' => $user_id
            ]);

            if ($check_username->fetch()) {

                $error = 'Username is already being used.';

            } else {

                /*
                | Check duplicate email
                */

                $check_email = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = :email
                    AND id != :id
                    LIMIT 1
                ");

                $check_email->execute([
                    'email' => $email,
                    'id' => $user_id
                ]);

                if ($check_email->fetch()) {

                    $error = 'Email address is already being used.';

                } else {

                    $update = $pdo->prepare("
                        UPDATE users
                        SET username = :username,
                            email = :email
                        WHERE id = :id
                    ");

                    $update->execute([
                        'username' => $username,
                        'email' => $email,
                        'id' => $user_id
                    ]);

                    $admin['username'] = $username;
                    $admin['email'] = $email;

                    $success = 'Your profile has been updated successfully.';
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PASSWORD
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'update_password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '') {

            $error = 'Please enter your current password.';

        } elseif (!password_verify($current_password, $admin['password'])) {

            $error = 'Current password is incorrect.';

        } elseif (strlen($new_password) < 8) {

            $error = 'New password must be at least 8 characters.';

        } elseif ($new_password !== $confirm_password) {

            $error = 'New password and confirmation do not match.';

        } elseif ($current_password === $new_password) {

            $error = 'New password must be different from your current password.';

        } else {

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            $update = $pdo->prepare("
                UPDATE users
                SET password = :password
                WHERE id = :id
            ");

            $update->execute([
                'password' => $hashed_password,
                'id' => $user_id
            ]);

            $success = 'Your password has been changed successfully.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| ADMIN DISPLAY NAME
|--------------------------------------------------------------------------
*/

$admin_username = $admin['username'] ?? 'Administrator';

$initial = strtoupper(
    substr($admin_username, 0, 1)
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Settings | CareSched Admin</title>

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

        :root {

            --primary: #2563eb;
            --primary-dark: #1d4ed8;

            --sidebar: #0b1f3a;
            --sidebar-light: #12345a;

            --bg: #f5f7fb;

            --text: #172033;
            --muted: #7b8798;

            --border: #e5eaf1;

            --white: #ffffff;

            --success: #16a34a;
            --danger: #dc2626;

        }

        html,
        body {
            min-height: 100%;
        }

        body {

            margin: 0;

            font-family: 'Inter', sans-serif;

            background:
                radial-gradient(
                    circle at top right,
                    rgba(37, 99, 235, .06),
                    transparent 30%
                ),
                var(--bg);

            color: var(--text);

        }

        a {
            text-decoration: none;
        }


        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;

            width: 260px;
            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #081a31 0%,
                    #0b2544 55%,
                    #0e3157 100%
                );

            color: white;

            padding: 20px 15px;

            z-index: 2000;

            overflow-y: auto;

            transition: .3s ease;

            box-shadow:
                8px 0 30px rgba(0, 0, 0, .08);

        }


        /* BRAND */

        .sidebar-brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 8px 10px 22px;

            margin-bottom: 20px;

            border-bottom:
                1px solid rgba(255,255,255,.08);

        }

        .brand-icon {

            width: 44px;
            height: 44px;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            box-shadow:
                0 8px 20px rgba(37,99,235,.25);

            font-size: 19px;

        }

        .brand-text strong {

            display: block;

            font-size: 18px;

            font-weight: 800;

            letter-spacing: -.4px;

        }

        .brand-text span {

            display: block;

            margin-top: 2px;

            font-size: 9px;

            color: rgba(255,255,255,.5);

            text-transform: uppercase;

            letter-spacing: 1px;

        }


        /* MENU */

        .menu-label {

            padding: 0 13px;

            margin:

                20px 0 8px;

            color:
                rgba(255,255,255,.38);

            font-size: 9px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1.3px;

        }

        .sidebar-link {

            position: relative;

            display: flex;

            align-items: center;

            gap: 13px;

            color:
                rgba(255,255,255,.68);

            padding: 11px 13px;

            margin-bottom: 4px;

            border-radius: 11px;

            font-size: 12px;

            font-weight: 600;

            transition: .2s ease;

        }

        .sidebar-link i {

            width: 20px;

            text-align: center;

            font-size: 14px;

        }

        .sidebar-link:hover {

            color: white;

            background:
                rgba(255,255,255,.07);

            transform:
                translateX(3px);

        }

        .sidebar-link.active {

            color: white;

            background:
                linear-gradient(
                    90deg,
                    rgba(37,99,235,.35),
                    rgba(37,99,235,.12)
                );

            box-shadow:
                inset 3px 0 0 #3b82f6;

        }

        .sidebar-link.active i {

            color: #60a5fa;

        }


        /* SIDEBAR BOTTOM */

        .sidebar-bottom {

            position: absolute;

            bottom: 18px;

            left: 15px;
            right: 15px;

            padding-top: 12px;

            border-top:
                1px solid rgba(255,255,255,.08);

        }

        .logout-link {

            color: #ffb4b4;

        }

        .logout-link:hover {

            color: white;

            background:
                rgba(220,53,69,.18);

        }


        /* =========================================================
           MAIN
        ========================================================= */

        .main {

            margin-left: 260px;

            min-height: 100vh;

            transition: .3s ease;

        }


        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {

            height: 76px;

            background:
                rgba(255,255,255,.94);

            backdrop-filter:
                blur(12px);

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 35px;

            position: sticky;

            top: 0;

            z-index: 1000;

        }

        .menu-toggle {

            display: none;

            border: none;

            background: #edf4ff;

            color: var(--primary);

            width: 42px;

            height: 42px;

            border-radius: 11px;

            font-size: 16px;

        }

        .page-title h1 {

            margin: 0;

            font-size: 20px;

            font-weight: 800;

            letter-spacing: -.4px;

        }

        .page-title p {

            margin: 4px 0 0;

            color: var(--muted);

            font-size: 10px;

        }


        /* ADMIN PROFILE */

        .admin-profile {

            display: flex;

            align-items: center;

            gap: 10px;

        }

        .admin-avatar {

            width: 41px;
            height: 41px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #eaf2ff,
                    #dbeafe
                );

            color: var(--primary);

            font-size: 14px;

            font-weight: 800;

        }

        .admin-profile strong {

            display: block;

            font-size: 12px;

            font-weight: 700;

        }

        .admin-profile span {

            display: block;

            margin-top: 2px;

            color: var(--muted);

            font-size: 9px;

        }


        /* =========================================================
           CONTENT
        ========================================================= */

        .content {

            padding: 32px 35px 45px;

            max-width: 1050px;

        }


        /* PAGE HEADER */

        .settings-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;

        }

        .settings-heading {

            display: flex;

            align-items: center;

            gap: 15px;

        }

        .settings-heading-icon {

            width: 52px;
            height: 52px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 15px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            color: white;

            box-shadow:
                0 10px 25px rgba(37,99,235,.18);

            font-size: 19px;

        }

        .settings-heading h2 {

            margin: 0;

            font-size: 18px;

            font-weight: 800;

        }

        .settings-heading p {

            margin: 4px 0 0;

            color: var(--muted);

            font-size: 11px;

        }


        /* ACCOUNT STATUS */

        .account-status {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 9px 13px;

            border-radius: 30px;

            background: #ecfdf3;

            color: #15803d;

            font-size: 10px;

            font-weight: 700;

            border: 1px solid #d1fae5;

        }

        .status-dot {

            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #22c55e;

            box-shadow:
                0 0 0 4px rgba(34,197,94,.12);

        }


        /* ALERT */

        .custom-alert {

            border: none;

            border-radius: 14px;

            padding: 13px 15px;

            margin-bottom: 20px;

            font-size: 11px;

            display: flex;

            align-items: center;

            gap: 10px;

            box-shadow:
                0 5px 18px rgba(0,0,0,.04);

        }

        .custom-alert.success {

            background: #ecfdf3;

            color: #166534;

            border:
                1px solid #bbf7d0;

        }

        .custom-alert.error {

            background: #fef2f2;

            color: #991b1b;

            border:
                1px solid #fecaca;

        }


        /* =========================================================
           GRID
        ========================================================= */

        .settings-grid {

            display: grid;

            grid-template-columns:
                minmax(0, 1.35fr)
                minmax(300px, .65fr);

            gap: 22px;

        }


        /* =========================================================
           CARD
        ========================================================= */

        .section-card {

            background: white;

            border:
                1px solid var(--border);

            border-radius: 20px;

            box-shadow:
                0 8px 30px rgba(20,40,70,.045);

            overflow: hidden;

            transition: .25s ease;

        }

        .section-card:hover {

            box-shadow:
                0 12px 35px rgba(20,40,70,.07);

        }

        .card-header-custom {

            padding: 21px 23px 17px;

            display: flex;

            align-items: center;

            gap: 13px;

            border-bottom:
                1px solid #eef1f5;

        }

        .card-icon {

            width: 40px;
            height: 40px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background: #eff6ff;

            color: var(--primary);

            font-size: 14px;

        }

        .card-icon.green {

            background: #ecfdf5;

            color: #059669;

        }

        .card-header-custom h3 {

            margin: 0;

            font-size: 13px;

            font-weight: 800;

        }

        .card-header-custom p {

            margin: 3px 0 0;

            color: var(--muted);

            font-size: 9px;

        }

        .card-body-custom {

            padding: 23px;

        }


        /* =========================================================
           PROFILE BOX
        ========================================================= */

        .profile-preview {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 13px;

            background:
                linear-gradient(
                    135deg,
                    #f8fbff,
                    #f1f6ff
                );

            border:
                1px solid #e5edfb;

            border-radius: 14px;

            margin-bottom: 22px;

        }

        .profile-preview-avatar {

            width: 45px;
            height: 45px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            color: white;

            font-size: 15px;

            font-weight: 800;

        }

        .profile-preview strong {

            display: block;

            font-size: 12px;

        }

        .profile-preview span {

            display: block;

            margin-top: 3px;

            color: var(--muted);

            font-size: 9px;

        }


        /* =========================================================
           FORM
        ========================================================= */

        .form-group {

            margin-bottom: 18px;

        }

        .form-label {

            display: block;

            margin-bottom: 7px;

            color: #334155;

            font-size: 10px;

            font-weight: 700;

        }

        .input-wrapper {

            position: relative;

        }

        .input-icon {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #94a3b8;

            font-size: 12px;

            z-index: 2;

        }

        .form-control {

            min-height: 43px;

            border:
                1px solid #dfe5ed;

            border-radius: 11px;

            padding:
                10px 13px 10px 38px;

            font-size: 11px;

            color: #1e293b;

            box-shadow: none;

            transition: .2s ease;

        }

        .form-control:focus {

            border-color:
                #60a5fa;

            box-shadow:
                0 0 0 4px
                rgba(37,99,235,.08);

        }

        .password-input {

            padding-right: 43px;

        }

        .password-toggle {

            position: absolute;

            right: 12px;

            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: transparent;

            color: #94a3b8;

            cursor: pointer;

            font-size: 12px;

        }

        .password-toggle:hover {

            color: var(--primary);

        }


        /* PASSWORD STRENGTH */

        .password-strength {

            display: flex;

            gap: 4px;

            margin-top: 8px;

        }

        .strength-bar {

            height: 4px;

            flex: 1;

            border-radius: 5px;

            background: #e5e7eb;

            transition: .2s ease;

        }

        .strength-text {

            margin-top: 5px;

            font-size: 9px;

            color: var(--muted);

        }


        /* =========================================================
           BUTTON
        ========================================================= */

        .btn-save {

            border: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            min-height: 41px;

            padding:
                9px 17px;

            border-radius: 11px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            color: white;

            font-size: 10px;

            font-weight: 700;

            box-shadow:
                0 7px 17px
                rgba(37,99,235,.18);

            transition: .2s ease;

        }

        .btn-save:hover {

            color: white;

            transform:
                translateY(-1px);

            box-shadow:
                0 10px 22px
                rgba(37,99,235,.25);

        }

        .btn-save.green {

            background:
                linear-gradient(
                    135deg,
                    #059669,
                    #10b981
                );

            box-shadow:
                0 7px 17px
                rgba(5,150,105,.18);

        }


        /* =========================================================
           SECURITY CARD
        ========================================================= */

        .security-tip {

            padding: 13px;

            background:
                #f8fafc;

            border:
                1px solid #edf1f5;

            border-radius: 12px;

            margin-bottom: 18px;

        }

        .security-tip-title {

            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 5px;

            color: #334155;

            font-size: 10px;

            font-weight: 800;

        }

        .security-tip p {

            margin: 0;

            color: var(--muted);

            font-size: 9px;

            line-height: 1.6;

        }

        .requirement-list {

            margin:
                10px 0 20px;

            padding: 0;

            list-style: none;

        }

        .requirement-list li {

            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 7px;

            color: #64748b;

            font-size: 9px;

        }

        .requirement-list i {

            color: #22c55e;

            font-size: 8px;

        }


        /* =========================================================
           FOOTER NOTE
        ========================================================= */

        .security-footer {

            margin-top: 20px;

            padding-top: 15px;

            border-top:
                1px solid #eef1f5;

            color: #94a3b8;

            font-size: 8px;

            line-height: 1.5;

        }


        /* =========================================================
           OVERLAY
        ========================================================= */

        .sidebar-overlay {

            display: none;

            position: fixed;

            inset: 0;

            background:
                rgba(2, 6, 23, .45);

            z-index: 1500;

            backdrop-filter:
                blur(2px);

        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1050px) {

            .settings-grid {

                grid-template-columns: 1fr;

            }

            .content {

                max-width: 850px;

            }

        }


        @media (max-width: 850px) {

            .sidebar {

                transform:
                    translateX(-100%);

            }

            .sidebar.show {

                transform:
                    translateX(0);

            }

            .sidebar-overlay.show {

                display: block;

            }

            .main {

                margin-left: 0;

            }

            .menu-toggle {

                display: flex;

                align-items: center;
                justify-content: center;

            }

            .topbar {

                padding:
                    0 20px;

            }

            .content {

                padding:
                    25px 20px 40px;

            }

        }


        @media (max-width: 600px) {

            .page-title p {

                display: none;

            }

            .admin-profile > div:last-child {

                display: none;

            }

            .settings-header {

                align-items: flex-start;

                flex-direction: column;

            }

            .account-status {

                align-self: flex-start;

            }

            .card-header-custom,
            .card-body-custom {

                padding:
                    18px;

            }

        }


        @media (max-width: 420px) {

            .content {

                padding:
                    20px 14px 35px;

            }

            .topbar {

                padding:
                    0 14px;

            }

            .settings-heading-icon {

                width: 45px;
                height: 45px;

            }

            .settings-heading h2 {

                font-size: 16px;

            }

            .btn-save {

                width: 100%;

            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>


<!-- SIDEBAR OVERLAY -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="d-flex align-items-center gap-3">

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-label="Open menu"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="page-title">

                <h1>Settings</h1>

                <p>
                    Manage your administrator account
                </p>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">
                <?= e($initial) ?>
            </div>

            <div>

                <strong>
                    <?= e($admin_username) ?>
                </strong>

                <span>
                    <?= e($admin['email'] ?? 'RHU Arakan') ?>
                </span>

            </div>

        </div>

    </header>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <section class="content">


        <!-- PAGE HEADER -->

        <div class="settings-header">

            <div class="settings-heading">

                <div class="settings-heading-icon">

                    <i class="fa-solid fa-sliders"></i>

                </div>

                <div>

                    <h2>
                        Account Settings
                    </h2>

                    <p>
                        Update your profile information and security settings.
                    </p>

                </div>

            </div>


            <div class="account-status">

                <span class="status-dot"></span>

                Account Active

            </div>

        </div>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($success): ?>

            <div class="custom-alert success">

                <i class="fa-solid fa-circle-check"></i>

                <span>
                    <?= e($success) ?>
                </span>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="custom-alert error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <span>
                    <?= e($error) ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             SETTINGS GRID
        ================================================== -->

        <div class="settings-grid">


            <!-- =================================================
                 PROFILE
            ================================================== -->

            <div class="section-card">

                <div class="card-header-custom">

                    <div class="card-icon">

                        <i class="fa-solid fa-user"></i>

                    </div>

                    <div>

                        <h3>
                            Profile Information
                        </h3>

                        <p>
                            Manage your administrator account details.
                        </p>

                    </div>

                </div>


                <div class="card-body-custom">


                    <!-- PROFILE PREVIEW -->

                    <div class="profile-preview">

                        <div class="profile-preview-avatar">

                            <?= e($initial) ?>

                        </div>

                        <div>

                            <strong>
                                <?= e($admin_username) ?>
                            </strong>

                            <span>
                                <?= e($admin['email'] ?? '') ?>
                            </span>

                        </div>

                    </div>


                    <form method="post">

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >


                        <!-- USERNAME -->

                        <div class="form-group">

                            <label class="form-label">
                                Username
                            </label>

                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-user input-icon"
                                ></i>

                                <input
                                    type="text"
                                    class="form-control"
                                    name="username"
                                    value="<?= e($admin['username'] ?? '') ?>"
                                    minlength="3"
                                    maxlength="50"
                                    required
                                >

                            </div>

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label class="form-label">
                                Email Address
                            </label>

                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-envelope input-icon"
                                ></i>

                                <input
                                    type="email"
                                    class="form-control"
                                    name="email"
                                    value="<?= e($admin['email'] ?? '') ?>"
                                    maxlength="150"
                                    required
                                >

                            </div>

                        </div>


                        <button
                            type="submit"
                            class="btn-save"
                        >

                            <i class="fa-solid fa-floppy-disk"></i>

                            Save Profile Changes

                        </button>

                    </form>

                </div>

            </div>


            <!-- =================================================
                 PASSWORD
            ================================================== -->

            <div class="section-card">

                <div class="card-header-custom">

                    <div class="card-icon green">

                        <i class="fa-solid fa-shield-halved"></i>

                    </div>

                    <div>

                        <h3>
                            Security
                        </h3>

                        <p>
                            Keep your administrator account secure.
                        </p>

                    </div>

                </div>


                <div class="card-body-custom">


                    <!-- SECURITY TIP -->

                    <div class="security-tip">

                        <div class="security-tip-title">

                            <i class="fa-solid fa-lock"></i>

                            Password Security

                        </div>

                        <p>

                            Use a strong password that is difficult
                            for others to guess.

                        </p>

                    </div>


                    <form method="post">

                        <input
                            type="hidden"
                            name="action"
                            value="update_password"
                        >


                        <!-- CURRENT PASSWORD -->

                        <div class="form-group">

                            <label class="form-label">
                                Current Password
                            </label>

                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-lock input-icon"
                                ></i>

                                <input
                                    type="password"
                                    class="form-control password-input"
                                    name="current_password"
                                    id="currentPassword"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword('currentPassword', this)"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                            </div>

                        </div>


                        <!-- NEW PASSWORD -->

                        <div class="form-group">

                            <label class="form-label">
                                New Password
                            </label>

                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-key input-icon"
                                ></i>

                                <input
                                    type="password"
                                    class="form-control password-input"
                                    name="new_password"
                                    id="newPassword"
                                    minlength="8"
                                    required
                                    oninput="checkPasswordStrength(this.value)"
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword('newPassword', this)"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                            </div>


                            <!-- PASSWORD STRENGTH -->

                            <div class="password-strength">

                                <span
                                    class="strength-bar"
                                    id="bar1"
                                ></span>

                                <span
                                    class="strength-bar"
                                    id="bar2"
                                ></span>

                                <span
                                    class="strength-bar"
                                    id="bar3"
                                ></span>

                                <span
                                    class="strength-bar"
                                    id="bar4"
                                ></span>

                            </div>

                            <div
                                class="strength-text"
                                id="strengthText"
                            >
                                Enter a password

                            </div>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">

                            <label class="form-label">
                                Confirm New Password
                            </label>

                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-check-double input-icon"
                                ></i>

                                <input
                                    type="password"
                                    class="form-control password-input"
                                    name="confirm_password"
                                    id="confirmPassword"
                                    minlength="8"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    onclick="togglePassword('confirmPassword', this)"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                            </div>

                        </div>


                        <!-- REQUIREMENTS -->

                        <ul class="requirement-list">

                            <li>

                                <i class="fa-solid fa-circle-check"></i>

                                At least 8 characters

                            </li>

                            <li>

                                <i class="fa-solid fa-circle-check"></i>

                                Use uppercase and lowercase letters

                            </li>

                            <li>

                                <i class="fa-solid fa-circle-check"></i>

                                Include numbers or special characters

                            </li>

                        </ul>


                        <button
                            type="submit"
                            class="btn-save green"
                        >

                            <i class="fa-solid fa-key"></i>

                            Update Password

                        </button>

                    </form>


                    <div class="security-footer">

                        <i class="fa-solid fa-circle-info me-1"></i>

                        Your password is securely hashed before
                        being stored in the database.

                    </div>

                </div>

            </div>


        </div>

    </section>

</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

    /*
    |--------------------------------------------------------------------------
    | MOBILE SIDEBAR
    |--------------------------------------------------------------------------
    */

    const menuToggle =
        document.getElementById('menuToggle');

    const sidebar =
        document.getElementById('sidebar');

    const sidebarOverlay =
        document.getElementById('sidebarOverlay');


    function openSidebar() {

        sidebar.classList.add('show');

        sidebarOverlay.classList.add('show');

    }


    function closeSidebar() {

        sidebar.classList.remove('show');

        sidebarOverlay.classList.remove('show');

    }


    if (menuToggle) {

        menuToggle.addEventListener(
            'click',
            openSidebar
        );

    }


    if (sidebarOverlay) {

        sidebarOverlay.addEventListener(
            'click',
            closeSidebar
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE SIDEBAR WHEN LINK IS CLICKED
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.sidebar-link')
        .forEach(function(link) {

            link.addEventListener(
                'click',
                function() {

                    if (window.innerWidth <= 850) {

                        closeSidebar();

                    }

                }
            );

        });


    /*
    |--------------------------------------------------------------------------
    | PASSWORD SHOW / HIDE
    |--------------------------------------------------------------------------
    */

    function togglePassword(
        inputId,
        button
    ) {

        const input =
            document.getElementById(inputId);

        const icon =
            button.querySelector('i');


        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove(
                'fa-eye'
            );

            icon.classList.add(
                'fa-eye-slash'
            );

        } else {

            input.type = 'password';

            icon.classList.remove(
                'fa-eye-slash'
            );

            icon.classList.add(
                'fa-eye'
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | PASSWORD STRENGTH
    |--------------------------------------------------------------------------
    */

    function checkPasswordStrength(password) {

        const bars = [

            document.getElementById('bar1'),

            document.getElementById('bar2'),

            document.getElementById('bar3'),

            document.getElementById('bar4')

        ];

        const text =
            document.getElementById('strengthText');


        bars.forEach(function(bar) {

            bar.style.background =
                '#e5e7eb';

        });


        if (password.length === 0) {

            text.textContent =
                'Enter a password';

            return;

        }


        let score = 0;


        if (password.length >= 8) {
            score++;
        }

        if (/[A-Z]/.test(password)) {
            score++;
        }

        if (/[0-9]/.test(password)) {
            score++;
        }

        if (/[^A-Za-z0-9]/.test(password)) {
            score++;
        }


        if (score === 1) {

            bars[0].style.background =
                '#ef4444';

            text.textContent =
                'Weak password';

        }

        else if (score === 2) {

            bars[0].style.background =
                '#f59e0b';

            bars[1].style.background =
                '#f59e0b';

            text.textContent =
                'Fair password';

        }

        else if (score === 3) {

            bars[0].style.background =
                '#3b82f6';

            bars[1].style.background =
                '#3b82f6';

            bars[2].style.background =
                '#3b82f6';

            text.textContent =
                'Good password';

        }

        else if (score === 4) {

            bars.forEach(function(bar) {

                bar.style.background =
                    '#22c55e';

            });

            text.textContent =
                'Strong password';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | AUTO HIDE ALERT
    |--------------------------------------------------------------------------
    */

    setTimeout(function() {

        document
            .querySelectorAll('.custom-alert')
            .forEach(function(alert) {

                alert.style.transition =
                    'opacity .4s ease';

                alert.style.opacity = '0';

                setTimeout(function() {

                    alert.remove();

                }, 400);

            });

    }, 5000);


</script>


</body>
</html>
