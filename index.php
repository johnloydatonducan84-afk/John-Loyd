<?php
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /caresched/admin/dashboard.php');
        exit;
    }

    if ($_SESSION['role'] === 'patient') {
        header('Location: /caresched/patient/dashboard.php');
        exit;
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

    <title>CareSched | Appointment Scheduling</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        rel="stylesheet"
    >

    <!-- Google Font -->
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
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(
                    circle at top left,
                    rgba(13, 110, 253, .15),
                    transparent 35%
                ),
                radial-gradient(
                    circle at bottom right,
                    rgba(25, 135, 84, .12),
                    transparent 35%
                ),
                #f8fafc;

            color: #172033;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;
        }


        /* ==============================
           MAIN CARD
        ============================== */

        .hero-card {
            width: 100%;
            max-width: 1050px;

            min-height: 580px;

            background: #ffffff;

            border-radius: 30px;

            overflow: hidden;

            display: grid;

            grid-template-columns:
                1.1fr .9fr;

            box-shadow:
                0 25px 80px
                rgba(15, 23, 42, .12);

            border:
                1px solid
                rgba(226, 232, 240, .9);
        }


        /* ==============================
           LEFT SIDE
        ============================== */

        .hero-copy {
            padding: 65px 60px;

            display: flex;
            flex-direction: column;
            justify-content: center;
        }


        /* LOGO */

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-bottom: 45px;
        }


        .brand-icon {
            width: 48px;
            height: 48px;

            border-radius: 15px;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #0dcaf0
                );

            color: white;

            font-size: 20px;

            box-shadow:
                0 10px 25px
                rgba(13, 110, 253, .25);
        }


        .brand-text {
            font-size: 20px;
            font-weight: 800;

            color: #172033;
        }


        .brand-subtitle {
            font-size: 10px;

            color: #94a3b8;

            letter-spacing: 1px;

            font-weight: 700;
        }


        /* BADGE */

        .system-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            width: fit-content;

            padding: 8px 13px;

            border-radius: 50px;

            background: #eff6ff;

            color: #0d6efd;

            font-size: 11px;

            font-weight: 700;

            margin-bottom: 22px;
        }


        /* TITLE */

        .hero-copy h1 {
            font-size: 3.3rem;

            font-weight: 800;

            line-height: 1.08;

            letter-spacing: -1.5px;

            margin-bottom: 20px;
        }


        .hero-copy h1 span {
            color: #0d6efd;
        }


        .hero-copy p {
            font-size: 1rem;

            color: #64748b;

            line-height: 1.8;

            max-width: 530px;

            margin-bottom: 32px;
        }


        /* BUTTONS */

        .hero-actions {
            display: flex;

            flex-wrap: wrap;

            gap: 12px;
        }


        .hero-actions .btn {
            min-width: 180px;

            border-radius: 12px;

            padding: 13px 20px;

            font-size: 14px;

            font-weight: 700;

            transition: .25s ease;
        }


        .btn-primary {
            box-shadow:
                0 10px 25px
                rgba(13, 110, 253, .25);
        }


        .btn-primary:hover {
            transform:
                translateY(-2px);
        }


        .btn-outline-primary:hover {
            transform:
                translateY(-2px);
        }


        /* ==============================
           FEATURES
        ============================== */

        .mini-features {
            display: flex;

            flex-wrap: wrap;

            gap: 20px;

            margin-top: 38px;

            padding-top: 25px;

            border-top:
                1px solid #eef2f7;
        }


        .mini-feature {
            display: flex;

            align-items: center;

            gap: 8px;

            font-size: 11px;

            font-weight: 600;

            color: #64748b;
        }


        .mini-feature i {
            color: #16a34a;

            font-size: 14px;
        }


        /* ==============================
           RIGHT SIDE
        ============================== */

        .hero-preview {
            position: relative;

            padding: 50px;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    180deg,
                    #f8fbff 0%,
                    #eef6ff 100%
                );

            overflow: hidden;
        }


        /* BACKGROUND CIRCLES */

        .hero-preview::before {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            border-radius: 50%;

            background:
                rgba(13, 110, 253, .08);

            top: -120px;
            right: -100px;
        }


        .hero-preview::after {
            content: "";

            position: absolute;

            width: 230px;
            height: 230px;

            border-radius: 50%;

            background:
                rgba(22, 163, 74, .08);

            bottom: -100px;
            left: -80px;
        }


        /* PREVIEW BOX */

        .preview-box {
            position: relative;

            z-index: 2;

            width: 100%;
            max-width: 350px;

            background: #ffffff;

            border-radius: 25px;

            padding: 34px;

            border:
                1px solid
                rgba(226, 232, 240, .9);

            box-shadow:
                0 20px 60px
                rgba(15, 23, 42, .10);
        }


        .preview-header {
            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 28px;
        }


        .preview-icon {
            width: 45px;
            height: 45px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background: #eff6ff;

            color: #0d6efd;

            font-size: 18px;
        }


        .preview-header h3 {
            margin: 0;

            font-size: 17px;

            font-weight: 800;
        }


        .preview-header span {
            font-size: 10px;

            color: #94a3b8;
        }


        .preview-item {
            display: flex;

            align-items: center;

            gap: 13px;

            padding: 14px 0;

            border-bottom:
                1px solid #f1f5f9;
        }


        .preview-item:last-child {
            border-bottom: none;
        }


        .preview-item i {
            width: 38px;
            height: 38px;

            border-radius: 12px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eff6ff;

            color: #0d6efd;

            font-size: 15px;
        }


        .preview-item:nth-child(3) i {
            background: #ecfdf5;

            color: #16a34a;
        }


        .preview-item:nth-child(4) i {
            background: #fff7ed;

            color: #ea580c;
        }


        .preview-text strong {
            display: block;

            font-size: 12px;

            color: #334155;

            margin-bottom: 3px;
        }


        .preview-text span {
            font-size: 10px;

            color: #94a3b8;
        }


        /* ==============================
           MOBILE RESPONSIVE
        ============================== */

        @media (max-width: 920px) {

            body {
                padding: 20px;
            }


            .hero-card {
                grid-template-columns: 1fr;

                max-width: 650px;
            }


            .hero-copy {
                padding: 50px 45px;
            }


            .hero-preview {
                padding: 45px;
            }


            .preview-box {
                max-width: 500px;
            }

        }


        @media (max-width: 640px) {

            body {
                padding: 12px;

                align-items: flex-start;
            }


            .hero-card {
                border-radius: 22px;
            }


            .hero-copy {
                padding: 35px 25px;
            }


            .brand {
                margin-bottom: 32px;
            }


            .hero-copy h1 {
                font-size: 2.35rem;

                letter-spacing: -1px;
            }


            .hero-copy p {
                font-size: .92rem;

                line-height: 1.7;
            }


            .hero-actions {
                flex-direction: column;
            }


            .hero-actions .btn {
                width: 100%;

                min-width: auto;
            }


            .mini-features {
                flex-direction: column;

                gap: 12px;
            }


            .hero-preview {
                padding: 25px;
            }


            .preview-box {
                padding: 25px;
            }

        }


        @media (max-width: 380px) {

            .hero-copy h1 {
                font-size: 2rem;
            }


            .brand-text {
                font-size: 18px;
            }

        }

    </style>

