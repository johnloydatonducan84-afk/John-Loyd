<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ================================
       CSRF VALIDATION
    ================================= */

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    /* ================================
       GET FORM DATA
    ================================= */

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    $email = filter_var(
        trim($_POST['email'] ?? ''),
        FILTER_VALIDATE_EMAIL
    );

    $username = trim($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $birth_date = $_POST['birth_date'] ?? null;
    $age = $_POST['age'] ?? null;
    $sex = $_POST['sex'] ?? null;
    $address = trim($_POST['address'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');


    /* ================================
       VALIDATION
    ================================= */

    if (
        !$first_name ||
        !$last_name ||
        !$email ||
        !$username ||
        !$password
    ) {
        $errors[] = 'Please fill in all required fields.';
    }

    if (!$email && !empty($_POST['email'])) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!empty($username) && strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters.';
    }


    /* ================================
       REGISTER USER
    ================================= */

    if (empty($errors)) {

        try {

            /*
             * Check if email or username already exists.
             */

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = :email
                   OR username = :username
                LIMIT 1
            ");

            $stmt->execute([
                ':email' => $email,
                ':username' => $username
            ]);

            if ($stmt->fetch()) {

                $errors[] = 'Email or username already exists.';

            } else {

                $pdo->beginTransaction();

                try {

                    /* ================================
                       HASH PASSWORD
                    ================================= */

                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                    /* ================================
                       INSERT USER
                    ================================= */

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                        (
                            role,
                            username,
                            email,
                            password,
                            status,
                            created_at
                        )
                        VALUES
                        (
                            :role,
                            :username,
                            :email,
                            :password,
                            :status,
                            NOW()
                        )
                    ");

                    $stmt->execute([
                        ':role' => 'patient',
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $password_hash,
                        ':status' => 'active'
                    ]);


                    $user_id = $pdo->lastInsertId();


                    /* ================================
                       INSERT PATIENT INFORMATION
                    ================================= */

                    $stmt = $pdo->prepare("
                        INSERT INTO patients
                        (
                            user_id,
                            first_name,
                            middle_name,
                            last_name,
                            birth_date,
                            age,
                            sex,
                            address,
                            contact_number,
                            created_at
                        )
                        VALUES
                        (
                            :user_id,
                            :first_name,
                            :middle_name,
                            :last_name,
                            :birth_date,
                            :age,
                            :sex,
                            :address,
                            :contact_number,
                            NOW()
                        )
                    ");

                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $first_name,
                        ':middle_name' => $middle_name ?: null,
                        ':last_name' => $last_name,
                        ':birth_date' => $birth_date ?: null,
                        ':age' => $age ?: null,
                        ':sex' => $sex ?: null,
                        ':address' => $address ?: null,
                        ':contact_number' => $contact_number ?: null
                    ]);


                    /* ================================
                       COMMIT
                    ================================= */

                    $pdo->commit();

                    flash(
                        'success',
                        'Registration successful! You can now log in to your CareSched account.'
                    );

                    redirect('/caresched/login.php');

                    exit;

                } catch (Exception $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log($e->getMessage());

                    $errors[] =
                        'Registration failed. Please try again later.';
                }
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $errors[] =
                'Unable to process registration. Please try again later.';
        }
    }
}

$token = csrf_token();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Create Account | CareSched</title>

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

    <style>

        /* =========================================
           RESET
        ========================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        /* =========================================
           ROOT
        ========================================= */

        :root {

            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #0ea5e9;

            --text: #172033;
            --text-light: #64748b;

            --border: #e2e8f0;

            --background: #f4f8fc;

            --white: #ffffff;

            --danger: #dc2626;
            --danger-bg: #fef2f2;

            --success: #16a34a;

        }


        /* =========================================
           BODY
        ========================================= */

        html,
        body {

            min-height: 100%;

        }


        body {

            font-family:
                "Inter",
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            color: var(--text);

            background:

                radial-gradient(
                    circle at 10% 10%,
                    rgba(37, 99, 235, 0.08),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 90% 90%,
                    rgba(14, 165, 233, 0.08),
                    transparent 30%
                ),

                var(--background);

        }


        /* =========================================
           PAGE
        ========================================= */

        .register-page {

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 40px 20px;

        }


        /* =========================================
           MAIN CARD
        ========================================= */

        .register-card {

            width: 100%;

            max-width: 1050px;

            display: grid;

            grid-template-columns: 330px 1fr;

            background: var(--white);

            border-radius: 28px;

            overflow: hidden;

            border: 1px solid rgba(226, 232, 240, 0.9);

            box-shadow:
                0 30px 80px rgba(15, 23, 42, 0.12),
                0 8px 30px rgba(15, 23, 42, 0.05);

            animation: pageEnter 0.55s ease;

        }


        @keyframes pageEnter {

            from {

                opacity: 0;

                transform: translateY(25px);

            }

            to {

                opacity: 1;

                transform: translateY(0);

            }

        }


        /* =========================================
           LEFT BRAND PANEL
        ========================================= */

        .brand-panel {

            position: relative;

            overflow: hidden;

            padding: 42px 34px;

            color: white;

            background:
                linear-gradient(
                    145deg,
                    #2563eb 0%,
                    #1d4ed8 55%,
                    #0ea5e9 100%
                );

            display: flex;

            flex-direction: column;

            justify-content: space-between;

        }


        .brand-panel::before {

            content: "";

            position: absolute;

            width: 220px;

            height: 220px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.08);

            top: -80px;

            right: -80px;

        }


        .brand-panel::after {

            content: "";

            position: absolute;

            width: 180px;

            height: 180px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.06);

            bottom: -70px;

            left: -70px;

        }


        .brand-content {

            position: relative;

            z-index: 2;

        }


        /* =========================================
           LOGO
        ========================================= */

        .logo {

            width: 70px;

            height: 70px;

            border-radius: 21px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: rgba(255, 255, 255, 0.16);

            border: 1px solid rgba(255, 255, 255, 0.25);

            backdrop-filter: blur(10px);

            margin-bottom: 28px;

            box-shadow:
                0 15px 30px rgba(0, 0, 0, 0.10);

        }


        .logo i {

            font-size: 31px;

        }


        .brand-name {

            font-size: 31px;

            font-weight: 800;

            letter-spacing: -1px;

            margin-bottom: 10px;

        }


        .brand-description {

            font-size: 14px;

            line-height: 1.7;

            color: rgba(255, 255, 255, 0.82);

        }


        /* =========================================
           BENEFITS
        ========================================= */

        .benefits {

            position: relative;

            z-index: 2;

            margin-top: 45px;

        }


        .benefit {

            display: flex;

            align-items: flex-start;

            gap: 12px;

            margin-bottom: 19px;

        }


        .benefit-icon {

            width: 31px;

            height: 31px;

            min-width: 31px;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: rgba(255, 255, 255, 0.13);

        }


        .benefit-icon i {

            font-size: 12px;

        }


        .benefit span {

            font-size: 12px;

            line-height: 1.5;

            color: rgba(255, 255, 255, 0.84);

        }


        .brand-footer {

            position: relative;

            z-index: 2;

            color: rgba(255, 255, 255, 0.62);

            font-size: 11px;

        }


        /* =========================================
           FORM PANEL
        ========================================= */

        .form-panel {

            padding: 42px 48px;

            background: #ffffff;

        }


        .form-header {

            margin-bottom: 30px;

        }


        .form-header h1 {

            font-size: 25px;

            font-weight: 750;

            letter-spacing: -0.5px;

            color: #172033;

            margin-bottom: 7px;

        }


        .form-header p {

            color: var(--text-light);

            font-size: 13px;

            line-height: 1.5;

        }


        /* =========================================
           SECTION TITLE
        ========================================= */

        .section-title {

            display: flex;

            align-items: center;

            gap: 9px;

            font-size: 13px;

            font-weight: 700;

            color: #334155;

            margin-bottom: 16px;

        }


        .section-title i {

            color: var(--primary);

            font-size: 13px;

        }


        .section-title span {

            flex: 1;

            height: 1px;

            background: #edf1f6;

        }


        /* =========================================
           FORM GRID
        ========================================= */

        .form-grid {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 17px 15px;

            margin-bottom: 27px;

        }


        .field {

            min-width: 0;

        }


        .field.full {

            grid-column: 1 / -1;

        }


        .field label {

            display: block;

            margin-bottom: 7px;

            font-size: 12px;

            font-weight: 650;

            color: #475569;

        }


        .required {

            color: #ef4444;

            margin-left: 2px;

        }


        /* =========================================
           INPUT
        ========================================= */

        .input-wrap {

            position: relative;

        }


        .input-wrap > i {

            position: absolute;

            left: 14px;

            top: 50%;

            transform: translateY(-50%);

            color: #94a3b8;

            font-size: 14px;

            pointer-events: none;

            transition: 0.2s ease;

            z-index: 2;

        }


        .input {

            width: 100%;

            height: 48px;

            border: 1px solid var(--border);

            border-radius: 12px;

            outline: none;

            background: #f8fafc;

            color: #1e293b;

            font-family: inherit;

            font-size: 13px;

            padding: 0 14px 0 41px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;

        }


        .input::placeholder {

            color: #a0aec0;

        }


        .input:hover {

            border-color: #cbd5e1;

        }


        .input:focus {

            background: #ffffff;

            border-color: var(--primary);

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, 0.08);

        }


        .input-wrap:focus-within > i {

            color: var(--primary);

        }


        select.input {

            appearance: none;

            cursor: pointer;

            padding-right: 38px;

        }


        .select-arrow {

            position: absolute;

            right: 14px;

            top: 50%;

            transform: translateY(-50%);

            color: #94a3b8;

            pointer-events: none;

            font-size: 11px;

        }


        /* =========================================
           PASSWORD
        ========================================= */

        .password-input {

            padding-right: 44px;

        }


        .password-toggle {

            position: absolute;

            right: 13px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #94a3b8;

            cursor: pointer;

            padding: 4px;

            transition: 0.2s ease;

        }


        .password-toggle:hover {

            color: var(--primary);

        }


        /* =========================================
           PASSWORD STRENGTH
        ========================================= */

        .password-strength {

            display: none;

            margin-top: 7px;

            font-size: 10px;

            color: var(--text-light);

        }


        .strength-bar {

            height: 4px;

            background: #e2e8f0;

            border-radius: 10px;

            overflow: hidden;

            margin-top: 5px;

        }


        .strength-fill {

            width: 0;

            height: 100%;

            border-radius: 10px;

            transition: width 0.3s ease;

        }


        /* =========================================
           ERROR
        ========================================= */

        .error-box {

            display: flex;

            align-items: flex-start;

            gap: 10px;

            background: var(--danger-bg);

            border: 1px solid #fecaca;

            color: var(--danger);

            border-radius: 12px;

            padding: 13px 14px;

            margin-bottom: 24px;

            font-size: 12px;

            line-height: 1.5;

        }


        .error-box i {

            margin-top: 1px;

        }


        .error-box ul {

            padding-left: 16px;

            margin: 0;

        }


        /* =========================================
           SECURITY
        ========================================= */

        .security {

            display: flex;

            align-items: center;

            gap: 9px;

            padding: 12px 14px;

            border-radius: 11px;

            background: #f8fafc;

            border: 1px solid #edf2f7;

            color: #64748b;

            font-size: 11px;

            margin-bottom: 20px;

        }


        .security i {

            color: var(--primary);

        }


        /* =========================================
           ACTIONS
        ========================================= */

        .form-actions {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

        }


        .login-link {

            color: #64748b;

            font-size: 12px;

        }


        .login-link a {

            color: var(--primary);

            text-decoration: none;

            font-weight: 700;

        }


        .login-link a:hover {

            text-decoration: underline;

        }


        .register-button {

            border: none;

            min-width: 155px;

            height: 48px;

            border-radius: 12px;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #1d4ed8
                );

            color: white;

            font-family: inherit;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 9px 20px rgba(37, 99, 235, 0.22);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .register-button:hover {

            transform: translateY(-2px);

            box-shadow:
                0 13px 26px rgba(37, 99, 235, 0.28);

        }


        .register-button:active {

            transform: translateY(0);

        }


        .register-button i {

            margin-left: 7px;

        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 900px) {

            .register-card {

                grid-template-columns: 1fr;

                max-width: 650px;

            }


            .brand-panel {

                padding: 30px;

                min-height: auto;

            }


            .benefits {

                display: grid;

                grid-template-columns: repeat(3, 1fr);

                gap: 15px;

                margin-top: 30px;

            }


            .benefit {

                margin-bottom: 0;

            }


            .brand-footer {

                display: none;

            }

        }


        @media (max-width: 650px) {

            .register-page {

                padding: 20px 12px;

            }


            .register-card {

                border-radius: 20px;

            }


            .brand-panel {

                padding: 27px 23px;

            }


            .benefits {

                grid-template-columns: 1fr;

            }


            .benefit {

                margin-bottom: 13px;

            }


            .form-panel {

                padding: 30px 22px;

            }


            .form-grid {

                grid-template-columns: 1fr;

                gap: 15px;

            }


            .field.full {

                grid-column: auto;

            }


            .form-actions {

                flex-direction: column-reverse;

                align-items: stretch;

            }


            .register-button {

                width: 100%;

            }


            .login-link {

                text-align: center;

            }

        }


        @media (max-width: 400px) {

            .form-panel {

                padding: 26px 17px;

            }


            .brand-name {

                font-size: 27px;

            }


            .form-header h1 {

                font-size: 22px;

            }

        }

    </style>

