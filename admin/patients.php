<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$search = trim($_GET['q'] ?? '');

$per_page = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$where = '';
$params = [];

if ($search !== '') {
    $where = "WHERE p.first_name LIKE :search OR p.last_name LIKE :search OR p.contact_number LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM patients p $where");
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_rows / $per_page));

$list_stmt = $pdo->prepare("
    SELECT
        p.*,
        (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS total_appointments,
        (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.id) AS last_visit
    FROM patients p
    $where
    ORDER BY p.first_name ASC
    LIMIT $per_page OFFSET $offset
");
$list_stmt->execute($params);
$patients = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_patients = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients | CareSched Admin</title>

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

        .section-card {
            background: white; border: 1px solid var(--border); border-radius: 18px;
            box-shadow: 0 5px 20px rgba(20,40,70,.04); overflow: hidden;
        }

        .section-header {
            padding: 20px 22px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }

        .section-header h3 { margin: 0; font-size: 15px; font-weight: 800; }
        .section-header span { color: var(--muted); font-size: 11px; }

        .search-box { display: flex; align-items: center; gap: 8px; }

        .search-box input {
            padding: 9px 13px; border: 1px solid var(--border); border-radius: 10px;
            font-size: 11px; min-width: 220px;
        }

        .search-box button {
            border: none; background: var(--primary); color: white; padding: 9px 13px; border-radius: 10px; font-size: 11px;
        }

        .patient-table { width: 100%; border-collapse: collapse; }

        .patient-table th {
            padding: 13px 22px; color: #8a96a8; font-size: 10px; text-transform: uppercase;
            letter-spacing: .5px; font-weight: 700; background: #fafbfd;
            border-bottom: 1px solid var(--border); text-align: left;
        }

        .patient-table td { padding: 14px 22px; border-bottom: 1px solid #f0f2f5; font-size: 11px; vertical-align: middle; }

        .patient-cell { display: flex; align-items: center; gap: 12px; }

        .patient-avatar {
            width: 36px; height: 36px; border-radius: 10px; background: #eaf2ff; color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0;
        }

        .patient-name { font-weight: 700; }
        .patient-sub { color: var(--muted); font-size: 9px; margin-top: 2px; }

        .badge-count {
            display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 20px;
            background: #eef4ff; color: var(--primary); font-size: 10px; font-weight: 700;
        }

        .view-btn {
            width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px; background: #eef4ff; color: var(--primary); font-size: 11px;
        }
        .view-btn:hover { background: var(--primary); color: white; }

        .empty-state { padding: 45px 20px; text-align: center; color: var(--muted); }
        .empty-state i { font-size: 30px; margin-bottom: 12px; opacity: .5; }
        .empty-state p { margin: 0; font-size: 12px; }

        .pagination-bar {
            padding: 16px 22px; display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }

        .pagination-bar span { color: var(--muted); font-size: 10px; }

        .page-link {
            display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px;
            border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 10px; font-weight: 700;
        }

        .page-link.disabled { opacity: .4; pointer-events: none; }

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
            .patient-table { min-width: 650px; }
            .table-wrapper { overflow-x: auto; }
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
    <a href="patients.php" class="sidebar-link active"><i class="fa-solid fa-users"></i> Patients</a>

    <div class="menu-label">Management</div>
    <a href="services.php" class="sidebar-link"><i class="fa-solid fa-stethoscope"></i> Services</a>
    <a href="schedules.php" class="sidebar-link"><i class="fa-solid fa-calendar-days"></i> Schedules</a>
    <a href="notifications.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> Notifications</a>

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
                <h1>Patients</h1>
                <p><?= e($total_patients) ?> registered patient<?= $total_patients == 1 ? '' : 's' ?></p>
            </div>
        </div>

        <div class="admin-profile">
            <div class="admin-avatar"><i class="fa-solid fa-user-shield"></i></div>
            <div><strong>Administrator</strong><span>RHU Arakan</span></div>
        </div>

    </header>

    <section class="content">

        <div class="section-card">

            <div class="section-header">

                <div>
                    <h3>All Patients</h3>
                    <span><?= e($total_rows) ?> matching record<?= $total_rows === 1 ? '' : 's' ?></span>
                </div>

                <form class="search-box" method="get">
                    <input type="text" name="q" placeholder="Search name or contact..." value="<?= e($search) ?>">
                    <button type="submit"><i class="fa-solid fa-search"></i></button>
                </form>

            </div>

            <?php if (!empty($patients)): ?>

                <div class="table-wrapper">

                    <table class="patient-table">

                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Sex</th>
                                <th>Age</th>
                                <th>Contact</th>
                                <th>Appointments</th>
                                <th>Last Visit</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($patients as $patient): ?>

                            <tr>

                                <td>
                                    <div class="patient-cell">

                                        <div class="patient-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>

                                        <div class="patient-name">
                                            <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?>
                                        </div>

                                    </div>
                                </td>

                                <td><?= e($patient['sex'] ?? 'N/A') ?></td>

                                <td><?= e($patient['age'] ?? 'N/A') ?></td>

                                <td><?= e($patient['contact_number'] ?? 'N/A') ?></td>

                                <td><span class="badge-count"><?= e($patient['total_appointments']) ?></span></td>

                                <td>
                                    <?= $patient['last_visit']
                                        ? e(date('M d, Y', strtotime($patient['last_visit'])))
                                        : '<span style="color:#b5bcc7;">Never</span>' ?>
                                </td>

                                <td>
                                    <a href="appointments.php?q=<?= urlencode($patient['first_name']) ?>" class="view-btn" title="View appointments">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="pagination-bar">

                    <span>Page <?= e($page) ?> of <?= e($total_pages) ?></span>

                    <div class="d-flex gap-2">

                        <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="?page=<?= $page - 1 ?>&q=<?= e($search) ?>">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>

                        <a class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>" href="?page=<?= $page + 1 ?>&q=<?= e($search) ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>

                    </div>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-user-slash"></i>
                    <p>No patients found.</p>
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