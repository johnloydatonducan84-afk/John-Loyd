<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HANDLE CREATE / UPDATE / DELETE / TOGGLE
|--------------------------------------------------------------------------
| services table:
|
| id
| service_name
| description
| duration
| max_patients
| status
| created_at
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | SAVE SERVICE
    |--------------------------------------------------------------------------
    */

    if ($action === 'save') {

        $id = (int) ($_POST['id'] ?? 0);

        $serviceName = trim($_POST['service_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $duration = (int) ($_POST['duration'] ?? 30);
        $maxPatients = (int) ($_POST['max_patients'] ?? 1);

        if ($serviceName === '') {

            $error = 'Service name is required.';

        } elseif ($duration < 5) {

            $error = 'Duration must be at least 5 minutes.';

        } elseif ($maxPatients < 1) {

            $error = 'Maximum patients must be at least 1.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                if ($id > 0) {

                    $update = $pdo->prepare("
                        UPDATE services
                        SET
                            service_name = :service_name,
                            description = :description,
                            duration = :duration,
                            max_patients = :max_patients
                        WHERE id = :id
                    ");

                    $update->execute([
                        ':service_name' => $serviceName,
                        ':description' => $description,
                        ':duration' => $duration,
                        ':max_patients' => $maxPatients,
                        ':id' => $id
                    ]);

                }

                /*
                |--------------------------------------------------------------------------
                | INSERT
                |--------------------------------------------------------------------------
                */

                else {

                    $insert = $pdo->prepare("
                        INSERT INTO services
                        (
                            service_name,
                            description,
                            duration,
                            max_patients,
                            status
                        )
                        VALUES
                        (
                            :service_name,
                            :description,
                            :duration,
                            :max_patients,
                            'active'
                        )
                    ");

                    $insert->execute([
                        ':service_name' => $serviceName,
                        ':description' => $description,
                        ':duration' => $duration,
                        ':max_patients' => $maxPatients
                    ]);
                }

                header('Location: services.php');
                exit;

            } catch (PDOException $e) {

                $error = 'Unable to save service: ' . $e->getMessage();
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE SERVICE
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {

            $error = 'Invalid service ID.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | FIRST CHECK IF SERVICE EXISTS
                |--------------------------------------------------------------------------
                */

                $checkService = $pdo->prepare("
                    SELECT id
                    FROM services
                    WHERE id = ?
                    LIMIT 1
                ");

                $checkService->execute([$id]);

                $serviceExists = $checkService->fetchColumn();

                if ($serviceExists === false) {

                    $error = 'Service not found.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK APPOINTMENTS USING THIS SERVICE
                    |--------------------------------------------------------------------------
                    */

                    $checkAppointments = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM appointments
                        WHERE service_id = ?
                    ");

                    $checkAppointments->execute([$id]);

                    $appointmentCount = (int) $checkAppointments->fetchColumn();


                    /*
                    |--------------------------------------------------------------------------
                    | IF APPOINTMENTS EXIST
                    |--------------------------------------------------------------------------
                    |
                    | We cannot safely delete the service because appointments
                    | still reference it.
                    |
                    | Therefore we deactivate it.
                    |
                    */

                    if ($appointmentCount > 0) {

                        $update = $pdo->prepare("
                            UPDATE services
                            SET status = 'inactive'
                            WHERE id = ?
                        ");

                        $update->execute([$id]);

                        $success =
                            'This service cannot be permanently deleted because '
                            . $appointmentCount
                            . ' appointment(s) are using it. The service was set to inactive.';

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | NO APPOINTMENTS
                    |--------------------------------------------------------------------------
                    |
                    | Safe to permanently delete.
                    |
                    */

                    else {

                        $delete = $pdo->prepare("
                            DELETE FROM services
                            WHERE id = ?
                        ");

                        $delete->execute([$id]);

                        if ($delete->rowCount() > 0) {

                            $success = 'Service deleted successfully.';

                        } else {

                            $error = 'Service could not be deleted.';
                        }
                    }
                }

            } catch (PDOException $e) {

                /*
                |--------------------------------------------------------------------------
                | FOREIGN KEY PROTECTION
                |--------------------------------------------------------------------------
                */

                if ((int) $e->errorInfo[1] === 1451) {

                    $error =
                        'This service cannot be deleted because it is being used '
                        . 'by existing appointments.';

                } else {

                    $error =
                        'Unable to delete service: '
                        . $e->getMessage();
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | TOGGLE SERVICE STATUS
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'toggle') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {

            try {

                $stmt = $pdo->prepare("
                    SELECT status
                    FROM services
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([$id]);

                $currentStatus = $stmt->fetchColumn();

                if ($currentStatus === false) {

                    $error = 'Service not found.';

                } else {

                    $newStatus =
                        strtolower((string) $currentStatus) === 'active'
                            ? 'inactive'
                            : 'active';

                    $toggle = $pdo->prepare("
                        UPDATE services
                        SET status = ?
                        WHERE id = ?
                    ");

                    $toggle->execute([
                        $newStatus,
                        $id
                    ]);
                }

            } catch (PDOException $e) {

                $error =
                    'Unable to change service status: '
                    . $e->getMessage();
            }

        } else {

            $error = 'Invalid service ID.';
        }

        header('Location: services.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FETCH SERVICES
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Do NOT put backslashes (\) before SELECT or other SQL keywords.
|
|--------------------------------------------------------------------------
*/

try {

    $services_stmt = $pdo->query("
        SELECT
            s.id,
            s.service_name,
            s.description,
            s.duration,
            s.max_patients,
            s.status,
            s.created_at,

            (
                SELECT COUNT(*)
                FROM appointments a
                WHERE a.service_id = s.id
            ) AS total_bookings

        FROM services s

        ORDER BY s.service_name ASC
    ");

    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $services = [];

    $error =
        'Unable to load services: '
        . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Services | CareSched Admin</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        :root {
            --primary: #0d6efd;
            --sidebar: #0b1f3a;
            --sidebar2: #102d50;
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #7b8798;
            --border: #e6ebf2;
            --success: #198754;
            --danger: #dc3545;
            --warning: #b76b00;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a {
            text-decoration: none;
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    var(--sidebar),
                    var(--sidebar2)
                );

            color: white;
            padding: 25px 16px;
            z-index: 1000;
            transition: .3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;

            padding:
                10px 12px 28px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);

            margin-bottom: 25px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #198754
                );

            font-size: 19px;
        }

        .brand-text strong {
            display: block;
            font-size: 18px;
            font-weight: 800;
        }

        .brand-text span {
            font-size: 10px;
            color: rgba(255,255,255,.55);

            text-transform: uppercase;
            letter-spacing: .8px;
        }

        .menu-label {
            padding: 0 13px;

            color: rgba(255,255,255,.4);

            font-size: 10px;
            font-weight: 700;

            text-transform: uppercase;
            letter-spacing: 1px;

            margin: 20px 0 8px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 13px;

            color: rgba(255,255,255,.7);

            padding: 12px 14px;

            border-radius: 11px;
            margin-bottom: 4px;

            font-size: 13px;
            font-weight: 500;

            transition: .2s ease;
        }

        .sidebar-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: white;

            background:
                rgba(13,110,253,.25);

            transform: translateX(3px);
        }

        .sidebar-link.active {
            box-shadow:
                inset 3px 0 0 #0d6efd;
        }

        .sidebar-bottom {
            position: absolute;
            bottom: 20px;
            left: 16px;
            right: 16px;
        }

        .logout-link {
            color: #ffb4b4;
        }

        .logout-link:hover {
            color: white;
            background: rgba(220,53,69,.18);
        }

        /* ================= MAIN ================= */

        .main {
            margin-left: 260px;
            min-height: 100vh;
        }

        .topbar {
            height: 76px;

            background: white;

            border-bottom:
                1px solid
                var(--border);

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 35px;

            position: sticky;
            top: 0;
            z-index: 500;
        }

        .menu-toggle {
            display: none;

            border: none;

            background: #eef4ff;

            color: var(--primary);

            width: 42px;
            height: 42px;

            border-radius: 10px;
        }

        .page-title h1 {
            margin: 0;

            font-size: 20px;
            font-weight: 800;
        }

        .page-title p {
            margin: 4px 0 0;

            color: var(--muted);

            font-size: 11px;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;

            border-radius: 12px;

            background: #eaf2ff;

            color: var(--primary);

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-profile strong {
            display: block;
            font-size: 12px;
        }

        .admin-profile span {
            display: block;
            font-size: 10px;
            color: var(--muted);
        }

        /* ================= CONTENT ================= */

        .content {
            padding: 30px 35px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 22px;
        }

        .page-header-left h2 {
            margin: 0;

            font-size: 18px;
            font-weight: 800;
        }

        .page-header-left p {
            margin: 5px 0 0;

            font-size: 11px;
            color: var(--muted);
        }

        .add-btn {
            border: none;

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #0ea5e9
                );

            color: white;

            padding: 11px 17px;

            border-radius: 10px;

            font-size: 11px;
            font-weight: 700;

            box-shadow:
                0 6px 15px
                rgba(13,110,253,.2);
        }

        .add-btn:hover {
            transform: translateY(-2px);
        }

        /* ================= ALERT ================= */

        .alert {
            font-size: 11px;
            border-radius: 12px;
        }

        /* ================= GRID ================= */

        .services-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    auto-fill,
                    minmax(280px, 1fr)
                );

            gap: 18px;
        }

        .service-card {
            background: white;

            border:
                1px solid
                var(--border);

            border-radius: 18px;

            padding: 20px;

            box-shadow:
                0 5px 20px
                rgba(20,40,70,.04);

            transition: .25s;

            position: relative;
            overflow: hidden;
        }

        .service-card::before {
            content: '';

            position: absolute;

            left: 0;
            top: 0;

            width: 100%;
            height: 3px;

            background:
                linear-gradient(
                    90deg,
                    #0d6efd,
                    #20c997
                );
        }

        .service-card:hover {
            transform: translateY(-4px);

            box-shadow:
                0 12px 30px
                rgba(20,40,70,.09);
        }

        .service-top {
            display: flex;

            justify-content: space-between;
            align-items: flex-start;

            margin-bottom: 14px;
        }

        .service-icon {
            width: 46px;
            height: 46px;

            border-radius: 13px;

            background:
                linear-gradient(
                    135deg,
                    #eaf2ff,
                    #e9f8f0
                );

            color: var(--primary);

            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ================= STATUS ================= */

        .status-badge {
            padding: 5px 11px;

            border-radius: 20px;

            font-size: 9px;
            font-weight: 700;

            text-transform: uppercase;
        }

        .status-active {
            background: #e9f8f0;
            color: #147346;
        }

        .status-inactive {
            background: #f1f3f5;
            color: #667085;
        }

        /* ================= SERVICE INFO ================= */

        .service-name {
            font-size: 14px;
            font-weight: 800;

            margin-bottom: 6px;
        }

        .service-desc {
            color: var(--muted);

            font-size: 10px;

            line-height: 1.6;

            margin-bottom: 16px;

            min-height: 34px;
        }

        .service-info {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;

            margin-bottom: 15px;
        }

        .info-badge {
            background: #f5f7fa;

            border:
                1px solid
                #e8edf3;

            padding: 5px 8px;

            border-radius: 8px;

            color: #667085;

            font-size: 9px;
        }

        .info-badge i {
            color: var(--primary);
        }

        /* ================= META ================= */

        .service-meta {
            display: flex;

            justify-content: space-between;
            align-items: center;

            padding-top: 13px;

            border-top:
                1px solid
                var(--border);
        }

        .booking-count {
            font-size: 9px;
            color: var(--muted);
        }

        .booking-count strong {
            color: var(--text);
        }

        .service-actions {
            display: flex;
            gap: 6px;
        }

        .icon-btn {
            width: 30px;
            height: 30px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border-radius: 8px;

            font-size: 10px;

            border: none;

            transition: .2s;
        }

        .icon-btn.edit {
            background: #eef4ff;
            color: var(--primary);
        }

        .icon-btn.edit:hover {
            background: var(--primary);
            color: white;
        }

        .icon-btn.toggle {
            background: #fff4df;
            color: var(--warning);
        }

        .icon-btn.toggle:hover {
            background: var(--warning);
            color: white;
        }

        .icon-btn.delete {
            background: #fff0f0;
            color: #b42318;
        }

        .icon-btn.delete:hover {
            background: #b42318;
            color: white;
        }

        /* ================= EMPTY ================= */

        .empty-state {
            padding: 70px 20px;

            text-align: center;

            color: var(--muted);

            grid-column: 1 / -1;

            background: white;

            border:
                1px solid
                var(--border);

            border-radius: 18px;
        }

        .empty-state i {
            font-size: 35px;
            margin-bottom: 15px;
            opacity: .4;
        }

        /* ================= MODAL ================= */

        .modal-content {
            border: none;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 20px 60px
                rgba(0,0,0,.15);
        }

        .modal-header {
            padding: 20px 22px;

            border-bottom:
                1px solid
                var(--border);
        }

        .modal-title {
            font-size: 16px;
            font-weight: 800;
        }

        .modal-body {
            padding: 22px;
        }

        .modal-footer {
            border-top:
                1px solid
                var(--border);

            padding: 15px 22px;
        }

        .form-label {
            font-size: 11px;
            font-weight: 700;

            margin-bottom: 7px;
        }

        .form-control {
            border:
                1px solid
                #dfe5ec;

            border-radius: 10px;

            font-size: 12px;

            padding: 10px 12px;
        }

        .form-control:focus {
            border-color: var(--primary);

            box-shadow:
                0 0 0 .2rem
                rgba(13,110,253,.08);
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 850px) {

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .menu-toggle {
                display: block;
            }

            .topbar {
                padding: 0 20px;
            }

            .content {
                padding: 25px 20px;
            }
        }

        @media (max-width: 600px) {

            .admin-profile > div {
                display: none;
            }

            .page-header {
                align-items: flex-start;

                gap: 15px;
            }

            .add-btn {
                padding: 9px 12px;
            }
        }

        @media print {
            .sidebar, .service-actions, .no-print { display: none !important; }
            .main { margin-left: 0 !important; }
        }

    </style>

</head>

<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div class="d-flex align-items-center gap-3">

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="page-title">

                <h1>
                    Healthcare Services
                </h1>

                <p>
                    Manage available RHU healthcare services
                </p>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>


            <div>

                <strong>
                    <?= e($_SESSION['username'] ?? 'Administrator') ?>
                </strong>

                <span>
                    <?= e($_SESSION['email'] ?? 'RHU Arakan') ?>
                </span>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">

                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <?php if ($success !== ''): ?>

            <div class="alert alert-success">

                <i class="fa-solid fa-circle-check me-2"></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <div class="page-header">


            <div class="page-header-left">

                <h2>
                    Services
                </h2>

                <p>
                    <?= count($services) ?>
                    healthcare service<?= count($services) !== 1 ? 's' : '' ?>
                    configured
                </p>

            </div>


            <div class="d-flex align-items-center gap-2">

                <button type="button" class="add-btn no-print" style="background:linear-gradient(135deg,#198754,#0d6efd);" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i>
                    Print List
                </button>

                <button
                    class="add-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#serviceModal"
                    onclick="openCreateModal()"
                >

                    <i class="fa-solid fa-plus me-1"></i>

                    Add Service

                </button>

            </div>

        </div>


        <!-- SERVICE CARDS -->

        <div class="services-grid">


            <?php if (!empty($services)): ?>


                <?php foreach ($services as $service): ?>

                    <?php

                    $serviceId =
                        (int) ($service['id'] ?? 0);

                    $serviceName =
                        (string) (
                            $service['service_name']
                            ?? 'Unnamed Service'
                        );

                    $description =
                        (string) (
                            $service['description']
                            ?? ''
                        );

                    $duration =
                        (int) (
                            $service['duration']
                            ?? 30
                        );

                    $maxPatients =
                        (int) (
                            $service['max_patients']
                            ?? 1
                        );

                    $status =
                        strtolower(
                            (string) (
                                $service['status']
                                ?? 'active'
                            )
                        );

                    $totalBookings =
                        (int) (
                            $service['total_bookings']
                            ?? 0
                        );

                    $isActive =
                        ($status === 'active');

                    ?>


                    <div class="service-card">


                        <div class="service-top">


                            <div class="service-icon">

                                <i class="fa-solid fa-stethoscope"></i>

                            </div>


                            <?php if ($isActive): ?>

                                <span class="status-badge status-active">

                                    <i class="fa-solid fa-circle-check me-1"></i>

                                    Active

                                </span>

                            <?php else: ?>

                                <span class="status-badge status-inactive">

                                    <i class="fa-solid fa-circle-pause me-1"></i>

                                    Inactive

                                </span>

                            <?php endif; ?>


                        </div>


                        <div class="service-name">

                            <?= htmlspecialchars($serviceName) ?>

                        </div>


                        <div class="service-desc">

                            <?php if ($description !== ''): ?>

                                <?= nl2br(
                                    htmlspecialchars($description)
                                ) ?>

                            <?php else: ?>

                                No description provided.

                            <?php endif; ?>

                        </div>


                        <div class="service-info">


                            <span class="info-badge">

                                <i class="fa-regular fa-clock me-1"></i>

                                <?= $duration ?> min

                            </span>


                            <span class="info-badge">

                                <i class="fa-solid fa-users me-1"></i>

                                <?= $maxPatients ?>
                                patient<?= $maxPatients !== 1 ? 's' : '' ?>

                            </span>


                        </div>


                        <div class="service-meta">


                            <div class="booking-count">

                                <i class="fa-solid fa-calendar-check me-1"></i>

                                <strong>
                                    <?= $totalBookings ?>
                                </strong>

                                booking<?= $totalBookings !== 1 ? 's' : '' ?>

                            </div>


                            <div class="service-actions">


                                <!-- EDIT -->

                                <button
                                    type="button"
                                    class="icon-btn edit"
                                    title="Edit Service"
                                    data-bs-toggle="modal"
                                    data-bs-target="#serviceModal"

                                    onclick='openEditModal(
                                        <?= json_encode(
                                            [
                                                'id' => $serviceId,
                                                'service_name' => $serviceName,
                                                'description' => $description,
                                                'duration' => $duration,
                                                'max_patients' => $maxPatients
                                            ],
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'
                                >

                                    <i class="fa-solid fa-pen"></i>

                                </button>


                                <!-- TOGGLE -->

                                <form
                                    method="post"
                                    style="display:inline;"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="toggle"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $serviceId ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="icon-btn toggle"
                                        title="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                                    >

                                        <?php if ($isActive): ?>

                                            <i class="fa-solid fa-power-off"></i>

                                        <?php else: ?>

                                            <i class="fa-solid fa-play"></i>

                                        <?php endif; ?>

                                    </button>

                                </form>


                                <!-- DELETE -->

                                <form
                                    method="post"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to delete this service?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $serviceId ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="icon-btn delete"
                                        title="Delete Service"
                                    >

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

                    <h5
                        style="
                            font-size:14px;
                            font-weight:800;
                        "
                    >
                        No Services Found
                    </h5>

                    <p
                        style="
                            font-size:11px;
                        "
                    >
                        Add your first healthcare service to get started.
                    </p>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- =====================================================
     SERVICE MODAL
===================================================== -->

<div
    class="modal fade"
    id="serviceModal"
    tabindex="-1"
    aria-hidden="true"
>


    <div class="modal-dialog modal-dialog-centered">


        <div class="modal-content">


            <form method="post">


                <input
                    type="hidden"
                    name="action"
                    value="save"
                >


                <input
                    type="hidden"
                    name="id"
                    id="service_id"
                    value=""
                >


                <div class="modal-header">


                    <div>

                        <h5
                            class="modal-title"
                            id="serviceModalLabel"
                        >
                            Add Service
                        </h5>

                        <small
                            style="
                                color:#7b8798;
                                font-size:10px;
                            "
                        >
                            Configure healthcare service details
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>


                </div>


                <div class="modal-body">


                    <div class="mb-3">

                        <label class="form-label">
                            Service Name
                        </label>


                        <input
                            type="text"
                            class="form-control"
                            name="service_name"
                            id="service_name"
                            placeholder="e.g. General Consultation"
                            required
                        >

                    </div>


                    <div class="mb-3">

                        <label class="form-label">
                            Description
                        </label>


                        <textarea
                            class="form-control"
                            name="description"
                            id="service_description"
                            rows="3"
                            placeholder="Describe this healthcare service..."
                        ></textarea>

                    </div>


                    <div class="row">


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Duration
                            </label>


                            <div class="input-group">

                                <input
                                    type="number"
                                    class="form-control"
                                    name="duration"
                                    id="service_duration"
                                    value="30"
                                    min="5"
                                    step="5"
                                    required
                                >

                                <span class="input-group-text">
                                    min
                                </span>

                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Maximum Patients
                            </label>


                            <input
                                type="number"
                                class="form-control"
                                name="max_patients"
                                id="service_max_patients"
                                value="1"
                                min="1"
                                required
                            >

                        </div>


                    </div>


                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-floppy-disk me-1"></i>

                        Save Service

                    </button>


                </div>


            </form>


        </div>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


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


if (menuToggle && sidebar) {

    menuToggle.addEventListener(
        'click',
        function () {

            sidebar.classList.toggle('show');

        }
    );

}


document.addEventListener(
    'click',
    function (event) {

        if (
            window.innerWidth <= 850 &&
            sidebar &&
            sidebar.classList.contains('show') &&
            !sidebar.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {

            sidebar.classList.remove('show');

        }

    }
);


/*
|--------------------------------------------------------------------------
| CREATE MODAL
|--------------------------------------------------------------------------
*/

function openCreateModal() {

    document.getElementById(
        'serviceModalLabel'
    ).textContent = 'Add Service';


    document.getElementById(
        'service_id'
    ).value = '';


    document.getElementById(
        'service_name'
    ).value = '';


    document.getElementById(
        'service_description'
    ).value = '';


    document.getElementById(
        'service_duration'
    ).value = 30;


    document.getElementById(
        'service_max_patients'
    ).value = 1;

}


/*
|--------------------------------------------------------------------------
| EDIT MODAL
|--------------------------------------------------------------------------
*/

function openEditModal(service) {

    document.getElementById(
        'serviceModalLabel'
    ).textContent = 'Edit Service';


    document.getElementById(
        'service_id'
    ).value = service.id || '';


    document.getElementById(
        'service_name'
    ).value = service.service_name || '';


    document.getElementById(
        'service_description'
    ).value = service.description || '';


    document.getElementById(
        'service_duration'
    ).value = service.duration || 30;


    document.getElementById(
        'service_max_patients'
    ).value = service.max_patients || 1;

}

</script>


</body>
</html>