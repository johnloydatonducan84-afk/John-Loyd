<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../email/email-notification.php';

ensure_role('patient');

$success = '';
$error = '';


// ======================================================
// GET LOGGED-IN USER
// ======================================================

$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    header('Location: ../login.php');
    exit;
}


// ======================================================
// GET PATIENT INFORMATION
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        p.id AS patient_id,
        p.first_name,
        p.middle_name,
        p.last_name,
        u.email,

        CONCAT(
            p.first_name,
            CASE
                WHEN p.middle_name IS NOT NULL
                AND p.middle_name != ''
                THEN CONCAT(' ', p.middle_name)
                ELSE ''
            END,
            ' ',
            p.last_name
        ) AS full_name

    FROM patients p

    INNER JOIN users u
        ON p.user_id = u.id

    WHERE p.user_id = ?

    LIMIT 1
");

$stmt->execute([$userId]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient information not found.');
}


// ======================================================
// GET ACTIVE SERVICES
// ======================================================

$servicesStmt = $pdo->query("
    SELECT
        id,
        service_name,
        description,
        duration,
        max_patients

    FROM services

    WHERE status = 'active'

    ORDER BY service_name ASC
");

$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// SUBMIT APPOINTMENT
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $serviceId = trim($_POST['service_id'] ?? '');
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $appointmentTime = trim($_POST['appointment_time'] ?? '');

    // ==================================================
    // VALIDATION
    // ==================================================

    if (
        empty($serviceId) ||
        empty($appointmentDate) ||
        empty($appointmentTime)
    ) {

        $error = 'Please fill in all required fields.';

    } else {

        try {

            // ==========================================
            // VALIDATE SERVICE ID
            // ==========================================

            if (!ctype_digit((string)$serviceId)) {
                throw new Exception('Invalid service selected.');
            }


            // ==========================================
            // VALIDATE DATE
            // ==========================================

            $today = date('Y-m-d');

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $appointmentDate
            );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $appointmentDate
            ) {

                throw new Exception(
                    'Invalid appointment date.'
                );
            }


            if ($appointmentDate < $today) {

                throw new Exception(
                    'Appointment date cannot be in the past.'
                );
            }


            // ==========================================
            // VALIDATE TIME
            // ==========================================

            $timeObject = DateTime::createFromFormat(
                'H:i',
                $appointmentTime
            );

            if (
                !$timeObject ||
                $timeObject->format('H:i') !== $appointmentTime
            ) {

                throw new Exception(
                    'Invalid appointment time.'
                );
            }


            // ==========================================
            // RHU TIME RANGE
            // 8:00 AM - 4:30 PM
            // ==========================================

            $selectedMinutes =
                ((int)substr($appointmentTime, 0, 2) * 60)
                + (int)substr($appointmentTime, 3, 2);

            $openingMinutes = 8 * 60;
            $closingMinutes = 16 * 60 + 30;


            if (
                $selectedMinutes < $openingMinutes ||
                $selectedMinutes > $closingMinutes
            ) {

                throw new Exception(
                    'Please select an appointment time between 8:00 AM and 4:30 PM.'
                );
            }


            // ==========================================
            // ONLY ALLOW 30-MINUTE INTERVALS
            // ==========================================

            $minutes = (int)substr(
                $appointmentTime,
                3,
                2
            );

            if (
                $minutes !== 0 &&
                $minutes !== 30
            ) {

                throw new Exception(
                    'Please select a valid 30-minute appointment slot.'
                );
            }


            // ==========================================
            // CONVERT TIME FOR DATABASE
            // ==========================================

            $databaseTime =
                $appointmentTime . ':00';


            // ==========================================
            // GET SERVICE
            // ==========================================

            $serviceStmt = $pdo->prepare("
                SELECT
                    id,
                    service_name,
                    description,
                    duration,
                    max_patients

                FROM services

                WHERE id = ?

                AND status = 'active'

                LIMIT 1
            ");

            $serviceStmt->execute([
                $serviceId
            ]);

            $service =
                $serviceStmt->fetch(PDO::FETCH_ASSOC);


            if (!$service) {

                throw new Exception(
                    'Selected service was not found or is inactive.'
                );
            }


            $serviceName =
                $service['service_name'];


            // ==================================================
            // DUPLICATE CHECK #1
            //
            // SAME PATIENT
            // SAME SERVICE
            // SAME DATE
            // SAME TIME
            //
            // Rejected/Cancelled appointments are ignored.
            // ==================================================

            $duplicateStmt = $pdo->prepare("
                SELECT
                    id,
                    status

                FROM appointments

                WHERE patient_id = ?

                AND service_id = ?

                AND appointment_date = ?

                AND appointment_time = ?

                AND status NOT IN (
                    'Rejected',
                    'Cancelled'
                )

                LIMIT 1
            ");

            $duplicateStmt->execute([

                $patient['patient_id'],

                $serviceId,

                $appointmentDate,

                $databaseTime

            ]);

            $duplicate =
                $duplicateStmt->fetch(PDO::FETCH_ASSOC);


            if ($duplicate) {

                throw new Exception(
                    'Duplicate appointment detected. You already have this appointment scheduled for '
                    . date('F d, Y', strtotime($appointmentDate))
                    . ' at '
                    . date('h:i A', strtotime($appointmentTime))
                    . '.'
                );
            }


            // ==================================================
            // DUPLICATE CHECK #2
            //
            // SAME PATIENT
            // SAME DATE
            // SAME TIME
            //
            // Even if the service is different, prevent the
            // patient from booking two appointments at the
            // exact same time.
            // ==================================================

            $sameTimeStmt = $pdo->prepare("
                SELECT
                    a.id,
                    s.service_name

                FROM appointments a

                INNER JOIN services s
                    ON a.service_id = s.id

                WHERE a.patient_id = ?

                AND a.appointment_date = ?

                AND a.appointment_time = ?

                AND a.status NOT IN (
                    'Rejected',
                    'Cancelled'
                )

                LIMIT 1
            ");

            $sameTimeStmt->execute([

                $patient['patient_id'],

                $appointmentDate,

                $databaseTime

            ]);

            $sameTime =
                $sameTimeStmt->fetch(PDO::FETCH_ASSOC);


            if ($sameTime) {

                throw new Exception(
                    'You already have an appointment at '
                    . date('h:i A', strtotime($appointmentTime))
                    . ' on '
                    . date('F d, Y', strtotime($appointmentDate))
                    . '.'
                );
            }


            // ==================================================
            // DUPLICATE CHECK #3
            //
            // SCHEDULE AVAILABILITY
            //
            // The selected date/time must fall inside an active
            // schedule the admin configured for this service, and
            // that schedule must still have open slots (max_slots).
            // ==================================================

            $scheduleStmt = $pdo->prepare("
                SELECT
                    id,
                    start_time,
                    end_time,
                    max_slots

                FROM schedules

                WHERE service_id = ?

                AND schedule_date = ?

                AND status = 'active'

                AND ? >= start_time

                AND ? < end_time

                LIMIT 1
            ");

            $scheduleStmt->execute([

                $serviceId,

                $appointmentDate,

                $databaseTime,

                $databaseTime

            ]);

            $schedule =
                $scheduleStmt->fetch(PDO::FETCH_ASSOC);


            if (!$schedule) {

                throw new Exception(
                    'The selected time is not available for booking. Please choose a time within an available schedule.'
                );
            }


            $scheduleBookedStmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_booked

                FROM appointments

                WHERE schedule_id = ?

                AND status NOT IN (
                    'Rejected',
                    'Cancelled'
                )
            ");

            $scheduleBookedStmt->execute([

                $schedule['id']

            ]);

            $scheduleBooked =
                (int) $scheduleBookedStmt->fetchColumn();


            if ($scheduleBooked >= (int) $schedule['max_slots']) {

                throw new Exception(
                    'This appointment time is already fully booked for '
                    . htmlspecialchars($serviceName)
                    . '. Please select another available time.'
                );
            }


            // ==================================================
            // INSERT APPOINTMENT
            // ==================================================

            $insert = $pdo->prepare("
                INSERT INTO appointments (

                    patient_id,
                    service_id,
                    schedule_id,
                    appointment_date,
                    appointment_time,
                    status

                )

                VALUES (

                    :patient_id,
                    :service_id,
                    :schedule_id,
                    :appointment_date,
                    :appointment_time,
                    'Pending'

                )
            ");


            $insert->execute([

                ':patient_id' =>
                    $patient['patient_id'],

                ':service_id' =>
                    $serviceId,

                ':schedule_id' =>
                    $schedule['id'],

                ':appointment_date' =>
                    $appointmentDate,

                ':appointment_time' =>
                    $databaseTime

            ]);


            $appointmentId =
                $pdo->lastInsertId();


            // ==================================================
            // SEND EMAIL TO ADMIN
            // ==================================================

            $adminEmail =
                'johnloydatonducan84@gmail.com';


            $emailSent = false;


            if (
                function_exists(
                    'sendAppointmentToAdmin'
                )
            ) {

                $emailSent =
                    sendAppointmentToAdmin(

                        $adminEmail,

                        $patient['full_name'],

                        $serviceName,

                        $appointmentDate,

                        $appointmentTime

                    );
            }


            // ==================================================
            // SAVE NOTIFICATION
            // ==================================================

            $notificationMessage =
                'A new appointment request has been submitted by '
                . $patient['full_name']
                . ' for '
                . $serviceName
                . ' on '
                . date(
                    'F d, Y',
                    strtotime($appointmentDate)
                )
                . ' at '
                . date(
                    'h:i A',
                    strtotime($appointmentTime)
                )
                . '.';


            $notificationStatus =
                $emailSent
                    ? 'Sent'
                    : 'Pending';


            $sentAt =
                $emailSent
                    ? date('Y-m-d H:i:s')
                    : null;


            $notificationStmt = $pdo->prepare("
                INSERT INTO notifications (

                    appointment_id,
                    patient_id,
                    email,
                    notification_type,
                    subject,
                    message,
                    status,
                    sent_at

                )

                VALUES (

                    :appointment_id,
                    :patient_id,
                    :email,
                    :notification_type,
                    :subject,
                    :message,
                    :status,
                    :sent_at

                )
            ");


            $notificationStmt->execute([

                ':appointment_id' =>
                    $appointmentId,

                ':patient_id' =>
                    $patient['patient_id'],

                ':email' =>
                    $adminEmail,

                ':notification_type' =>
                    'Appointment',

                ':subject' =>
                    'CareSched Appointment Request',

                ':message' =>
                    $notificationMessage,

                ':status' =>
                    $notificationStatus,

                ':sent_at' =>
                    $sentAt

            ]);


            // ==================================================
            // SUCCESS MESSAGE
            // ==================================================

            $success =
                'Appointment submitted successfully!';


            if ($emailSent) {

                $success .=
                    ' The administrator has been notified by email.';

            } else {

                $success .=
                    ' Email notification could not be sent.';
            }


            // Clear POST values
            $_POST = [];


        } catch (PDOException $e) {

            $error =
                'Database error: ' .
                $e->getMessage();

        } catch (Exception $e) {

            $error =
                $e->getMessage();
        }
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

    <title>
        Book Appointment | CareSched
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- Google Font -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family: 'Inter', sans-serif;

    min-height: 100vh;

    background:
        linear-gradient(
            135deg,
            #eef6ff,
            #f8fbff,
            #eafaf6
        );

    color: #1e293b;
}


.background-shape {

    position: fixed;

    border-radius: 50%;

    filter: blur(80px);

    opacity: .3;

    z-index: -1;
}


.shape-one {

    width: 320px;
    height: 320px;

    background: #0d6efd;

    top: -120px;
    left: -100px;
}


.shape-two {

    width: 350px;
    height: 350px;

    background: #20c997;

    right: -120px;
    bottom: -150px;
}


.page-wrapper {

    min-height: 100vh;

    padding: 30px 15px 40px;
}


.container-main {

    max-width: 1100px;

    margin: auto;
}


/* TOPBAR */

.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;
}


.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    text-decoration: none;

    color: #0f172a;
}


.brand-icon {

    width: 48px;

    height: 48px;

    border-radius: 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    color: white;

    font-size: 22px;

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #0dcaf0
        );

    box-shadow:
        0 10px 25px
        rgba(13,110,253,.25);
}


.brand-name {

    font-size: 21px;

    font-weight: 800;
}


.brand-subtitle {

    font-size: 11px;

    color: #64748b;
}


.dashboard-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 10px 16px;

    border-radius: 12px;

    text-decoration: none;

    color: #334155;

    font-size: 13px;

    font-weight: 700;

    background:
        rgba(255,255,255,.8);

    border:
        1px solid #e2e8f0;

    transition: .25s;
}


.dashboard-btn:hover {

    background: white;

    color: #0d6efd;

    transform: translateY(-2px);
}


/* HERO */

.hero {

    position: relative;

    overflow: hidden;

    border-radius: 25px;

    padding: 30px;

    margin-bottom: 22px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #0dcaf0
        );

    box-shadow:
        0 20px 50px
        rgba(13,110,253,.20);
}


