<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =========================
       CSRF CHECK
    ========================= */

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }


    /* =========================
       GET FORM DATA
    ========================= */

    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';


    /* =========================
       VALIDATION
    ========================= */

    if ($identifier === '') {
        $errors[] = 'Please enter your email or username.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }


    /* =========================
       LOGIN
    ========================= */

    if (empty($errors)) {

        try {

            /*
             * IMPORTANT:
             * Use different PDO placeholders.
             *
             * Do NOT use:
             *
             * email = :identifier
             * OR username = :identifier
             *
             * because this can cause HY093.
             */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    role,
                    username,
                    email,
                    password,
                    status
                FROM users
                WHERE email = :email
                   OR username = :username
                LIMIT 1
            ");


            $stmt->execute([
                'email'    => $identifier,
                'username' => $identifier
            ]);


            $user = $stmt->fetch(PDO::FETCH_ASSOC);


            /* =========================
               CHECK ACCOUNT
            ========================= */

            if (!$user) {

                $errors[] = 'Invalid email/username or password.';

            } elseif ($user['status'] !== 'active') {

                $errors[] = 'Your account is currently inactive. Please contact the administrator.';

            } elseif (!password_verify($password, $user['password'])) {

                $errors[] = 'Invalid email/username or password.';

            } else {

                /* =========================
                   LOGIN SUCCESS
                ========================= */

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']   = $user['email'];


                /*
                 * Patient login
                 */

                if ($user['role'] === 'patient') {

                    redirect('/caresched/patient/dashboard.php');

                }


                /*
                 * Admin login
                 */

                elseif ($user['role'] === 'admin') {

                    redirect('/caresched/admin/');

                }


                /*
                 * Other RHU staff
                 */

                else {

                    redirect('/caresched/patient/dashboard.php');

                }
            }


        } catch (PDOException $e) {

            /*
             * Log the real database error
             * instead of displaying it to users.
             */

            error_log(
                'CareSched Login Error: ' .
                $e->getMessage()
            );

            $errors[] =
                'Unable to process your login right now. Please try again later.';
        }
    }
}


$token = csrf_token();

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
        Patient Login - CareSched
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

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;

            font-family: 'Inter', sans-serif;

            background:
                radial-gradient(
                    circle at 10% 20%,
                    rgba(37, 99, 235, .10),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 90% 80%,
                    rgba(14, 165, 233, .10),
                    transparent 30%
                ),

                #f8fafc;
        }


        .login-wrapper {

            width: 100%;

            max-width: 430px;
        }


        .login-card {

            padding: 38px 34px;

            background:
                rgba(255, 255, 255, .92);

            border:
                1px solid rgba(226, 232, 240, .9);

            border-radius: 24px;

            box-shadow:
                0 25px 70px
                rgba(15, 23, 42, .10);

            backdrop-filter:
                blur(15px);

            animation:
                fadeUp .5s ease;
        }


        .brand {

            text-align: center;

            margin-bottom: 28px;
        }


        .brand-icon {

            width: 62px;

            height: 62px;

            margin:
                0 auto 15px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 18px;

            color: white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            box-shadow:
                0 12px 28px
                rgba(37, 99, 235, .20);
        }


        .brand-icon i {

            font-size: 25px;
        }


        .brand h1 {

            margin-bottom: 7px;

            color: #172033;

            font-size: 25px;

            font-weight: 800;

            letter-spacing: -.7px;
        }


        .brand h1 span {

            color: #2563eb;
        }


        .brand p {

            margin: 0;

            color: #64748b;

            font-size: 12px;

            line-height: 1.6;
        }


        .login-title {

            margin-bottom: 20px;

            color: #172033;

            font-size: 17px;

            font-weight: 700;
        }


        .form-group {

            margin-bottom: 17px;
        }


        .form-label {

            margin-bottom: 7px;

            color: #334155;

            font-size: 11px;

            font-weight: 700;
        }


        .input-wrapper {

            position: relative;
        }


        .input-wrapper > i:first-child {

            position: absolute;

            left: 15px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #94a3b8;

            font-size: 13px;

            z-index: 2;
        }


        .form-control {

            height: 48px;

            padding:
                10px 44px 10px 43px;

            border:
                1px solid #dbe4f0;

            border-radius: 12px;

            color: #1e293b;

            background: #f8fafc;

            font-size: 12px;

            box-shadow: none;

            transition: .25s ease;
        }


        .form-control:focus {

            border-color: #2563eb;

            background: white;

            box-shadow:
                0 0 0 4px
                rgba(37, 99, 235, .08);
        }


        .show-password {

            position: absolute;

            right: 15px;

            top: 50%;

            transform:
                translateY(-50%);

            color: #94a3b8;

            cursor: pointer;

            font-size: 13px;

            z-index: 3;
        }


        .show-password:hover {

            color: #2563eb;
        }


        .security-note {

            display: flex;

            align-items: center;

            gap: 8px;

            padding: 11px 12px;

            margin:
                5px 0 20px;

            border-radius: 10px;

            color: #64748b;

            background: #f8fafc;

            font-size: 10px;

            line-height: 1.5;
        }


        .security-note i {

            color: #2563eb;

            font-size: 12px;
        }


        .login-actions {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 10px;

            margin-bottom: 23px;
        }


        .forgot-link {

            color: #2563eb;

            font-size: 11px;

            font-weight: 600;

            text-decoration: none;
        }


        .forgot-link:hover {

            text-decoration: underline;
        }


        .login-button {

            min-width: 115px;

            height: 45px;

            border: none;

            border-radius: 11px;

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
                0 9px 20px
                rgba(37, 99, 235, .18);

            transition: .25s ease;
        }


        .login-button:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 13px 25px
                rgba(37, 99, 235, .25);
        }


        .register-text {

            padding-top: 20px;

            border-top:
                1px solid #eef2f7;

            text-align: center;

            color: #64748b;

            font-size: 11px;
        }


        .register-text a {

            color: #2563eb;

            font-weight: 700;

            text-decoration: none;
        }


        .register-text a:hover {

            text-decoration: underline;
        }


        .back-home {

            display: block;

            margin-top: 20px;

            color: #64748b;

            font-size: 10px;

            text-align: center;

            text-decoration: none;
        }


        .back-home:hover {

            color: #2563eb;
        }


        .alert {

            border-radius: 11px;

            font-size: 11px;
        }


        .alert ul {

            padding-left: 18px;
        }


        @keyframes fadeUp {

            from {

                opacity: 0;

                transform:
                    translateY(15px);
            }

            to {

                opacity: 1;

                transform:
                    translateY(0);
            }
        }


        @media (max-width: 480px) {

            body {

                padding: 15px;
            }


            .login-card {

                padding:
                    30px 22px;

                border-radius: 20px;
            }


            .login-actions {

                align-items:
                    stretch;

                flex-direction:
                    column;
            }


            .login-button {

                width: 100%;
            }

        }

    </style>

