<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$error = '';

/*
|--------------------------------------------------------------------------
| HANDLE CREATE / UPDATE / DELETE
| Assumes: services(id, service_name, description, duration_minutes, is_active)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {

        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int) ($_POST['duration_minutes'] ?? 30);

        if ($name === '') {
            $error = 'Service name is required.';
        } else {

            if ($id > 0) {

                $update = $pdo->prepare("
                    UPDATE services
                    SET service_name = :name, description = :description, duration_minutes = :duration
                    WHERE id = :id
                ");

                $update->execute([
                    'name' => $name,
                    'description' => $description,
                    'duration' => $duration,
                    'id' => $id
                ]);

            } else {

                $insert = $pdo->prepare("
                    INSERT INTO services (service_name, description, duration_minutes, is_active)
                    VALUES (:name, :description, :duration, 1)
                ");

                $insert->execute([
                    'name' => $name,
                    'description' => $description,
                    'duration' => $duration
                ]);
            }

            header('Location: services.php');
            exit;
        }

    } elseif ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {

            $delete = $pdo->prepare("DELETE FROM services WHERE id = :id");
            $delete->execute(['id' => $id]);
        }

        header('Location: services.php');
        exit;

    } elseif ($action === 'toggle') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {

            $toggle = $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = :id");
            $toggle->execute(['id' => $id]);
        }

        header('Location: services.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FETCH SERVICES
|--------------------------------------------------------------------------
*/

$services_stmt = $pdo->query("
    SELECT
        s.*,
        (SELECT COUNT(*) FROM appointments a WHERE a.service_id = s.id) AS total_bookings
    FROM services s
    ORDER BY s.service_name ASC
");

$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services | CareSched Admin</title>

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

        .services-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;
        }

        .service-card {
            background: white; border: 1px solid var(--border); border-radius: 16px; padding: 20px;
            box-shadow: 0 5px 20px rgba(20,40,70,.04);
        }

        .service-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }

        .service-icon {
            width: 42px; height: 42px; border-radius: 12px; background: #eaf2ff; color: var(--primary);
            display: flex; align-items: center; justify-content: center; font-size: 16px;
        }

        .status-badge {
            padding: 4px 10px; border-radius: 20px; font-size: 9px; font-weight: 700;
        }

        .status-active { background: #e9f8f0; color: #147346; }
        .status-inactive { background: #eef1f5; color: #667085; }

        .service-name { font-size: 13px; font-weight: 800; margin-bottom: 5px; }
        .service-desc { color: var(--muted); font-size: 10px; line-height: 1.6; margin-bottom: 14px; min-height: 32px; }

        .service-meta {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 12px; border-top: 1px solid var(--border); font-size: 10px; color: var(--muted);
        }

        .service-actions { display: flex; gap: 6px; }

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

        .empty-state { padding: 60px 20px; text-align: center; color: var(--muted); grid-column: 1 / -1; }
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
    <a href="services.php" class="sidebar-link active"><i class="fa-solid fa-stethoscope"></i> Services</a>
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
                <h1>Services</h1>
                <p>Manage available healthcare services</p>
            </div>
        </div>

        <div class="admin-profile">
            <div class="admin-avatar"><i class="fa-solid fa-user-shield"></i></div>
            <div><strong>Administrator</strong><span>RHU Arakan</span></div>
        </div>

    </header>

    <section class="content">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <span style="color:#7b8798; font-size:11px;"><?= count($services) ?> service<?= count($services) === 1 ? '' : 's' ?> configured</span>

            <button class="add-btn" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="openCreateModal()">
                <i class="fa-solid fa-plus me-1"></i> Add Service
            </button>

        </div>

        <div class="services-grid">

            <?php if (!empty($services)): ?>

                <?php foreach ($services as $service): ?>

                    <div class="service-card">

                        <div class="service-top">

                            <div class="service-icon"><i class="fa-solid fa-stethoscope"></i></div>

                            <span class="status-badge <?= $service['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $service['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>

                        </div>

                        <div class="service-name"><?= e($service['service_name']) ?></div>

                        <div class="service-desc"><?= e($service['description'] ?: 'No description provided.') ?></div>

                        <div class="service-meta">

                            <span><i class="fa-regular fa-clock me-1"></i> <?= e($service['duration_minutes']) ?> min</span>

                            <div class="service-actions">

                                <button
                                    class="icon-btn edit"
                                    title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#serviceModal"
                                    onclick='openEditModal(<?= json_encode($service) ?>)'
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>

                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                    <button type="submit" class="icon-btn toggle" title="Toggle status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </button>
                                </form>

                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this service? This cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                                    <button type="submit" class="icon-btn delete" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-state">
                    <i class="fa-solid fa-stethoscope"></i>
                    <p>No services configured yet. Add your first service to get started.</p>
                </div>

            <?php endif; ?>

        </div>

    </section>

</main>


<!-- SERVICE MODAL -->

<div class="modal fade" id="serviceModal" tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content" style="border-radius:16px; border:none;">

            <form method="post">

                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="service_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="serviceModalLabel" style="font-weight:800; font-size:15px;">Add Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2" style="font-size:11px;"><?= e($error) ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:11px; font-weight:700;">Service Name</label>
                        <input type="text" class="form-control" name="service_name" id="service_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:11px; font-weight:700;">Description</label>
                        <textarea class="form-control" name="description" id="service_description" rows="3"></textarea>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" style="font-size:11px; font-weight:700;">Duration (minutes)</label>
                        <input type="number" class="form-control" name="duration_minutes" id="service_duration" value="30" min="5" step="5">
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Service</button>
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
    document.getElementById('serviceModalLabel').textContent = 'Add Service';
    document.getElementById('service_id').value = '';
    document.getElementById('service_name').value = '';
    document.getElementById('service_description').value = '';
    document.getElementById('service_duration').value = 30;
}

function openEditModal(service) {
    document.getElementById('serviceModalLabel').textContent = 'Edit Service';
    document.getElementById('service_id').value = service.id;
    document.getElementById('service_name').value = service.service_name;
    document.getElementById('service_description').value = service.description || '';
    document.getElementById('service_duration').value = service.duration_minutes || 30;
}

</script>

</body>
</html>