.hero h1 {

    margin: 0 0 8px;

    font-size: 28px;

    font-weight: 800;
}


.hero p {

    margin: 0;

    font-size: 14px;

    opacity: .9;
}


.patient-pill {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-top: 18px;

    padding: 9px 14px;

    border-radius: 50px;

    background:
        rgba(255,255,255,.15);

    font-size: 13px;
}


/* MAIN CARD */

.main-card {

    overflow: hidden;

    border-radius: 25px;

    background:
        rgba(255,255,255,.9);

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 20px 60px
        rgba(15,23,42,.08);
}


.form-section {

    padding: 35px;
}


.section-title {

    font-size: 19px;

    font-weight: 800;
}


.section-description {

    margin-top: 5px;

    margin-bottom: 28px;

    color: #64748b;

    font-size: 13px;
}


/* ALERT */

.custom-alert {

    padding: 15px 17px;

    border-radius: 14px;

    font-size: 13px;

    margin-bottom: 22px;
}


.success-alert {

    color: #047857;

    background: #ecfdf5;

    border: 1px solid #a7f3d0;
}


.error-alert {

    color: #be123c;

    background: #fff1f2;

    border: 1px solid #fecdd3;
}


/* FORM */

.form-label {

    color: #334155;

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 8px;
}


.input-wrapper {

    position: relative;
}


