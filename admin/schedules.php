<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

/*
|--------------------------------------------------------------------------
| NOTE ON SCHEMA
|--------------------------------------------------------------------------
| This page assumes a `schedules` table with columns:
|   id, service_id, day_of_week (Mon..Sun), start_time, end_time,
|   slot_capacity, is_active
| Adjust column names below to match your actual table if different.
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {

        $id = (int) ($_POST['id'] ?? 0);
        $service_id = (int) ($_POST['service_id'] ?? 0);
        $day_of_week = $_POST['day_of_week'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $slot_capacity = (int) ($_POST['slot_capacity'] ?? 1);

        if ($service_id <= 0 || $day_of_week === '' || $start_time === '' || $end_time === '') {
            $error = 'Please fill in all required fields.';
        } else {

            if ($id > 0) {

                $update = $pdo->prepare("
                    UPDATE schedules
                    SET service_id = :service_id, day_of_week = :day, start_time = :start, end_time = :end, slot_capacity = :capacity
                    WHERE id = :id
                ");

                $update->execute([
                    'service_id' => $service_id,
                    'day' => $day_of_week,
                    'start' => $start_time,
                    'end' => $end_time,
                    'capacity' => $slot_capacity,
                    'id' => $id
                ]);

            } else {

                $insert = $pdo->prepare("
                    INSERT INTO schedules (service_id, day_of_week, start_time, end_time, slot_capacity, is_active)
                    VALUES (:service_id, :day, :start, :end, :capacity, 1)
                ");

                $insert->execute([
                    'service_id' => $service_id,
                    'day' => $day_of_week,
                    'start' => $start_time,
                    'end' => $end_time,
                    'capacity' => $slot_capacity
                ]);
            }

            header('Location: schedules.php');
            exit;
        }

    } elseif ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $delete = $pdo->prepare("DELETE FROM schedules WHERE id = :id");
            $delete->execute(['id' => $id]);
        }

        header('Location: schedules.php');
        exit;

    } elseif ($action === 'toggle') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $toggle = $pdo->prepare("UPDATE schedules SET is_active = NOT is_active WHERE id = :id");
            $toggle->execute(['id' => $id]);
        }

        header('Location: schedules.php');
        exit;
    }
}

$services = $pdo->query("SELECT id, service_name FROM services ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$day_order = "FIELD(sc.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";

$schedules_stmt = $pdo->query("
    SELECT sc.*, s.service_name
    FROM schedules sc
    INNER JOIN services s ON sc.service_id = s.id
    ORDER BY $day_order, sc.start_time ASC
");

$schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedules | CareSched Admin</title>

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

        .add-btn {
            border: none; background: linear-gradient(135deg, #0d6efd, #0ea5e9); color: white;
            padding: 10px 16px; border-radius: 10px; font-size: 11px; font-weight: 700;
        }

        .notice-banner {
            background: #fff8e6; border: 1px solid #ffe8a3; color: #8a6100;
            padding: 12px 16px; border-radius: 12px; font-size: 10.5px; margin-bottom: 18px;
        }

        .section-card {
            background: white; border: 1px solid var(--border); border-radius: 18px;
            box-shadow: 0 5px 20px rgba(20,40,70,.04); overflow: hidden;
        }

        .schedule-table { width: 100%; border-collapse: collapse; }

        .schedule-table th {
            padding: 13px 22px; color: #8a96a8; font-size: 10px; text-transform: uppercase;
            letter-spacing: .5px; font-weight: 700; background: #fafbfd;
            border-bottom: 1px solid var(--border); text-align: left;
        }

        .schedule-table td { padding: 14px 22px; border-bottom: 1px solid #f0f2f5; font-size: 11px; vertical-align: middle; }

        .day-badge {
            display: inline-flex; padding: 5px 10px; border-radius: 20px;
            background: #eef4ff; color: var(--primary); font-size: 9px; font-weight: 700;
        }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 9px; font-weight: 700; }
        .status-active { background: #e9f8f0; color: #147346; }
        .status-inactive { background: #eef1f5; color: #667085; }

        .icon-btn {
            width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: 10px; border: none;
        }

        .icon-btn.edit { background: #eef4ff; color: var(--primary); }
        .icon-btn.edit:hover { background: var(--primary); color: white; }
        .icon-btn.toggle { background: #fff4df; color: #b76b00; }
        .icon-btn.toggle:hover { background: #b76b00; color: white; }
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
            .schedule-table { min-width: 650px; }
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
    <a href="patients.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Patients</a>

    <div class="menu-label">Management</div>
    <a href="services.php" class="sidebar-link"><i class="fa-solid fa-stethoscope"></i> Services</a>
    <a href="schedules.php" class="sidebar-link active"><i class="fa-solid fa-calendar-days"></i> Schedules</a>
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
                <h1>Schedules</h1>
                <p>Set weekly availability per service</p>
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
            This page expects a <code>schedules</code> table (service_id, day_of_week, start_time, end_time, slot_capacity, is_active). Adjust the query in the file if your columns differ.
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">

            <span style="color:#7b8798; font-size:11px;"><?= count($schedules) ?> schedule slot<?= count($schedules) === 1 ? '' : 's' ?> configured</span>

            <button class="add-btn" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="openCreateModal()" <?= empty($services) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-plus me-1"></i> Add Schedule
            </button>

        </div>

        <div class="section-card">

            <?php if (!empty($schedules)): ?>

                <div class="table-wrapper">

                    <table class="schedule-table">

                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($schedules as $schedule): ?>

                            <tr>

                                <td><strong><?= e($schedule['service_name']) ?></strong></td>

                                <td><span class="day-badge"><?= e($schedule['day_of_week']) ?></span></td>

                                <td>
                                    <?= e(date('h:i A', strtotime($schedule['start_time']))) ?>
                                    &ndash;
                                    <?= e(date('h:i A', strtotime($schedule['end_time']))) ?>
                                </td>

                                <td><?= e($schedule['slot_capacity']) ?> patient(s)</td>

                                <td>
                                    <span class="status-badge <?= $schedule['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $schedule['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="d-flex gap-2">

                                        <button
                                            class="icon-btn edit"
                                            title="Edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#scheduleModal"
                                            onclick='openEditModal(<?= json_encode($schedule) ?>)'
                                        >
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
                                            <button type="submit" class="icon-btn toggle" title="Toggle"><i class="fa-solid fa-power-off"></i></button>
                                        </form>

                                        <form method="post" onsubmit="return confirm('Delete this schedule slot?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $schedule['id'] ?>">
                                            <button type="submit" class="icon-btn delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-calendar-days"></i>
                    <p>No schedules configured yet.</p>
                </div>

            <?php endif; ?>

        </div>

    </section>

</main>


<!-- SCHEDULE MODAL -->

<div class="modal fade" id="scheduleModal" tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content" style="border-radius:16px; border:none;">

            <form method="post">

                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="schedule_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel" style="font-weight:800; font-size:15px;">Add Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2" style="font-size:11px;"><?= e($error) ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:11px; font-weight:700;">Service</label>
                        <select class="form-select" name="service_id" id="schedule_service" required>
                            <option value="">Select service</option>
                            <?php foreach ($services as $svc): ?>
                                <option value="<?= (int) $svc['id'] ?>"><?= e($svc['service_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:11px; font-weight:700;">Day of Week</label>
                        <select class="form-select" name="day_of_week" id="schedule_day" required>
                            <option value="">Select day</option>
                            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                                <option value="<?= e($day) ?>"><?= e($day) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">

                        <div class="col-6">
                            <label class="form-label" style="font-size:11px; font-weight:700;">Start Time</label>
                            <input type="time" class="form-control" name="start_time" id="schedule_start" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label" style="font-size:11px; font-weight:700;">End Time</label>
                            <input type="time" class="form-control" name="end_time" id="schedule_end" required>
                        </div>

                    </div>

                    <div class="mb-1">
                        <label class="form-label" style="font-size:11px; font-weight:700;">Slot Capacity</label>
                        <input type="number" class="form-control" name="slot_capacity" id="schedule_capacity" value="1" min="1">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Schedule</button>
                </div>

            </form>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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

function openCreateModal() {
    document.getElementById('scheduleModalLabel').textContent = 'Add Schedule';
    document.getElementById('schedule_id').value = '';
    document.getElementById('schedule_service').value = '';
    document.getElementById('schedule_day').value = '';
    document.getElementById('schedule_start').value = '';
    document.getElementById('schedule_end').value = '';
    document.getElementById('schedule_capacity').value = 1;
}

function openEditModal(schedule) {
    document.getElementById('scheduleModalLabel').textContent = 'Edit Schedule';
    document.getElementById('schedule_id').value = schedule.id;
    document.getElementById('schedule_service').value = schedule.service_id;
    document.getElementById('schedule_day').value = schedule.day_of_week;
    document.getElementById('schedule_start').value = schedule.start_time;
    document.getElementById('schedule_end').value = schedule.end_time;
    document.getElementById('schedule_capacity').value = schedule.slot_capacity;
}

</script>

</body>
</html>