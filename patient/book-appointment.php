<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('patient');

$errors = [];

/* =========================================================
   GET LOGGED-IN PATIENT
========================================================= */

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        p.*,
        u.email
    FROM patients p
    INNER JOIN users u
        ON u.id = p.user_id
    WHERE p.user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $user_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient profile not found.');
}


/* =========================================================
   GET ACTIVE SERVICES
========================================================= */

$stmt = $pdo->query("
    SELECT
        id,
        service_name,
        description
    FROM services
    WHERE status = 'active'
    ORDER BY service_name ASC
");

$serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   SERVICE ICONS
========================================================= */

$serviceIcons = [

    'General Consultation'
        => 'fa-stethoscope',

    'Prenatal Care'
        => 'fa-person-pregnant',

    'Postnatal Care'
        => 'fa-baby',

    'Vaccination'
        => 'fa-syringe',

    'Maternal and Child Health'
        => 'fa-children',

    'Family Planning'
        => 'fa-people-roof',

    'Medical Check-up'
        => 'fa-heart-pulse',

    'Health Certificate'
        => 'fa-file-medical'

];


/* =========================================================
   BUILD SERVICE ARRAY
========================================================= */

$services = [];

foreach ($serviceRows as $row) {

    $services[$row['service_name']] = [

        'id' => $row['id'],

        'description' =>
            $row['description']
            ?: 'Healthcare service',

        'icon' =>
            $serviceIcons[$row['service_name']]
            ?? 'fa-stethoscope'

    ];

}


/* =========================================================
   FORM VALUES
========================================================= */

$service = '';

$appointment_date = '';

$appointment_time = '';

$reason = '';

$contact_number =
    $patient['contact_number'] ?? '';

$notes = '';


/* =========================================================
   SUBMIT APPOINTMENT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* -----------------------------------------------------
       CSRF
    ----------------------------------------------------- */

    if (
        !verify_csrf(
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $errors[] =
            'Invalid request. Please refresh the page and try again.';

    }


    /* -----------------------------------------------------
       GET FORM DATA
    ----------------------------------------------------- */

    $service =
        trim(
            $_POST['service'] ?? ''
        );

    $appointment_date =
        $_POST['appointment_date'] ?? '';

    $appointment_time =
        $_POST['appointment_time'] ?? '';

    $reason =
        trim(
            $_POST['reason'] ?? ''
        );

    $contact_number =
        trim(
            $_POST['contact_number'] ?? ''
        );

    $notes =
        trim(
            $_POST['notes'] ?? ''
        );


    /* -----------------------------------------------------
       VALIDATION
    ----------------------------------------------------- */

    if (!$service) {

        $errors[] =
            'Please select a healthcare service.';

    }


    if (!$appointment_date) {

        $errors[] =
            'Please select an appointment date.';

    }


    if (!$appointment_time) {

        $errors[] =
            'Please select an appointment time.';

    }


    if (!$reason) {

        $errors[] =
            'Please provide a reason for your appointment.';

    }


    if (!$contact_number) {

        $errors[] =
            'Please provide your contact number.';

    }


    /* -----------------------------------------------------
       VALIDATE DATE
    ----------------------------------------------------- */

    if ($appointment_date) {

        $today = date('Y-m-d');

        if ($appointment_date < $today) {

            $errors[] =
                'Appointment date cannot be in the past.';

        }

    }


    /* -----------------------------------------------------
       VALIDATE TIME
    ----------------------------------------------------- */

    if ($appointment_time) {

        $time = strtotime($appointment_time);

        $opening =
            strtotime('08:00');

        $closing =
            strtotime('16:30');

        if (
            $time < $opening ||
            $time > $closing
        ) {

            $errors[] =
                'Appointment time must be between 8:00 AM and 4:30 PM.';

        }

    }


    /* -----------------------------------------------------
       GET SERVICE ID
    ----------------------------------------------------- */

    $service_id = null;

    if (
        $service &&
        isset($services[$service])
    ) {

        $service_id =
            $services[$service]['id'];

    } elseif ($service) {

        $errors[] =
            'Invalid healthcare service selected.';

    }


    /* =====================================================
       SAVE APPOINTMENT
    ===================================================== */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /* -------------------------------------------------
               UPDATE PATIENT CONTACT NUMBER
            ------------------------------------------------- */

            $updatePatient =
                $pdo->prepare("
                    UPDATE patients
                    SET contact_number = :contact_number
                    WHERE id = :patient_id
                ");

            $updatePatient->execute([

                'contact_number'
                    => $contact_number,

                'patient_id'
                    => $patient['id']

            ]);


            /* -------------------------------------------------
               CHECK DUPLICATE APPOINTMENT
            ------------------------------------------------- */

            $check =
                $pdo->prepare("
                    SELECT COUNT(*)
                    FROM appointments
                    WHERE patient_id = :patient_id
                    AND appointment_date = :appointment_date
                    AND appointment_time = :appointment_time
                    AND status IN ('Pending', 'Approved')
                ");

            $check->execute([

                'patient_id'
                    => $patient['id'],

                'appointment_date'
                    => $appointment_date,

                'appointment_time'
                    => $appointment_time

            ]);

            $existing =
                $check->fetchColumn();


            if ($existing > 0) {

                throw new Exception(
                    'You already have an appointment at this date and time.'
                );

            }


            /* -------------------------------------------------
               CHECK SAME TIME SLOT
            ------------------------------------------------- */

            $checkSlot =
                $pdo->prepare("
                    SELECT COUNT(*)
                    FROM appointments
                    WHERE appointment_date = :appointment_date
                    AND appointment_time = :appointment_time
                    AND status IN ('Pending', 'Approved')
                ");

            $checkSlot->execute([

                'appointment_date'
                    => $appointment_date,

                'appointment_time'
                    => $appointment_time

            ]);

            $slotCount =
                $checkSlot->fetchColumn();


            /*
             * Maximum simultaneous appointments.
             * Change this number if your RHU wants another limit.
             */

            $maximumAppointments = 10;


            if (
                $slotCount >=
                $maximumAppointments
            ) {

                throw new Exception(
                    'The selected time slot is already full. Please choose another time.'
                );

            }


            /* -------------------------------------------------
               INSERT APPOINTMENT
            ------------------------------------------------- */

            $insert =
                $pdo->prepare("
                    INSERT INTO appointments
                    (
                        patient_id,
                        service_id,
                        schedule_id,
                        appointment_date,
                        appointment_time,
                        reason,
                        notes,
                        status,
                        created_at
                    )
                    VALUES
                    (
                        :patient_id,
                        :service_id,
                        NULL,
                        :appointment_date,
                        :appointment_time,
                        :reason,
                        :notes,
                        'Pending',
                        NOW()
                    )
                ");

            $insert->execute([

                'patient_id'
                    => $patient['id'],

                'service_id'
                    => $service_id,

                'appointment_date'
                    => $appointment_date,

                'appointment_time'
                    => $appointment_time,

                'reason'
                    => $reason,

                'notes'
                    => $notes

            ]);


            /* -------------------------------------------------
               GET NEW APPOINTMENT ID
            ------------------------------------------------- */

            $appointment_id =
                $pdo->lastInsertId();


            /* -------------------------------------------------
               CREATE NOTIFICATION RECORD
            ------------------------------------------------- */

            $notification =
                $pdo->prepare("
                    INSERT INTO notifications
                    (
                        appointment_id,
                        patient_id,
                        email,
                        notification_type,
                        subject,
                        message,
                        status,
                        created_at
                    )
                    VALUES
                    (
                        :appointment_id,
                        :patient_id,
                        :email,
                        :notification_type,
                        :subject,
                        :message,
                        'Pending',
                        NOW()
                    )
                ");

            $notification->execute([

                'appointment_id'
                    => $appointment_id,

                'patient_id'
                    => $patient['id'],

                'email'
                    => $patient['email'],

                'notification_type'
                    => 'Appointment',

                'subject'
                    => 'CareSched Appointment Request',

                'message'
                    =>
                    'Your appointment request has been successfully submitted and is currently pending RHU approval.'

            ]);


            /* -------------------------------------------------
               COMMIT
            ------------------------------------------------- */

            $pdo->commit();


            /* -------------------------------------------------
               SUCCESS
            ------------------------------------------------- */

            flash(
                'success',
                'Appointment submitted successfully! Your request is now pending RHU approval.'
            );


            header(
                'Location: dashboard.php'
            );

            exit;


        } catch (Exception $e) {


            /* -------------------------------------------------
               ROLLBACK
            ------------------------------------------------- */

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();

            }


            /* -------------------------------------------------
               LOG ERROR
            ------------------------------------------------- */

            error_log(
                'CareSched Appointment Error: '
                . $e->getMessage()
            );


            /*
             * Show actual error temporarily while debugging.
             * Once everything works, change this to:
             *
             * $errors[] =
             * 'Unable to submit your appointment. Please try again later.';
             */

            $errors[] =
                $e->getMessage();

        }

    }

}


/* =========================================================
   CSRF TOKEN
========================================================= */

$token =
    csrf_token();

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
    Book Appointment - CareSched
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

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    min-height: 100vh;

    font-family:
        'Inter',
        sans-serif;

    color: #172033;

    background:

        radial-gradient(
            circle at 10% 10%,
            rgba(37, 99, 235, .08),
            transparent 28%
        ),

        radial-gradient(
            circle at 90% 90%,
            rgba(14, 165, 233, .08),
            transparent 30%
        ),

        #f8fafc;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    min-height: 72px;

    background:
        rgba(255,255,255,.92);

    backdrop-filter:
        blur(18px);

    border-bottom:
        1px solid #e8eef6;
}


.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #172033;

    font-size: 19px;

    font-weight: 800;

    text-decoration: none;
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
}


.brand span {

    color: #2563eb;
}


.back-link {

    color: #64748b;

    font-size: 11px;

    font-weight: 600;

    text-decoration: none;
}


.back-link:hover {

    color: #2563eb;
}


/* =========================================================
   PAGE
========================================================= */

.page {

    padding:
        45px 0 70px;
}


.page-heading {

    margin-bottom: 25px;
}


.page-heading .label {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    margin-bottom: 10px;

    color: #2563eb;

    font-size: 10px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: 1.3px;
}


.page-heading h1 {

    margin-bottom: 8px;

    color: #172033;

    font-size: 29px;

    font-weight: 800;

    letter-spacing: -.8px;
}


.page-heading p {

    margin: 0;

    color: #64748b;

    font-size: 12px;

    line-height: 1.7;
}


/* =========================================================
   BOOKING CARD
========================================================= */

.booking-card {

    padding: 28px;

    border:
        1px solid #e7edf5;

    border-radius: 20px;

    background: white;

    box-shadow:
        0 15px 45px
        rgba(15,23,42,.06);
}


.card-title {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 22px;

    color: #1e293b;

    font-size: 14px;

    font-weight: 800;
}


.card-title-icon {

    width: 36px;

    height: 36px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    color: #2563eb;

    background: #eff6ff;
}


/* =========================================================
   PATIENT BOX
========================================================= */

.patient-box {

    padding: 18px;

    margin-bottom: 25px;

    border:
        1px solid #dbeafe;

    border-radius: 15px;

    background:
        linear-gradient(
            135deg,
            #f8fbff,
            #eff6ff
        );
}


.patient-header {

    display: flex;

    align-items: center;

    gap: 12px;
}


.patient-avatar {

    width: 43px;

    height: 43px;

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

    font-size: 16px;
}


.patient-name {

    color: #1e293b;

    font-size: 12px;

    font-weight: 800;
}


.patient-email {

    margin-top: 3px;

    color: #64748b;

    font-size: 10px;
}


.patient-details {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

    margin-top: 15px;

    padding-top: 15px;

    border-top:
        1px solid #dbeafe;
}


.detail-label {

    margin-bottom: 4px;

    color: #94a3b8;

    font-size: 8px;

    font-weight: 700;

    text-transform: uppercase;
}


.detail-value {

    color: #334155;

    font-size: 10px;

    font-weight: 600;
}


/* =========================================================
   FORM
========================================================= */

.form-label {

    margin-bottom: 7px;

    color: #334155;

    font-size: 10px;

    font-weight: 700;
}


.required {

    color: #ef4444;
}


.input-wrapper {

    position: relative;
}


.input-wrapper > i {

    position: absolute;

    left: 14px;

    top: 23px;

    transform:
        translateY(-50%);

    color: #94a3b8;

    font-size: 12px;

    z-index: 2;
}


.form-control,
.form-select {

    min-height: 46px;

    padding:
        10px 14px 10px 40px;

    border:
        1px solid #dbe4f0;

    border-radius: 11px;

    color: #1e293b;

    background: #f8fafc;

    font-size: 11px;

    box-shadow: none;

    transition: .2s ease;
}


.form-control:focus,
.form-select:focus {

    border-color: #2563eb;

    background: white;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,.07);
}