.input-icon {

    position: absolute;

    left: 16px;

    top: 50%;

    transform: translateY(-50%);

    z-index: 5;

    color: #64748b;
}


.form-control,
.form-select {

    min-height: 53px;

    border-radius: 13px;

    border: 1px solid #dbe4ee;

    background: white;

    font-size: 14px;

    transition: .25s;
}


.input-wrapper
.form-control {

    padding-left: 45px;
}


.input-wrapper
.form-select {

    padding-left: 45px;
}


.form-control:focus,
.form-select:focus {

    border-color: #0d6efd;

    box-shadow:
        0 0 0 4px
        rgba(13,110,253,.10);
}


/* SERVICE INFO */

.service-info {

    display: none;

    margin-top: 10px;

    padding: 13px 15px;

    border-radius: 13px;

    background: #eff6ff;

    border: 1px solid #dbeafe;
}


.service-info.show {

    display: block;
}


.service-info-title {

    color: #0d6efd;

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 3px;
}


.service-info-text {

    margin: 0;

    color: #64748b;

    font-size: 12px;
}


/* SUBMIT */

.submit-btn {

    width: 100%;

    min-height: 54px;

    border: none;

    border-radius: 14px;

    color: white;

    font-size: 14px;

    font-weight: 700;

    background:
        linear-gradient(
            135deg,
            #0d6efd,
            #0dcaf0
        );

    box-shadow:
        0 12px 25px
        rgba(13,110,253,.22);

    transition: .25s;
}


