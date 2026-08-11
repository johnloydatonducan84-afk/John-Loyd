<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

$emailConfig = require __DIR__ . '/../config/email-config.php';


function sendCareSchedEmail(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $htmlMessage
): array {

    global $emailConfig;

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = $emailConfig['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $emailConfig['username'];
        $mail->Password   = $emailConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $emailConfig['port'];

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $emailConfig['from_email'],
            $emailConfig['from_name']
        );

        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlMessage;
        $mail->AltBody = strip_tags($htmlMessage);

        $mail->send();

        return [
            'success' => true,
            'message' => 'Email sent successfully.'
        ];

    } catch (Exception $e) {

        error_log(
            'CareSched PHPMailer Error: ' . $mail->ErrorInfo
        );

        return [
            'success' => false,
            'message' => $mail->ErrorInfo
        ];
    }
}