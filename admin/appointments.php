<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ensure_role('admin');


// ======================================================
// SMTP CONFIGURATION
// ======================================================

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USERNAME = 'johnloydatonducan84@gmail.com';

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
| Use a NEW Gmail APP PASSWORD here.
| Do NOT use your normal Gmail password.
|
| Example:
| const SMTP_PASSWORD = 'xxxx xxxx xxxx xxxx';
|
*/
const SMTP_PASSWORD = 'fkmt qqeb tlaa gsdf';

const SMTP_FROM_NAME = 'CareSched - RHU Arakan';


// ======================================================
// SEND APPOINTMENT EMAIL
// ======================================================

function sendAppointmentNotification(
    string $patientEmail,
    string $patientName,
    string $serviceName,
    string $appointmentDate,
    string $appointmentTime,
    string $status,
    string $rejectionReason = ''
): array {

    $mail = new PHPMailer(true);

    try {

        // SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Charset
        $mail->CharSet = 'UTF-8';

        // Sender
        $mail->setFrom(
            SMTP_USERNAME,
            SMTP_FROM_NAME
        );

        // Recipient
        $mail->addAddress(
            $patientEmail,
            $patientName
        );

        // ==================================================
        // SUBJECT
        // ==================================================

        switch ($status) {

            case 'Approved':
                $subject = 'CareSched Appointment Approved';
                break;

            case 'Rejected':
                $subject = 'CareSched Appointment Rejected';
                break;

            case 'Completed':
                $subject = 'CareSched Appointment Completed';
                break;

            case 'Cancelled':
                $subject = 'CareSched Appointment Cancelled';
                break;

            case 'Pending':
                $subject = 'CareSched Appointment Status Updated';
                break;

            default:
                $subject = 'CareSched Appointment Update';
        }


        // ==================================================
        // FORMAT DATE
        // ==================================================

        $formattedDate = date(
            'F d, Y',
            strtotime($appointmentDate)
        );


        // ==================================================
        // FORMAT TIME
        // ==================================================

        $formattedTime = date(
            'h:i A',
            strtotime($appointmentTime)
        );


        // ==================================================
        // STATUS MESSAGE
        // ==================================================

        $statusColor = '#2563eb';
        $statusMessage = '';


        switch ($status) {

            case 'Approved':

                $statusColor = '#16a34a';

                $statusMessage =
                    'Good news! Your appointment has been approved by the Rural Health Unit.';

                break;


            case 'Rejected':

                $statusColor = '#dc2626';

                $statusMessage =
                    'We are sorry, but your appointment request has been rejected by the Rural Health Unit.';

                break;


            case 'Completed':

                $statusColor = '#0284c7';

                $statusMessage =
                    'Your CareSched appointment has been marked as completed. Thank you for using our service.';

                break;


            case 'Cancelled':

                $statusColor = '#64748b';

                $statusMessage =
                    'Your CareSched appointment has been cancelled.';

                break;


            case 'Pending':

                $statusColor = '#d97706';

                $statusMessage =
                    'Your appointment status is currently pending. Please wait for further confirmation from the Rural Health Unit.';

                break;


            default:

                $statusMessage =
                    'Your appointment status has been updated.';
        }


        // ==================================================
        // SAFE VALUES
        // ==================================================

        $safePatientName = htmlspecialchars(
            $patientName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeServiceName = htmlspecialchars(
            $serviceName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeStatus = htmlspecialchars(
            $status,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeDate = htmlspecialchars(
            $formattedDate,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeTime = htmlspecialchars(
            $formattedTime,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeMessage = htmlspecialchars(
            $statusMessage,
            ENT_QUOTES,
            'UTF-8'
        );


        // ==================================================
        // REJECTION REASON
        // ==================================================

        $rejectionHtml = '';

        if (
            $status === 'Rejected'
            &&
            $rejectionReason !== ''
        ) {

            $safeReason = htmlspecialchars(
                $rejectionReason,
                ENT_QUOTES,
                'UTF-8'
            );

            $rejectionHtml = '

                <div style="
                    margin-top:20px;
                    padding:15px;
                    border-radius:10px;
                    background:#fef2f2;
                    border:1px solid #fecaca;
                ">

                    <div style="
                        font-size:13px;
                        font-weight:700;
                        color:#991b1b;
                        margin-bottom:6px;
                    ">
                        Reason for Rejection
                    </div>

                    <div style="
                        font-size:13px;
                        color:#7f1d1d;
                        line-height:1.6;
                    ">
                        ' . $safeReason . '
                    </div>

                </div>

            ';
        }


        // ==================================================
        // HTML EMAIL
        // ==================================================

        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<title>CareSched Appointment Notification</title>

</head>


<body style="
    margin:0;
    padding:0;
    background:#f4f8fc;
    font-family:Arial,Helvetica,sans-serif;
">


<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="padding:35px 15px;"
>

<tr>

<td align="center">


<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        max-width:620px;
        background:#ffffff;
        border-radius:18px;
        overflow:hidden;
        box-shadow:0 10px 35px rgba(15,23,42,.10);
    "
>


<!-- HEADER -->

<tr>

<td style="
    padding:28px;
    background:linear-gradient(
        135deg,
        #2563eb,
        #1d4ed8,
        #0ea5e9
    );
    color:#ffffff;
">

<div style="
    font-size:24px;
    font-weight:800;
">
    ❤️ CareSched
</div>

<div style="
    margin-top:5px;
    font-size:12px;
    color:rgba(255,255,255,.8);
">
    Rural Health Unit Appointment System
</div>

</td>

</tr>


<!-- CONTENT -->

<tr>

<td style="padding:35px 32px;">

<div style="
    font-size:14px;
    color:#64748b;
    margin-bottom:7px;
">
    Hello,
</div>


<div style="
    font-size:22px;
    font-weight:700;
    color:#172033;
    margin-bottom:15px;
">
    ' . $safePatientName . '
</div>


<div style="
    padding:14px 16px;
    border-radius:10px;
    background:#f8fafc;
    border-left:4px solid ' . $statusColor . ';
    color:#475569;
    font-size:13px;
    line-height:1.6;
">
    ' . $safeMessage . '
</div>


<!-- STATUS -->

<div style="
    text-align:center;
    margin:25px 0;
">

<span style="
    display:inline-block;
    padding:9px 18px;
    border-radius:50px;
    background:' . $statusColor . ';
    color:#ffffff;
    font-size:13px;
    font-weight:700;
">
    ' . $safeStatus . '
</span>

</div>


<!-- APPOINTMENT DETAILS -->

<div style="
    font-size:14px;
    font-weight:700;
    color:#172033;
    margin-bottom:12px;
">
    Appointment Details
</div>


<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        border:1px solid #e2e8f0;
        border-radius:10px;
        overflow:hidden;
    "
>

<tr>

<td style="
    padding:12px;
    width:38%;
    color:#64748b;
    font-size:12px;
    background:#f8fafc;
">
    Service
</td>

<td style="
    padding:12px;
    color:#1e293b;
    font-size:13px;
    font-weight:600;
">
    ' . $safeServiceName . '
</td>

</tr>


<tr>

<td style="
    padding:12px;
    color:#64748b;
    font-size:12px;
    background:#f8fafc;
">
    Date
</td>

<td style="
    padding:12px;
    color:#1e293b;
    font-size:13px;
    font-weight:600;
">
    ' . $safeDate . '
</td>

</tr>


<tr>

<td style="
    padding:12px;
    color:#64748b;
    font-size:12px;
    background:#f8fafc;
">
    Time
</td>

<td style="
    padding:12px;
    color:#1e293b;
    font-size:13px;
    font-weight:600;
">
    ' . $safeTime . '
</td>

</tr>


<tr>

<td style="
    padding:12px;
    color:#64748b;
    font-size:12px;
    background:#f8fafc;
">
    Status
</td>

<td style="
    padding:12px;
    color:' . $statusColor . ';
    font-size:13px;
    font-weight:700;
">
    ' . $safeStatus . '
</td>

</tr>

</table>


' . $rejectionHtml . '


<div style="
    margin-top:25px;
    font-size:12px;
    color:#64748b;
    line-height:1.7;
">

    Please keep this email for your appointment reference.

    If you have questions regarding your appointment,
    please contact the Rural Health Unit.

</div>


</td>

</tr>


<!-- FOOTER -->

<tr>

<td style="
    padding:20px 32px;
    background:#f8fafc;
    border-top:1px solid #e2e8f0;
    text-align:center;
">

<div style="
    font-size:11px;
    color:#94a3b8;
">
    This is an automated notification from CareSched.
</div>

<div style="
    margin-top:5px;
    font-size:11px;
    color:#94a3b8;
">
    Rural Health Unit of Arakan, Cotabato
</div>

</td>

</tr>


</table>

</td>

</tr>

</table>


</body>

</html>
';


        // ==================================================
        // PLAIN TEXT EMAIL
        // ==================================================

        $plainText =
            "CareSched Appointment Notification\n\n"
            . "Hello " . $patientName . ",\n\n"
            . $statusMessage . "\n\n"
            . "Appointment Details\n"
            . "-------------------\n"
            . "Service: " . $serviceName . "\n"
            . "Date: " . $formattedDate . "\n"
            . "Time: " . $formattedTime . "\n"
            . "Status: " . $status . "\n";


        if (
            $status === 'Rejected'
            &&
            $rejectionReason !== ''
        ) {

            $plainText .=
                "\nReason for Rejection:\n"
                . $rejectionReason
                . "\n";
        }


        $plainText .=
            "\nPlease keep this email for your appointment reference.\n\n"
            . "CareSched\n"
            . "Rural Health Unit of Arakan, Cotabato";


        $mail->AltBody = $plainText;


        // SEND
        $mail->send();


        return [
            'success' => true,
            'message' => 'Email notification sent successfully.'
        ];


    } catch (Exception $e) {

        error_log(
            'CareSched PHPMailer Error: '
            . $mail->ErrorInfo
        );

        return [
            'success' => false,
            'message' =>
                'Appointment updated, but email notification could not be sent. '
                . $mail->ErrorInfo
        ];
    }
}


// ======================================================
// FLASH MESSAGES
// ======================================================

$success =
    $_SESSION['appointment_success'] ?? '';

$error =
    $_SESSION['appointment_error'] ?? '';

unset(
    $_SESSION['appointment_success'],
    $_SESSION['appointment_error']
);


// ======================================================
// STATUS UPDATE
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appointmentId =
        $_POST['appointment_id'] ?? '';

    $status =
        $_POST['status'] ?? '';

    $rejectionReason =
        trim(
            $_POST['rejection_reason'] ?? ''
        );


    $allowedStatuses = [
        'Pending',
        'Approved',
        'Rejected',
        'Completed',
        'Cancelled'
    ];


    // ==================================================
    // VALIDATION
    // ==================================================

    if (
        !ctype_digit(
            (string)$appointmentId
        )
    ) {

        $_SESSION['appointment_error'] =
            'Invalid appointment ID.';

        header('Location: appointments.php');
        exit;

    }


    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $_SESSION['appointment_error'] =
            'Invalid appointment status.';

        header('Location: appointments.php');
        exit;

    }


    if (
        $status === 'Rejected'
        &&
        $rejectionReason === ''
    ) {

        $_SESSION['appointment_error'] =
            'Please provide a rejection reason.';

        header('Location: appointments.php');
        exit;
    }


    try {

        // ==================================================
        // GET APPOINTMENT
        // ==================================================

        $checkStmt = $pdo->prepare("

            SELECT

                a.id,
                a.patient_id,
                a.service_id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                a.rejection_reason,

                p.first_name,
                p.middle_name,
                p.last_name,

                u.email,
                u.username,

                s.service_name

            FROM appointments a

            INNER JOIN patients p
                ON a.patient_id = p.id

            INNER JOIN users u
                ON p.user_id = u.id

            INNER JOIN services s
                ON a.service_id = s.id

            WHERE a.id = ?

            LIMIT 1

        ");


        $checkStmt->execute([
            $appointmentId
        ]);


        $appointment =
            $checkStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$appointment) {

            throw new Exception(
                'Appointment not found.'
            );
        }


        // ==================================================
        // CURRENT STATUS
        // ==================================================

        $oldStatus =
            $appointment['status'];


        // ==================================================
        // PREVENT DUPLICATE EMAIL
        // ==================================================

        if ($oldStatus === $status) {

            $_SESSION['appointment_success'] =
                'Appointment status is already '
                . $status
                . '. No email notification was sent.';

            header('Location: appointments.php');
            exit;
        }


        // ==================================================
        // CHECK PATIENT EMAIL
        // ==================================================

        $patientEmail =
            trim(
                $appointment['email'] ?? ''
            );


        if (
            !filter_var(
                $patientEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            throw new Exception(
                'The patient does not have a valid email address.'
            );
        }


        // ==================================================
        // PATIENT NAME
        // ==================================================

        $patientName =
            trim(

                $appointment['first_name']
                . ' '

                . (
                    !empty(
                        $appointment['middle_name']
                    )
                    ?
                    $appointment['middle_name']
                    . ' '
                    :
                    ''
                )

                . $appointment['last_name']
            );


        // ==================================================
        // UPDATE DATABASE
        // ==================================================

        if ($status === 'Rejected') {

            $updateStmt =
                $pdo->prepare("

                    UPDATE appointments

                    SET

                        status = :status,

                        rejection_reason =
                            :rejection_reason,

                        updated_at =
                            NOW()

                    WHERE id = :id

                ");


            $updateStmt->execute([

                ':status' =>
                    $status,

                ':rejection_reason' =>
                    $rejectionReason,

                ':id' =>
                    $appointmentId

            ]);

        } else {

            $updateStmt =
                $pdo->prepare("

                    UPDATE appointments

                    SET

                        status = :status,

                        rejection_reason =
                            NULL,

                        updated_at =
                            NOW()

                    WHERE id = :id

                ");


            $updateStmt->execute([

                ':status' =>
                    $status,

                ':id' =>
                    $appointmentId

            ]);
        }


        // ==================================================
        // SEND EMAIL
        // ==================================================

        $emailResult =
            sendAppointmentNotification(

                $patientEmail,

                $patientName,

                $appointment['service_name'],

                $appointment['appointment_date'],

                $appointment['appointment_time'],

                $status,

                $rejectionReason
            );


        // ==================================================
        // SUCCESS MESSAGE
        // ==================================================

        if (
            $emailResult['success']
        ) {

            $_SESSION['appointment_success'] =

                'Appointment for '
                . $patientName
                . ' has been updated from '
                . $oldStatus
                . ' to '
                . $status
                . '. Email notification sent to '
                . $patientEmail
                . '.';

        } else {

            $_SESSION['appointment_success'] =

                'Appointment for '
                . $patientName
                . ' has been updated from '
                . $oldStatus
                . ' to '
                . $status
                . '.';

            $_SESSION['appointment_error'] =
                $emailResult['message'];
        }


        // ==================================================
        // POST REDIRECT GET
        // ==================================================

        header(
            'Location: appointments.php'
        );

        exit;


    } catch (PDOException $e) {

        $_SESSION['appointment_error'] =
            'Database error: '
            . $e->getMessage();

        header('Location: appointments.php');
        exit;


    } catch (Exception $e) {

        $_SESSION['appointment_error'] =
            $e->getMessage();

        header('Location: appointments.php');
        exit;
    }
}


// ======================================================
// GET APPOINTMENTS
// ======================================================

try {

    $stmt = $pdo->query("

        SELECT

            a.id AS appointment_id,

            a.patient_id,

            a.service_id,

            a.appointment_date,

            a.appointment_time,

            a.reason,

            a.notes,

            a.status,

            a.rejection_reason,

            a.created_at,

            a.updated_at,

            p.first_name,

            p.middle_name,

            p.last_name,

            p.contact_number,

            p.address,

            u.email,

            u.username,

            s.service_name,

            s.description AS service_description,

            s.duration,

            s.max_patients

        FROM appointments a

        INNER JOIN patients p
            ON a.patient_id = p.id

        INNER JOIN users u
            ON p.user_id = u.id

        INNER JOIN services s
            ON a.service_id = s.id

        ORDER BY

            a.appointment_date DESC,

            a.appointment_time DESC,

            a.id DESC

    ");


    $appointments =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    die(

        'Database error: '

        . htmlspecialchars(
            $e->getMessage()
        )

    );
}


// ======================================================
// COUNTERS
// ======================================================

$totalAppointments =
    count($appointments);

$pendingCount = 0;
$approvedCount = 0;
$completedCount = 0;
$rejectedCount = 0;
$cancelledCount = 0;


foreach (
    $appointments
    as $appointment
) {

    switch (
        $appointment['status']
    ) {

        case 'Pending':
            $pendingCount++;
            break;

        case 'Approved':
            $approvedCount++;
            break;

        case 'Completed':
            $completedCount++;
            break;

        case 'Rejected':
            $rejectedCount++;
            break;

        case 'Cancelled':
            $cancelledCount++;
            break;
    }
}


// ======================================================
// HELPER FUNCTIONS
// ======================================================

function patientFullName($appointment)
{
    return trim(

        $appointment['first_name']

        . ' '

        . (

            !empty(
                $appointment['middle_name']
            )

            ?

            $appointment['middle_name']
            . ' '

            :

            ''
        )

        . $appointment['last_name']
    );
}


function statusClass($status)
{
    switch ($status) {

        case 'Pending':
            return 'pending';

        case 'Approved':
            return 'approved';

        case 'Completed':
            return 'completed';

        case 'Rejected':
            return 'rejected';

        case 'Cancelled':
            return 'cancelled';

        default:
            return 'pending';
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
    Appointments | CareSched Admin
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

    background:
        linear-gradient(
            135deg,
            #f8fafc,
            #eef6ff
        );

    color: #1e293b;
}


/* =====================================================
   MAIN
===================================================== */

.main {

    margin-left: 260px;

    min-height: 100vh;

    padding: 30px;
}


.topbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}


.page-title {

    font-size: 27px;

    font-weight: 800;

    margin: 0;
}


.page-subtitle {

    color: #64748b;

    font-size: 13px;

    margin-top: 5px;
}


.admin-badge {

    padding: 10px 15px;

    border-radius: 12px;

    background: white;

    border: 1px solid #e2e8f0;

    font-size: 12px;

    font-weight: 700;
}


/* =====================================================
   ALERT
===================================================== */

.alert-custom {

    border: none;

    border-radius: 14px;

    padding: 15px 18px;

    font-size: 13px;

    margin-bottom: 20px;
}


/* =====================================================
   STATS
===================================================== */

.stats {

    display: grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}


.stat-card {

    padding: 20px;

    border-radius: 18px;

    background: white;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 10px 30px
        rgba(15,23,42,.05);
}


.stat-icon {

    width: 38px;

    height: 38px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    margin-bottom: 12px;

    color: #0d6efd;

    background: #eff6ff;
}


.stat-number {

    font-size: 25px;

    font-weight: 800;
}


.stat-label {

    color: #64748b;

    font-size: 11px;

    font-weight: 600;
}


/* =====================================================
   APPOINTMENT CARD
===================================================== */

.card-main {

    background: white;

    border-radius: 20px;

    border: 1px solid #e2e8f0;

    overflow: hidden;

    box-shadow:
        0 15px 45px
        rgba(15,23,42,.06);
}


.card-header-custom {

    padding: 20px 22px;

    border-bottom: 1px solid #e2e8f0;

    display: flex;

    justify-content: space-between;

    align-items: center;
}


.card-header-custom h5 {

    margin: 0;

    font-size: 16px;

    font-weight: 800;
}


.table-responsive {

    overflow-x: auto;
}


table {

    min-width: 1050px;
}


.table thead th {

    padding: 14px 18px;

    color: #64748b;

    background: #f8fafc;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .4px;

    border-bottom: 1px solid #e2e8f0;
}


.table tbody td {

    padding: 16px 18px;

    vertical-align: middle;

    border-bottom: 1px solid #f1f5f9;

    font-size: 12px;
}


.table tbody tr:hover {

    background: #f8fbff;
}


/* =====================================================
   PATIENT
===================================================== */

.patient-name {

    font-weight: 700;

    color: #1e293b;
}


.patient-email {

    color: #94a3b8;

    font-size: 10px;

    margin-top: 3px;
}


.service-name {

    font-weight: 700;

    color: #334155;
}


.date-main {

    font-weight: 700;
}


.time-main {

    color: #64748b;

    font-size: 11px;
}


/* =====================================================
   STATUS
===================================================== */

.status {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 6px 10px;

    border-radius: 50px;

    font-size: 10px;

    font-weight: 700;
}


.status.pending {

    color: #92400e;

    background: #fef3c7;
}


.status.approved {

    color: #166534;

    background: #dcfce7;
}


.status.completed {

    color: #075985;

    background: #e0f2fe;
}


.status.rejected {

    color: #991b1b;

    background: #fee2e2;
}


.status.cancelled {

    color: #475569;

    background: #e2e8f0;
}


/* =====================================================
   ACTION
===================================================== */

.action-form {

    display: flex;

    gap: 5px;

    align-items: center;

    flex-wrap: wrap;
}


.action-form select {

    min-width: 110px;

    border-radius: 8px;

    border: 1px solid #dbe4ee;

    padding: 7px;

    font-size: 10px;
}


.btn-update {

    border: none;

    border-radius: 8px;

    padding: 8px 10px;

    color: white;

    background: #0d6efd;

    font-size: 11px;

    font-weight: 700;
}


.btn-update:hover {

    background: #0b5ed7;
}


/* =====================================================
   EMPTY
===================================================== */

.empty {

    padding: 70px 20px;

    text-align: center;

    color: #94a3b8;
}


.empty i {

    font-size: 45px;

    margin-bottom: 15px;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);
    }
}


@media (max-width: 768px) {

    .main {

        margin-left: 0;

        padding: 20px 12px;
    }

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .topbar {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }
}


@media (max-width: 480px) {

    .stats {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<!-- ======================================================
     SHARED ADMIN SIDEBAR
     
     IMPORTANT:
     This sidebar comes from:
     /caresched/includes/admin_sidebar.php
====================================================== -->

<?php
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>


<!-- ======================================================
     MAIN CONTENT
====================================================== -->

<main class="main">


<!-- TOPBAR -->

<div class="topbar">

<div>

<h1 class="page-title">
    Appointments
</h1>

<div class="page-subtitle">
    Manage and monitor patient appointment requests.
</div>

</div>


<div class="admin-badge">

<i class="bi bi-shield-check text-primary me-1"></i>

<?= e($_SESSION['username'] ?? 'Administrator') ?>

<small style="display:block; font-weight:500; color:#64748b; font-size:9px; margin-top:2px;"><?= e($_SESSION['email'] ?? '') ?></small>

</div>

</div>


<!-- ======================================================
     ALERTS
====================================================== -->

<?php if ($success): ?>

<div class="alert alert-success alert-custom">

<i class="bi bi-check-circle-fill me-2"></i>

<?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert alert-danger alert-custom">

<i class="bi bi-exclamation-circle-fill me-2"></i>

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>


<!-- ======================================================
     STATISTICS
====================================================== -->

<div class="stats">


<!-- TOTAL -->

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-calendar3"></i>

</div>

<div class="stat-number">

<?= $totalAppointments ?>

</div>

<div class="stat-label">

Total Appointments

</div>

</div>


<!-- PENDING -->

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-hourglass-split"></i>

</div>

<div class="stat-number">

<?= $pendingCount ?>

</div>

<div class="stat-label">

Pending

</div>

</div>


<!-- APPROVED -->

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-check-circle"></i>

</div>

<div class="stat-number">

<?= $approvedCount ?>

</div>

<div class="stat-label">

Approved

</div>

</div>


<!-- COMPLETED -->

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-check2-all"></i>

</div>

<div class="stat-number">

<?= $completedCount ?>

</div>

<div class="stat-label">

Completed

</div>

</div>


<!-- REJECTED / CANCELLED -->

<div class="stat-card">

<div class="stat-icon">

<i class="bi bi-x-circle"></i>

</div>

<div class="stat-number">

<?= $rejectedCount + $cancelledCount ?>

</div>

<div class="stat-label">

Rejected / Cancelled

</div>

</div>


</div>


<!-- ======================================================
     APPOINTMENTS
====================================================== -->

<div class="card-main">


<div class="card-header-custom">

<h5>

<i class="bi bi-calendar-check text-primary me-2"></i>

Appointment Requests

</h5>


<span class="badge text-bg-light">

<?= $totalAppointments ?>

records

</span>

</div>


<?php if (empty($appointments)): ?>

<div class="empty">

<i class="bi bi-calendar-x"></i>

<h5>
    No appointments found
</h5>

<p>
    There are currently no appointment records.
</p>

</div>


<?php else: ?>


<div class="table-responsive">

<table class="table mb-0">

<thead>

<tr>

<th>
    Patient
</th>

<th>
    Service
</th>

<th>
    Date & Time
</th>

<th>
    Status
</th>

<th>
    Action
</th>

</tr>

</thead>


<tbody>


<?php foreach ($appointments as $appointment): ?>


<?php

$patientName =
    patientFullName(
        $appointment
    );

?>


<tr>


<!-- ==================================================
     PATIENT
================================================== -->

<td>

<div class="patient-name">

<?= htmlspecialchars(
    $patientName
) ?>

</div>


<div class="patient-email">

<i class="bi bi-envelope me-1"></i>

<?= htmlspecialchars(
    $appointment['email']
) ?>

</div>


<?php if (
    !empty(
        $appointment['contact_number']
    )
): ?>

<div class="patient-email">

<i class="bi bi-telephone me-1"></i>

<?= htmlspecialchars(
    $appointment['contact_number']
) ?>

</div>

<?php endif; ?>

</td>


<!-- ==================================================
     SERVICE
================================================== -->

<td>

<div class="service-name">

<?= htmlspecialchars(
    $appointment['service_name']
) ?>

</div>


<?php if (
    !empty(
        $appointment['duration']
    )
): ?>

<div class="patient-email">

<?= (int)$appointment['duration'] ?>

minutes

</div>

<?php endif; ?>

</td>


<!-- ==================================================
     DATE / TIME
================================================== -->

<td>

<div class="date-main">

<?= date(
    'M d, Y',
    strtotime(
        $appointment['appointment_date']
    )
) ?>

</div>


<div class="time-main">

<i class="bi bi-clock me-1"></i>

<?= date(
    'h:i A',
    strtotime(
        $appointment['appointment_time']
    )
) ?>

</div>

</td>


<!-- ==================================================
     STATUS
================================================== -->

<td>

<span class="status <?= statusClass(
    $appointment['status']
) ?>">


<?php if (
    $appointment['status']
    === 'Pending'
): ?>

<i class="bi bi-hourglass-split"></i>


<?php elseif (
    $appointment['status']
    === 'Approved'
): ?>

<i class="bi bi-check-circle"></i>


<?php elseif (
    $appointment['status']
    === 'Completed'
): ?>

<i class="bi bi-check2-all"></i>


<?php elseif (
    $appointment['status']
    === 'Rejected'
): ?>

<i class="bi bi-x-circle"></i>


<?php else: ?>

<i class="bi bi-slash-circle"></i>

<?php endif; ?>


<?= htmlspecialchars(
    $appointment['status']
) ?>

</span>


<?php if (
    $appointment['status']
    === 'Rejected'
    &&
    !empty(
        $appointment['rejection_reason']
    )
): ?>

<div
    class="patient-email mt-2"
    title="<?= htmlspecialchars(
        $appointment['rejection_reason']
    ) ?>"
>

Reason:

<?= htmlspecialchars(
    $appointment['rejection_reason']
) ?>

</div>

<?php endif; ?>

</td>


<!-- ==================================================
     ACTION
================================================== -->

<td>

<form
    method="POST"
    class="action-form"
    onsubmit="return validateStatusForm(this);"
>


<input
    type="hidden"
    name="appointment_id"
    value="<?= (int)$appointment['appointment_id'] ?>"
>


<select
    name="status"
    class="form-select status-select"
    onchange="toggleReason(this)"
>

<option
    value="Pending"
    <?= $appointment['status'] === 'Pending'
        ? 'selected'
        : '' ?>
>
    Pending
</option>


<option
    value="Approved"
    <?= $appointment['status'] === 'Approved'
        ? 'selected'
        : '' ?>
>
    Approved
</option>


<option
    value="Completed"
    <?= $appointment['status'] === 'Completed'
        ? 'selected'
        : '' ?>
>
    Completed
</option>


<option
    value="Rejected"
    <?= $appointment['status'] === 'Rejected'
        ? 'selected'
        : '' ?>
>
    Rejected
</option>


<option
    value="Cancelled"
    <?= $appointment['status'] === 'Cancelled'
        ? 'selected'
        : '' ?>
>
    Cancelled
</option>

</select>


<input
    type="text"
    name="rejection_reason"
    class="form-control rejection-input"
    placeholder="Reason for rejection"
    value="<?= htmlspecialchars(
        $appointment['rejection_reason'] ?? ''
    ) ?>"
    style="
        display:
        <?= $appointment['status'] === 'Rejected'
            ? 'block'
            : 'none'
        ?>;
        min-width:190px;
        margin-top:5px;
        font-size:10px;
    "
>


<button
    type="submit"
    class="btn-update"
>

<i class="bi bi-send me-1"></i>

Update

</button>


</form>

</td>


</tr>


<?php endforeach; ?>


</tbody>

</table>

</div>

<?php endif; ?>


</div>


</main>


<!-- ======================================================
     JAVASCRIPT
====================================================== -->

<script>

function toggleReason(select) {

    const form =
        select.closest('form');

    if (!form) {
        return;
    }


    const reason =
        form.querySelector(
            '.rejection-input'
        );


    if (!reason) {
        return;
    }


    if (
        select.value === 'Rejected'
    ) {

        reason.style.display =
            'block';

        reason.required =
            true;

    } else {

        reason.style.display =
            'none';

        reason.required =
            false;

        reason.value =
            '';
    }
}



function validateStatusForm(form) {

    const select =
        form.querySelector(
            '.status-select'
        );


    const reason =
        form.querySelector(
            '.rejection-input'
        );


    if (!select) {
        return false;
    }


    // Rejection reason required
    if (
        select.value === 'Rejected'
    ) {

        if (
            !reason
            ||
            reason.value.trim() === ''
        ) {

            alert(
                'Please provide a rejection reason.'
            );


            if (reason) {
                reason.focus();
            }


            return false;
        }
    }


    // Confirmation
    let message =
        'Update this appointment status to '
        + select.value
        + '?';


    if (
        select.value === 'Rejected'
    ) {

        message =
            'Reject this appointment and send the rejection reason to the patient?';

    } else if (
        select.value === 'Approved'
    ) {

        message =
            'Approve this appointment and send an email notification to the patient?';

    } else if (
        select.value === 'Completed'
    ) {

        message =
            'Mark this appointment as completed and notify the patient?';

    } else if (
        select.value === 'Cancelled'
    ) {

        message =
            'Cancel this appointment and notify the patient?';
    }


    return confirm(message);
}


// Initialize rejection fields

document
    .querySelectorAll(
        '.status-select'
    )
    .forEach(function(select) {

        toggleReason(select);

    });

</script>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>