.submit-btn:hover {

    color: white;

    transform: translateY(-2px);
}


/* INFO */

.info-section {

    height: 100%;

    padding: 35px;

    background:
        linear-gradient(
            180deg,
            #f8fbff,
            #eef7ff
        );

    border-left:
        1px solid #e2e8f0;
}


.info-title {

    font-size: 17px;

    font-weight: 800;

    margin-bottom: 22px;
}


.info-item {

    display: flex;

    gap: 13px;

    margin-bottom: 21px;
}


.info-icon {

    flex-shrink: 0;

    width: 40px;

    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    color: #0d6efd;

    background: #dbeafe;
}


.info-item h6 {

    margin: 0 0 4px;

    font-size: 13px;

    font-weight: 700;
}


.info-item p {

    margin: 0;

    color: #64748b;

    font-size: 12px;

    line-height: 1.5;
}


.email-box {

    margin-top: 25px;

    padding: 16px;

    border-radius: 15px;

    background: white;

    border: 1px solid #e2e8f0;
}


.email-box small {

    display: block;

    color: #64748b;

    font-size: 11px;

    margin-bottom: 5px;
}


.email-box strong {

    display: block;

    color: #334155;

    font-size: 12px;

    word-break: break-word;
}


.footer {

    text-align: center;

    margin-top: 20px;

    color: #94a3b8;

    font-size: 11px;
}


