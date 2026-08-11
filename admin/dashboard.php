<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$total_patients = $pdo
    ->query("SELECT COUNT(*) FROM patients")
    ->fetchColumn();

$today_appointments = $pdo->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE appointment_date = CURDATE()
");
$today_appointments->execute();
$today_count = $today_appointments->fetchColumn();

$pending = $pdo
    ->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")
    ->fetchColumn();

$approved = $pdo
    ->query("SELECT COUNT(*) FROM appointments WHERE status = 'Approved'")
    ->fetchColumn();

/*
|--------------------------------------------------------------------------
| RECENT APPOINTMENTS
|--------------------------------------------------------------------------
*/

$recent_stmt = $pdo->query("
    SELECT
        a.id,
        a.appointment_date,
        a.appointment_time,
        a.status,
        a.reason,
        p.first_name,
        p.last_name,
        s.service_name
    FROM appointments a
    INNER JOIN patients p
        ON a.patient_id = p.id
    INNER JOIN services s
        ON a.service_id = s.id
    ORDER BY a.created_at DESC
    LIMIT 6
");

$recent_appointments = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TODAY'S APPOINTMENTS
|--------------------------------------------------------------------------
*/

$today_stmt = $pdo->query("
    SELECT
        a.id,
        a.appointment_time,
        a.status,
        p.first_name,
        p.last_name,
        s.service_name
    FROM appointments a
    INNER JOIN patients p
        ON a.patient_id = p.id
    INNER JOIN services s
        ON a.service_id = s.id
    WHERE a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC
    LIMIT 5
");

$today_appointments_list = $today_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | CareSched</title>

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
            --primary: #0d6efd;
            --primary-dark: #0758c9;
            --sidebar: #0b1f3a;
            --sidebar-light: #14345c;
            --bg: #f4f7fb;
            --text: #172033;
            --muted: #7b8798;
            --border: #e6ebf2;
            --white: #ffffff;
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

        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;

            width: 260px;
            height: 100vh;

            background:
                linear-gradient(
                    180deg,
                    #0b1f3a,
                    #102d50
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

            padding: 10px 12px 28px;

            border-bottom: 1px solid rgba(255,255,255,.08);

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

            background: rgba(13,110,253,.25);

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
            color: #fff;

            background: rgba(220,53,69,.18);
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            margin-left: 260px;

            min-height: 100vh;

            transition: .3s ease;
        }

        /* TOPBAR */

        .topbar {
            height: 76px;

            background: white;

            border-bottom: 1px solid var(--border);

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

            font-size: 15px;
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

        /* CONTENT */

        .content {
            padding: 30px 35px;
        }

        .welcome {
            margin-bottom: 28px;
        }

        .welcome h2 {
            font-size: 25px;

            font-weight: 800;

            margin: 0 0 7px;
        }

        .welcome p {
            margin: 0;

            color: var(--muted);

            font-size: 13px;
        }

        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stat-card {
            position: relative;

            background: white;

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 22px;

            height: 100%;

            overflow: hidden;

            transition: .25s ease;

            box-shadow:
                0 5px 20px rgba(20,40,70,.04);
        }

        .stat-card:hover {
            transform: translateY(-5px);

            box-shadow:
                0 15px 35px rgba(20,40,70,.10);
        }

        .stat-card::after {
            content: "";

            position: absolute;

            width: 90px;
            height: 90px;

            border-radius: 50%;

            background: rgba(13,110,253,.05);

            right: -30px;
            top: -30px;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-icon {
            width: 45px;
            height: 45px;

            border-radius: 13px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 17px;
        }

        .blue {
            background: #eaf2ff;
            color: #0d6efd;
        }

        .green {
            background: #e9f8f0;
            color: #198754;
        }

        .orange {
            background: #fff4df;
            color: #f59f00;
        }

        .purple {
            background: #f0eaff;
            color: #7950f2;
        }

        .stat-label {
            color: var(--muted);

            font-size: 11px;

            font-weight: 600;

            margin-top: 18px;

            text-transform: uppercase;

            letter-spacing: .4px;
        }

        .stat-number {
            font-size: 30px;

            font-weight: 800;

            margin-top: 3px;
        }

        .stat-link {
            display: inline-block;

            margin-top: 10px;

            color: var(--primary);

            font-size: 11px;

            font-weight: 600;
        }

        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .section-card {
            background: white;

            border: 1px solid var(--border);

            border-radius: 18px;

            box-shadow:
                0 5px 20px rgba(20,40,70,.04);

            overflow: hidden;
        }

        .section-header {
            padding: 20px 22px;

            border-bottom: 1px solid var(--border);

            display: flex;

            justify-content: space-between;

            align-items: center;
        }

        .section-header h3 {
            margin: 0;

            font-size: 15px;

            font-weight: 800;
        }

        .section-header span {
            color: var(--muted);

            font-size: 11px;
        }

        .quick-actions {
            padding: 20px;

            display: grid;

            grid-template-columns: repeat(4, 1fr);

            gap: 12px;
        }

        .quick-btn {
            display: flex;

            align-items: center;

            gap: 11px;

            padding: 14px;

            border: 1px solid var(--border);

            border-radius: 13px;

            color: var(--text);

            background: #fff;

            transition: .2s ease;
        }

        .quick-btn:hover {
            border-color: #bcd4fa;

            background: #f6f9ff;

            transform: translateY(-2px);

            color: var(--primary);
        }

        .quick-btn i {
            width: 36px;
            height: 36px;

            border-radius: 10px;

            display: flex;

            align-items: center;
            justify-content: center;

            background: #eef4ff;

            color: var(--primary);
        }

        .quick-btn strong {
            display: block;

            font-size: 11px;
        }

        .quick-btn small {
            display: block;

            color: var(--muted);

            font-size: 9px;

            margin-top: 2px;
        }

        /* =====================================================
           TABLE
        ===================================================== */

        .appointment-table {
            width: 100%;

            border-collapse: collapse;
        }

        .appointment-table th {
            padding: 13px 22px;

            color: #8a96a8;

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: .5px;

            font-weight: 700;

            background: #fafbfd;

            border-bottom: 1px solid var(--border);

            text-align: left;
        }

        .appointment-table td {
            padding: 14px 22px;

            border-bottom: 1px solid #f0f2f5;

            font-size: 11px;

            vertical-align: middle;
        }

        .patient-name {
            font-weight: 700;
        }

        .service-name {
            color: var(--muted);
        }

        .status {
            display: inline-flex;

            align-items: center;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 700;
        }

        .status-pending {
            background: #fff4df;

            color: #b76b00;
        }

        .status-approved {
            background: #e9f8f0;

            color: #147346;
        }

        .status-rejected {
            background: #fff0f0;

            color: #b42318;
        }

        .status-completed {
            background: #eef1f5;

            color: #667085;
        }

        .view-btn {
            width: 30px;
            height: 30px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border-radius: 8px;

            background: #eef4ff;

            color: var(--primary);

            font-size: 11px;
        }

        .view-btn:hover {
            background: var(--primary);

            color: white;
        }

        .empty-state {
            padding: 45px 20px;

            text-align: center;

            color: var(--muted);
        }

        .empty-state i {
            font-size: 30px;

            margin-bottom: 12px;

            opacity: .5;
        }

        .empty-state p {
            margin: 0;

            font-size: 12px;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 1000px) {

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

        }

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

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .admin-profile > div {
                display: none;
            }

            .appointment-table {
                min-width: 650px;
            }

            .table-wrapper {
                overflow-x: auto;
            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">

        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>

        <div class="brand-text">
            <strong>CareSched</strong>
            <span>Admin Portal</span>
        </div>

    </div>


    <div class="menu-label">
        Main Menu
    </div>

    <a
        href="dashboard.php"
        class="sidebar-link active"
    >
        <i class="fa-solid fa-grid-2"></i>
        Dashboard
    </a>

    <a
        href="appointments.php"
        class="sidebar-link"
    >
        <i class="fa-solid fa-calendar-check"></i>
        Appointments
    </a>

    <a
        href="patients.php"
        class="sidebar-link"
    >
        <i class="fa-solid fa-users"></i>
        Patients
    </a>


    <div class="menu-label">
        Management
    </div>

    <a
        href="services.php"
        class="sidebar-link"
    >
        <i class="fa-solid fa-stethoscope"></i>
        Services
    </a>

    <a
        href="schedules.php"
        class="sidebar-link"
    >
        <i class="fa-solid fa-calendar-days"></i>
        Schedules
    </a>

    <a
        href="notifications.php"
        class="sidebar-link"
    >
        <i class="fa-solid fa-bell"></i>
        Notifications
    </a>


    <div class="menu-label">
        System
    </div>

    <a
        href="settings.php"
        class="sidebar-link"
    >
        <i class="fa-solid fa-gear"></i>
        Settings
    </a>


    <div class="sidebar-bottom">

        <a
            href="/caresched/logout.php"
            class="sidebar-link logout-link"
            onclick="return confirm('Are you sure you want to logout?');"
        >
            <i class="fa-solid fa-right-from-bracket"></i>
            Logout
        </a>

    </div>

</aside>


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
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="page-title">

                <h1>Dashboard</h1>

                <p>
                    CareSched Administration
                </p>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>

            <div>

                <strong>Administrator</strong>

                <span>RHU Arakan</span>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <!-- WELCOME -->

        <div class="welcome">

            <h2>
                Good day, Administrator 👋
            </h2>

            <p>
                Here's what's happening with your healthcare appointments today.
            </p>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="row g-3 mb-4">


            <!-- PATIENTS -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon blue">
                            <i class="fa-solid fa-users"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Total Patients
                    </div>

                    <div class="stat-number">
                        <?= e($total_patients) ?>
                    </div>

                    <a
                        href="patients.php"
                        class="stat-link"
                    >
                        View patients
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>

                </div>

            </div>


            <!-- TODAY -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon green">
                            <i class="fa-solid fa-calendar-day"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Today's Appointments
                    </div>

                    <div class="stat-number">
                        <?= e($today_count) ?>
                    </div>

                    <a
                        href="appointments.php?date=today"
                        class="stat-link"
                    >
                        View today's schedule
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>

                </div>

            </div>


            <!-- PENDING -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon orange">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Pending Appointments
                    </div>

                    <div class="stat-number">
                        <?= e($pending) ?>
                    </div>

                    <a
                        href="appointments.php?status=Pending"
                        class="stat-link"
                    >
                        Review pending
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>

                </div>

            </div>


            <!-- APPROVED -->

            <div class="col-xl-3 col-md-6">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon purple">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Approved Appointments
                    </div>

                    <div class="stat-number">
                        <?= e($approved) ?>
                    </div>

                    <a
                        href="appointments.php?status=Approved"
                        class="stat-link"
                    >
                        View approved
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>

                </div>

            </div>

        </div>


        <!-- =================================================
             QUICK ACTIONS
        ================================================== -->

        <div class="section-card mb-4">

            <div class="section-header">

                <div>

                    <h3>
                        Quick Actions
                    </h3>

                    <span>
                        Frequently used administration tools
                    </span>

                </div>

            </div>


            <div class="quick-actions">


                <a
                    href="appointments.php"
                    class="quick-btn"
                >

                    <i class="fa-solid fa-calendar-check"></i>

                    <div>

                        <strong>
                            Manage Appointments
                        </strong>

                        <small>
                            Approve or update bookings
                        </small>

                    </div>

                </a>


                <a
                    href="patients.php"
                    class="quick-btn"
                >

                    <i class="fa-solid fa-user-group"></i>

                    <div>

                        <strong>
                            Patient Records
                        </strong>

                        <small>
                            View registered patients
                        </small>

                    </div>

                </a>


                <a
                    href="services.php"
                    class="quick-btn"
                >

                    <i class="fa-solid fa-stethoscope"></i>

                    <div>

                        <strong>
                            Healthcare Services
                        </strong>

                        <small>
                            Manage available services
                        </small>

                    </div>

                </a>


                <a
                    href="schedules.php"
                    class="quick-btn"
                >

                    <i class="fa-solid fa-calendar-plus"></i>

                    <div>

                        <strong>
                            Manage Schedules
                        </strong>

                        <small>
                            Set appointment schedules
                        </small>

                    </div>

                </a>

            </div>

        </div>


        <!-- =================================================
             TABLES
        ================================================== -->

        <div class="row g-4">


            <!-- RECENT APPOINTMENTS -->

            <div class="col-xl-8">

                <div class="section-card">

                    <div class="section-header">

                        <div>

                            <h3>
                                Recent Appointments
                            </h3>

                            <span>
                                Latest patient bookings
                            </span>

                        </div>

                        <a
                            href="appointments.php"
                            class="stat-link"
                        >
                            View all
                            <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>

                    </div>


                    <?php if (!empty($recent_appointments)): ?>

                        <div class="table-wrapper">

                            <table class="appointment-table">

                                <thead>

                                    <tr>

                                        <th>Patient</th>

                                        <th>Service</th>

                                        <th>Date</th>

                                        <th>Time</th>

                                        <th>Status</th>

                                        <th></th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php foreach ($recent_appointments as $appointment): ?>

                                    <?php

                                    $status = strtolower(
                                        $appointment['status']
                                    );

                                    $status_class = 'status-pending';

                                    if ($status === 'approved') {
                                        $status_class = 'status-approved';
                                    } elseif (
                                        $status === 'rejected'
                                    ) {
                                        $status_class = 'status-rejected';
                                    } elseif (
                                        $status === 'completed'
                                    ) {
                                        $status_class = 'status-completed';
                                    }

                                    ?>

                                    <tr>

                                        <td>

                                            <div class="patient-name">

                                                <?= e(
                                                    $appointment['first_name']
                                                    . ' '
                                                    . $appointment['last_name']
                                                ) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <span class="service-name">

                                                <?= e(
                                                    $appointment['service_name']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e(
                                                date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $appointment['appointment_date']
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= e(
                                                date(
                                                    'h:i A',
                                                    strtotime(
                                                        $appointment['appointment_time']
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <span
                                                class="status <?= $status_class ?>"
                                            >

                                                <?= e(
                                                    $appointment['status']
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                                href="appointment-view.php?id=<?= (int)$appointment['id'] ?>"
                                                class="view-btn"
                                                title="View appointment"
                                            >

                                                <i class="fa-solid fa-eye"></i>

                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">

                            <i class="fa-solid fa-calendar-xmark"></i>

                            <p>
                                No recent appointments found.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- TODAY -->

            <div class="col-xl-4">

                <div class="section-card">

                    <div class="section-header">

                        <div>

                            <h3>
                                Today's Schedule
                            </h3>

                            <span>
                                <?= date('F d, Y') ?>
                            </span>

                        </div>

                    </div>


                    <?php if (!empty($today_appointments_list)): ?>

                        <div class="p-2">

                            <?php foreach ($today_appointments_list as $today): ?>

                                <a
                                    href="appointment-view.php?id=<?= (int)$today['id'] ?>"
                                    class="d-flex align-items-center gap-3 p-3 border-bottom text-dark"
                                >

                                    <div
                                        class="stat-icon blue"
                                        style="width:38px;height:38px;font-size:12px;"
                                    >

                                        <i class="fa-solid fa-calendar-check"></i>

                                    </div>

                                    <div class="flex-grow-1">

                                        <div
                                            style="
                                                font-size:11px;
                                                font-weight:700;
                                            "
                                        >

                                            <?= e(
                                                $today['first_name']
                                                . ' '
                                                . $today['last_name']
                                            ) ?>

                                        </div>

                                        <div
                                            style="
                                                font-size:9px;
                                                color:#7b8798;
                                                margin-top:3px;
                                            "
                                        >

                                            <?= e(
                                                $today['service_name']
                                            ) ?>

                                        </div>

                                    </div>

                                    <div
                                        style="
                                            font-size:10px;
                                            font-weight:700;
                                            color:#0d6efd;
                                        "
                                    >

                                        <?= e(
                                            date(
                                                'h:i A',
                                                strtotime(
                                                    $today['appointment_time']
                                                )
                                            )
                                        ) ?>

                                    </div>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-state">

                            <i class="fa-solid fa-calendar-day"></i>

                            <p>
                                No appointments scheduled for today.
                            </p>

                        </div>

                    <?php endif; ?>


                    <div class="p-3">

                        <a
                            href="appointments.php?date=today"
                            class="btn btn-primary w-100"
                            style="
                                border-radius:10px;
                                font-size:11px;
                                font-weight:700;
                            "
                        >

                            <i class="fa-solid fa-calendar-days me-2"></i>

                            View Full Schedule

                        </a>

                    </div>

                </div>

            </div>

        </div>


    </section>

</main>


<!-- =========================================================
     MOBILE SIDEBAR SCRIPT
========================================================= -->

<script>

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


/*
|--------------------------------------------------------------------------
| CLOSE SIDEBAR WHEN CLICKING OUTSIDE ON MOBILE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function (event) {

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

</script>


</body>
</html>