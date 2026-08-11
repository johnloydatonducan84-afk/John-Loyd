<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('patient');

$user_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Patient Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM patients
    WHERE user_id = :uid
    LIMIT 1
");

$stmt->execute([
    'uid' => $user_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient profile not found.');
}


/*
|--------------------------------------------------------------------------
| Upcoming Appointments
|--------------------------------------------------------------------------
*/

$upcoming = $pdo->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE patient_id = :pid
    AND appointment_date >= CURDATE()
    AND status IN ('Pending', 'Approved', 'Confirmed')
");

$upcoming->execute([
    'pid' => $patient['id']
]);

$upcoming_count = $upcoming->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Appointments
|--------------------------------------------------------------------------
*/

$pending = $pdo->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE patient_id = :pid
    AND status = 'Pending'
");

$pending->execute([
    'pid' => $patient['id']
]);

$pending_count = $pending->fetchColumn();


/*
|--------------------------------------------------------------------------
| Completed Appointments
|--------------------------------------------------------------------------
*/

$completed = $pdo->prepare("
    SELECT COUNT(*)
    FROM appointments
    WHERE patient_id = :pid
    AND status = 'Completed'
");

$completed->execute([
    'pid' => $patient['id']
]);

$completed_count = $completed->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Appointments
|--------------------------------------------------------------------------
*/

$appointments = $pdo->prepare("
    SELECT *
    FROM appointments
    WHERE patient_id = :pid
    ORDER BY appointment_date DESC, appointment_time DESC
    LIMIT 5
");

$appointments->execute([
    'pid' => $patient['id']
]);

$recent_appointments = $appointments->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helper for Status Badge
|--------------------------------------------------------------------------
*/

function statusClass($status)
{
    switch (strtolower($status)) {

        case 'approved':
        case 'confirmed':
            return 'status-approved';

        case 'pending':
            return 'status-pending';

        case 'completed':
            return 'status-completed';

        case 'cancelled':
        case 'rejected':
            return 'status-cancelled';

        default:
            return 'status-default';
    }
}

?>

<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Patient Dashboard - CareSched
    </title>


    <!-- Google Font -->

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <!-- Font Awesome -->

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family: 'Inter', sans-serif;

            background:
                radial-gradient(
                    circle at 0% 0%,
                    rgba(37, 99, 235, .08),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 100% 100%,
                    rgba(14, 165, 233, .07),
                    transparent 28%
                ),
                #f8fafc;

            color: #172033;
        }


        /* =====================================================
           NAVBAR
        ===================================================== */

        .top-navbar {

            height: 72px;

            background: rgba(255,255,255,.94);

            backdrop-filter: blur(15px);

            border-bottom: 1px solid #e8eef6;

            position: sticky;

            top: 0;

            z-index: 1000;
        }


        .navbar-inner {

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 10px;

            color: #172033;

            text-decoration: none;

            font-size: 19px;

            font-weight: 800;
        }


        .brand:hover {
            color: #172033;
        }


        .brand-icon {

            width: 40px;

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            box-shadow:
                0 8px 20px
                rgba(37,99,235,.18);
        }


        .brand span {
            color: #2563eb;
        }


        .user-menu {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .user-avatar {

            width: 38px;

            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            color: #2563eb;

            background: #eff6ff;

            font-size: 13px;
        }


        .user-info {

            line-height: 1.2;
        }


        .user-name {

            color: #1e293b;

            font-size: 10px;

            font-weight: 700;
        }


        .user-role {

            margin-top: 3px;

            color: #94a3b8;

            font-size: 8px;
        }


        .logout-btn {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 9px 13px;

            border: 1px solid #e2e8f0;

            border-radius: 9px;

            color: #64748b;

            background: white;

            font-size: 9px;

            font-weight: 600;

            text-decoration: none;

            transition: .2s ease;
        }


        .logout-btn:hover {

            color: #ef4444;

            border-color: #fecaca;

            background: #fffafa;
        }


        /* =====================================================
           MAIN
        ===================================================== */

        .dashboard {

            padding:
                35px 0 60px;
        }


        /* =====================================================
           WELCOME
        ===================================================== */

        .welcome-card {

            position: relative;

            overflow: hidden;

            padding: 30px;

            border-radius: 20px;

            color: white;

            background:
                linear-gradient(
                    120deg,
                    #1d4ed8,
                    #2563eb 50%,
                    #0ea5e9
                );

            box-shadow:
                0 18px 45px
                rgba(37,99,235,.18);

            margin-bottom: 25px;
        }


        .welcome-card::after {

            content: "";

            position: absolute;

            width: 230px;

            height: 230px;

            border-radius: 50%;

            right: -75px;

            top: -100px;

            background:
                rgba(255,255,255,.08);
        }


        .welcome-card::before {

            content: "";

            position: absolute;

            width: 130px;

            height: 130px;

            border-radius: 50%;

            right: 130px;

            bottom: -80px;

            background:
                rgba(255,255,255,.06);
        }


        .welcome-content {

            position: relative;

            z-index: 2;
        }


        .welcome-label {

            margin-bottom: 8px;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1.2px;

            opacity: .8;
        }


        .welcome-card h1 {

            margin: 0 0 8px;

            font-size: 26px;

            font-weight: 800;

            letter-spacing: -.5px;
        }


        .welcome-card p {

            max-width: 570px;

            margin: 0;

            color: rgba(255,255,255,.82);

            font-size: 11px;

            line-height: 1.7;
        }


        .welcome-action {

            position: relative;

            z-index: 2;

            margin-top: 20px;
        }


        .book-btn {

            display: inline-flex;

            align-items: center;

            gap: 9px;

            padding: 11px 17px;

            border: none;

            border-radius: 10px;

            color: #1d4ed8;

            background: white;

            font-size: 10px;

            font-weight: 700;

            text-decoration: none;

            box-shadow:
                0 8px 20px
                rgba(0,0,0,.1);

            transition: .2s ease;
        }


        .book-btn:hover {

            color: #1d4ed8;

            transform:
                translateY(-2px);

            box-shadow:
                0 12px 25px
                rgba(0,0,0,.15);
        }


        /* =====================================================
           SECTION TITLE
        ===================================================== */

        .section-title {

            margin-bottom: 14px;

            color: #1e293b;

            font-size: 13px;

            font-weight: 800;
        }


        .section-subtitle {

            margin-top: -9px;

            margin-bottom: 18px;

            color: #94a3b8;

            font-size: 9px;
        }


        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stat-card {

            position: relative;

            overflow: hidden;

            min-height: 125px;

            padding: 21px;

            border: 1px solid #e7edf5;

            border-radius: 17px;

            background: white;

            box-shadow:
                0 10px 30px
                rgba(15,23,42,.04);

            transition: .25s ease;
        }


        .stat-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 16px 35px
                rgba(15,23,42,.07);
        }


        .stat-icon {

            width: 38px;

            height: 38px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 11px;

            margin-bottom: 12px;

            font-size: 13px;
        }


        .icon-blue {

            color: #2563eb;

            background: #eff6ff;
        }


        .icon-orange {

            color: #f59e0b;

            background: #fffbeb;
        }


        .icon-green {

            color: #10b981;

            background: #ecfdf5;
        }


        .stat-title {

            color: #94a3b8;

            font-size: 8px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .5px;
        }


        .stat-number {

            margin-top: 3px;

            color: #172033;

            font-size: 24px;

            font-weight: 800;
        }


        /* =====================================================
           APPOINTMENT CARD
        ===================================================== */

        .content-card {

            padding: 22px;

            border: 1px solid #e7edf5;

            border-radius: 17px;

            background: white;

            box-shadow:
                0 10px 30px
                rgba(15,23,42,.04);
        }


        .appointment-item {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 14px 0;

            border-bottom:
                1px solid #eef2f7;
        }


        .appointment-item:last-child {

            border-bottom: none;

            padding-bottom: 0;
        }


        .appointment-date {

            width: 48px;

            min-width: 48px;

            height: 52px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            border-radius: 11px;

            color: #2563eb;

            background: #eff6ff;
        }


        .appointment-date .month {

            font-size: 7px;

            font-weight: 800;

            text-transform: uppercase;
        }


        .appointment-date .day {

            font-size: 17px;

            font-weight: 800;
        }


        .appointment-info {

            flex: 1;

            min-width: 0;
        }


        .appointment-service {

            margin-bottom: 4px;

            color: #334155;

            font-size: 10px;

            font-weight: 800;
        }


        .appointment-details {

            display: flex;

            gap: 12px;

            color: #94a3b8;

            font-size: 8px;
        }


        .appointment-details i {

            margin-right: 3px;
        }


        .status {

            display: inline-flex;

            align-items: center;

            padding: 5px 8px;

            border-radius: 20px;

            font-size: 7px;

            font-weight: 800;

            text-transform: uppercase;

            white-space: nowrap;
        }


        .status-approved {

            color: #047857;

            background: #ecfdf5;
        }


        .status-pending {

            color: #b45309;

            background: #fffbeb;
        }


        .status-completed {

            color: #2563eb;

            background: #eff6ff;
        }


        .status-cancelled {

            color: #b91c1c;

            background: #fef2f2;
        }


        .status-default {

            color: #475569;

            background: #f1f5f9;
        }


        .empty-state {

            padding: 35px 15px;

            text-align: center;
        }


        .empty-icon {

            width: 48px;

            height: 48px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin: 0 auto 12px;

            border-radius: 14px;

            color: #2563eb;

            background: #eff6ff;
        }


        .empty-state h6 {

            margin-bottom: 5px;

            color: #334155;

            font-size: 11px;

            font-weight: 700;
        }


        .empty-state p {

            margin: 0;

            color: #94a3b8;

            font-size: 9px;
        }


        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .quick-action {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 13px;

            margin-bottom: 9px;

            border: 1px solid #edf1f6;

            border-radius: 11px;

            color: #334155;

            background: white;

            text-decoration: none;

            transition: .2s ease;
        }


        .quick-action:last-child {

            margin-bottom: 0;
        }


        .quick-action:hover {

            border-color: #bfdbfe;

            background: #f8fbff;

            transform:
                translateX(2px);
        }


        .quick-icon {

            width: 34px;

            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 9px;

            color: #2563eb;

            background: #eff6ff;

            font-size: 11px;
        }


        .quick-text strong {

            display: block;

            color: #334155;

            font-size: 9px;
        }


        .quick-text span {

            display: block;

            margin-top: 2px;

            color: #94a3b8;

            font-size: 7px;
        }


        .quick-arrow {

            margin-left: auto;

            color: #cbd5e1;

            font-size: 9px;
        }


        /* =====================================================
           PROFILE
        ===================================================== */

        .profile-card {

            padding: 20px;

            border: 1px solid #e7edf5;

            border-radius: 17px;

            background: white;

            box-shadow:
                0 10px 30px
                rgba(15,23,42,.04);
        }


        .profile-top {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 17px;
        }


        .profile-avatar {

            width: 45px;

            height: 45px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 13px;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            font-size: 15px;
        }


        .profile-name {

            color: #1e293b;

            font-size: 11px;

            font-weight: 800;
        }


        .profile-label {

            margin-top: 3px;

            color: #94a3b8;

            font-size: 8px;
        }


        .profile-row {

            display: flex;

            justify-content: space-between;

            padding: 8px 0;

            border-bottom:
                1px solid #f1f5f9;

            font-size: 8px;
        }


        .profile-row:last-child {

            border-bottom: none;
        }


        .profile-row span {

            color: #94a3b8;
        }


        .profile-row strong {

            max-width: 160px;

            color: #475569;

            font-weight: 600;

            text-align: right;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .dashboard-footer {

            padding-top: 25px;

            text-align: center;

            color: #94a3b8;

            font-size: 8px;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 767px) {

            .top-navbar {

                height: auto;

                padding: 12px 0;
            }


            .user-info {

                display: none;
            }


            .dashboard {

                padding-top: 25px;
            }


            .welcome-card {

                padding: 24px;
            }


            .welcome-card h1 {

                font-size: 22px;
            }


            .appointment-item {

                align-items: flex-start;
            }


            .status {

                margin-left: auto;
            }

        }


        @media (max-width: 500px) {

            .logout-btn span {

                display: none;
            }


            .appointment-details {

                flex-direction: column;

                gap: 3px;
            }


            .appointment-item {

                flex-wrap: wrap;
            }


            .status {

                margin-left: 61px;

                margin-top: -5px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="top-navbar">

    <div class="container">

        <div class="navbar-inner">


            <a
                href="/caresched/"
                class="brand"
            >

                <div class="brand-icon">

                    <i
                        class="fa-solid fa-heart-pulse"
                    ></i>

                </div>

                Care<span>Sched</span>

            </a>


            <div class="user-menu">


                <div class="user-avatar">

                    <i
                        class="fa-solid fa-user"
                    ></i>

                </div>


                <div class="user-info">

                    <div class="user-name">

                        <?= e(
                            $patient['first_name']
                            . ' '
                            . $patient['last_name']
                        ) ?>

                    </div>

                    <div class="user-role">
                        Patient
                    </div>

                </div>


                <a
                    href="/caresched/logout.php"
                    class="logout-btn"
                >

                    <i
                        class="fa-solid fa-right-from-bracket"
                    ></i>

                    <span>Logout</span>

                </a>


            </div>

        </div>

    </div>

</nav>


<!-- =====================================================
     DASHBOARD
===================================================== -->

<main class="dashboard">

    <div class="container">


        <!-- =================================================
             WELCOME
        ================================================= -->

        <div class="welcome-card">

            <div class="welcome-content">


                <div class="welcome-label">

                    <i
                        class="fa-solid fa-hand-wave me-1"
                    ></i>

                    Patient Portal

                </div>


                <h1>

                    Good day,
                    <?= e(
                        $patient['first_name']
                    ) ?>!

                </h1>


                <p>

                    Manage your healthcare appointments,
                    view your schedules, and stay updated
                    with CareSched notifications.

                </p>


                <div class="welcome-action">

                    <a
                        href="/caresched/patient/book-appointment.php"
                        class="book-btn"
                    >

                        <i
                            class="fa-solid fa-calendar-plus"
                        ></i>

                        Book an Appointment

                    </a>

                </div>

            </div>

        </div>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <div class="section-title">
            Appointment Overview
        </div>


        <div class="row g-3 mb-4">


            <!-- UPCOMING -->

            <div class="col-md-4">

                <div class="stat-card">

                    <div
                        class="stat-icon icon-blue"
                    >

                        <i
                            class="fa-solid fa-calendar-check"
                        ></i>

                    </div>


                    <div class="stat-title">
                        Upcoming Appointments
                    </div>


                    <div class="stat-number">

                        <?= e(
                            $upcoming_count
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- PENDING -->

            <div class="col-md-4">

                <div class="stat-card">

                    <div
                        class="stat-icon icon-orange"
                    >

                        <i
                            class="fa-solid fa-hourglass-half"
                        ></i>

                    </div>


                    <div class="stat-title">
                        Pending Appointments
                    </div>


                    <div class="stat-number">

                        <?= e(
                            $pending_count
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="col-md-4">

                <div class="stat-card">

                    <div
                        class="stat-icon icon-green"
                    >

                        <i
                            class="fa-solid fa-circle-check"
                        ></i>

                    </div>


                    <div class="stat-title">
                        Completed Appointments
                    </div>


                    <div class="stat-number">

                        <?= e(
                            $completed_count
                        ) ?>

                    </div>

                </div>

            </div>


        </div>


        <!-- =================================================
             CONTENT
        ================================================= -->

        <div class="row g-4">


            <!-- =============================================
                 APPOINTMENTS
            ============================================== -->

            <div class="col-lg-8">


                <div class="content-card">


                    <div class="section-title mb-1">

                        <i
                            class="fa-solid fa-calendar-days me-2"
                            style="color:#2563eb;"
                        ></i>

                        My Appointments

                    </div>


                    <div class="section-subtitle">

                        Your latest appointment records

                    </div>


                    <?php if (
                        !empty($recent_appointments)
                    ): ?>


                        <?php foreach (
                            $recent_appointments
                            as $appointment
                        ): ?>


                            <?php

                            $timestamp = strtotime(
                                $appointment['appointment_date']
                            );

                            $month = date(
                                'M',
                                $timestamp
                            );

                            $day = date(
                                'd',
                                $timestamp
                            );

                            $time = date(
                                'h:i A',
                                strtotime(
                                    $appointment['appointment_time']
                                )
                            );

                            ?>


                            <div class="appointment-item">


                                <div class="appointment-date">

                                    <div class="month">
                                        <?= e($month) ?>
                                    </div>

                                    <div class="day">
                                        <?= e($day) ?>
                                    </div>

                                </div>


                                <div class="appointment-info">


                                    <div class="appointment-service">

                                        <?= e(
                                            $appointment['service']
                                            ?? 'Healthcare Appointment'
                                        ) ?>

                                    </div>


                                    <div class="appointment-details">

                                        <span>

                                            <i
                                                class="fa-regular fa-clock"
                                            ></i>

                                            <?= e($time) ?>

                                        </span>


                                        <span>

                                            <i
                                                class="fa-solid fa-calendar"
                                            ></i>

                                            <?= e(
                                                date(
                                                    'F d, Y',
                                                    $timestamp
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                </div>


                                <div>

                                    <span
                                        class="status <?= e(
                                            statusClass(
                                                $appointment['status']
                                            )
                                        ) ?>"
                                    >

                                        <?= e(
                                            $appointment['status']
                                        ) ?>

                                    </span>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <div class="empty-state">


                            <div class="empty-icon">

                                <i
                                    class="fa-regular fa-calendar-xmark"
                                ></i>

                            </div>


                            <h6>
                                No appointments yet
                            </h6>


                            <p>
                                You don't have any appointment
                                records.
                            </p>


                            <a
                                href="/caresched/patient/book-appointment.php"
                                class="book-btn mt-3"
                            >

                                <i
                                    class="fa-solid fa-calendar-plus"
                                ></i>

                                Book Your First Appointment

                            </a>


                        </div>


                    <?php endif; ?>


                </div>


            </div>


            <!-- =============================================
                 RIGHT SIDEBAR
            ============================================== -->

            <div class="col-lg-4">


                <!-- QUICK ACTIONS -->

                <div class="content-card mb-4">


                    <div class="section-title mb-3">

                        <i
                            class="fa-solid fa-bolt me-2"
                            style="color:#2563eb;"
                        ></i>

                        Quick Actions

                    </div>


                    <a
                        href="/caresched/patient/book-appointment.php"
                        class="quick-action"
                    >

                        <div class="quick-icon">

                            <i
                                class="fa-solid fa-calendar-plus"
                            ></i>

                        </div>


                        <div class="quick-text">

                            <strong>
                                Book Appointment
                            </strong>

                            <span>
                                Schedule a healthcare visit
                            </span>

                        </div>


                        <i
                            class="fa-solid fa-chevron-right quick-arrow"
                        ></i>

                    </a>


                    <a
                        href="/caresched/patient/appointments.php"
                        class="quick-action"
                    >

                        <div class="quick-icon">

                            <i
                                class="fa-solid fa-calendar-days"
                            ></i>

                        </div>


                        <div class="quick-text">

                            <strong>
                                My Appointments
                            </strong>

                            <span>
                                View your appointment history
                            </span>

                        </div>


                        <i
                            class="fa-solid fa-chevron-right quick-arrow"
                        ></i>

                    </a>


                    <a
                        href="/caresched/patient/profile.php"
                        class="quick-action"
                    >

                        <div class="quick-icon">

                            <i
                                class="fa-solid fa-user-pen"
                            ></i>

                        </div>


                        <div class="quick-text">

                            <strong>
                                My Profile
                            </strong>

                            <span>
                                Manage your information
                            </span>

                        </div>


                        <i
                            class="fa-solid fa-chevron-right quick-arrow"
                        ></i>

                    </a>


                </div>


                <!-- PROFILE -->

                <div class="profile-card">


                    <div class="profile-top">


                        <div class="profile-avatar">

                            <i
                                class="fa-solid fa-user"
                            ></i>

                        </div>


                        <div>

                            <div class="profile-name">

                                <?= e(
                                    $patient['first_name']
                                    . ' '
                                    . $patient['last_name']
                                ) ?>

                            </div>


                            <div class="profile-label">
                                Patient Information
                            </div>

                        </div>


                    </div>


                    <div class="profile-row">

                        <span>
                            Sex
                        </span>

                        <strong>
                            <?= e(
                                $patient['sex']
                                ?? 'Not provided'
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-row">

                        <span>
                            Age
                        </span>

                        <strong>
                            <?= e(
                                $patient['age']
                                ?? 'Not provided'
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-row">

                        <span>
                            Contact
                        </span>

                        <strong>
                            <?= e(
                                $patient['contact_number']
                                ?? 'Not provided'
                            ) ?>
                        </strong>

                    </div>


                </div>


            </div>


        </div>


        <!-- FOOTER -->

        <div class="dashboard-footer">

            <i
                class="fa-solid fa-heart-pulse me-1"
            ></i>

            CareSched — Rural Health Unit,
            Arakan, Cotabato

            <br>

            <span>
                Smart Scheduling. Better Healthcare.
            </span>

        </div>


    </div>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>