textarea.form-control {

    min-height: 105px;

    resize: vertical;

    padding-top: 13px;
}


.form-select {

    cursor: pointer;
}


/* =========================================================
   SERVICE GRID
========================================================= */

.service-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-bottom: 20px;
}


.service-option {

    position: relative;
}


.service-option input {

    position: absolute;

    opacity: 0;

    pointer-events: none;
}


.service-option label {

    display: flex;

    align-items: center;

    gap: 10px;

    min-height: 65px;

    padding: 11px;

    border:
        1px solid #e2e8f0;

    border-radius: 12px;

    cursor: pointer;

    background: white;

    transition: .2s ease;
}


.service-option label:hover {

    border-color: #bfdbfe;

    background: #f8fbff;
}


.service-option input:checked + label {

    border-color: #2563eb;

    background: #eff6ff;

    box-shadow:
        0 0 0 2px
        rgba(37,99,235,.06);
}


.service-icon {

    width: 36px;

    height: 36px;

    min-width: 36px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    color: #2563eb;

    background: #eff6ff;

    font-size: 13px;
}


.service-name {

    color: #334155;

    font-size: 10px;

    font-weight: 700;
}


.service-description {

    margin-top: 3px;

    color: #94a3b8;

    font-size: 8px;

    line-height: 1.4;
}


