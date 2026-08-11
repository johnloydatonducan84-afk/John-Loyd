<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| NOTE ON SCHEMA
|--------------------------------------------------------------------------
| Assumes a `users` table with columns: id, username, email, password
| Adjust column names below to match your actual table if different.
|--------------------------------------------------------------------------
*/

$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$user_stmt->execute(['id' => $user_id]);
$admin = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die('Account not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {
            $error = 'Username and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {

            $update = $pdo->prepare("
                UPDATE users
                SET username = :username, email = :email
                WHERE id = :id
            ");

            $update->execute([
                'username' => $username,
                'email' => $email,
                'id' => $user_id
            ]);

            $success = 'Profile updated successfully.';
            $admin['username'] = $username;
            $admin['email'] = $email;
        }

    } elseif ($action === 'update_password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_password, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } else {

            $update = $pdo->prepare("
                UPDATE users
                SET password = :password
                WHERE id = :id
            ");

            $update->execute([
                'password' => password_hash($new_password, PASSWORD_DEFAULT),
                'id' => $user_id
            ]);

            $success = 'Password changed successfully.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | CareSched Admin</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * { box-sizing: border-box; }

        :root {
            --primary: #0d6efd; --sidebar: #0b1f3a; --bg: #f4f7fb; --text: #172033;
            --muted: #7b8798; --border: #e6ebf2; --white: #ffffff;
        }

        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
        a { text-decoration: none; }

        .sidebar {
            position: fixed; top: 0; left: 0; width: 260px; height: 100vh;
            background: linear-gradient(180deg, #0b1f3a, #102d50);
            color: white; padding: 25px 16px; z-index: 1000; transition: .3s ease; overflow-y: auto;
        }

        .sidebar-brand {
            display: flex; align-items: center; gap: 12px; padding: 10px 12px 28px;
            border-bottom: 1px solid rgba(255,255,255,.08); margin-bottom: 25px;
        }

        .brand-icon {
            width: 43px; height: 43px; display: flex; align-items: center; justify-content: center;
            border-radius: 13px; background: linear-gradient(135deg, #0d6efd, #198754); font-size: 19px;
        }

        .brand-text strong { display: block; font-size: 18px; font-weight: 800; }
        .brand-text span { font-size: 10px; color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: .8px; }

        .menu-label {
            padding: 0 13px; color: rgba(255,255,255,.4); font-size: 10px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; margin: 20px 0 8px;
        }

        .sidebar-link {
            display: flex; align-items: center; gap: 13px; color: rgba(255,255,255,.7);
            padding: 12px 14px; border-radius: 11px; margin-bottom: 4px; font-size: 13px;
            font-weight: 500; transition: .2s ease;
        }

        .sidebar-link i { width: 20px; text-align: center; }
        .sidebar-link:hover, .sidebar-link.active { color: white; background: rgba(13,110,253,.25); transform: translateX(3px); }
        .sidebar-link.active { box-shadow: inset 3px 0 0 #0d6efd; }

        .sidebar-bottom { position: absolute; bottom: 20px; left: 16px; right: 16px; }
        .logout-link { color: #ffb4b4; }
        .logout-link:hover { color: #fff; background: rgba(220,53,69,.18); }

        .main { margin-left: 260px; min-height: 100vh; transition: .3s ease; }

        .topbar {
            height: 76px; background: white; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 35px; position: sticky; top: 0; z-index: 500;
        }

        .menu-toggle { display: none; border: none; background: #eef4ff; color: var(--primary); width: 42px; height: 42px; border-radius: 10px; }

        .page-title h1 { margin: 0; font-size: 20px; font-weight: 800; }
        .page-title p { margin: 4px 0 0; color: var(--muted); font-size: 11px; }

        .admin-profile { display: flex; align-items: center; gap: 11px; }
        .admin-avatar {
            width: 40px; height: 40px; border-radius: 12px; background: #eaf2ff; color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 15px;
        }
        .admin-profile strong { display: block; font-size: 12px; }
        .admin-profile span { display: block; font-size: 10px; color: var(--muted); }

        .content { padding: 30px 35px; max-width: 720px; }

        .section-card {
            background: white; border: 1px solid var(--border); border-radius: 18px;
            box-shadow: 0 5px 20px rgba(20,40,70,.04); padding: 24px; margin-bottom: 22px;
        }

        .section-card h3 { margin: 0 0 4px; font-size: 14px; font-weight: 800; }
        .section-card > p { margin: 0 0 18px; color: var(--muted); font-size: 11px; }

        .form-label { font-size: 11px; font-weight: 700; }
        .form-control, .form-select { font-size: 12px; }

        .btn-save {
            border: none; background: linear-gradient(135deg, #0d6efd, #0ea5e9); color: white;
            padding: 10px 18px; border-radius: 10px; font-size: 11px; font-weight: 700;
        }

        @media (max-width: 850px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; }
            .menu-toggle { display: block; }
            .topbar { padding: 0 20px; }
            .content { padding: 25px 20px; }
        }

        @media (max-width: 600px) {
            .admin-profile > div { display: none; }
        }

    </style>

</head>

<body>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fa-solid fa-heart-pulse"></i></div>
        <div class="brand-text"><strong>CareSched</strong><span>Admin Portal</span></div>
    </div>

    <div class="menu-label">Main Menu</div>
    <a href="dashboard.php" class="sidebar-link"><i class="fa-solid fa-grid-2"></i> Dashboard</a>
    <a href="appointments.php" class="sidebar-link"><i class="fa-solid fa-calendar-check"></i> Appointments</a>
    <a href="patients.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Patients</a>

    <div class="menu-label">Management</div>
    <a href="services.php" class="sidebar-link"><i class="fa-solid fa-stethoscope"></i> Services</a>
    <a href="schedules.php" class="sidebar-link"><i class="fa-solid fa-calendar-days"></i> Schedules</a>
    <a href="notifications.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> Notifications</a>

    <div class="menu-label">System</div>
    <a href="settings.php" class="sidebar-link active"><i class="fa-solid fa-gear"></i> Settings</a>

    <div class="sidebar-bottom">
        <a href="/caresched/logout.php" class="sidebar-link logout-link" onclick="return confirm('Are you sure you want to logout?');">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>

</aside>

<main class="main">

    <header class="topbar">

        <div class="d-flex align-items-center gap-3">
            <button class="menu-toggle" id="menuToggle" type="button"><i class="fa-solid fa-bars"></i></button>
            <div class="page-title">
                <h1>Settings</h1>
                <p>Manage your administrator account</p>
            </div>
        </div>

        <div class="admin-profile">
            <div class="admin-avatar"><i class="fa-solid fa-user-shield"></i></div>
            <div><strong>Administrator</strong><span>RHU Arakan</span></div>
        </div>

    </header>

    <section class="content">

        <?php if ($success): ?>
            <div class="alert alert-success py-2" style="font-size:11px; border-radius:10px;"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2" style="font-size:11px; border-radius:10px;"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- PROFILE -->

        <div class="section-card">

            <h3>Profile Information</h3>
            <p>Update your account's username and email address.</p>

            <form method="post">

                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" value="<?= e($admin['username'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?= e($admin['email'] ?? '') ?>" required>
                </div>

                <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>

            </form>

        </div>

        <!-- PASSWORD -->

        <div class="section-card">

            <h3>Change Password</h3>
            <p>Choose a strong password you don't use elsewhere.</p>

            <form method="post">

                <input type="hidden" name="action" value="update_password">

                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="new_password" minlength="8" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                </div>

                <button type="submit" class="btn-save"><i class="fa-solid fa-key me-1"></i> Update Password</button>

            </form>

        </div>

    </section>

</main>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () { sidebar.classList.toggle('show'); });
}
document.addEventListener('click', function (event) {
    if (window.innerWidth <= 850 && sidebar.classList.contains('show') && !sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('show');
    }
});
</script>

</body>
</html>