/* MOBILE */

@media (max-width: 767px) {

    .page-wrapper {

        padding: 20px 12px 30px;
    }

    .brand-subtitle {

        display: none;
    }

    .brand-name {

        font-size: 18px;
    }

    .brand-icon {

        width: 43px;
        height: 43px;
    }

    .dashboard-btn {

        padding: 9px 12px;

        font-size: 12px;
    }

    .hero {

        padding: 25px 21px;

        border-radius: 20px;
    }

    .hero h1 {

        font-size: 23px;
    }

    .form-section,
    .info-section {

        padding: 25px 20px;
    }

    .info-section {

        border-left: none;

        border-top:
            1px solid #e2e8f0;
    }
}

</style>

</head>


<body>


<div class="background-shape shape-one"></div>
<div class="background-shape shape-two"></div>


<div class="page-wrapper">

<div class="container-main">


<!-- ======================================================
     TOP BAR
====================================================== -->

<div class="topbar">

    <a
        href="dashboard.php"
        class="brand"
    >

        <div class="brand-icon">

            <i class="bi bi-heart-pulse-fill"></i>

        </div>

        <div>

            <div class="brand-name">
                CareSched
            </div>

            <div class="brand-subtitle">
                Healthcare Appointment System
            </div>

        </div>

    </a>


    <a
        href="dashboard.php"
        class="dashboard-btn"
    >

        <i class="bi bi-grid-1x2-fill"></i>

        Dashboard

    </a>

</div>


<!-- ======================================================
     HERO
====================================================== -->

<div class="hero">

    <h1>
        Book Your Appointment
    </h1>

    <p>
        Schedule your healthcare visit with
        the Rural Health Unit of Arakan.
    </p>

    <div class="patient-pill">

        <i class="bi bi-person-circle"></i>

        <?= htmlspecialchars(
            $patient['full_name']
        ) ?>

    </div>

</div>


<!-- ======================================================
     MAIN CARD
====================================================== -->

<div class="main-card">

<div class="row g-0">


<!-- FORM -->

<div class="col-lg-7">

<div class="form-section">


<div class="section-title">
    Appointment Details
</div>


<div class="section-description">

    Please select your preferred service,
    date and available time.

</div>


<!-- SUCCESS -->

<?php if ($success): ?>

<div class="custom-alert success-alert">

    <i class="bi bi-check-circle-fill me-2"></i>

    <?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<!-- ERROR -->

<?php if ($error): ?>

<div class="custom-alert error-alert">

    <i class="bi bi-exclamation-circle-fill me-2"></i>

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    id="appointmentForm"
>


<!-- SERVICE -->

<div class="mb-4">

<label
    for="service_id"
    class="form-label"
>
    Healthcare Service
</label>


<div class="input-wrapper">

<i class="bi bi-heart-pulse input-icon"></i>


<select
    name="service_id"
    id="service_id"
    class="form-select"
    required
>

<option value="">
    Select a healthcare service
</option>


<?php foreach ($services as $service): ?>

<option
    value="<?= (int)$service['id'] ?>"
    data-description="<?= htmlspecialchars(
        $service['description'] ?? ''
    ) ?>"
    data-duration="<?= (int)$service['duration'] ?>"
