<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {

    // =========================
    // SMTP SERVER SETTINGS
    // =========================
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    // Your Gmail address
    $mail->Username   = 'johnloydatonducan84@gmail.com';

    // IMPORTANT:
    // This MUST be your Gmail APP PASSWORD,
    // NOT your normal Gmail password.
    $mail->Password   = 'newg udeh cxnt vcdn';

    // Gmail SMTP using TLS
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // =========================
    // SENDER
    // =========================
    $mail->setFrom(
        'johnloydatonducan84@gmail.com',
        'CareSched'
    );

    // =========================
    // RECIPIENT
    // =========================
    $mail->addAddress(
        'jaylloydcalimpo20@gmail.com',
        'Jay Lloyd'
    );

    // Reply-to
    $mail->addReplyTo(
        'johnloydatondunducan84@gmail.com',
        'CareSched'
    );

    // =========================
    // ATTACHMENT
    // =========================
    $attachment = __DIR__ . '/assets/caresched.jpg';

    if (file_exists($attachment)) {
        $mail->addAttachment(
            $attachment,
            'caresched.jpg'
        );
    }

    // =========================
    // EMAIL CONTENT
    // =========================
    $mail->isHTML(true);

    $mail->Subject = 'CareSched Appointment Notification';

    $mail->Body = '
        <div style="font-family: Arial, sans-serif;">
            <h2>CareSched Appointment Notification</h2>

            <p>Hello Jay Lloyd,</p>

            <p>
                This is a test email from the
                <strong>CareSched Appointment Scheduling System</strong>.
            </p>

            <p>
                Your email notification system is working successfully.
            </p>

            <br>

            <p>Thank you.</p>

            <strong>CareSched</strong>
        </div>
    ';

    $mail->AltBody =
        'CareSched Appointment Notification

        Hello Jay Lloyd,

        This is a test email from the CareSched
        Appointment Scheduling System.

        Your email notification system is working successfully.

        Thank you.
        CareSched';

    // =========================
    // SEND
    // =========================
    $mail->send();

    echo 'Message has been sent successfully!';

} catch (Exception $e) {

    echo 'Message could not be sent.<br>';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
}