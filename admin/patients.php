<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$error = '';

/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS (CREATE / UPDATE / DELETE)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        if ($action === 'create' || $action === 'update') {

            $id = (int) ($_POST['id'] ?? 0);

            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $birth_date = $_POST['birth_date'] ?? null;
            $age = $_POST['age'] ?? null;
            $sex = $_POST['sex'] ?? null;
            $address = trim($_POST['address'] ?? '');
            $contact_number = trim($_POST['contact_number'] ?? '');

            if (!$first_name || !$last_name || !$email) {
                throw new Exception('Please fill in all required fields.');
            }

            if ($action === 'create') {

                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                if (!$username || !$password) {
                    throw new Exception('Please fill in all required fields.');
                }

                if (strlen($username) < 4) {
                    throw new Exception('Username must be at least 4 characters.');
                }

                if (strlen($password) < 8) {
                    throw new Exception('Password must be at least 8 characters.');
                }

                $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1");
                $dupStmt->execute([':email' => $email, ':username' => $username]);

                if ($dupStmt->fetch()) {
                    throw new Exception('Email or username already exists.');
                }

                $pdo->beginTransaction();

                $userStmt = $pdo->prepare("
                    INSERT INTO users (role, username, email, password, status, created_at)
                    VALUES ('patient', :username, :email, :password, 'active', NOW())
                ");

                $userStmt->execute([
                    ':username' => $username,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $user_id = $pdo->lastInsertId();

                $insertStmt = $pdo->prepare("
                    INSERT INTO patients
                        (user_id, first_name, middle_name, last_name, birth_date, age, sex, address, contact_number, created_at)
                    VALUES
                        (:user_id, :first_name, :middle_name, :last_name, :birth_date, :age, :sex, :address, :contact_number, NOW())
                ");

                $insertStmt->execute([
                    ':user_id' => $user_id,
                    ':first_name' => $first_name,
                    ':middle_name' => $middle_name ?: null,
                    ':last_name' => $last_name,
                    ':birth_date' => $birth_date ?: null,
                    ':age' => $age ?: null,
                    ':sex' => $sex ?: null,
                    ':address' => $address ?: null,
                    ':contact_number' => $contact_number ?: null,
                ]);

                $pdo->commit();

            } else {

                if ($id <= 0) {
                    throw new Exception('Invalid patient record.');
                }

                $patientStmt = $pdo->prepare("SELECT user_id FROM patients WHERE id = :id LIMIT 1");
                $patientStmt->execute([':id' => $id]);
                $patientRow = $patientStmt->fetch(PDO::FETCH_ASSOC);

                if (!$patientRow) {
                    throw new Exception('Patient record not found.');
                }

                $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id LIMIT 1");
                $dupStmt->execute([':email' => $email, ':user_id' => $patientRow['user_id']]);

                if ($dupStmt->fetch()) {
                    throw new Exception('That email is already used by another account.');
                }

                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare("
                    UPDATE patients SET
                        first_name = :first_name,
                        middle_name = :middle_name,
                        last_name = :last_name,
                        birth_date = :birth_date,
                        age = :age,
                        sex = :sex,
                        address = :address,
                        contact_number = :contact_number,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $updateStmt->execute([
                    ':first_name' => $first_name,
                    ':middle_name' => $middle_name ?: null,
                    ':last_name' => $last_name,
                    ':birth_date' => $birth_date ?: null,
                    ':age' => $age ?: null,
                    ':sex' => $sex ?: null,
                    ':address' => $address ?: null,
                    ':contact_number' => $contact_number ?: null,
                    ':id' => $id,
                ]);

                $emailStmt = $pdo->prepare("UPDATE users SET email = :email WHERE id = :user_id");
                $emailStmt->execute([':email' => $email, ':user_id' => $patientRow['user_id']]);

                $pdo->commit();
            }

            header('Location: patients.php');
            exit;

        } elseif ($action === 'delete') {

            $id = (int) ($_POST['id'] ?? 0);

            if ($id > 0) {

                $patientStmt = $pdo->prepare("SELECT user_id FROM patients WHERE id = :id LIMIT 1");
                $patientStmt->execute([':id' => $id]);
                $patientRow = $patientStmt->fetch(PDO::FETCH_ASSOC);

                if ($patientRow) {
                    // Deleting the linked user cascades to patients + appointments.
                    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
                    $deleteStmt->execute([':user_id' => $patientRow['user_id']]);
                }
            }

            header('Location: patients.php');
            exit;
        }

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = 'Database error: ' . $e->getMessage();
    }
}

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
    SELECT p.*,
           u.email,
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS total_appointments,
           (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.id) AS last_visit
    FROM patients p
    INNER JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.first_name ASC, p.last_name ASC
    LIMIT $per_page OFFSET $offset
");
$list_stmt->execute($params);
$patients = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_patients = (int) $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$total_male = (int) $pdo->query("SELECT COUNT(*) FROM patients WHERE LOWER(sex) = 'male'")->fetchColumn();
$total_female = (int) $pdo->query("SELECT COUNT(*) FROM patients WHERE LOWER(sex) = 'female'")->fetchColumn();
$total_appointments = (int) $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

function initials($first, $last) {
    return strtoupper(substr((string)$first, 0, 1) . substr((string)$last, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patients | CareSched Admin</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
* { box-sizing: border-box; }
:root {
    --primary: #0d6efd;
    --primary-dark: #084298;
    --sidebar: #0b1f3a;
    --sidebar-light: #102d50;
    --bg: #f4f7fb;
    --text: #172033;
    --muted: #7b8798;
    --border: #e6ebf2;
    --white: #fff;
    --success: #198754;
    --warning: #f59e0b;
    --purple: #7c3aed;
}
body { margin:0; font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); }
a { text-decoration:none; }

/* SIDEBAR */
.sidebar {
    position:fixed; top:0; left:0; width:260px; height:100vh;
    background:linear-gradient(180deg,var(--sidebar),var(--sidebar-light));
    color:#fff; padding:24px 16px; z-index:1000; overflow-y:auto; transition:.3s ease;
}
.sidebar-brand {
    display:flex; align-items:center; gap:12px; padding:10px 12px 26px;
    margin-bottom:22px; border-bottom:1px solid rgba(255,255,255,.08);
}
.brand-icon {
    width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#0d6efd,#198754); font-size:19px;
}
.brand-text strong { display:block; font-size:18px; font-weight:800; }
.brand-text span { display:block; margin-top:2px; font-size:9px; color:rgba(255,255,255,.55); text-transform:uppercase; letter-spacing:1px; }
.menu-label { padding:0 13px; margin:19px 0 8px; color:rgba(255,255,255,.38); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; }
.sidebar-link {
    display:flex; align-items:center; gap:13px; padding:12px 14px; margin-bottom:4px; border-radius:11px;
    color:rgba(255,255,255,.7); font-size:12px; font-weight:500; transition:.2s ease;
}
.sidebar-link i { width:20px; text-align:center; }
.sidebar-link:hover { color:#fff; background:rgba(13,110,253,.20); transform:translateX(3px); }
.sidebar-link.active { color:#fff; background:rgba(13,110,253,.28); box-shadow:inset 3px 0 0 #0d6efd; }
.sidebar-bottom { margin-top:28px; padding-top:15px; border-top:1px solid rgba(255,255,255,.08); }
.logout-link { color:#ffb4b4; }
.logout-link:hover { color:#fff; background:rgba(220,53,69,.18); }

/* MAIN */
.main { margin-left:260px; min-height:100vh; }
.topbar {
    height:76px; padding:0 35px; display:flex; align-items:center; justify-content:space-between;
    background:#fff; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:500;
}
.menu-toggle { display:none; width:42px; height:42px; border:none; border-radius:10px; background:#eef4ff; color:var(--primary); }
.page-title h1 { margin:0; font-size:21px; font-weight:800; }
.page-title p { margin:4px 0 0; color:var(--muted); font-size:10px; }
.admin-profile { display:flex; align-items:center; gap:11px; }
.admin-avatar { width:41px; height:41px; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#eaf2ff; color:var(--primary); }
.admin-profile strong { display:block; font-size:12px; }
.admin-profile span { display:block; margin-top:2px; color:var(--muted); font-size:9px; }
.content { padding:30px 35px; }

/* HERO */
.page-hero {
    display:flex; justify-content:space-between; align-items:center; gap:20px;
    margin-bottom:22px; padding:24px 26px; border-radius:20px;
    background:linear-gradient(135deg,#0d6efd,#0b4fa8);
    color:#fff; box-shadow:0 12px 30px rgba(13,110,253,.16);
}
.page-hero h2 { margin:0; font-size:20px; font-weight:800; }
.page-hero p { margin:6px 0 0; font-size:11px; opacity:.82; }
.hero-icon {
    width:64px; height:64px; border-radius:18px; display:flex; align-items:center; justify-content:center;
    background:rgba(255,255,255,.14); font-size:25px;
}
.hero-action-btn {
    display:inline-flex; align-items:center; height:39px; padding:0 16px; border-radius:11px;
    border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.12); color:#fff;
    font-size:11px; font-weight:700; transition:.2s ease; white-space:nowrap;
}
.hero-action-btn:hover { background:rgba(255,255,255,.22); transform:translateY(-2px); }
.hero-action-primary { background:#fff; color:var(--primary); border-color:#fff; }
.hero-action-primary:hover { background:#eef4ff; }

/* ALERT */
.alert-custom { padding:12px 15px; margin-bottom:18px; border-radius:12px; font-size:11px; }
.alert-danger-custom { color:#842029; background:#f8d7da; border:1px solid #f5c2c7; }

/* ACTION ICONS */
.action-icons { display:flex; gap:6px; }
.icon-btn {
    width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;
    border:none; border-radius:8px; font-size:10px; transition:.2s ease; cursor:pointer;
}
.icon-btn:hover { transform:translateY(-2px); }
.icon-btn.edit { color:var(--primary); background:#eef4ff; }
.icon-btn.edit:hover { color:#fff; background:var(--primary); }
.icon-btn.delete { color:#b42318; background:#fff0f0; }
.icon-btn.delete:hover { color:#fff; background:#b42318; }
.icon-btn.print { color:#147346; background:#e9f8f0; }
.icon-btn.print:hover { color:#fff; background:#147346; }

/* PRINT */
@media print {
    .sidebar, .topbar, .page-hero, .stats-grid, .search-box,
    .action-icons, .pagination-bar, .no-print { display:none !important; }
    .main { margin-left:0 !important; }
    .content { padding:0 !important; }
    .section-card { box-shadow:none !important; border:none !important; }
}

/* STATS */
.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:22px; }
.stat-card {
    display:flex; align-items:center; gap:14px; padding:18px; background:#fff; border:1px solid var(--border);
    border-radius:16px; box-shadow:0 5px 20px rgba(20,40,70,.04); transition:.2s ease;
}
.stat-card:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(20,40,70,.08); }
.stat-icon { width:44px; height:44px; border-radius:13px; display:flex; align-items:center; justify-content:center; background:#eef4ff; color:var(--primary); }
.stat-card.success .stat-icon { background:#e9f8f0; color:var(--success); }
.stat-card.warning .stat-icon { background:#fff5df; color:var(--warning); }
.stat-card.purple .stat-icon { background:#f1ecff; color:var(--purple); }
.stat-label { color:var(--muted); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
.stat-number { margin-top:3px; font-size:21px; font-weight:800; }

/* PATIENT CARD */
.section-card { background:#fff; border:1px solid var(--border); border-radius:18px; overflow:hidden; box-shadow:0 5px 25px rgba(20,40,70,.04); }
.section-header {
    padding:20px 22px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between;
    align-items:center; flex-wrap:wrap; gap:14px;
}
.section-title h3 { margin:0; font-size:15px; font-weight:800; }
.section-title span { display:block; margin-top:4px; color:var(--muted); font-size:10px; }
.search-box { display:flex; align-items:center; gap:8px; }
.search-field { position:relative; }
.search-field i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#9aa5b5; font-size:11px; }
.search-box input {
    width:250px; height:39px; padding:0 13px 0 34px; border:1px solid var(--border);
    border-radius:10px; outline:none; font-size:11px; transition:.2s ease;
}
.search-box input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(13,110,253,.08); }
.search-box button { height:39px; border:none; border-radius:10px; background:var(--primary); color:#fff; padding:0 14px; font-size:11px; font-weight:700; }
.clear-search { height:39px; display:inline-flex; align-items:center; padding:0 12px; border-radius:10px; background:#f3f5f8; color:#687386; font-size:10px; font-weight:700; }

.table-wrapper { overflow-x:auto; }
.patient-table { width:100%; min-width:850px; border-collapse:collapse; }
.patient-table th {
    padding:14px 20px; background:#fafbfd; color:#8995a7; border-bottom:1px solid var(--border);
    text-align:left; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
}
.patient-table td { padding:16px 20px; border-bottom:1px solid #f0f2f5; font-size:11px; vertical-align:middle; }
.patient-table tbody tr { transition:.2s ease; }
.patient-table tbody tr:hover { background:#fafcff; }
.patient-cell { display:flex; align-items:center; gap:12px; }
.patient-avatar {
    width:39px; height:39px; flex-shrink:0; border-radius:12px; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,#eaf2ff,#f3f7ff); color:var(--primary); font-size:11px; font-weight:800;
}
.patient-name { font-size:11px; font-weight:800; color:var(--text); }
.patient-sub { margin-top:3px; color:var(--muted); font-size:9px; }
.sex-badge, .badge-count {
    display:inline-flex; align-items:center; padding:5px 10px; border-radius:20px; font-size:9px; font-weight:700;
}
.sex-male { background:#eef4ff; color:#0d6efd; }
.sex-female { background:#fff0f5; color:#c13570; }
.sex-other { background:#f1f3f5; color:#667085; }
.badge-count { background:#e9f8f0; color:#147346; }
.last-visit { color:#596579; font-size:10px; }
.never { color:#b0b8c4; font-size:10px; }
.view-btn {
    width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;
    border-radius:9px; background:#eef4ff; color:var(--primary); transition:.2s ease;
}
.view-btn:hover { background:var(--primary); color:#fff; transform:translateY(-2px); }

/* EMPTY + PAGINATION */
.empty-state { padding:65px 20px; text-align:center; color:var(--muted); }
.empty-state-icon { width:65px; height:65px; margin:0 auto 14px; border-radius:18px; display:flex; align-items:center; justify-content:center; background:#eef4ff; color:var(--primary); font-size:23px; }
.empty-state h5 { margin:0; color:var(--text); font-size:14px; font-weight:800; }
.empty-state p { margin:6px 0 0; font-size:10px; }
.pagination-bar { padding:16px 22px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
.pagination-bar span { color:var(--muted); font-size:10px; }
.page-nav { display:flex; gap:7px; }
.page-link {
    display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 10px;
    border:1px solid var(--border); border-radius:9px; background:#fff; color:var(--text); font-size:10px; font-weight:700;
}
.page-link:hover { border-color:var(--primary); color:var(--primary); }
.page-link.disabled { opacity:.4; pointer-events:none; }

@media (max-width:1100px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:850px) {
    .sidebar { transform:translateX(-100%); }
    .sidebar.show { transform:translateX(0); box-shadow:20px 0 40px rgba(0,0,0,.18); }
    .main { margin-left:0; }
    .menu-toggle { display:block; }
    .topbar { padding:0 20px; }
    .content { padding:25px 20px; }
}
@media (max-width:600px) {
    .admin-profile > div:last-child { display:none; }
    .page-hero { padding:20px; }
    .page-hero h2 { font-size:17px; }
    .hero-icon { width:54px; height:54px; font-size:21px; }
    .stats-grid { grid-template-columns:1fr; }
    .section-header { align-items:stretch; }
    .search-box { width:100%; }
    .search-field { flex:1; }
    .search-box input { width:100%; }
}
</style>
</head>

<body>

<!-- SAME SIDEBAR DESIGN - Patients Active -->
<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main">

<header class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="menu-toggle" id="menuToggle" type="button"><i class="fa-solid fa-bars"></i></button>
        <div class="page-title">
            <h1>Patients</h1>
            <p>Manage and monitor registered patient records</p>
        </div>
    </div>

    <div class="admin-profile">
        <div class="admin-avatar"><i class="fa-solid fa-user-shield"></i></div>
        <div><strong><?= e($_SESSION['username'] ?? 'Administrator') ?></strong><span><?= e($_SESSION['email'] ?? 'RHU Arakan') ?></span></div>
    </div>
</header>

<section class="content">

    <div class="page-hero">
        <div>
            <h2>Patient Management</h2>
            <p>View registered patients, appointment history, and visit information.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="hero-action-btn" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Print List
            </button>
            <button type="button" class="hero-action-btn hero-action-primary" data-bs-toggle="modal" data-bs-target="#patientModal" onclick="openCreateModal()">
                <i class="fa-solid fa-plus me-1"></i> Add Patient
            </button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-custom alert-danger-custom no-print">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div><div class="stat-label">Total Patients</div><div class="stat-number"><?= number_format($total_patients) ?></div></div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa-solid fa-mars"></i></div>
            <div><div class="stat-label">Male Patients</div><div class="stat-number"><?= number_format($total_male) ?></div></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fa-solid fa-venus"></i></div>
            <div><div class="stat-label">Female Patients</div><div class="stat-number"><?= number_format($total_female) ?></div></div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
            <div><div class="stat-label">Appointments</div><div class="stat-number"><?= number_format($total_appointments) ?></div></div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">
            <div class="section-title">
                <h3>All Registered Patients</h3>
                <span><?= number_format($total_rows) ?> matching record<?= $total_rows === 1 ? '' : 's' ?></span>
            </div>

            <form class="search-box" method="get">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Search name or contact..." value="<?= e($search) ?>">
                </div>
                <button type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="patients.php" class="clear-search">Clear</a>
                <?php endif; ?>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <?php
                            $sex = strtolower(trim((string)($patient['sex'] ?? '')));
                            $sexClass = $sex === 'male' ? 'sex-male' : ($sex === 'female' ? 'sex-female' : 'sex-other');
                        ?>
                        <tr>
                            <td>
                                <div class="patient-cell">
                                    <div class="patient-avatar"><?= e(initials($patient['first_name'], $patient['last_name'])) ?></div>
                                    <div>
                                        <div class="patient-name"><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></div>
                                        <div class="patient-sub">Patient ID #<?= e($patient['id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sex-badge <?= $sexClass ?>"><?= e($patient['sex'] ?? 'N/A') ?></span></td>
                            <td><?= e($patient['age'] ?? 'N/A') ?></td>
                            <td><?= e($patient['contact_number'] ?? 'N/A') ?></td>
                            <td><span class="badge-count"><?= number_format((int)$patient['total_appointments']) ?></span></td>
                            <td>
                                <?php if ($patient['last_visit']): ?>
                                    <span class="last-visit"><?= e(date('M d, Y', strtotime($patient['last_visit']))) ?></span>
                                <?php else: ?>
                                    <span class="never">No visit yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-icons">
                                    <a href="appointments.php?q=<?= urlencode($patient['first_name']) ?>" class="view-btn" title="View appointments">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    <button
                                        type="button"
                                        class="icon-btn edit"
                                        title="Edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#patientModal"
                                        onclick='openEditModal(<?= htmlspecialchars(json_encode($patient), ENT_QUOTES, "UTF-8") ?>)'
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <a
                                        href="patient-print.php?id=<?= (int)$patient['id'] ?>"
                                        target="_blank"
                                        class="icon-btn print"
                                        title="Print patient record"
                                    >
                                        <i class="fa-solid fa-print"></i>
                                    </a>

                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this patient record? This will also remove their account and appointment history. This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$patient['id'] ?>">
                                        <button type="submit" class="icon-btn delete" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-bar">
                <span>Showing <?= number_format($total_rows === 0 ? 0 : $offset + 1) ?>–<?= number_format(min($offset + $per_page, $total_rows)) ?> of <?= number_format($total_rows) ?> patients</span>
                <div class="page-nav">
                    <a class="page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="?page=<?= max(1, $page - 1) ?>&q=<?= urlencode($search) ?>">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <span class="page-link">Page <?= $page ?>/<?= $total_pages ?></span>
                    <a class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>" href="?page=<?= min($total_pages, $page + 1) ?>&q=<?= urlencode($search) ?>">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-user-slash"></i></div>
                <h5>No patients found</h5>
                <p><?= $search !== '' ? 'Try using a different search keyword.' : 'There are no registered patients yet.' ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
</main>

<!-- ================= CREATE/EDIT MODAL ================= -->
<div class="modal fade" id="patientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border:none; border-radius:18px; overflow:hidden;">
            <form method="post">
                <input type="hidden" name="action" id="patient_action" value="create">
                <input type="hidden" name="id" id="patient_id" value="">

                <div class="modal-header" style="padding:20px 22px; border-bottom:1px solid var(--border);">
                    <h5 class="modal-title" id="patientModalLabel" style="font-size:16px; font-weight:800;">Add Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body" style="padding:22px;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:10px; font-weight:700;">First Name <span style="color:#dc3545;">*</span></label>
                            <input type="text" class="form-control" name="first_name" id="patient_first_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Middle Name</label>
                            <input type="text" class="form-control" name="middle_name" id="patient_middle_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Last Name <span style="color:#dc3545;">*</span></label>
                            <input type="text" class="form-control" name="last_name" id="patient_last_name" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Birth Date</label>
                            <input type="date" class="form-control" name="birth_date" id="patient_birth_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Age</label>
                            <input type="number" class="form-control" name="age" id="patient_age" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Sex</label>
                            <select class="form-select" name="sex" id="patient_sex">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" id="patient_contact_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Email <span style="color:#dc3545;">*</span></label>
                            <input type="email" class="form-control" name="email" id="patient_email" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label" style="font-size:10px; font-weight:700;">Address</label>
                            <input type="text" class="form-control" name="address" id="patient_address">
                        </div>

                        <div class="col-12 patient-account-fields">
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" style="font-size:10px; font-weight:700;">Username <span style="color:#dc3545;">*</span></label>
                                    <input type="text" class="form-control" name="username" id="patient_username" minlength="4">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" style="font-size:10px; font-weight:700;">Password <span style="color:#dc3545;">*</span></label>
                                    <input type="password" class="form-control" name="password" id="patient_password" minlength="8" placeholder="Minimum 8 characters">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding:15px 22px; border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save me-1"></i> Save Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function () {
        sidebar.classList.toggle('show');
    });
}

document.addEventListener('click', function (event) {
    if (
        window.innerWidth <= 850 &&
        sidebar &&
        sidebar.classList.contains('show') &&
        !sidebar.contains(event.target) &&
        menuToggle &&
        !menuToggle.contains(event.target)
    ) {
        sidebar.classList.remove('show');
    }
});

/* ================= CREATE/EDIT MODAL ================= */

const patientUsername = document.getElementById('patient_username');
const patientPassword = document.getElementById('patient_password');
const accountFields = document.querySelector('.patient-account-fields');

function openCreateModal() {
    document.getElementById('patientModalLabel').textContent = 'Add Patient';
    document.getElementById('patient_action').value = 'create';
    document.getElementById('patient_id').value = '';
    document.getElementById('patient_first_name').value = '';
    document.getElementById('patient_middle_name').value = '';
    document.getElementById('patient_last_name').value = '';
    document.getElementById('patient_birth_date').value = '';
    document.getElementById('patient_age').value = '';
    document.getElementById('patient_sex').value = '';
    document.getElementById('patient_contact_number').value = '';
    document.getElementById('patient_email').value = '';
    document.getElementById('patient_address').value = '';
    patientUsername.value = '';
    patientPassword.value = '';

    accountFields.style.display = '';
    patientUsername.required = true;
    patientPassword.required = true;
}

function openEditModal(patient) {
    document.getElementById('patientModalLabel').textContent = 'Edit Patient';
    document.getElementById('patient_action').value = 'update';
    document.getElementById('patient_id').value = patient.id;
    document.getElementById('patient_first_name').value = patient.first_name || '';
    document.getElementById('patient_middle_name').value = patient.middle_name || '';
    document.getElementById('patient_last_name').value = patient.last_name || '';
    document.getElementById('patient_birth_date').value = patient.birth_date ? patient.birth_date.substring(0, 10) : '';
    document.getElementById('patient_age').value = patient.age || '';
    document.getElementById('patient_sex').value = patient.sex || '';
    document.getElementById('patient_contact_number').value = patient.contact_number || '';
    document.getElementById('patient_email').value = patient.email || '';
    document.getElementById('patient_address').value = patient.address || '';

    accountFields.style.display = 'none';
    patientUsername.required = false;
    patientPassword.required = false;
}
</script>
</body>
</html>