>

<?= htmlspecialchars(
    $service['service_name']
) ?>

— <?= (int)$service['duration'] ?> minutes

</option>

<?php endforeach; ?>

</select>

</div>


<div
    class="service-info"
    id="serviceInfo"
>

<div
    class="service-info-title"
    id="serviceInfoTitle"
></div>

<p
    class="service-info-text"
    id="serviceInfoText"
></p>

</div>

</div>


<!-- DATE -->

<div class="mb-4">

<label
    for="appointment_date"
    class="form-label"
>
    Appointment Date
</label>


<div class="input-wrapper">

<i class="bi bi-calendar-event input-icon"></i>


<input
    type="date"
    name="appointment_date"
    id="appointment_date"
    class="form-control"
    min="<?= date('Y-m-d') ?>"
    value="<?= htmlspecialchars(
        $_POST['appointment_date'] ?? ''
    ) ?>"
    required
>

</div>

</div>


<!-- TIME -->

<div class="mb-4">

<label
    for="appointment_time"
    class="form-label"
>
    Preferred Time
</label>


<div class="input-wrapper">

<i class="bi bi-clock input-icon"></i>


<select
    name="appointment_time"
    id="appointment_time"
    class="form-select"
    required
>

<option value="">
    Select appointment time
</option>


<?php

$start =
    strtotime('08:00');

$end =
    strtotime('16:30');

for (
    $time = $start;
    $time <= $end;
    $time += 30 * 60
):

    $timeValue =
        date('H:i', $time);

    $timeDisplay =
        date('h:i A', $time);

    $selected =
        (
            ($_POST['appointment_time'] ?? '')
            ===
            $timeValue
        )
        ? 'selected'
        : '';

?>

<option
    value="<?= $timeValue ?>"
    <?= $selected ?>
>

<?= $timeDisplay ?>

</option>

<?php endfor; ?>

</select>

</div>


<div class="form-text mt-2">

<i class="bi bi-info-circle"></i>

Appointment hours:
8:00 AM – 4:30 PM

</div>


<div
    class="form-text mt-1"
    id="slotStatus"
></div>

</div>


<!-- SUBMIT -->

<button
    type="submit"
    class="submit-btn"
    id="submitBtn"
>

<i class="bi bi-calendar-check me-2"></i>

Submit Appointment

</button>


</form>


</div>

</div>


<!-- INFORMATION -->

<div class="col-lg-5">

<div class="info-section">


<div class="info-title">

<i class="bi bi-shield-check text-primary me-2"></i>

Before You Book

</div>


<div class="info-item">

<div class="info-icon">

<i class="bi bi-calendar-check"></i>

</div>

<div>

<h6>
Choose Your Schedule
</h6>

<p>
Select your preferred appointment date and time.
</p>

</div>

</div>


<div class="info-item">

<div class="info-icon">

<i class="bi bi-hourglass-split"></i>

</div>

<div>

<h6>
Wait for Approval
</h6>

<p>
Your appointment will remain pending until reviewed by RHU staff.
</p>

</div>

</div>


<div class="info-item">

<div class="info-icon">

<i class="bi bi-envelope-check"></i>

</div>

<div>

<h6>
Email Notification
</h6>

<p>
The administrator will receive an email notification.
</p>

</div>

</div>


<div class="info-item">

<div class="info-icon">

<i class="bi bi-shield-check"></i>

</div>

<div>

<h6>
Duplicate Protection
</h6>

<p>
CareSched prevents duplicate bookings for the same patient, date and time.
</p>

</div>

</div>


<div class="email-box">

<small>

<i class="bi bi-envelope me-1"></i>

Your registered email

</small>


<strong>

<?= htmlspecialchars(
    $patient['email']
) ?>

</strong>

</div>


</div>

</div>


</div>

</div>


<div class="footer">

© <?= date('Y') ?>

CareSched · Rural Health Unit of Arakan

</div>


</div>

</div>


<script>


// ======================================================
// SERVICE INFORMATION
// ======================================================

const serviceSelect =
    document.getElementById(
        'service_id'
    );

