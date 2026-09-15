<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| ACTUAL DATABASE SCHEMA
|--------------------------------------------------------------------------
| schedules:
| id
| service_id
| schedule_date
| start_time
| end_time
| max_slots
| available_slots
| status
|
| services:
| id
| service_name
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | SAVE / CREATE / UPDATE
        |--------------------------------------------------------------------------
        */

        if ($action === 'save') {

            $id = (int)($_POST['id'] ?? 0);

            $service_id = (int)($_POST['service_id'] ?? 0);
            $schedule_date = trim($_POST['schedule_date'] ?? '');
            $start_time = trim($_POST['start_time'] ?? '');
            $end_time = trim($_POST['end_time'] ?? '');
            $max_slots = (int)($_POST['max_slots'] ?? 1);

            /*
            |--------------------------------------------------------------------------
            | VALIDATION
            |--------------------------------------------------------------------------
            */

            if (
                $service_id <= 0 ||
                $schedule_date === '' ||
                $start_time === '' ||
                $end_time === ''
            ) {

                $error = 'Please complete all required fields.';

            } elseif ($max_slots < 1) {

                $error = 'Maximum slots must be at least 1.';

            } elseif ($start_time >= $end_time) {

                $error = 'End time must be later than start time.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | CHECK SERVICE
                |--------------------------------------------------------------------------
                */

                $serviceCheck = $pdo->prepare("
                    SELECT id
                    FROM services
                    WHERE id = :id
                    LIMIT 1
                ");

                $serviceCheck->execute([
                    ':id' => $service_id
                ]);

                if (!$serviceCheck->fetch()) {

                    $error = 'Selected service does not exist.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK DUPLICATE SCHEDULE
                    |--------------------------------------------------------------------------
                    */

                    if ($id > 0) {

                        $duplicate = $pdo->prepare("
                            SELECT id
                            FROM schedules
                            WHERE service_id = :service_id
                            AND schedule_date = :schedule_date
                            AND start_time = :start_time
                            AND end_time = :end_time
                            AND id != :id
                            LIMIT 1
                        ");

                        $duplicate->execute([
                            ':service_id' => $service_id,
                            ':schedule_date' => $schedule_date,
                            ':start_time' => $start_time,
                            ':end_time' => $end_time,
                            ':id' => $id
                        ]);

                    } else {

                        $duplicate = $pdo->prepare("
                            SELECT id
                            FROM schedules
                            WHERE service_id = :service_id
                            AND schedule_date = :schedule_date
                            AND start_time = :start_time
                            AND end_time = :end_time
                            LIMIT 1
                        ");

                        $duplicate->execute([
                            ':service_id' => $service_id,
                            ':schedule_date' => $schedule_date,
                            ':start_time' => $start_time,
                            ':end_time' => $end_time
                        ]);
                    }

                    if ($duplicate->fetch()) {

                        $error = 'This schedule already exists for the selected service, date and time.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | UPDATE
                        |--------------------------------------------------------------------------
                        */

                        if ($id > 0) {

                            $update = $pdo->prepare("
                                UPDATE schedules
                                SET
                                    service_id = :service_id,
                                    schedule_date = :schedule_date,
                                    start_time = :start_time,
                                    end_time = :end_time,
                                    max_slots = :max_slots,
                                    available_slots = :available_slots
                                WHERE id = :id
                            ");

                            $update->execute([
                                ':service_id' => $service_id,
                                ':schedule_date' => $schedule_date,
                                ':start_time' => $start_time,
                                ':end_time' => $end_time,
                                ':max_slots' => $max_slots,
                                ':available_slots' => $max_slots,
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
                                INSERT INTO schedules
                                (
                                    service_id,
                                    schedule_date,
                                    start_time,
                                    end_time,
                                    max_slots,
                                    available_slots,
                                    status
                                )
                                VALUES
                                (
                                    :service_id,
                                    :schedule_date,
                                    :start_time,
                                    :end_time,
                                    :max_slots,
                                    :available_slots,
                                    'active'
                                )
                            ");

                            $insert->execute([
                                ':service_id' => $service_id,
                                ':schedule_date' => $schedule_date,
                                ':start_time' => $start_time,
                                ':end_time' => $end_time,
                                ':max_slots' => $max_slots,
                                ':available_slots' => $max_slots
                            ]);
                        }

                        header('Location: schedules.php');
                        exit;
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id > 0) {

                $delete = $pdo->prepare("
                    DELETE FROM schedules
                    WHERE id = :id
                ");

                $delete->execute([
                    ':id' => $id
                ]);
            }

            header('Location: schedules.php');
            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | TOGGLE STATUS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'toggle') {

            $id = (int)($_POST['id'] ?? 0);

            if ($id > 0) {

                $toggle = $pdo->prepare("
                    UPDATE schedules
                    SET status =
                        CASE
                            WHEN status = 'active' THEN 'inactive'
                            ELSE 'active'
                        END
                    WHERE id = :id
                ");

                $toggle->execute([
                    ':id' => $id
                ]);
            }

            header('Location: schedules.php');
            exit;
        }

    } catch (PDOException $e) {

        $error = 'Database error: ' . $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| GET SERVICES
|--------------------------------------------------------------------------
*/

$servicesStmt = $pdo->query("
    SELECT
        id,
        service_name
    FROM services
    WHERE status = 'active'
    ORDER BY service_name ASC
");

$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET SCHEDULES
|--------------------------------------------------------------------------
*/

/*
 * available_slots is computed live from actual bookings (instead of the
 * stored column, which is only ever set to max_slots on save) so this
 * matches what patients see as bookable on the appointment form.
 */
$schedulesStmt = $pdo->query("
    SELECT
        sc.id,
        sc.service_id,
        sc.schedule_date,
        sc.start_time,
        sc.end_time,
        sc.max_slots,
        GREATEST(
            sc.max_slots - COALESCE((
                SELECT COUNT(*)
                FROM appointments a
                WHERE a.schedule_id = sc.id
                  AND a.status NOT IN ('Rejected', 'Cancelled')
            ), 0),
            0
        ) AS available_slots,
        sc.status,
        s.service_name
    FROM schedules sc
    INNER JOIN services s
        ON sc.service_id = s.id
    ORDER BY
        sc.schedule_date ASC,
        sc.start_time ASC
");

$schedules = $schedulesStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| COUNT
|--------------------------------------------------------------------------
*/

$totalSchedules = count($schedules);

$activeSchedules = 0;
$inactiveSchedules = 0;

foreach ($schedules as $schedule) {

    if (strtolower($schedule['status']) === 'active') {
        $activeSchedules++;
    } else {
        $inactiveSchedules++;
    }
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

<title>Schedules | CareSched Admin</title>


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
    --primary-dark: #084298;

    --sidebar: #0b1f3a;
    --sidebar-light: #102d50;

    --bg: #f4f7fb;

    --text: #172033;
    --muted: #7b8798;

    --border: #e6ebf2;

    --white: #ffffff;

    --success: #198754;
    --danger: #dc3545;
    --warning: #f59e0b;
}


body {

    margin: 0;

    background: var(--bg);

    color: var(--text);

    font-family: 'Inter', sans-serif;

}


/* ================= SIDEBAR ================= */

.sidebar {

    position: fixed;

    top: 0;
    left: 0;

    width: 260px;

    height: 100vh;

    padding: 24px 16px;

    background:
        linear-gradient(
            180deg,
            var(--sidebar),
            var(--sidebar-light)
        );

    color: white;

    z-index: 1000;

    overflow-y: auto;

    transition: .3s ease;
}


.sidebar-brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 10px 12px 26px;

    margin-bottom: 22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}


.brand-icon {

    width: 44px;

    height: 44px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

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

    display: block;

    margin-top: 2px;

    font-size: 9px;

    color: rgba(255,255,255,.55);

    text-transform: uppercase;

    letter-spacing: 1px;

}


.menu-label {

    padding: 0 13px;

    margin: 19px 0 8px;

    color: rgba(255,255,255,.38);

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.sidebar-link {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 12px 14px;

    margin-bottom: 4px;

    border-radius: 11px;

    color: rgba(255,255,255,.7);

    font-size: 12px;

    font-weight: 500;

    transition: .2s ease;

}


.sidebar-link i {

    width: 20px;

    text-align: center;

}


.sidebar-link:hover {

    color: white;

    background:
        rgba(13,110,253,.20);

    transform: translateX(3px);

}


.sidebar-link.active {

    color: white;

    background:
        rgba(13,110,253,.28);

    box-shadow:
        inset 3px 0 0
        #0d6efd;
}


.sidebar-bottom {

    margin-top: 30px;

    padding-top: 15px;

    border-top: 1px solid rgba(255,255,255,.08);
}


.logout-link {

    color: #ffb4b4;
}


.logout-link:hover {

    color: white;

    background:
        rgba(220,53,69,.18);
}


/* ================= MAIN ================= */

.main {

    margin-left: 260px;

    min-height: 100vh;

}


/* ================= TOPBAR ================= */

.topbar {

    height: 76px;

    padding: 0 35px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    background: white;

    border-bottom:
        1px solid
        var(--border);

    position: sticky;

    top: 0;

    z-index: 500;
}


.menu-toggle {

    display: none;

    width: 42px;

    height: 42px;

    border: none;

    border-radius: 10px;

    background: #eef4ff;

    color: var(--primary);

}


.page-title h1 {

    margin: 0;

    font-size: 21px;

    font-weight: 800;

}


.page-title p {

    margin: 4px 0 0;

    color: var(--muted);

    font-size: 10px;

}


.admin-profile {

    display: flex;

    align-items: center;

    gap: 11px;

}


.admin-avatar {

    width: 41px;

    height: 41px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    background: #eaf2ff;

    color: var(--primary);

}


.admin-profile strong {

    display: block;

    font-size: 12px;

}


.admin-profile span {

    display: block;

    margin-top: 2px;

    color: var(--muted);

    font-size: 9px;

}


/* ================= CONTENT ================= */

.content {

    padding: 30px 35px;

}


/* ================= STATS ================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 16px;

    margin-bottom: 22px;

}


.stat-card {

    display: flex;

    align-items: center;

    gap: 14px;

    padding: 18px;

    background: white;

    border:
        1px solid
        var(--border);

    border-radius: 16px;

    box-shadow:
        0 5px 20px
        rgba(20,40,70,.04);

}


.stat-icon {

    width: 43px;

    height: 43px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eef4ff;

    color: var(--primary);

}


.stat-card.success .stat-icon {

    background: #e9f8f0;

    color: var(--success);

}


.stat-card.warning .stat-icon {

    background: #fff5df;

    color: var(--warning);

}


.stat-label {

    color: var(--muted);

    font-size: 9px;

    font-weight: 600;

}


.stat-number {

    margin-top: 2px;

    font-size: 20px;

    font-weight: 800;

}


/* ================= HEADER ================= */

.page-actions {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 16px;

}


.schedule-count {

    color: var(--muted);

    font-size: 10px;

}


.add-btn {

    border: none;

    padding: 11px 17px;

    border-radius: 11px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #0ea5e9
        );

    font-size: 11px;

    font-weight: 700;

    box-shadow:
        0 5px 15px
        rgba(13,110,253,.18);

    transition: .2s ease;

}


.add-btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px
        rgba(13,110,253,.25);

}


/* ================= ALERT ================= */

.alert-custom {

    padding: 12px 15px;

    margin-bottom: 18px;

    border-radius: 12px;

    font-size: 10px;

}


.alert-danger-custom {

    color: #842029;

    background: #f8d7da;

    border: 1px solid #f5c2c7;

}


/* ================= TABLE ================= */

.section-card {

    overflow: hidden;

    background: white;

    border:
        1px solid
        var(--border);

    border-radius: 18px;

    box-shadow:
        0 5px 25px
        rgba(20,40,70,.04);

}


.table-wrapper {

    overflow-x: auto;

}


.schedule-table {

    width: 100%;

    min-width: 800px;

    border-collapse: collapse;

}


.schedule-table th {

    padding: 14px 20px;

    background: #fafbfd;

    color: #8995a7;

    border-bottom:
        1px solid
        var(--border);

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .5px;

}


.schedule-table td {

    padding: 16px 20px;

    border-bottom:
        1px solid
        #f0f2f5;

    font-size: 11px;

    vertical-align: middle;

}


.schedule-table tbody tr {

    transition: .2s ease;

}


.schedule-table tbody tr:hover {

    background: #fafcff;

}


.service-name {

    font-weight: 700;

}


.date-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 10px;

    border-radius: 9px;

    background: #eef4ff;

    color: var(--primary);

    font-size: 9px;

    font-weight: 700;

}


.time-box {

    display: flex;

    align-items: center;

    gap: 6px;

    color: #455468;

    font-weight: 600;

}


.capacity-box {

    color: #667085;

    font-size: 10px;

}


.status-badge {

    display: inline-flex;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 8px;

    font-weight: 700;

}


.status-active {

    color: #147346;

    background: #e9f8f0;

}


.status-inactive {

    color: #667085;

    background: #eef1f5;

}


.action-buttons {

    display: flex;

    gap: 6px;

}


.icon-btn {

    width: 30px;

    height: 30px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: none;

    border-radius: 8px;

    font-size: 10px;

    transition: .2s ease;

}


.icon-btn:hover {

    transform: translateY(-2px);

}


.icon-btn.edit {

    color: var(--primary);

    background: #eef4ff;

}


.icon-btn.edit:hover {

    color: white;

    background: var(--primary);

}


.icon-btn.toggle {

    color: #b76b00;

    background: #fff4df;

}


.icon-btn.toggle:hover {

    color: white;

    background: #b76b00;

}


.icon-btn.delete {

    color: #b42318;

    background: #fff0f0;

}


.icon-btn.delete:hover {

    color: white;

    background: #b42318;

}


/* ================= EMPTY ================= */

.empty-state {

    padding: 70px 20px;

    text-align: center;

    color: var(--muted);

}


.empty-state-icon {

    width: 70px;

    height: 70px;

    margin: 0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 20px;

    background: #eef4ff;

    color: var(--primary);

    font-size: 25px;

}


.empty-state h5 {

    font-size: 14px;

    font-weight: 800;

    color: var(--text);

}


.empty-state p {

    margin: 5px 0 0;

    font-size: 10px;

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

    padding: 15px 22px;

    border-top:
        1px solid
        var(--border);

}


.form-label {

    margin-bottom: 6px;

    font-size: 10px;

    font-weight: 700;

}


.form-control,
.form-select {

    min-height: 43px;

    border-radius: 10px;

    border:
        1px solid
        #dfe5ec;

    font-size: 11px;

}


.form-control:focus,
.form-select:focus {

    border-color: var(--primary);

    box-shadow:
        0 0 0 3px
        rgba(13,110,253,.1);

}


.required {

    color: var(--danger);

}


/* ================= MOBILE ================= */

@media (max-width: 1000px) {

    .stats-grid {

        grid-template-columns:
            repeat(3, 1fr);

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


    .main {

        margin-left: 0;

    }


    .menu-toggle {

        display: block;

    }


    .topbar {

        padding:
            0 20px;

    }


    .content {

        padding:
            25px 20px;

    }


    .stats-grid {

        grid-template-columns:
            1fr;

    }

}


@media (max-width: 600px) {

    .admin-profile > div:last-child {

        display: none;

    }


    .page-actions {

        align-items:
            flex-start;

        gap: 15px;

        flex-direction:
            column;

    }


    .add-btn {

        width: 100%;

    }

}

@media print {
    .sidebar, .stats-grid, .no-print, .action-buttons, th:last-child, td:last-child { display: none !important; }
    .main { margin-left: 0 !important; }
}

</style>

</head>


<body>


<!-- ================= SIDEBAR ================= -->

<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>


<!-- ================= MAIN ================= -->

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

                <h1>Schedules</h1>

                <p>
                    Manage appointment dates, times and available slots
                </p>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>


            <div>

                <strong><?= e($_SESSION['username'] ?? 'Administrator') ?></strong>

                <span><?= e($_SESSION['email'] ?? 'RHU Arakan') ?></span>

            </div>

        </div>


    </header>


    <!-- CONTENT -->

    <section class="content">


        <?php if ($error): ?>

            <div class="alert-custom alert-danger-custom">

                <i class="fa-solid fa-circle-exclamation me-2"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- STATS -->

        <div class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>

                <div>

                    <div class="stat-label">
                        Total Schedules
                    </div>

                    <div class="stat-number">
                        <?= $totalSchedules ?>
                    </div>

                </div>

            </div>


            <div class="stat-card success">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>

                <div>

                    <div class="stat-label">
                        Active
                    </div>

                    <div class="stat-number">
                        <?= $activeSchedules ?>
                    </div>

                </div>

            </div>


            <div class="stat-card warning">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-pause"></i>

                </div>

                <div>

                    <div class="stat-label">
                        Inactive
                    </div>

                    <div class="stat-number">
                        <?= $inactiveSchedules ?>
                    </div>

                </div>

            </div>


        </div>


        <!-- PAGE ACTION -->

        <div class="page-actions">


            <div class="schedule-count">

                <?= $totalSchedules ?>

                schedule
                <?= $totalSchedules == 1 ? '' : 's' ?>

                configured

            </div>


            <div class="d-flex align-items-center gap-2">

                <button type="button" class="add-btn no-print" style="background:linear-gradient(135deg,#198754,#0d6efd);" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i>
                    Print List
                </button>

                <button
                    class="add-btn"
                    data-bs-toggle="modal"
                    data-bs-target="#scheduleModal"
                    onclick="openCreateModal()"
                    <?= empty($services) ? 'disabled' : '' ?>
                >

                    <i class="fa-solid fa-plus me-1"></i>

                    Add Schedule

                </button>

            </div>


        </div>


        <!-- TABLE -->

        <div class="section-card">


            <?php if (!empty($schedules)): ?>


                <div class="table-wrapper">


                    <table class="schedule-table">


                        <thead>

                            <tr>

                                <th>
                                    Service
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Time
                                </th>

                                <th>
                                    Slots
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($schedules as $schedule): ?>


                            <tr>


                                <!-- SERVICE -->

                                <td>

                                    <div class="service-name">

                                        <?= htmlspecialchars(
                                            $schedule['service_name']
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <span class="date-badge">

                                        <i class="fa-regular fa-calendar"></i>

                                        <?= htmlspecialchars(
                                            date(
                                                'M d, Y',
                                                strtotime(
                                                    $schedule['schedule_date']
                                                )
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- TIME -->

                                <td>

                                    <div class="time-box">

                                        <i class="fa-regular fa-clock"></i>

                                        <?= htmlspecialchars(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $schedule['start_time']
                                                )
                                            )
                                        ) ?>

                                        <span>–</span>

                                        <?= htmlspecialchars(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $schedule['end_time']
                                                )
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <!-- SLOTS -->

                                <td>

                                    <div class="capacity-box">

                                        <strong>
                                            <?= (int)$schedule['available_slots'] ?>
                                        </strong>

                                        /
                                        <?= (int)$schedule['max_slots'] ?>

                                        available

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php

                                    $isActive =
                                        strtolower(
                                            $schedule['status']
                                        ) === 'active';

                                    ?>


                                    <span
                                        class="status-badge
                                        <?= $isActive
                                            ? 'status-active'
                                            : 'status-inactive'
                                        ?>"
                                    >

                                        <i
                                            class="
                                            fa-solid
                                            <?= $isActive
                                                ? 'fa-circle-check'
                                                : 'fa-circle-pause'
                                            ?>
                                            me-1"
                                        ></i>

                                        <?= $isActive
                                            ? 'Active'
                                            : 'Inactive'
                                        ?>

                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>


                                    <div class="action-buttons">


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="icon-btn edit"
                                            title="Edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#scheduleModal"
                                            onclick='openEditModal(<?= htmlspecialchars(
                                                json_encode($schedule),
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>)'
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
                                                value="<?= (int)$schedule['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="icon-btn toggle"
                                                title="Toggle Status"
                                            >

                                                <i class="fa-solid fa-power-off"></i>

                                            </button>

                                        </form>


                                        <!-- DELETE -->

                                        <form
                                            method="post"
                                            style="display:inline;"
                                            onsubmit="return confirm('Delete this schedule? This cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$schedule['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="icon-btn delete"
                                                title="Delete"
                                            >

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


            <?php else: ?>


                <div class="empty-state">


                    <div class="empty-state-icon">

                        <i class="fa-solid fa-calendar-xmark"></i>

                    </div>


                    <h5>
                        No schedules yet
                    </h5>


                    <p>
                        Create a schedule to allow patients to book appointments.
                    </p>


                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- ================= MODAL ================= -->

<div
    class="modal fade"
    id="scheduleModal"
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
                    id="schedule_id"
                    value=""
                >


                <div class="modal-header">


                    <h5
                        class="modal-title"
                        id="scheduleModalLabel"
                    >
                        Add Schedule
                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>


                </div>


                <div class="modal-body">


                    <!-- SERVICE -->

                    <div class="mb-3">


                        <label class="form-label">

                            Service

                            <span class="required">*</span>

                        </label>


                        <select
                            class="form-select"
                            name="service_id"
                            id="schedule_service"
                            required
                        >

                            <option value="">
                                Select service
                            </option>


                            <?php foreach ($services as $service): ?>

                                <option
                                    value="<?= (int)$service['id'] ?>"
                                >

                                    <?= htmlspecialchars(
                                        $service['service_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>


                    </div>


                    <!-- DATE -->

                    <div class="mb-3">


                        <label class="form-label">

                            Schedule Date

                            <span class="required">*</span>

                        </label>


                        <input
                            type="date"
                            class="form-control"
                            name="schedule_date"
                            id="schedule_date"
                            min="<?= date('Y-m-d') ?>"
                            required
                        >


                    </div>


                    <!-- TIME -->

                    <div class="row g-3 mb-3">


                        <div class="col-6">


                            <label class="form-label">

                                Start Time

                                <span class="required">*</span>

                            </label>


                            <input
                                type="time"
                                class="form-control"
                                name="start_time"
                                id="schedule_start"
                                required
                            >


                        </div>


                        <div class="col-6">


                            <label class="form-label">

                                End Time

                                <span class="required">*</span>

                            </label>


                            <input
                                type="time"
                                class="form-control"
                                name="end_time"
                                id="schedule_end"
                                required
                            >


                        </div>


                    </div>


                    <!-- MAX SLOTS -->

                    <div class="mb-2">


                        <label class="form-label">

                            Maximum Slots

                            <span class="required">*</span>

                        </label>


                        <input
                            type="number"
                            class="form-control"
                            name="max_slots"
                            id="schedule_slots"
                            value="10"
                            min="1"
                            max="100"
                            required
                        >


                        <div
                            class="mt-2"
                            style="
                                color:#7b8798;
                                font-size:9px;
                            "
                        >

                            Number of patients allowed for this schedule.

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

                        <i class="fa-solid fa-save me-1"></i>

                        Save Schedule

                    </button>


                </div>


            </form>


        </div>


    </div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<script>


/* ================= MOBILE SIDEBAR ================= */

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


/* ================= CREATE ================= */

function openCreateModal() {

    document.getElementById(
        'scheduleModalLabel'
    ).textContent = 'Add Schedule';


    document.getElementById(
        'schedule_id'
    ).value = '';


    document.getElementById(
        'schedule_service'
    ).value = '';


    document.getElementById(
        'schedule_date'
    ).value = '';


    document.getElementById(
        'schedule_start'
    ).value = '';


    document.getElementById(
        'schedule_end'
    ).value = '';


    document.getElementById(
        'schedule_slots'
    ).value = '10';

}


/* ================= EDIT ================= */

function openEditModal(schedule) {


    document.getElementById(
        'scheduleModalLabel'
    ).textContent = 'Edit Schedule';


    document.getElementById(
        'schedule_id'
    ).value = schedule.id;


    document.getElementById(
        'schedule_service'
    ).value = schedule.service_id;


    document.getElementById(
        'schedule_date'
    ).value = schedule.schedule_date;


    document.getElementById(
        'schedule_start'
    ).value =
        schedule.start_time.substring(0, 5);


    document.getElementById(
        'schedule_end'
    ).value =
        schedule.end_time.substring(0, 5);


    document.getElementById(
        'schedule_slots'
    ).value = schedule.max_slots;

}


/* ================= CLOSE MOBILE SIDEBAR ================= */

document.addEventListener(
    'click',
    function(event) {

        if (
            window.innerWidth <= 850 &&
            sidebar.classList.contains('show') &&
            !sidebar.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {

            sidebar.classList.remove('show');

        }

    }
);


/* ================= TIME VALIDATION ================= */

const scheduleForm =
    document.querySelector(
        '#scheduleModal form'
    );


if (scheduleForm) {

    scheduleForm.addEventListener(
        'submit',
        function(event) {

            const start =
                document.getElementById(
                    'schedule_start'
                ).value;

            const end =
                document.getElementById(
                    'schedule_end'
                ).value;


            if (start && end && start >= end) {

                event.preventDefault();

                alert(
                    'End time must be later than start time.'
                );

            }

        }
    );

}

</script>


</body>

</html>