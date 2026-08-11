<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

/*
|--------------------------------------------------------------------------
| NOTE ON SCHEMA
|--------------------------------------------------------------------------
| This page assumes a `notifications` table with columns:
|   id, title, message, is_read, created_at, appointment_id (nullable)
| Adjust column names below to match your actual table if different.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
            $update->execute(['id' => $id]);
        }

        header('Location: notifications.php');
        exit;

    } elseif ($action === 'mark_all_read') {

        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");

        header('Location: notifications.php');
        exit;

    } elseif ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $delete = $pdo->prepare("DELETE FROM notifications WHERE id = :id");
            $delete->execute(['id' => $id]);
        }

        header('Location: notifications.php');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'all';

$where = '';
if ($filter === 'unread') {
    $where = 'WHERE is_read = 0';
}

$notifications_stmt = $pdo->query("
    SELECT *
    FROM notifications
    $where
    ORDER BY created_at DESC
    LIMIT 50
");

$notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_count = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | CareSched Admin</title>

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

        .content { padding: 30px 35px; }

        .notice-banner {
            background: #fff8e6; border: 1px solid #ffe8a3; color: #8a6100;
            padding: 12px 16px; border-radius: 12px; font-size: 10.5px; margin-bottom: 18px;
        }

        .filter-tabs { display: flex; gap: 8px; }

        .filter-tab {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 13px;
            border: 1px solid var(--border); border-radius: 30px; color: #64748b;
            background: white; font-size: 10px; font-weight: 700;
        }

        .filter-tab.active { color: white; border-color: transparent; background: linear-gradient(135deg, #0d6efd, #0ea5e9); }

        .mark-btn {
            border: 1px solid var(--border); background: white; color: var(--text);
            padding: 8px 13px; border-radius: 10px; font-size: 10px; font-weight: 700;
        }
        .mark-btn:hover { border-color: #bcd4fa; color: var(--primary); }

        .section-card {
            background: white; border: 1px solid var(--border); border-radius: 18px;
            box-shadow: 0 5px 20px rgba(20,40,70,.04); overflow: hidden;
        }

        .notif-item {
            display: flex; gap: 14px; padding: 16px 22px; border-bottom: 1px solid #f0f2f5;
        }

        .notif-item.unread { background: #f7faff; }
        .notif-item:last-child { border-bottom: none; }

        .notif-icon {
            width: 38px; height: 38px; border-radius: 11px; background: #eaf2ff; color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0;
        }

        .notif-body { flex: 1; min-width: 0; }
        .notif-title { font-size: 11px; font-weight: 700; margin-bottom: 3px; }
        .notif-message { font-size: 10px; color: var(--muted); line-height: 1.6; }
        .notif-time { font-size: 9px; color: #b5bcc7; margin-top: 6px; }

        .notif-actions { display: flex; gap: 6px; align-items: flex-start; }

        .icon-btn {
            width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: 10px; border: none;
        }

        .icon-btn.read { background: #e9f8f0; color: #147346; }
        .icon-btn.read:hover { background: #147346; color: white; }
        .icon-btn.delete { background: #fff0f0; color: #b42318; }
        .icon-btn.delete:hover { background: #b42318; color: white; }

        .empty-state { padding: 45px 20px; text-align: center; color: var(--muted); }
        .empty-state i { font-size: 30px; margin-bottom: 12px; opacity: .5; }

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
    <a href="notifications.php" class="sidebar-link active"><i class="fa-solid fa-bell"></i> Notifications</a>

    <div class="menu-label">System</div>
    <a href="settings.php" class="sidebar-link"><i class="fa-solid fa-gear"></i> Settings</a>

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
                <h1>Notifications</h1>
                <p><?= e($unread_count) ?> unread notification<?= $unread_count === 1 ? '' : 's' ?></p>
            </div>
        </div>

        <div class="admin-profile">
            <div class="admin-avatar"><i class="fa-solid fa-user-shield"></i></div>
            <div><strong>Administrator</strong><span>RHU Arakan</span></div>
        </div>

    </header>

    <section class="content">

        <div class="notice-banner">
            <i class="fa-solid fa-circle-info me-1"></i>
            This page expects a <code>notifications</code> table (title, message, is_read, created_at). Adjust the query in the file if your columns differ.
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                <a href="?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="mark-btn"><i class="fa-solid fa-check-double me-1"></i> Mark all as read</button>
            </form>

        </div>

        <div class="section-card">

            <?php if (!empty($notifications)): ?>

                <?php foreach ($notifications as $notif): ?>

                    <div class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>">

                        <div class="notif-icon"><i class="fa-solid fa-bell"></i></div>

                        <div class="notif-body">
                            <div class="notif-title"><?= e($notif['title'] ?? 'Notification') ?></div>
                            <div class="notif-message"><?= e($notif['message'] ?? '') ?></div>
                            <div class="notif-time"><?= e(date('M d, Y h:i A', strtotime($notif['created_at']))) ?></div>
                        </div>

                        <div class="notif-actions">

                            <?php if (!$notif['is_read']): ?>

                                <form method="post">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= (int) $notif['id'] ?>">
                                    <button type="submit" class="icon-btn read" title="Mark as read"><i class="fa-solid fa-check"></i></button>
                                </form>

                            <?php endif; ?>

                            <form method="post" onsubmit="return confirm('Delete this notification?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $notif['id'] ?>">
                                <button type="submit" class="icon-btn delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-bell-slash"></i>
                    <p>No notifications to show.</p>
                </div>

            <?php endif; ?>

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