/* =========================================================
   INFO BOX
========================================================= */

.info-box {

    display: flex;

    gap: 10px;

    padding: 13px;

    margin-top: 20px;

    border-radius: 11px;

    color: #475569;

    background: #f8fafc;

    font-size: 9px;

    line-height: 1.6;
}


.info-box i {

    color: #2563eb;

    font-size: 12px;
}


/* =========================================================
   SUBMIT BUTTON
========================================================= */

.submit-button {

    width: 100%;

    min-height: 48px;

    margin-top: 22px;

    border: none;

    border-radius: 12px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    font-size: 11px;

    font-weight: 700;

    box-shadow:
        0 10px 22px
        rgba(37,99,235,.18);

    transition: .25s ease;

    cursor: pointer;
}


.submit-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 14px 28px
        rgba(37,99,235,.25);
}


/* =========================================================
   SIDE CARD
========================================================= */

.side-card {

    padding: 23px;

    border:
        1px solid #e7edf5;

    border-radius: 18px;

    background: white;

    box-shadow:
        0 12px 35px
        rgba(15,23,42,.05);

    margin-bottom: 18px;
}


.side-card h5 {

    margin-bottom: 17px;

    color: #1e293b;

    font-size: 13px;

    font-weight: 800;
}


.schedule-row {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 11px 0;

    border-bottom:
        1px solid #eef2f7;
}