</head>


<body>


<div class="register-page">


    <div class="register-card">


        <!-- =====================================
             LEFT BRAND PANEL
        ====================================== -->

        <aside class="brand-panel">

            <div class="brand-content">

                <div class="logo">

                    <i class="fa-solid fa-heart-pulse"></i>

                </div>


                <div class="brand-name">
                    CareSched
                </div>


                <div class="brand-description">

                    Your convenient healthcare
                    appointment scheduling system.

                    Create your account and manage
                    your appointments with ease.

                </div>


                <div class="benefits">


                    <div class="benefit">

                        <div class="benefit-icon">

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>

                        <span>
                            Book and manage your
                            healthcare appointments online.
                        </span>

                    </div>


                    <div class="benefit">

                        <div class="benefit-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>

                        <span>
                            Receive important appointment
                            notifications through email.
                        </span>

                    </div>


                    <div class="benefit">

                        <div class="benefit-icon">

                            <i class="fa-solid fa-shield-halved"></i>

                        </div>

                        <span>
                            Your account information is
                            protected with secure login.
                        </span>

                    </div>


                </div>

            </div>


            <div class="brand-footer">

                CareSched · Patient Portal

            </div>

        </aside>


        <!-- =====================================
             FORM PANEL
        ====================================== -->

        <main class="form-panel">


            <div class="form-header">

                <h1>
                    Create your account
                </h1>

                <p>
                    Register as a patient to start
                    scheduling your healthcare appointments.
                </p>

            </div>


            <!-- =====================================
                 ERRORS
            ====================================== -->

            <?php if (!empty($errors)): ?>

                <div class="error-box">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <div>

                        <strong>
                            Please check the following:
                        </strong>

                        <ul>

                            <?php foreach ($errors as $e): ?>

                                <li>
                                    <?= e($e) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                </div>

            <?php endif; ?>


            <form
                method="post"
                novalidate
                autocomplete="off"
            >


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($token) ?>"
                >


                <!-- =====================================
                     PERSONAL INFORMATION
                ====================================== -->

                <div class="section-title">

                    <i class="fa-solid fa-user"></i>

                    <span>
                        Personal Information
                    </span>

                </div>


                <div class="form-grid">


                    <!-- FIRST NAME -->

                    <div class="field">

                        <label>
                            First Name
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-user"></i>

                            <input
                                type="text"
                                name="first_name"
                                class="input"
                                placeholder="Enter first name"
                                value="<?= e($_POST['first_name'] ?? '') ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- MIDDLE NAME -->

                    <div class="field">

                        <label>
                            Middle Name
                        </label>

                        <div class="input-wrap">

                            <i class="fa-regular fa-user"></i>

                            <input
                                type="text"
                                name="middle_name"
                                class="input"
                                placeholder="Enter middle name"
                                value="<?= e($_POST['middle_name'] ?? '') ?>"
                            >

                        </div>

                    </div>


                    <!-- LAST NAME -->

                    <div class="field">

                        <label>
                            Last Name
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-user"></i>

                            <input
                                type="text"
                                name="last_name"
                                class="input"
                                placeholder="Enter last name"
                                value="<?= e($_POST['last_name'] ?? '') ?>"
                                required
                            >

                        </div>

                    </div>


                    <!-- BIRTH DATE -->

                    <div class="field">

                        <label>
                            Birth Date
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-calendar"></i>

                            <input
                                type="date"
                                name="birth_date"
                                class="input"
                                value="<?= e($_POST['birth_date'] ?? '') ?>"
                            >

                        </div>

                    </div>


                    <!-- AGE -->

                    <div class="field">

                        <label>
                            Age
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-cake-candles"></i>

                            <input
                                type="number"
                                name="age"
                                class="input"
                                placeholder="Age"
                                min="1"
                                max="120"
                                value="<?= e($_POST['age'] ?? '') ?>"
                            >

                        </div>

                    </div>


                    <!-- SEX -->

                    <div class="field">

                        <label>
                            Sex
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-venus-mars"></i>

                            <select
                                name="sex"
                                class="input"
                            >

                                <option value="">
                                    Select sex
                                </option>

                                <option
                                    value="Male"
                                    <?= (($_POST['sex'] ?? '') === 'Male') ? 'selected' : '' ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= (($_POST['sex'] ?? '') === 'Female') ? 'selected' : '' ?>
                                >
                                    Female
                                </option>

                            </select>

                            <i class="fa-solid fa-chevron-down select-arrow"></i>

                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="field full">

                        <label>
                            Address
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-location-dot"></i>

                            <input
                                type="text"
                                name="address"
                                class="input"
                                placeholder="Enter your complete address"
                                value="<?= e($_POST['address'] ?? '') ?>"
                            >

                        </div>

                    </div>


                    <!-- CONTACT -->

                    <div class="field">

                        <label>
                            Contact Number
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-phone"></i>

                            <input
                                type="tel"
                                name="contact_number"
                                class="input"
                                placeholder="09XXXXXXXXX"
                                value="<?= e($_POST['contact_number'] ?? '') ?>"
                            >

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="field">

                        <label>
                            Email Address
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-envelope"></i>

                            <input
                                type="email"
                                name="email"
                                class="input"
                                placeholder="you@example.com"
                                value="<?= e($_POST['email'] ?? '') ?>"
                                required
                            >

                        </div>

                    </div>

                </div>


                <!-- =====================================
                     ACCOUNT INFORMATION
                ====================================== -->

                <div class="section-title">

                    <i class="fa-solid fa-lock"></i>

                    <span>
                        Account Information
                    </span>

                </div>


                <div class="form-grid">


                    <!-- USERNAME -->

                    <div class="field full">

                        <label>
                            Username
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-at"></i>

                            <input
                                type="text"
                                name="username"
                                class="input"
                                placeholder="Choose a username"
                                value="<?= e($_POST['username'] ?? '') ?>"
                                autocomplete="username"
                                required
                            >

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="field">

                        <label>
                            Password
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="password_reg"
                                type="password"
                                name="password"
                                class="input password-input"
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                id="togglePassReg"
                                aria-label="Show password"
                            >

                                <i class="fa-solid fa-eye"></i>

                            </button>

                        </div>


                        <div
                            class="password-strength"
                            id="passwordStrength"
                        >

                            Password strength

                            <div class="strength-bar">

                                <div
                                    class="strength-fill"
                                    id="strengthFill"
                                ></div>

                            </div>

                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="field">

                        <label>
                            Confirm Password
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrap">

                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="confirm_reg"
                                type="password"
                                name="confirm_password"
                                class="input password-input"
                                placeholder="Re-enter password"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                id="toggleConfirm"
                                aria-label="Show password"
                            >

                                <i class="fa-solid fa-eye"></i>

                            </button>

                        </div>

                    </div>

                </div>


                <!-- SECURITY NOTICE -->

                <div class="security">

                    <i class="fa-solid fa-shield-halved"></i>

                    <span>
                        Your password is securely encrypted
                        before it is stored in the system.
                    </span>

                </div>


                <!-- ACTIONS -->

                <div class="form-actions">


                    <div class="login-link">

                        Already have an account?

                        <a href="login.php">
                            Sign in
                        </a>

                    </div>


                    <button
                        type="submit"
                        class="register-button"
                    >

                        Create Account

                        <i class="fa-solid fa-arrow-right"></i>

                    </button>


                </div>


            </form>

        </main>

    </div>

