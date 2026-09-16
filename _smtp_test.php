<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

ensure_role('admin');

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/plain');

$config = require __DIR__ . '/config/config.php';

echo "smtp.username set = " . (!empty($config['smtp']['username']) && $config['smtp']['username'] !== 'your-email@gmail.com' ? 'YES' : 'NO (still placeholder)') . "\n";
echo "smtp.password set = " . (!empty($config['smtp']['password']) && $config['smtp']['password'] !== 'your-email-password' ? 'YES' : 'NO (still placeholder)') . "\n";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $config['smtp']['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp']['username'];
    $mail->Password = $config['smtp']['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $config['smtp']['port'];
    $mail->Timeout = 20;
    $mail->SMTPDebug = 0;

    $mail->setFrom($config['smtp']['username'], $config['smtp']['from_name']);
    $mail->addAddress($config['smtp']['username']);
    $mail->Subject = 'CareSched SMTP test';
    $mail->Body = 'This is an automated test email confirming SMTP credentials work.';

    $mail->send();

    echo "RESULT: SUCCESS - test email sent to " . $config['smtp']['username'] . "\n";

} catch (Exception $e) {
    echo "RESULT: FAILED - " . $mail->ErrorInfo . "\n";
}
