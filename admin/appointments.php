<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ensure_role('admin');


/*
|--------------------------------------------------------------------------
| GMAIL / PHPMailer CONFIGURATION
|--------------------------------------------------------------------------
|
| PALITAN ANG VALUES SA IBABA
|
*/

$smtpHost = 'smtp.gmail.com';
$smtpUsername = 'YOUR_GMAIL@gmail.com';
$smtpPassword = 'YOUR_16_DIGIT_APP_PASSWORD';
$smtpPort = 587;


/*
|--------------------------------------------------------------------------
| SEND APPOINTMENT EMAIL
|--------------------------------------------------------------------------
*/

function sendAppointmentEmail(
    $patientEmail,
    $patientName,
    $serviceName,
    $appointmentDate,
    $appointmentTime,
    $status
) {
    global $smtpHost;
    global $smtpUsername;
    global $smtpPassword;
    global $smtpPort;

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $smtpUsername,
            'CareSched - RHU Arakan'
        );

        $mail->addAddress(
            $patientEmail,
            $patientName
        );

        $mail->isHTML(true);


        if ($status === 'Approved') {

            $subject = 'CareSched Appointment Approved';

            $statusColor = '#198754';

            $statusMessage = '
                <p>
                    Good news! Your appointment request has been
                    <strong>approved</strong> by the Rural Health Unit of Arakan.
                </p>
            ';

        } elseif ($status === 'Rejected') {

            $subject = 'CareSched Appointment Rejected';

            $statusColor = '#dc3545';

            $statusMessage = '
                <p>
                    We regret to inform you that your appointment request
                    has been <strong>rejected</strong>.
                </p>

                <p>
                    If you need further assistance, please contact the
                    Rural Health Unit of Arakan.
                </p>
            ';

        } else {

            $subject = 'CareSched Appointment Update';

            $statusColor = '#0d6efd';

            $statusMessage = '
                <p>
                    Your CareSched appointment status has been updated.
                </p>
            ';
        }


        $safeName = htmlspecialchars(
            $patientName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeService = htmlspecialchars(
            $serviceName,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeDate = htmlspecialchars(
            $appointmentDate,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeTime = htmlspecialchars(
            $appointmentTime,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeStatus = htmlspecialchars(
            $status,
            ENT_QUOTES,
            'UTF-8'
        );


        $mail->Subject = $subject;


        $mail->Body = '
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CareSched Appointment</title>

</head>


<body style="
    margin:0;
    padding:0;
    background:#f4f7fb;
    font-family:Arial,Helvetica,sans-serif;
">


<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        width:100%;
        background:#f4f7fb;
        padding:35px 15px;
    "
>

<tr>

<td align="center">


<table
    width="600"
    cellpadding="0"
    cellspacing="0"
    style="
        max-width:600px;
        width:100%;
        background:#ffffff;
        border-radius:18px;
        overflow:hidden;
        border:1px solid #e5e7eb;
    "
>


<!-- HEADER -->

<tr>

<td
    style="
        background:#0d6efd;
        padding:30px;
        text-align:center;
        color:#ffffff;
    "
>

<div
    style="
        font-size:28px;
        font-weight:800;
    "
>
    CareSched
</div>

<div
    style="
        margin-top:7px;
        font-size:13px;
        opacity:.9;
    "
>
    RHU Arakan Appointment Scheduling System
</div>

</td>

</tr>


<!-- CONTENT -->

<tr>

<td
    style="
        padding:35px;
    "
>

<h2
    style="
        margin:0 0 18px;
        color:#172033;
        font-size:23px;
    "
>
    Appointment ' . $safeStatus . '
</h2>


<p
    style="
        color:#4b5563;
        font-size:14px;
        line-height:1.7;
    "
>
    Hello <strong>' . $safeName . '</strong>,
</p>


<div
    style="
        color:#4b5563;
        font-size:14px;
        line-height:1.7;
    "
>
    ' . $statusMessage . '
</div>


<!-- STATUS -->

<div
    style="
        margin:25px 0;
        padding:18px;
        background:#f8fafc;
        border-radius:12px;
        text-align:center;
    "
>

<div
    style="
        color:#64748b;
        font-size:12px;
    "
>
    Appointment Status
</div>


<div
    style="
        margin-top:7px;
        color:' . $statusColor . ';
        font-size:22px;
        font-weight:800;
    "
>
    ' . $safeStatus . '
</div>

</div>


<!-- APPOINTMENT DETAILS -->

<table
    width="100%"
    cellpadding="10"
    cellspacing="0"
    style="
        border-collapse:collapse;
        font-size:14px;
    "
>

<tr>

<td
    style="
        border-bottom:1px solid #e5e7eb;
        color:#64748b;
    "
>
    Service
</td>

<td
    align="right"
    style="
        border-bottom:1px solid #e5e7eb;
        color:#172033;
        font-weight:bold;
    "
>
    ' . $safeService . '
</td>

</tr>


<tr>

<td
    style="
        border-bottom:1px solid #e5e7eb;
        color:#64748b;
    "
>
    Date
</td>

<td
    align="right"
    style="
        border-bottom:1px solid #e5e7eb;
        color:#172033;
        font-weight:bold;
    "
>
    ' . $safeDate . '
</td>

</tr>


<tr>

<td
    style="
        color:#64748b;
    "
>
    Time
</td>

<td
    align="right"
    style="
        color:#172033;
        font-weight:bold;
    "
>
    ' . $safeTime . '
</td>

</tr>

</table>


<p
    style="
        margin-top:28px;
        color:#64748b;
        font-size:13px;
        line-height:1.7;
    "
>
    Please keep this email as your appointment reference.
    For questions or concerns, please contact the
    Rural Health Unit of Arakan.
</p>


</td>

</tr>


<!-- FOOTER -->

<tr>

<td
    style="
        background:#f8fafc;
        padding:20px;
        text-align:center;
        color:#94a3b8;
        font-size:12px;
    "
>

This is an automated message from CareSched.<br>

Rural Health Unit - Arakan

</td>

</tr>


</table>

</td>

</tr>

</table>

</body>

</html>
';


        $mail->AltBody =
            "Hello {$patientName},\n\n" .
            "Your CareSched appointment status is: {$status}\n\n" .
            "Service: {$serviceName}\n" .
            "Date: {$appointmentDate}\n" .
            "Time: {$appointmentTime}\n\n" .
            "Rural Health Unit - Arakan";


        $mail->send();

        return [
            'success' => true,
            'message' => 'Email sent successfully.'
        ];

    } catch (Exception $e) {

        error_log(
            'CareSched PHPMailer Error: ' .
            $mail->ErrorInfo
        );

        return [
            'success' => false,
            'message' => $mail->ErrorInfo
        ];
    }
}


/*
|--------------------------------------------------------------------------
| HANDLE STATUS UPDATE
| Approve / Reject / Complete / Cancel
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'update_status'
) {

    $id = (int) ($_POST['id'] ?? 0);

    $new_status =
        trim($_POST['status'] ?? '');


    $allowed_statuses = [
        'Approved',
        'Rejected',
        'Completed',
        'Cancelled',
        'Pending'
    ];


    if (
        $id > 0 &&
        in_array(
            $new_status,
            $allowed_statuses,
            true
        )
    ) {


        /*
        |--------------------------------------------------------------------------
        | GET APPOINTMENT INFORMATION
        |--------------------------------------------------------------------------
        |
        | users.email is connected through:
        |
        | appointments.patient_id
        |        ↓
        | patients.id
        |        ↓
        | patients.user_id
        |        ↓
        | users.id
        |        ↓
        | users.email
        |
        */

        $appointment_stmt = $pdo->prepare("
            SELECT

                a.id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.status AS current_status,

                p.first_name,
                p.last_name,

                u.email,

                s.service_name

            FROM appointments a

            INNER JOIN patients p
                ON a.patient_id = p.id

            INNER JOIN users u
                ON p.user_id = u.id

            INNER JOIN services s
                ON a.service_id = s.id

            WHERE a.id = :id

            LIMIT 1
        ");


        $appointment_stmt->execute([
            'id' => $id
        ]);


        $appointment =
            $appointment_stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($appointment) {


            $current_status =
                $appointment['current_status'];


            /*
            |--------------------------------------------------------------------------
            | UPDATE APPOINTMENT STATUS
            |--------------------------------------------------------------------------
            */

            $update = $pdo->prepare("
                UPDATE appointments

                SET
                    status = :status

                WHERE id = :id
            ");


            $update->execute([
                'status' => $new_status,
                'id' => $id
            ]);


            /*
            |--------------------------------------------------------------------------
            | SEND EMAIL ONLY WHEN STATUS ACTUALLY CHANGES
            |--------------------------------------------------------------------------
            */

            if (
                $current_status !== $new_status &&
                in_array(
                    $new_status,
                    [
                        'Approved',
                        'Rejected'
                    ],
                    true
                )
            ) {


                $patientName =
                    trim(
                        $appointment['first_name']
                        . ' '
                        . $appointment['last_name']
                    );


                $patientEmail =
                    trim($appointment['email']);


                $serviceName =
                    $appointment['service_name'];


                $formattedDate =
                    date(
                        'F d, Y',
                        strtotime(
                            $appointment[
                                'appointment_date'
                            ]
                        )
                    );


                $formattedTime =
                    date(
                        'h:i A',
                        strtotime(
                            $appointment[
                                'appointment_time'
                            ]
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | SEND EMAIL
                |--------------------------------------------------------------------------
                */

                if (
                    filter_var(
                        $patientEmail,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {


                    $emailResult =
                        sendAppointmentEmail(
                            $patientEmail,
                            $patientName,
                            $serviceName,
                            $formattedDate,
                            $formattedTime,
                            $new_status
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE NOTIFICATION
                    |--------------------------------------------------------------------------
                    */

                    $notificationStatus =
                        $emailResult['success']
                        ? 'Sent'
                        : 'Failed';


                    $notificationSubject =
                        $new_status === 'Approved'
                        ? 'CareSched Appointment Approved'
                        : 'CareSched Appointment Rejected';


                    $notificationMessage =
                        "Appointment status: {$new_status}\n" .
                        "Service: {$serviceName}\n" .
                        "Date: {$formattedDate}\n" .
                        "Time: {$formattedTime}";


                    $notificationInsert =
                        $pdo->prepare("
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


                    $notificationInsert->execute([

                        'appointment_id' =>
                            $appointment['id'],

                        'patient_id' =>
                            $appointment['patient_id'],

                        'email' =>
                            $patientEmail,

                        'notification_type' =>
                            'Appointment',

                        'subject' =>
                            $notificationSubject,

                        'message' =>
                            $notificationMessage,

                        'status' =>
                            $notificationStatus,

                        'sent_at' =>
                            $emailResult['success']
                            ? date('Y-m-d H:i:s')
                            : null
                    ]);
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | KEEP CURRENT FILTER AFTER UPDATE
    |--------------------------------------------------------------------------
    */

    $redirectParams = [];


    if (isset($_GET['status'])) {

        $redirectParams['status'] =
            $_GET['status'];
    }


    if (isset($_GET['date'])) {

        $redirectParams['date'] =
            $_GET['date'];
    }


    if (isset($_GET['q'])) {

        $redirectParams['q'] =
            $_GET['q'];
    }


    if (isset($_GET['page'])) {

        $redirectParams['page'] =
            $_GET['page'];
    }


    $redirectUrl =
        'appointments.php';


    if (!empty($redirectParams)) {

        $redirectUrl .= '?' .
            http_build_query(
                $redirectParams
            );
    }


    header(
        'Location: ' .
        $redirectUrl
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$allowed_filters = [
    'all',
    'Pending',
    'Approved',
    'Rejected',
    'Completed',
    'Cancelled'
];


$status_filter =
    $_GET['status'] ?? 'all';


if (
    !in_array(
        $status_filter,
        $allowed_filters,
        true
    )
) {

    $status_filter = 'all';
}


$date_filter =
    $_GET['date'] ?? '';


$search =
    trim(
        $_GET['q'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$per_page = 10;


$page =
    max(
        1,
        (int) (
            $_GET['page'] ?? 1
        )
    );


$offset =
    ($page - 1) *
    $per_page;


/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$where = [];

$params = [];


if ($status_filter !== 'all') {

    $where[] =
        'a.status = :status';

    $params['status'] =
        $status_filter;
}


if ($date_filter === 'today') {

    $where[] =
        'a.appointment_date = CURDATE()';
}


if ($search !== '') {

    $where[] =
        "(
            p.first_name LIKE :search
            OR p.last_name LIKE :search
        )";

    $params['search'] =
        '%' . $search . '%';
}


$where_sql =
    $where
    ? 'WHERE ' .
      implode(
          ' AND ',
          $where
      )
    : '';


/*
|--------------------------------------------------------------------------
| COUNT APPOINTMENTS
|--------------------------------------------------------------------------
*/

$count_stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM appointments a

    INNER JOIN patients p
        ON a.patient_id = p.id

    INNER JOIN services s
        ON a.service_id = s.id

    $where_sql
");


$count_stmt->execute(
    $params
);


$total_rows =
    (int) $count_stmt->fetchColumn();


$total_pages =
    max(
        1,
        (int) ceil(
            $total_rows /
            $per_page
        )
    );


/*
|--------------------------------------------------------------------------
| GET APPOINTMENTS
|--------------------------------------------------------------------------
*/

$list_stmt = $pdo->prepare("
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

    $where_sql

    ORDER BY
        a.appointment_date DESC,
        a.appointment_time DESC

    LIMIT $per_page
    OFFSET $offset
");


$list_stmt->execute(
    $params
);


$appointments =
    $list_stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| STATUS COUNTS
|--------------------------------------------------------------------------
*/

$tab_counts = [

    'all' => 0,

    'Pending' => 0,

    'Approved' => 0,

    'Rejected' => 0,

    'Completed' => 0,

    'Cancelled' => 0

];


$counts_stmt =
    $pdo->query("
        SELECT
            status,
            COUNT(*) AS total

        FROM appointments

        GROUP BY status
    ");


foreach (
    $counts_stmt->fetchAll(
        PDO::FETCH_ASSOC
    ) as $row
) {

    $tab_counts['all'] +=
        (int) $row['total'];


    if (
        isset(
            $tab_counts[
                $row['status']
            ]
        )
    ) {

        $tab_counts[
            $row['status']
        ] =
            (int) $row['total'];
    }
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function statusClass($status)
{
    switch (
        strtolower($status)
    ) {

        case 'approved':

            return 'status-approved';

        case 'pending':

            return 'status-pending';

        case 'completed':

            return 'status-completed';

        case 'rejected':

        case 'cancelled':

            return 'status-rejected';

        default:

            return 'status-completed';
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


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
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

            font-family:
                'Inter',
                sans-serif;

            background:
                var(--bg);

            color:
                var(--text);
        }


        a {
            text-decoration: none;
        }


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

            padding:
                25px 16px;

            z-index: 1000;

            transition:
                .3s ease;

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

            color:
                rgba(255,255,255,.55);

            text-transform:
                uppercase;

            letter-spacing:
                .8px;
        }


        .menu-label {

            padding:
                0 13px;

            color:
                rgba(255,255,255,.4);

            font-size: 10px;

            font-weight: 700;

            text-transform:
                uppercase;

            letter-spacing:
                1px;

            margin:
                20px 0 8px;
        }


        .sidebar-link {

            display: flex;

            align-items: center;

            gap: 13px;

            color:
                rgba(255,255,255,.7);

            padding:
                12px 14px;

            border-radius: 11px;

            margin-bottom: 4px;

            font-size: 13px;

            font-weight: 500;

            transition:
                .2s ease;
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

            transform:
                translateX(3px);
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

            color:
                #ffb4b4;
        }


        .logout-link:hover {

            color: #fff;

            background:
                rgba(220,53,69,.18);
        }


        .main {

            margin-left: 260px;

            min-height: 100vh;

            transition:
                .3s ease;
        }


        .topbar {

            height: 76px;

            background: white;

            border-bottom:
                1px solid var(--border);

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 35px;

            position: sticky;

            top: 0;

            z-index: 500;
        }


        .menu-toggle {

            display: none;

            border: none;

            background:
                #eef4ff;

            color:
                var(--primary);

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

            margin:
                4px 0 0;

            color:
                var(--muted);

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

            background:
                #eaf2ff;

            color:
                var(--primary);

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

            color:
                var(--muted);
        }


        .content {

            padding:
                30px 35px;
        }


        .section-card {

            background: white;

            border:
                1px solid var(--border);

            border-radius: 18px;

            box-shadow:
                0 5px 20px
                rgba(20,40,70,.04);

            overflow: hidden;
        }


        .section-header {

            padding:
                20px 22px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            justify-content: space-between;

            align-items: center;

            flex-wrap: wrap;

            gap: 12px;
        }


        .section-header h3 {

            margin: 0;

            font-size: 15px;

            font-weight: 800;
        }


        .section-header span {

            color:
                var(--muted);

            font-size: 11px;
        }


        .filter-bar {

            padding:
                16px 22px;

            border-bottom:
                1px solid var(--border);

            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            align-items: center;

            justify-content: space-between;
        }


        .filter-tabs {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;
        }


        .filter-tab {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding:
                8px 13px;

            border:
                1px solid var(--border);

            border-radius: 30px;

            color:
                #64748b;

            background: white;

            font-size: 10px;

            font-weight: 700;

            transition:
                .2s ease;
        }


        .filter-tab:hover {

            color:
                var(--primary);

            border-color:
                #bcd4fa;
        }


        .filter-tab.active {

            color: white;

            border-color:
                transparent;

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #0ea5e9
                );
        }


        .filter-tab .count {

            padding:
                1px 6px;

            border-radius: 20px;

            background:
                rgba(0,0,0,.06);

            font-size: 8px;
        }


        .filter-tab.active .count {

            background:
                rgba(255,255,255,.25);
        }


        .search-box {

            display: flex;

            align-items: center;

            gap: 8px;
        }


        .search-box input {

            padding:
                9px 13px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            font-size: 11px;

            min-width: 200px;
        }


        .search-box button {

            border: none;

            background:
                var(--primary);

            color: white;

            padding:
                9px 13px;

            border-radius: 10px;

            font-size: 11px;
        }


        .appointment-table {

            width: 100%;

            border-collapse:
                collapse;
        }


        .appointment-table th {

            padding:
                13px 22px;

            color:
                #8a96a8;

            font-size: 10px;

            text-transform:
                uppercase;

            letter-spacing:
                .5px;

            font-weight: 700;

            background:
                #fafbfd;

            border-bottom:
                1px solid var(--border);

            text-align: left;
        }


        .appointment-table td {

            padding:
                14px 22px;

            border-bottom:
                1px solid #f0f2f5;

            font-size: 11px;

            vertical-align:
                middle;
        }


        .patient-name {

            font-weight: 700;
        }


        .service-name {

            color:
                var(--muted);
        }


        .status {

            display: inline-flex;

            align-items: center;

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 9px;

            font-weight: 700;
        }


        .status-pending {

            background:
                #fff4df;

            color:
                #b76b00;
        }


        .status-approved {

            background:
                #e9f8f0;

            color:
                #147346;
        }


        .status-rejected {

            background:
                #fff0f0;

            color:
                #b42318;
        }


        .status-completed {

            background:
                #eef1f5;

            color:
                #667085;
        }


        .action-btns {

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

            font-size: 11px;

            border: none;
        }


        .icon-btn.view {

            background:
                #eef4ff;

            color:
                var(--primary);
        }


        .icon-btn.view:hover {

            background:
                var(--primary);

            color: white;
        }


        .icon-btn.approve {

            background:
                #e9f8f0;

            color:
                #147346;
        }


        .icon-btn.approve:hover {

            background:
                #147346;

            color: white;
        }


        .icon-btn.reject {

            background:
                #fff0f0;

            color:
                #b42318;
        }


        .icon-btn.reject:hover {

            background:
                #b42318;

            color: white;
        }


        .empty-state {

            padding:
                45px 20px;

            text-align: center;

            color:
                var(--muted);
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


        .pagination-bar {

            padding:
                16px 22px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            flex-wrap: wrap;

            gap: 10px;
        }


        .pagination-bar span {

            color:
                var(--muted);

            font-size: 10px;
        }


        .page-link {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 30px;

            height: 30px;

            border:
                1px solid var(--border);

            border-radius: 8px;

            color:
                var(--text);

            font-size: 10px;

            font-weight: 700;
        }


        .page-link.active {

            background:
                var(--primary);

            border-color:
                var(--primary);

            color: white;
        }


        .page-link.disabled {

            opacity: .4;

            pointer-events: none;
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
        }


        @media (max-width: 600px) {

            .admin-profile > div {

                display: none;
            }


            .appointment-table {

                min-width: 700px;
            }


            .table-wrapper {

                overflow-x: auto;
            }
        }

    </style>

</head>


<body>


<aside
    class="sidebar"
    id="sidebar"
>


    <div class="sidebar-brand">

        <div class="brand-icon">

            <i class="fa-solid fa-heart-pulse"></i>

        </div>


        <div class="brand-text">

            <strong>
                CareSched
            </strong>

            <span>
                Admin Portal
            </span>

        </div>

    </div>


    <div class="menu-label">
        Main Menu
    </div>


    <a
        href="dashboard.php"
        class="sidebar-link"
    >

        <i class="fa-solid fa-grid-2"></i>

        Dashboard

    </a>


    <a
        href="appointments.php"
        class="sidebar-link active"
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
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >

            <i class="fa-solid fa-right-from-bracket"></i>

            Logout

        </a>

    </div>


</aside>


<main class="main">


    <header class="topbar">


        <div
            class="d-flex align-items-center gap-3"
        >

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="page-title">

                <h1>
                    Appointments
                </h1>

                <p>
                    Manage patient appointment bookings
                </p>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>


            <div>

                <strong>
                    Administrator
                </strong>

                <span>
                    RHU Arakan
                </span>

            </div>

        </div>


    </header>


    <section class="content">


        <div class="section-card">


            <div class="section-header">

                <div>

                    <h3>
                        All Appointments
                    </h3>

                    <span>

                        <?= e($total_rows) ?>

                        total record

                        <?= $total_rows === 1 ? '' : 's' ?>

                    </span>

                </div>

            </div>


            <div class="filter-bar">


                <div class="filter-tabs">


                    <?php

                    foreach (
                        [
                            'all' => 'All',
                            'Pending' => 'Pending',
                            'Approved' => 'Approved',
                            'Rejected' => 'Rejected',
                            'Completed' => 'Completed',
                            'Cancelled' => 'Cancelled'
                        ]
                        as $key => $label
                    ):

                    ?>


                        <a
                            href="?status=<?= e($key) ?><?= $date_filter ? '&date=' . e($date_filter) : '' ?>"
                            class="filter-tab <?= $status_filter === $key ? 'active' : '' ?>"
                        >

                            <?= e($label) ?>

                            <span class="count">

                                <?= e(
                                    $tab_counts[$key] ?? 0
                                ) ?>

                            </span>

                        </a>


                    <?php endforeach; ?>


                </div>


                <form
                    class="search-box"
                    method="get"
                >


                    <?php if ($status_filter !== 'all'): ?>

                        <input
                            type="hidden"
                            name="status"
                            value="<?= e($status_filter) ?>"
                        >

                    <?php endif; ?>


                    <input
                        type="text"
                        name="q"
                        placeholder="Search patient name..."
                        value="<?= e($search) ?>"
                    >


                    <button type="submit">

                        <i class="fa-solid fa-search"></i>

                    </button>


                </form>


            </div>


            <?php if (!empty($appointments)): ?>


                <div class="table-wrapper">


                    <table class="appointment-table">


                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

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
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $appointments
                            as $appointment
                        ): ?>


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

                                    <span
                                        class="service-name"
                                    >

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
                                                $appointment[
                                                    'appointment_date'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $appointment[
                                                    'appointment_time'
                                                ]
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="status <?= statusClass($appointment['status']) ?>"
                                    >

                                        <?= e(
                                            $appointment['status']
                                        ) ?>

                                    </span>

                                </td>


                                <td>


                                    <div class="action-btns">


                                        <a
                                            href="appointment-view.php?id=<?= (int) $appointment['id'] ?>"
                                            class="icon-btn view"
                                            title="View"
                                        >

                                            <i
                                                class="fa-solid fa-eye"
                                            ></i>

                                        </a>


                                        <?php if (
                                            strtolower(
                                                $appointment['status']
                                            ) === 'pending'
                                        ): ?>


                                            <!-- APPROVE -->

                                            <form
                                                method="post"
                                                style="display:inline;"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="update_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $appointment['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="Approved"
                                                >

                                                <button
                                                    type="submit"
                                                    class="icon-btn approve"
                                                    title="Approve"
                                                >

                                                    <i
                                                        class="fa-solid fa-check"
                                                    ></i>

                                                </button>

                                            </form>


                                            <!-- REJECT -->

                                            <form
                                                method="post"
                                                style="display:inline;"
                                                onsubmit="
                                                    return confirm(
                                                        'Reject this appointment?'
                                                    );
                                                "
                                            >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="update_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $appointment['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="Rejected"
                                                >

                                                <button
                                                    type="submit"
                                                    class="icon-btn reject"
                                                    title="Reject"
                                                >

                                                    <i
                                                        class="fa-solid fa-xmark"
                                                    ></i>

                                                </button>

                                            </form>


                                        <?php endif; ?>


                                    </div>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


                <div class="pagination-bar">


                    <span>

                        Page
                        <?= e($page) ?>

                        of

                        <?= e($total_pages) ?>

                    </span>


                    <div class="d-flex gap-2">


                        <a
                            class="page-link <?= $page <= 1 ? 'disabled' : '' ?>"
                            href="?page=<?= $page - 1 ?>&status=<?= e($status_filter) ?>&q=<?= e($search) ?>"
                        >

                            <i
                                class="fa-solid fa-chevron-left"
                            ></i>

                        </a>


                        <a
                            class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>"
                            href="?page=<?= $page + 1 ?>&status=<?= e($status_filter) ?>&q=<?= e($search) ?>"
                        >

                            <i
                                class="fa-solid fa-chevron-right"
                            ></i>

                        </a>


                    </div>


                </div>


            <?php else: ?>


                <div class="empty-state">

                    <i
                        class="fa-solid fa-calendar-xmark"
                    ></i>

                    <p>
                        No appointments found for this filter.
                    </p>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


<script>

const menuToggle =
    document.getElementById(
        'menuToggle'
    );

const sidebar =
    document.getElementById(
        'sidebar'
    );


if (
    menuToggle &&
    sidebar
) {

    menuToggle.addEventListener(
        'click',
        function () {

            sidebar.classList.toggle(
                'show'
            );

        }
    );

}


document.addEventListener(
    'click',
    function (event) {

        if (
            window.innerWidth <= 850 &&
            sidebar.classList.contains('show') &&
            !sidebar.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {

            sidebar.classList.remove(
                'show'
            );

        }

    }
);

</script>


</body>

</html>