</div>


<script>

/* =========================================
   PASSWORD TOGGLE
========================================= */

function setupPasswordToggle(buttonId, inputId) {

    const button =
        document.getElementById(buttonId);

    const input =
        document.getElementById(inputId);

    if (!button || !input) {
        return;
    }

    button.addEventListener('click', function () {

        const icon =
            button.querySelector('i');

        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove('fa-eye');

            icon.classList.add('fa-eye-slash');

        } else {

            input.type = 'password';

            icon.classList.remove('fa-eye-slash');

            icon.classList.add('fa-eye');

        }

    });

}


setupPasswordToggle(
    'togglePassReg',
    'password_reg'
);


setupPasswordToggle(
    'toggleConfirm',
    'confirm_reg'
);


/* =========================================
   PASSWORD STRENGTH
========================================= */

const passwordInput =
    document.getElementById('password_reg');

const passwordStrength =
    document.getElementById('passwordStrength');

const strengthFill =
    document.getElementById('strengthFill');


if (passwordInput) {

    passwordInput.addEventListener('input', function () {

        const password = this.value;

        if (password.length === 0) {

            passwordStrength.style.display = 'none';

            strengthFill.style.width = '0%';

            return;

        }


        passwordStrength.style.display = 'block';


        let score = 0;


        if (password.length >= 8) {
            score++;
        }

        if (/[A-Z]/.test(password)) {
            score++;
        }

        if (/[a-z]/.test(password)) {
            score++;
        }

        if (/[0-9]/.test(password)) {
            score++;
        }

        if (/[^A-Za-z0-9]/.test(password)) {
            score++;
        }


        const percentage =
            Math.min(score * 20, 100);


        strengthFill.style.width =
            percentage + '%';


        if (score <= 2) {

            strengthFill.style.background =
                '#ef4444';

        } else if (score === 3) {

            strengthFill.style.background =
                '#f59e0b';

        } else {

            strengthFill.style.background =
                '#16a34a';

        }

    });

}


