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
| Status Filter
|--------------------------------------------------------------------------
*/

$allowed_filters = [
    'all',
    'pending',
    'approved',
    'completed',
    'cancelled'
];

$filter = $_GET['status'] ?? 'all';

if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}


/*
|--------------------------------------------------------------------------
| Full Appointment History
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT *
    FROM appointments
    WHERE patient_id = :pid
";

$params = [
    'pid' => $patient['id']
];

switch ($filter) {

    case 'pending':
        $sql .= " AND status = 'Pending'";
        break;

    case 'approved':
        $sql .= " AND status IN ('Approved', 'Confirmed')";
        break;

    case 'completed':
        $sql .= " AND status = 'Completed'";
        break;

    case 'cancelled':
        $sql .= " AND status IN ('Cancelled', 'Rejected')";
        break;
}

$sql .= " ORDER BY appointment_date DESC, appointment_time DESC";

$appointments_stmt = $pdo->prepare($sql);
$appointments_stmt->execute($params);
$appointments = $appointments_stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Counts for Filter Tabs
|--------------------------------------------------------------------------
*/

$counts_stmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM appointments
    WHERE patient_id = :pid
    GROUP BY status
");

$counts_stmt->execute([
    'pid' => $patient['id']
]);

$status_counts = [
    'all'       => 0,
    'pending'   => 0,
    'approved'  => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($counts_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

    $status_counts['all'] += (int) $row['total'];

    switch (strtolower($row['status'])) {

        case 'pending':
            $status_counts['pending'] += (int) $row['total'];
            break;

        case 'approved':
        case 'confirmed':
            $status_counts['approved'] += (int) $row['total'];
            break;

        case 'completed':
            $status_counts['completed'] += (int) $row['total'];
            break;

        case 'cancelled':
        case 'rejected':
            $status_counts['cancelled'] += (int) $row['total'];
            break;
    }
}


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
        My Appointments - CareSched
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


        .page-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            flex-wrap: wrap;

            gap: 12px;

            margin-bottom: 22px;
        }


        .page-title h1 {

            margin: 0 0 4px;

            color: #172033;

            font-size: 22px;

            font-weight: 800;

            letter-spacing: -.4px;
        }


        .page-title p {

            margin: 0;

            color: #94a3b8;

            font-size: 10px;
        }


        .book-btn {

            display: inline-flex;

            align-items: center;

            gap: 9px;

            padding: 11px 17px;

            border: none;

            border-radius: 10px;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            font-size: 10px;

            font-weight: 700;

            text-decoration: none;

            box-shadow:
                0 8px 20px
                rgba(37,99,235,.18);

            transition: .2s ease;
        }


        .book-btn:hover {

            color: white;

            transform:
                translateY(-2px);

            box-shadow:
                0 12px 25px
                rgba(37,99,235,.25);
        }


        .back-btn {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 11px 17px;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            color: #64748b;

            background: white;

            font-size: 10px;

            font-weight: 700;

            text-decoration: none;

            transition: .2s ease;
        }


        .back-btn:hover {

            color: #2563eb;

            border-color: #bfdbfe;

            background: #f8fbff;
        }


        /* =====================================================
           FILTER TABS
        ===================================================== */

        .filter-tabs {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 20px;
        }


        .filter-tab {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 9px 14px;

            border: 1px solid #e7edf5;

            border-radius: 30px;

            color: #64748b;

            background: white;

            font-size: 9px;

            font-weight: 700;

            text-decoration: none;

            transition: .2s ease;
        }


        .filter-tab:hover {

            color: #2563eb;

            border-color: #bfdbfe;

            background: #f8fbff;
        }


        .filter-tab.active {

            color: white;

            border-color: transparent;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );
        }


        .filter-tab .count {

            padding: 1px 6px;

            border-radius: 20px;

            background: rgba(0,0,0,.06);

            font-size: 8px;
        }


        .filter-tab.active .count {

            background: rgba(255,255,255,.25);
        }


        /* =====================================================
           CONTENT CARD
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

            padding: 45px 15px;

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

            margin: 0 0 14px;

            color: #94a3b8;

            font-size: 9px;
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
     APPOINTMENT HISTORY
===================================================== -->

<main class="dashboard">

    <div class="container">


        <div class="page-header">

            <div class="page-title">

                <h1>
                    My Appointments
                </h1>

                <p>
                    Full history of your appointment records
                </p>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="/caresched/patient/dashboard.php"
                    class="back-btn"
                >

                    <i
                        class="fa-solid fa-arrow-left"
                    ></i>

                    Back to Dashboard

                </a>


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


        <!-- FILTER TABS -->

        <div class="filter-tabs">

            <?php

            $tabs = [
                'all'       => 'All',
                'pending'   => 'Pending',
                'approved'  => 'Approved',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled'
            ];

            foreach ($tabs as $key => $label):

            ?>

                <a
                    href="?status=<?= e($key) ?>"
                    class="filter-tab <?= $filter === $key ? 'active' : '' ?>"
                >

                    <?= e($label) ?>

                    <span class="count">
                        <?= e($status_counts[$key]) ?>
                    </span>

                </a>

            <?php endforeach; ?>

        </div>


        <!-- APPOINTMENT LIST -->

        <div class="content-card">


            <?php if (!empty($appointments)): ?>


                <?php foreach ($appointments as $appointment): ?>


                    <?php

                    $timestamp = strtotime(
                        $appointment['appointment_date']
                    );

                    $month = date('M', $timestamp);
                    $day = date('d', $timestamp);

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
                        No appointments found
                    </h6>


                    <p>

                        <?= $filter === 'all'
                            ? "You don't have any appointment records yet."
                            : "You don't have any {$filter} appointments." ?>

                    </p>


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


            <?php endif; ?>


        </div>


        <!-- FOOTER -->

        <div
            class="text-center mt-4"
            style="color:#94a3b8; font-size:8px;"
        >

            <i
                class="fa-solid fa-heart-pulse me-1"
            ></i>

            CareSched — Rural Health Unit,
            Arakan, Cotabato

        </div>


    </div>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>