.schedule-row:last-child {

    border-bottom: none;
}


.schedule-icon {

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


.schedule-row strong {

    display: block;

    color: #334155;

    font-size: 10px;
}


.schedule-row span {

    display: block;

    margin-top: 2px;

    color: #94a3b8;

    font-size: 8px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    border-radius: 11px;

    font-size: 10px;
}


.alert ul {

    padding-left: 18px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 767px) {

    .page {

        padding:
            30px 0 50px;
    }


    .booking-card {

        padding: 20px;
    }


    .patient-details {

        grid-template-columns:
            1fr;
    }


    .service-grid {

        grid-template-columns:
            1fr;
    }

}


@media (max-width: 480px) {

    .brand {

        font-size: 17px;
    }


    .page-heading h1 {

        font-size: 25px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="container">

        <div class="d-flex align-items-center justify-content-between">

            <a
                href="dashboard.php"
                class="brand"
            >

                <div class="brand-icon">

                    <i
                        class="fa-solid fa-heart-pulse"
                    ></i>

                </div>

                Care<span>Sched</span>

            </a>


            <a
                href="dashboard.php"
                class="back-link"
            >

                <i
                    class="fa-solid fa-arrow-left me-1"
                ></i>

                Dashboard

            </a>

        </div>

    </div>

</nav>


<!-- =========================================================
     PAGE
========================================================= -->

<main class="page">

<div class="container">


    <!-- PAGE HEADING -->

    <div class="page-heading">

        <div class="label">

            <i
                class="fa-solid fa-calendar-check"
            ></i>

            Appointment Scheduling

        </div>


        <h1>
            Book an Appointment
        </h1>


        <p>

            Choose your healthcare service and
            preferred schedule at the Rural Health Unit.

        </p>

    </div>


    <div class="row g-4">


        <!-- =================================================
             FORM
        ================================================== -->

        <div class="col-lg-8">

            <div class="booking-card">


                <div class="card-title">

                    <div class="card-title-icon">

                        <i
                            class="fa-solid fa-calendar-plus"
                        ></i>

                    </div>

                    Appointment Information

                </div>


                <!-- ERRORS -->

                <?php if (!empty($errors)): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >

                        <strong>
                            Please check the following:
                        </strong>

                        <ul class="mb-0 mt-2">

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= e($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <!-- SUCCESS -->

                <?php if ($msg = flash('success')): ?>

                    <div
                        class="alert alert-success"
                        role="alert"
                    >

                        <i
                            class="fa-solid fa-circle-check me-2"
                        ></i>

                        <?= e($msg) ?>

                    </div>

                <?php endif; ?>


                <!-- PATIENT INFORMATION -->

                <div class="patient-box">

                    <div class="patient-header">

                        <div class="patient-avatar">

                            <i
                                class="fa-solid fa-user"
                            ></i>

                        </div>


                        <div>

                            <div class="patient-name">

                                <?= e(
                                    trim(
                                        $patient['first_name']
                                        . ' '
                                        . ($patient['middle_name'] ?? '')
                                        . ' '
                                        . $patient['last_name']
                                    )
                                ) ?>

                            </div>


                            <div class="patient-email">

                                <?= e(
                                    $patient['email']
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <div class="patient-details">


                        <div>

                            <div class="detail-label">
                                Age
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $patient['age']
                                    ?? 'N/A'
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="detail-label">
                                Sex
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $patient['sex']
                                    ?? 'N/A'
                                ) ?>

                            </div>

                        </div>


                        <div>

                            <div class="detail-label">
                                Contact
                            </div>

                            <div class="detail-value">

                                <?= e(
                                    $patient['contact_number']
                                    ?? 'N/A'
                                ) ?>

                            </div>

                        </div>


                    </div>

                </div>


                <!-- =================================================
                     APPOINTMENT FORM
                ================================================== -->

                <form
                    method="POST"
                    action=""
                    novalidate
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($token) ?>"
                    >


                    <!-- SERVICE -->

                    <div class="mb-4">

                        <label class="form-label">

                            Healthcare Service

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="service-grid">


                            <?php if (empty($services)): ?>

                                <div
                                    class="alert alert-warning"
                                    style="grid-column:1/-1;"
                                >

                                    No active healthcare services
                                    are currently available.

                                </div>

                            <?php endif; ?>


                            <?php foreach (
                                $services
                                as $serviceName
                                => $serviceInfo
                            ): ?>


                                <div class="service-option">

                                    <input
                                        type="radio"
                                        name="service"
                                        id="<?= e(
                                            'service_' .
                                            md5($serviceName)
                                        ) ?>"
                                        value="<?= e(
                                            $serviceName
                                        ) ?>"
                                        <?= $service ===
                                            $serviceName
                                            ? 'checked'
                                            : '' ?>
                                        required
                                    >


                                    <label
                                        for="<?= e(
                                            'service_' .
                                            md5($serviceName)
                                        ) ?>"
                                    >

                                        <div class="service-icon">

                                            <i
                                                class="fa-solid <?= e(
                                                    $serviceInfo['icon']
                                                ) ?>"
                                            ></i>

                                        </div>


                                        <div>

                                            <div class="service-name">

                                                <?= e(
                                                    $serviceName
                                                ) ?>

                                            </div>


                                            <div
                                                class="service-description"
                                            >

                                                <?= e(
                                                    $serviceInfo[
                                                        'description'
                                                    ]
                                                ) ?>

                                            </div>

                                        </div>

                                    </label>

                                </div>


                            <?php endforeach; ?>


                        </div>

                    </div>


                    <!-- DATE + TIME -->

                    <div class="row">


                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Appointment Date

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-calendar"
                                ></i>


                                <input
                                    type="date"
                                    name="appointment_date"
                                    class="form-control"
                                    value="<?= e(
                                        $appointment_date
                                    ) ?>"
                                    min="<?= date('Y-m-d') ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div class="col-md-6 mb-3">

                            <label class="form-label">

                                Preferred Time

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <div class="input-wrapper">

                                <i
                                    class="fa-solid fa-clock"
                                ></i>


                                <input
                                    type="time"
                                    name="appointment_time"
                                    class="form-control"
                                    value="<?= e(
                                        $appointment_time
                                    ) ?>"
                                    min="08:00"
                                    max="16:30"
                                    required
                                >

                            </div>

                        </div>


                    </div>


                    <!-- REASON -->

                    <div class="mb-3">

                        <label class="form-label">

                            Reason for Appointment

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrapper">

                            <i
                                class="fa-solid fa-notes-medical"
                            ></i>


                            <textarea
                                name="reason"
                                class="form-control"
                                placeholder="Briefly describe the reason for your appointment..."
                                required
                            ><?= e($reason) ?></textarea>

                        </div>

                    </div>


                    <!-- CONTACT -->

                    <div class="mb-3">

                        <label class="form-label">

                            Contact Number

                            <span class="required">
                                *
                            </span>

                        </label>


                        <div class="input-wrapper">

                            <i
                                class="fa-solid fa-phone"
                            ></i>


                            <input
                                type="tel"
                                name="contact_number"
                                class="form-control"
                                value="<?= e(
                                    $contact_number
                                ) ?>"
                                placeholder="09XXXXXXXXX"
                                required
                            >

                        </div>

                    </div>


                    <!-- NOTES -->

                    <div class="mb-3">

                        <label class="form-label">

                            Additional Notes

                            <span
                                style="
                                    font-weight:400;
                                    color:#94a3b8;
                                "
                            >

                                (Optional)

                            </span>

                        </label>


                        <div class="input-wrapper">

                            <i
                                class="fa-solid fa-message"
                            ></i>


                            <textarea
                                name="notes"
                                class="form-control"
                                placeholder="Add any additional information..."
                            ><?= e($notes) ?></textarea>

                        </div>

                    </div>


                    <!-- INFORMATION -->

                    <div class="info-box">

                        <i
                            class="fa-solid fa-circle-info"
                        ></i>


                        <span>

                            Your appointment will initially be
                            marked as <strong>Pending</strong>.
                            RHU staff will review your request.
                            You will receive an email notification
                            once your appointment status is updated.

                        </span>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="submit-button"
                    >

                        <i
                            class="fa-solid fa-calendar-check me-2"
                        ></i>

                        Submit Appointment Request

                    </button>


                </form>


            </div>

        </div>


        <!-- =================================================
             SIDE INFORMATION
        ================================================== -->

        <div class="col-lg-4">


            <!-- RHU SCHEDULE -->

            <div class="side-card">

                <h5>

                    <i
                        class="fa-solid fa-clock me-2"
                        style="color:#2563eb;"
                    ></i>

                    RHU Schedule

                </h5>


                <div class="schedule-row">

                    <div class="schedule-icon">

                        <i
                            class="fa-solid fa-calendar-day"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Monday - Friday
                        </strong>

                        <span>
                            Regular appointment days
                        </span>

                    </div>

                </div>


                <div class="schedule-row">

                    <div class="schedule-icon">

                        <i
                            class="fa-solid fa-sun"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            8:00 AM - 4:30 PM
                        </strong>

                        <span>
                            Healthcare service hours
                        </span>

                    </div>

                </div>


                <div class="schedule-row">

                    <div class="schedule-icon">

                        <i
                            class="fa-solid fa-envelope"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Email Notifications
                        </strong>

                        <span>
                            Appointment status updates
                        </span>

                    </div>

                </div>

            </div>


            <!-- BOOKING TIPS -->

            <div class="side-card">

                <h5>

                    <i
                        class="fa-solid fa-lightbulb me-2"
                        style="color:#2563eb;"
                    ></i>

                    Booking Tips

                </h5>


                <div class="schedule-row">

                    <div class="schedule-icon">

                        <i
                            class="fa-solid fa-1"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Choose your service
                        </strong>

                        <span>
                            Select the healthcare service you need.
                        </span>

                    </div>

                </div>


                <div class="schedule-row">

                    <div class="schedule-icon">

                        <i
                            class="fa-solid fa-2"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Select your schedule
                        </strong>

                        <span>
                            Choose an available date and time.
                        </span>

                    </div>

                </div>


                <div class="schedule-row">

                    <div class="schedule-icon">

                        <i
                            class="fa-solid fa-3"
                        ></i>

                    </div>


                    <div>

                        <strong>
                            Check your email
                        </strong>

                        <span>
                            Watch for appointment notifications.
                        </span>

                    </div>

                </div>

            </div>


            <!-- SECURITY -->

            <div class="side-card">

                <h5>

                    <i
                        class="fa-solid fa-shield-halved me-2"
                        style="color:#2563eb;"
                    ></i>

                    Your Information

                </h5>


                <p
                    style="
                        color:#64748b;
                        font-size:10px;
                        line-height:1.7;
                        margin:0;
                    "
                >

                    Your personal and appointment information
                    is handled securely by the CareSched system
                    and is used for healthcare appointment
                    management.

                </p>

            </div>


        </div>


    </div>