</head>


<body>


<div class="login-wrapper">


    <div class="login-card">


        <!-- BRAND -->

        <div class="brand">

            <div class="brand-icon">

                <i
                    class="fa-solid fa-heart-pulse"
                ></i>

            </div>


            <h1>
                Care<span>Sched</span>
            </h1>


            <p>
                Smart Scheduling. Better Healthcare.
            </p>

        </div>


        <!-- TITLE -->

        <div class="login-title">

            <i
                class="fa-solid fa-right-to-bracket me-2"
                style="color:#2563eb;"
            ></i>

            Patient Login

        </div>


        <!-- ERROR -->

        <?php if (!empty($errors)): ?>

            <div
                class="alert alert-danger"
                role="alert"
            >

                <ul class="mb-0">

                    <?php foreach ($errors as $e): ?>

                        <li>
                            <?= e($e) ?>
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

                <?= e($msg) ?>

            </div>

        <?php endif; ?>


        <!-- LOGIN FORM -->

        <form
            method="post"
            novalidate
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($token) ?>"
            >


            <!-- EMAIL / USERNAME -->

            <div class="form-group">

                <label class="form-label">

                    Email or Username

                </label>


                <div class="input-wrapper">

                    <i
                        class="fa-solid fa-user"
                    ></i>


                    <input
                        type="text"
                        name="identifier"
                        class="form-control"
                        placeholder="Enter your email or username"
                        autocomplete="username"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label class="form-label">

                    Password

                </label>


                <div class="input-wrapper">

                    <i
                        class="fa-solid fa-lock"
                    ></i>


                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >


                    <i
                        id="togglePass"
                        class="fa-solid fa-eye show-password"
                        title="Show/Hide password"
                    ></i>

                </div>

            </div>


            <!-- SECURITY -->

            <div class="security-note">

                <i
                    class="fa-solid fa-shield-halved"
                ></i>

                <span>
                    Your account information is protected
                    with secure authentication.
                </span>

            </div>


            <!-- ACTIONS -->

            <div class="login-actions">


                <a
                    href="auth/forgot-password.php"
                    class="forgot-link"
                >

                    Forgot Password?

                </a>


                <button
                    type="submit"
                    class="login-button"
                >

                    <i
                        class="fa-solid fa-right-to-bracket me-1"
                    ></i>

                    Login

                </button>


            </div>


            <!-- REGISTER -->

            <div class="register-text">

                Don't have an account?

                <a href="register.php">

                    Create one

                </a>

            </div>

        </form>


        <!-- HOME -->

        <a
            href="index.php"
            class="back-home"
        >

            <i
                class="fa-solid fa-arrow-left me-1"
            ></i>

            Back to CareSched

        </a>


    </div>

</div>


<script>

    /* =========================================
       SHOW / HIDE PASSWORD
    ========================================= */

    const togglePass =
        document.getElementById('togglePass');

    const password =
        document.getElementById('password');


    if (togglePass && password) {

        togglePass.addEventListener(
            'click',
            function () {

                if (password.type === 'password') {

                    password.type = 'text';

                    this.classList.remove(
                        'fa-eye'
                    );

                    this.classList.add(
                        'fa-eye-slash'
                    );

                } else {

                    password.type = 'password';

                    this.classList.remove(
                        'fa-eye-slash'
                    );

                    this.classList.add(
                        'fa-eye'
                    );

                }

            }
        );

    }

</script>


</body>

</html>