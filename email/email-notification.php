<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';


/**
 * MAIN EMAIL FUNCTION
 */
function sendEmailNotification(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $message
): bool {

    $mail = new PHPMailer(true);

    try {

        // ==========================================
        // SMTP CONFIGURATION
        // ==========================================

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        // CareSched Gmail Account
        $mail->Username = 'johnloydatonducan84@gmail.com';

        // PUT YOUR NEW GMAIL APP PASSWORD HERE
        $mail->Password = 'mycz fpmd nmiw wvyi';

        // Gmail TLS
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // Gmail SMTP Port
        $mail->Port = 587;

        // Connection timeout
        $mail->Timeout = 30;

        // Disable debug in production
        $mail->SMTPDebug = 0;


        // ==========================================
        // SENDER
        // ==========================================

        $mail->setFrom(
            'johnloydatonducan84@gmail.com',
            'CareSched - RHU Arakan'
        );


        // ==========================================
        // RECIPIENT
        // ==========================================

        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );


        // ==========================================
        // REPLY TO
        // ==========================================

        $mail->addReplyTo(
            'johnloydatonducan84@gmail.com',
            'CareSched'
        );


        // ==========================================
        // EMAIL CONTENT
        // ==========================================

        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = $message;

        $mail->AltBody = strip_tags($message);


        // ==========================================
        // SEND
        // ==========================================

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            'CareSched Email Error: ' . $mail->ErrorInfo
        );

        return false;
    }
}


/**
 * PATIENT -> ADMIN
 * New appointment notification
 */
function sendAppointmentToAdmin(
    string $adminEmail,
    string $patientName,
    string $serviceName,
    string $appointmentDate,
    string $appointmentTime
): bool {

    $subject = 'New Appointment Request - CareSched';

    $safePatientName = htmlspecialchars($patientName);
    $safeServiceName = htmlspecialchars($serviceName);
    $safeDate = htmlspecialchars($appointmentDate);
    $safeTime = htmlspecialchars($appointmentTime);

    $message = "
    <!DOCTYPE html>
    <html>
    <body style='margin:0;padding:20px;background:#f4f4f4;font-family:Arial,sans-serif;'>

        <div style='max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:10px;'>

            <h2 style='color:#1f2937;'>
                New Appointment Request
            </h2>

            <p>Hello Administrator,</p>

            <p>
                A new appointment has been submitted through
                the <strong>CareSched Appointment Scheduling System</strong>.
            </p>

            <hr>

            <h3>Appointment Details</h3>

            <p>
                <strong>Patient Name:</strong>
                {$safePatientName}
            </p>

            <p>
                <strong>Service:</strong>
                {$safeServiceName}
            </p>

            <p>
                <strong>Appointment Date:</strong>
                {$safeDate}
            </p>

            <p>
                <strong>Appointment Time:</strong>
                {$safeTime}
            </p>

            <hr>

            <p>
                Please login to the CareSched Administrator Dashboard
                to review this appointment.
            </p>

            <br>

            <p>
                <strong>CareSched</strong><br>
                RHU Arakan
            </p>

        </div>

    </body>
    </html>
    ";

    return sendEmailNotification(
        $adminEmail,
        'CareSched Administrator',
        $subject,
        $message
    );
}


/**
 * ADMIN -> PATIENT
 * Appointment status notification
 */
function sendAppointmentStatusToPatient(
    string $patientEmail,
    string $patientName,
    string $serviceName,
    string $appointmentDate,
    string $appointmentTime,
    string $status
): bool {

    $subject = 'Appointment Status Update - CareSched';

    $safePatientName = htmlspecialchars($patientName);
    $safeServiceName = htmlspecialchars($serviceName);
    $safeDate = htmlspecialchars($appointmentDate);
    $safeTime = htmlspecialchars($appointmentTime);
    $safeStatus = htmlspecialchars($status);

    $message = "
    <!DOCTYPE html>
    <html>

    <body style='margin:0;padding:20px;background:#f4f4f4;font-family:Arial,sans-serif;'>

        <div style='max-width:600px;margin:auto;background:#ffffff;padding:30px;border-radius:10px;'>

            <h2 style='color:#1f2937;'>
                Appointment Status Update
            </h2>

            <p>
                Hello <strong>{$safePatientName}</strong>,
            </p>

            <p>
                Your appointment status has been updated.
            </p>

            <hr>

            <h3>Appointment Details</h3>

            <p>
                <strong>Service:</strong>
                {$safeServiceName}
            </p>

            <p>
                <strong>Appointment Date:</strong>
                {$safeDate}
            </p>

            <p>
                <strong>Appointment Time:</strong>
                {$safeTime}
            </p>

            <p>
                <strong>Status:</strong>
                {$safeStatus}
            </p>

            <hr>

            <p>
                Please login to your CareSched account
                for more information.
            </p>

            <br>

            <p>
                Thank you.
            </p>

            <p>
                <strong>CareSched</strong><br>
                RHU Arakan
            </p>

        </div>

    </body>
    </html>
    ";

    return sendEmailNotification(
        $patientEmail,
        $patientName,
        $subject,
        $message
    );
}