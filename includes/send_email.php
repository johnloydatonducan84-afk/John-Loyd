<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

/*
|--------------------------------------------------------------------------
| SEND EMAIL
|--------------------------------------------------------------------------
| Centralized email function.
| Gmail SMTP settings are stored here, NOT in register.php.
|--------------------------------------------------------------------------
*/

function sendEmail(
    string $to,
    string $name,
    string $subject,
    string $htmlBody,
    string $plainText = ''
): bool {

    $mail = new PHPMailer(true);

    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        /*
        |--------------------------------------------------------------------------
        | USE YOUR EXISTING GMAIL SETTINGS HERE
        |--------------------------------------------------------------------------
        */

        $mail->Username = 'YOUR_EXISTING_GMAIL@gmail.com';

        $mail->Password = 'YOUR_EXISTING_GMAIL_APP_PASSWORD';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';


        /*
        |--------------------------------------------------------------------------
        | SENDER
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $mail->Username,
            'CareSched'
        );


        /*
        |--------------------------------------------------------------------------
        | RECIPIENT
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $to,
            $name
        );


        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject = $subject;

        $mail->Body = $htmlBody;

        $mail->AltBody =
            $plainText ?: strip_tags($htmlBody);


        /*
        |--------------------------------------------------------------------------
        | SEND
        |--------------------------------------------------------------------------
        */

        return $mail->send();

    } catch (Exception $e) {

        error_log(
            'CareSched Email Error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| REGISTRATION EMAIL
|--------------------------------------------------------------------------
*/

function sendRegistrationEmail(
    string $email,
    string $firstName,
    string $lastName,
    string $username
): bool {

    $safeFirstName =
        htmlspecialchars(
            $firstName,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeLastName =
        htmlspecialchars(
            $lastName,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeUsername =
        htmlspecialchars(
            $username,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeEmail =
        htmlspecialchars(
            $email,
            ENT_QUOTES,
            'UTF-8'
        );


    /*
    |--------------------------------------------------------------------------
    | SUBJECT
    |--------------------------------------------------------------------------
    */

    $subject =
        'Welcome to CareSched - Registration Successful';


    /*
    |--------------------------------------------------------------------------
    | HTML EMAIL
    |--------------------------------------------------------------------------
    */

    $html = '

    <!DOCTYPE html>

    <html>

    <head>

        <meta charset="UTF-8">

        <title>
            Welcome to CareSched
        </title>

    </head>

    <body style="
        margin:0;
        padding:0;
        background:#f4f8fc;
        font-family:Arial,Helvetica,sans-serif;
    ">

        <div style="
            max-width:650px;
            margin:40px auto;
            background:#ffffff;
            border-radius:18px;
            overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,0.08);
        ">

            <!-- HEADER -->

            <div style="
                background:linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );
                padding:35px;
                text-align:center;
                color:#ffffff;
            ">

                <div style="
                    font-size:38px;
                    margin-bottom:10px;
                ">
                    ❤️
                </div>

                <h1 style="
                    margin:0;
                    font-size:30px;
                ">
                    CareSched
                </h1>

                <p style="
                    margin:8px 0 0;
                    font-size:14px;
                    opacity:.9;
                ">
                    Healthcare Appointment Scheduling System
                </p>

            </div>


            <!-- CONTENT -->

            <div style="
                padding:35px;
                color:#172033;
            ">

                <h2 style="
                    margin-top:0;
                    color:#2563eb;
                ">
                    Welcome, ' . $safeFirstName . '!
                </h2>


                <p style="
                    font-size:15px;
                    line-height:1.7;
                ">
                    Your CareSched patient account has been
                    successfully created.
                </p>


                <!-- ACCOUNT DETAILS -->

                <div style="
                    background:#f8fafc;
                    border:1px solid #e2e8f0;
                    border-radius:12px;
                    padding:20px;
                    margin:25px 0;
                ">

                    <p style="
                        margin:0 0 12px;
                        font-size:14px;
                    ">

                        <strong>
                            Name:
                        </strong>

                        ' . $safeFirstName . ' '
                          . $safeLastName . '

                    </p>


                    <p style="
                        margin:0 0 12px;
                        font-size:14px;
                    ">

                        <strong>
                            Username:
                        </strong>

                        ' . $safeUsername . '

                    </p>


                    <p style="
                        margin:0;
                        font-size:14px;
                    ">

                        <strong>
                            Email:
                        </strong>

                        ' . $safeEmail . '

                    </p>

                </div>


                <p style="
                    font-size:15px;
                    line-height:1.7;
                ">

                    You can now log in to CareSched and
                    schedule your healthcare appointments
                    online.

                </p>


                <!-- BUTTON -->

                <div style="
                    text-align:center;
                    margin:30px 0;
                ">

                    <a
                        href="' . e(app_full_url('login.php')) . '"
                        style="
                            display:inline-block;
                            padding:14px 28px;
                            background:#2563eb;
                            color:#ffffff;
                            text-decoration:none;
                            border-radius:10px;
                            font-weight:bold;
                            font-size:14px;
                        "
                    >
                        Login to CareSched
                    </a>

                </div>


                <!-- SECURITY -->

                <div style="
                    background:#eff6ff;
                    border-left:4px solid #2563eb;
                    padding:15px;
                    margin-top:25px;
                ">

                    <p style="
                        margin:0;
                        color:#475569;
                        font-size:13px;
                        line-height:1.6;
                    ">

                        Please keep your login credentials
                        secure and never share your password
                        with anyone.

                    </p>

                </div>


                <p style="
                    margin-top:30px;
                    font-size:13px;
                    color:#64748b;
                ">

                    Thank you for registering with CareSched.

                </p>

            </div>


            <!-- FOOTER -->

            <div style="
                background:#f8fafc;
                border-top:1px solid #e2e8f0;
                padding:20px;
                text-align:center;
                color:#94a3b8;
                font-size:12px;
            ">

                CareSched · Patient Portal

                <br>

                Rural Health Unit - Arakan

            </div>

        </div>

    </body>

    </html>
    ';


    /*
    |--------------------------------------------------------------------------
    | PLAIN TEXT VERSION
    |--------------------------------------------------------------------------
    */

    $plainText =
        "Welcome to CareSched, "
        . $firstName . "!\n\n"
        . "Your patient account has been successfully created.\n\n"
        . "Name: "
        . $firstName . " "
        . $lastName . "\n"
        . "Username: "
        . $username . "\n"
        . "Email: "
        . $email . "\n\n"
        . "You can now log in to CareSched and "
        . "schedule your healthcare appointments.\n\n"
        . "CareSched - Patient Portal";


    /*
    |--------------------------------------------------------------------------
    | SEND
    |--------------------------------------------------------------------------
    */

    return sendEmail(
        $email,
        $firstName . ' ' . $lastName,
        $subject,
        $html,
        $plainText
    );
}