/* =========================================
   AUTO CALCULATE AGE
========================================= */

const birthDate =
    document.querySelector(
        'input[name="birth_date"]'
    );

const ageInput =
    document.querySelector(
        'input[name="age"]'
    );


if (birthDate && ageInput) {

    birthDate.addEventListener('change', function () {

        if (!this.value) {
            return;
        }


        const birth =
            new Date(this.value);

        const today =
            new Date();


        let age =
            today.getFullYear() -
            birth.getFullYear();


        const month =
            today.getMonth() -
            birth.getMonth();


        if (
            month < 0 ||
            (
                month === 0 &&
                today.getDate() < birth.getDate()
            )
        ) {

            age--;

        }


        if (age >= 0 && age <= 120) {

            ageInput.value = age;

        }

    });

}


/* =========================================
   CONFIRM PASSWORD VISUAL CHECK
========================================= */

const confirmInput =
    document.getElementById('confirm_reg');


if (confirmInput && passwordInput) {

    confirmInput.addEventListener('input', function () {

        if (this.value === '') {

            this.style.borderColor = '';

            return;

        }


        if (this.value === passwordInput.value) {

            this.style.borderColor =
                '#16a34a';

        } else {

            this.style.borderColor =
                '#ef4444';

        }

    });

}

</script>


</body>

</html>