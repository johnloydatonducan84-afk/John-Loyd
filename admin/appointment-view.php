<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: appointments.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE STATUS UPDATE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {

    $new_status = $_POST['status'] ?? '';
    $allowed_statuses = ['Approved', 'Rejected', 'Completed', 'Cancelled', 'Pending'];

    if (in_array($new_status, $allowed_statuses, true)) {

        $update = $pdo->prepare("
            UPDATE appointments
            SET status = :status
            WHERE id = :id
        ");

        $update->execute([
            'status' => $new_status,
            'id' => $id
        ]);
    }

    header('Location: appointment-view.php?id=' . $id);
    exit;
}


/*
|--------------------------------------------------------------------------
| FETCH APPOINTMENT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.*,
        p.first_name,
        p.last_name,
        p.sex,
        p.age,
        p.contact_number,
        s.service_name,
        s.description AS service_description
    FROM appointments a
    INNER JOIN patients p ON a.patient_id = p.id
    INNER JOIN services s ON a.service_id = s.id
    WHERE a.id = :id
    LIMIT 1
");

$stmt->execute(['id' => $id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die('Appointment not found.');
}

function statusClass($status)
{
    switch (strtolower($status)) {
        case 'approved':
            return 'status-approved';
        case 'pending':
            return 'status-pending';
        case 'completed':
            return 'status-completed';
        case 'rejected':
        case 'cancelled':
            return 'status-rejected';
        default:
            return 'status-completed';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details | CareSched Admin</title>

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

        .back-link {
            display: inline-flex; align-items: center; gap: 8px; color: var(--muted);
            font-size: 11px; font-weight: 700; margin-bottom: 18px;
        }
        .back-link:hover { color: var(--primary); }

        .section-card {
            background: white; border: 1px solid var(--border); border-radius: 18px;
            box-shadow: 0 5px 20px rgba(20,40,70,.04); padding: 26px;
        }

        .detail-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 14px; padding-bottom: 20px; border-bottom: 1px solid var(--border); margin-bottom: 20px;
        }

        .detail-header h2 { margin: 0 0 5px; font-size: 18px; font-weight: 800; }
        .detail-header p { margin: 0; color: var(--muted); font-size: 11px; }

        .status {
            display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 20px;
            font-size: 10px; font-weight: 700;
        }

        .status-pending { background: #fff4df; color: #b76b00; }
        .status-approved { background: #e9f8f0; color: #147346; }
        .status-rejected { background: #fff0f0; color: #b42318; }
        .status-completed { background: #eef1f5; color: #667085; }

        .detail-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px 30px; margin-bottom: 24px;
        }

        .detail-row span { display: block; color: var(--muted); font-size: 9px; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; margin-bottom: 4px; }
        .detail-row strong { display: block; font-size: 12px; color: var(--text); }

        .action-row { display: flex; gap: 10px; flex-wrap: wrap; padding-top: 20px; border-top: 1px solid var(--border); }

        .btn-action {
            border: none; padding: 11px 18px; border-radius: 10px; font-size: 11px; font-weight: 700;
        }

        .btn-approve { background: #147346; color: white; }
        .btn-reject { background: #b42318; color: white; }
        .btn-complete { background: var(--primary); color: white; }
        .btn-cancel { background: #eef1f5; color: #475569; }

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
            .detail-grid { grid-template-columns: 1fr; }
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
    <a href="appointments.php" class="sidebar-link active"><i class="fa-solid fa-calendar-check"></i> Appointments</a>
    <a href="patients.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Patients</a>

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
                <h1>Appointment Details</h1>
                <p>Review and manage this booking</p>
            </div>
        </div>

        <div class="admin-profile">
            <div class="admin-avatar"><i class="fa-solid fa-user-shield"></i></div>
            <div><strong>Administrator</strong><span>RHU Arakan</span></div>
        </div>

    </header>

    <section class="content">

        <a href="appointments.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Appointments
        </a>

        <div class="section-card">

            <div class="detail-header">

                <div>
                    <h2><?= e($appointment['first_name'] . ' ' . $appointment['last_name']) ?></h2>
                    <p><?= e($appointment['service_name']) ?></p>
                </div>

                <span class="status <?= statusClass($appointment['status']) ?>">
                    <?= e($appointment['status']) ?>
                </span>

            </div>

            <div class="detail-grid">

                <div class="detail-row">
                    <span>Appointment Date</span>
                    <strong><?= e(date('F d, Y', strtotime($appointment['appointment_date']))) ?></strong>
                </div>

                <div class="detail-row">
                    <span>Appointment Time</span>
                    <strong><?= e(date('h:i A', strtotime($appointment['appointment_time']))) ?></strong>
                </div>

                <div class="detail-row">
                    <span>Sex</span>
                    <strong><?= e($appointment['sex'] ?? 'Not provided') ?></strong>
                </div>

                <div class="detail-row">
                    <span>Age</span>
                    <strong><?= e($appointment['age'] ?? 'Not provided') ?></strong>
                </div>

                <div class="detail-row">
                    <span>Contact Number</span>
                    <strong><?= e($appointment['contact_number'] ?? 'Not provided') ?></strong>
                </div>

                <div class="detail-row">
                    <span>Booked On</span>
                    <strong><?= e(date('M d, Y h:i A', strtotime($appointment['created_at']))) ?></strong>
                </div>

                <div class="detail-row" style="grid-column: 1 / -1;">
                    <span>Reason for Visit</span>
                    <strong><?= e($appointment['reason'] ?? 'Not provided') ?></strong>
                </div>

            </div>

            <div class="action-row">

                <?php if (strtolower($appointment['status']) === 'pending'): ?>

                    <form method="post">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="Approved">
                        <button type="submit" class="btn-action btn-approve"><i class="fa-solid fa-check me-1"></i> Approve</button>
                    </form>

                    <form method="post" onsubmit="return confirm('Reject this appointment?');">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="Rejected">
                        <button type="submit" class="btn-action btn-reject"><i class="fa-solid fa-xmark me-1"></i> Reject</button>
                    </form>

                <?php endif; ?>

                <?php if (in_array(strtolower($appointment['status']), ['approved', 'confirmed'], true)): ?>

                    <form method="post">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="Completed">
                        <button type="submit" class="btn-action btn-complete"><i class="fa-solid fa-circle-check me-1"></i> Mark Completed</button>
                    </form>

                <?php endif; ?>

                <?php if (!in_array(strtolower($appointment['status']), ['cancelled', 'completed', 'rejected'], true)): ?>

                    <form method="post" onsubmit="return confirm('Cancel this appointment?');">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="status" value="Cancelled">
                        <button type="submit" class="btn-action btn-cancel"><i class="fa-solid fa-ban me-1"></i> Cancel</button>
                    </form>

                <?php endif; ?>

            </div>

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