const serviceInfo =
    document.getElementById(
        'serviceInfo'
    );

const serviceInfoTitle =
    document.getElementById(
        'serviceInfoTitle'
    );

const serviceInfoText =
    document.getElementById(
        'serviceInfoText'
    );


serviceSelect.addEventListener(
    'change',
    function () {

        if (!this.value) {

            serviceInfo.classList.remove(
                'show'
            );

            return;
        }


        const selected =
            this.options[
                this.selectedIndex
            ];


        const description =
            selected.dataset.description || '';


        const duration =
            selected.dataset.duration || '';


        serviceInfoTitle.textContent =
            selected.textContent.trim();


        serviceInfoText.textContent =
            description
            + ' • Estimated duration: '
            + duration
            + ' minutes.';


        serviceInfo.classList.add(
            'show'
        );

    }
);


// ======================================================
// SCHEDULE-BASED SLOT AVAILABILITY
//
// Only appointment times that fall inside an admin-
// configured schedule AND still have open slots can be
// selected. Everything else is disabled.
// ======================================================

const appointmentDateInput =
    document.getElementById('appointment_date');

const appointmentTimeSelect =
    document.getElementById('appointment_time');

const slotStatus =
    document.getElementById('slotStatus');

const timeOptions =
    Array.from(appointmentTimeSelect.options).filter(
        (opt) => opt.value !== ''
    );

const originalTimeLabels =
    new Map(
        timeOptions.map(
            (opt) => [opt.value, opt.textContent.trim()]
        )
    );

function lockTimeOptions(message) {

    timeOptions.forEach((opt) => {
        opt.disabled = true;
        opt.textContent = originalTimeLabels.get(opt.value);
    });

    appointmentTimeSelect.value = '';

    slotStatus.textContent = message || '';
}

function refreshAvailableSlots() {

    const serviceId = serviceSelect.value;
    const date = appointmentDateInput.value;

    if (!serviceId || !date) {

        lockTimeOptions(
            'Select a service and date to see available appointment times.'
        );

        return;
    }

    slotStatus.textContent = 'Checking available slots...';

    fetch(
        'ajax-schedule-slots.php?service_id='
        + encodeURIComponent(serviceId)
        + '&date='
        + encodeURIComponent(date)
    )
        .then((response) => response.json())
        .then((data) => {

            const slots = data.slots || {};
            let anyAvailable = false;

            timeOptions.forEach((opt) => {

                const isAvailable = slots[opt.value] === true;

                opt.disabled = !isAvailable;

                opt.textContent =
                    originalTimeLabels.get(opt.value)
                    + (isAvailable ? '' : ' (Unavailable)');

                if (isAvailable) {
                    anyAvailable = true;
                }
            });

            if (
                appointmentTimeSelect.value &&
                slots[appointmentTimeSelect.value] !== true
            ) {
                appointmentTimeSelect.value = '';
            }

            slotStatus.textContent = anyAvailable
                ? ''
                : 'No available appointment slots for this service on the selected date. Please choose another date.';

        })
        .catch(() => {

            lockTimeOptions(
                'Unable to check slot availability right now. Please try again.'
            );

        });
}

serviceSelect.addEventListener('change', refreshAvailableSlots);
appointmentDateInput.addEventListener('change', refreshAvailableSlots);

lockTimeOptions(
    'Select a service and date to see available appointment times.'
);


// ======================================================
// SUBMIT BUTTON
// ======================================================

const appointmentForm =
    document.getElementById(
        'appointmentForm'
    );

const submitBtn =
    document.getElementById(
        'submitBtn'
    );


appointmentForm.addEventListener(
    'submit',
    function (event) {

        const selectedOption =
            appointmentTimeSelect.options[
                appointmentTimeSelect.selectedIndex
            ];

        if (
            !selectedOption ||
            !selectedOption.value ||
            selectedOption.disabled
        ) {

            event.preventDefault();

            slotStatus.textContent =
                'Please select an available appointment time.';

            return;
        }

        submitBtn.disabled = true;

        submitBtn.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Checking Appointment...';

    }
);

</script>


</body>
</html>