</head>


<body>


<div class="hero-card">


    <!-- =========================================
         LEFT CONTENT
    ========================================== -->

    <div class="hero-copy">


        <!-- LOGO -->

        <div class="brand">

            <div class="brand-icon">

                <i class="fa-solid fa-heart-pulse"></i>

            </div>


            <div>

                <div class="brand-text">

                    CareSched

                </div>


                <div class="brand-subtitle">

                    RHU ARAKAN

                </div>

            </div>

        </div>


        <!-- BADGE -->

        <div class="system-badge">

            <i class="fa-solid fa-calendar-check"></i>

            APPOINTMENT SCHEDULING SYSTEM

        </div>


        <!-- TITLE -->

        <h1>

            Your health
            <span>
                schedule,
            </span>

            made easier.

        </h1>


        <!-- DESCRIPTION -->

        <p>

            Schedule your appointment with the Rural Health Unit
            of Arakan quickly and conveniently. Create an account,
            manage your bookings, and receive appointment updates
            through email notifications.

        </p>


        <!-- ACTIONS -->

        <div class="hero-actions">


            <a
                href="/caresched/login.php"
                class="btn btn-primary"
            >

                <i class="fa-solid fa-right-to-bracket me-2"></i>

                Patient Login

            </a>


            <a
                href="/caresched/register.php"
                class="btn btn-outline-primary"
            >

                <i class="fa-solid fa-user-plus me-2"></i>

                Create Account

            </a>


        </div>


        <!-- ADMIN LOGIN -->

        <a
            href="/caresched/admin/"
            class="mini-feature mt-3"
            style="text-decoration:none; width:fit-content;"
        >

            <i class="fa-solid fa-user-shield"></i>

            Staff / Admin Login

        </a>


        <!-- FEATURES -->

        <div class="mini-features">


            <div class="mini-feature">

                <i class="fa-solid fa-circle-check"></i>

                Easy Appointment Booking

            </div>


            <div class="mini-feature">

                <i class="fa-solid fa-circle-check"></i>

                Email Notifications

            </div>


            <div class="mini-feature">

                <i class="fa-solid fa-circle-check"></i>

                Secure Access

            </div>


        </div>


    </div>



    <!-- =========================================
         RIGHT PREVIEW
    ========================================== -->

    <div class="hero-preview">


        <div class="preview-box">


            <div class="preview-header">


                <div class="preview-icon">

                    <i class="fa-solid fa-calendar-heart"></i>

                </div>


                <div>

                    <h3>

                        Quick & Simple

                    </h3>


                    <span>

                        Manage your health appointments

                    </span>

                </div>


            </div>



            <!-- ITEM -->

            <div class="preview-item">


                <i class="fa-solid fa-calendar-plus"></i>


                <div class="preview-text">

                    <strong>

                        Book an Appointment

                    </strong>


                    <span>

                        Choose your preferred service and schedule.

                    </span>

                </div>


            </div>



            <!-- ITEM -->

            <div class="preview-item">


                <i class="fa-solid fa-envelope"></i>


                <div class="preview-text">

                    <strong>

                        Email Notifications

                    </strong>


                    <span>

                        Receive updates about your appointment status.

                    </span>

                </div>


            </div>



            <!-- ITEM -->

            <div class="preview-item">


                <i class="fa-solid fa-clock"></i>


                <div class="preview-text">

                    <strong>

                        Track Your Schedule

                    </strong>


                    <span>

                        View and manage your appointment history.

                    </span>

                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>