</div>

</main>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<!-- =========================================================
     FORM JAVASCRIPT
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.querySelector(
                'form'
            );

        const button =
            document.querySelector(
                '.submit-button'
            );


        if (form && button) {

            form.addEventListener(
                'submit',
                function () {

                    button.disabled =
                        true;

                    button.innerHTML = `
                        <span
                            class="spinner-border spinner-border-sm me-2"
                            role="status"
                            aria-hidden="true"
                        ></span>

                        Submitting Appointment...
                    `;

                }
            );

        }


        /* ---------------------------------------------
           DATE VALIDATION
        --------------------------------------------- */

        const dateInput =
            document.querySelector(
                'input[name="appointment_date"]'
            );


        if (dateInput) {

            const today =
                new Date()
                    .toISOString()
                    .split('T')[0];

            dateInput.min =
                today;

        }


        /* ---------------------------------------------
           TIME VALIDATION
        --------------------------------------------- */

        const timeInput =
            document.querySelector(
                'input[name="appointment_time"]'
            );


        if (timeInput) {

            timeInput.addEventListener(
                'change',
                function () {

                    if (
                        this.value <
                        '08:00' ||
                        this.value >
                        '16:30'
                    ) {

                        alert(
                            'Please select a time between 8:00 AM and 4:30 PM.'
                        );

                        this.value = '';

                    }

                }
            );

        }

    }
);

</script>


</body>

</html>