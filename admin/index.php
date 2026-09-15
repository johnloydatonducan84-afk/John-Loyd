<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = [];

/*
|--------------------------------------------------------------------------
| ADMIN LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $errors[] = 'Please enter your username and password.';

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    username,
                    email,
                    password,
                    role,
                    status
                FROM users
                WHERE username = :username
                  AND role = 'admin'
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                ':username' => $username
            ]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {

                $errors[] = 'Invalid admin username or password.';

            } elseif (!password_verify($password, $admin['password'])) {

                $errors[] = 'Invalid admin username or password.';

            } else {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['email'] = $admin['email'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_logged_in'] = true;

                header('Location: dashboard.php');
                exit;
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $errors[] = 'Database error. Please try again.';
        }
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

<title>Admin Login | CareSched</title>


<!-- Bootstrap -->
<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
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

/* =====================================================
   GLOBAL
===================================================== */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {

    margin: 0;

    min-height: 100vh;

    font-family: 'Inter', sans-serif;

    color: #172033;

    background:
        radial-gradient(
            circle at 10% 10%,
            rgba(37, 99, 235, .14),
            transparent 28%
        ),
        radial-gradient(
            circle at 90% 90%,
            rgba(14, 165, 233, .12),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #f8fafc,
            #eef6ff
        );

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 30px;

    overflow-x: hidden;
}


/* =====================================================
   BACKGROUND DECORATIONS
===================================================== */

.background-shape {

    position: fixed;

    border-radius: 50%;

    pointer-events: none;

    filter: blur(2px);

    z-index: 0;
}

.shape-one {

    width: 320px;

    height: 320px;

    top: -160px;

    left: -120px;

    background:
        rgba(37, 99, 235, .08);
}

.shape-two {

    width: 280px;

    height: 280px;

    right: -120px;

    bottom: -120px;

    background:
        rgba(14, 165, 233, .08);
}


/* =====================================================
   MAIN LOGIN CONTAINER
===================================================== */

.login-wrapper {

    position: relative;

    z-index: 2;

    width: 100%;

    max-width: 1080px;

    min-height: 650px;

    display: grid;

    grid-template-columns: 1.05fr .95fr;

    background: rgba(255,255,255,.96);

    border: 1px solid rgba(255,255,255,.8);

    border-radius: 30px;

    overflow: hidden;

    box-shadow:
        0 35px 90px rgba(15,23,42,.14);

    backdrop-filter: blur(12px);
}


/* =====================================================
   LEFT PANEL
===================================================== */

.login-info {

    position: relative;

    padding: 58px;

    color: #fff;

    overflow: hidden;

    background:
        linear-gradient(
            145deg,
            #0f172a 0%,
            #172554 45%,
            #1d4ed8 100%
        );

    display: flex;

    flex-direction: column;

    justify-content: space-between;
}


/* Decorative circles */

.login-info::before {

    content: "";

    position: absolute;

    width: 420px;

    height: 420px;

    border-radius: 50%;

    right: -210px;

    top: -180px;

    background:
        rgba(59,130,246,.16);
}

.login-info::after {

    content: "";

    position: absolute;

    width: 330px;

    height: 330px;

    border-radius: 50%;

    left: -180px;

    bottom: -170px;

    background:
        rgba(14,165,233,.12);
}


/* =====================================================
   BRAND
===================================================== */

.brand {

    position: relative;

    z-index: 3;

    display: flex;

    align-items: center;

    gap: 13px;

    font-size: 24px;

    font-weight: 800;

    letter-spacing: -.5px;
}

.brand-icon {

    width: 48px;

    height: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 15px;

    background:
        linear-gradient(
            135deg,
            #3b82f6,
            #06b6d4
        );

    box-shadow:
        0 10px 25px rgba(0,0,0,.2);

    font-size: 21px;
}

.brand small {

    display: block;

    margin-top: 2px;

    font-size: 9px;

    font-weight: 600;

    letter-spacing: 1.5px;

    opacity: .55;
}


/* =====================================================
   LEFT CONTENT
===================================================== */

.info-content {

    position: relative;

    z-index: 3;

    margin-top: 35px;
}

.info-tag {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 7px 12px;

    border-radius: 50px;

    background: rgba(255,255,255,.10);

    border: 1px solid rgba(255,255,255,.12);

    font-size: 10px;

    font-weight: 700;

    letter-spacing: .5px;

    margin-bottom: 22px;
}

.info-tag i {

    color: #67e8f9;
}


.info-content h1 {

    font-size: 45px;

    line-height: 1.08;

    font-weight: 800;

    letter-spacing: -1.5px;

    margin: 0 0 20px;
}

.info-content h1 span {

    color: #67e8f9;
}


.info-content > p {

    max-width: 460px;

    margin: 0;

    color: rgba(255,255,255,.75);

    font-size: 14px;

    line-height: 1.8;
}


/* =====================================================
   FEATURES
===================================================== */

.features {

    margin-top: 34px;

    display: grid;

    gap: 12px;
}

.feature {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 11px 13px;

    width: fit-content;

    min-width: 300px;

    border-radius: 13px;

    background: rgba(255,255,255,.06);

    border: 1px solid rgba(255,255,255,.07);
}

.feature-icon {

    width: 37px;

    height: 37px;

    flex-shrink: 0;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: rgba(255,255,255,.10);

    color: #93c5fd;

    font-size: 14px;
}

.feature span {

    font-size: 11px;

    font-weight: 600;

    color: rgba(255,255,255,.82);
}


/* =====================================================
   LEFT FOOTER
===================================================== */

.info-footer {

    position: relative;

    z-index: 3;

    display: flex;

    align-items: center;

    gap: 8px;

    color: rgba(255,255,255,.42);

    font-size: 10px;
}

.info-footer i {

    color: #67e8f9;
}


/* =====================================================
   RIGHT LOGIN AREA
===================================================== */

.login-form-area {

    padding: 60px 65px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background: #ffffff;
}


/* =====================================================
   LOGIN HEADER
===================================================== */

.login-header {

    margin-bottom: 28px;
}

.admin-icon {

    width: 58px;

    height: 58px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 16px;

    color: #2563eb;

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #e0f2fe
        );

    border: 1px solid #dbeafe;

    box-shadow:
        0 8px 20px rgba(37,99,235,.08);

    font-size: 21px;

    margin-bottom: 19px;
}

.login-header h2 {

    margin: 0 0 7px;

    color: #0f172a;

    font-size: 29px;

    font-weight: 800;

    letter-spacing: -.7px;
}

.login-header p {

    margin: 0;

    color: #64748b;

    font-size: 12px;

    line-height: 1.6;
}


/* =====================================================
   ERROR
===================================================== */

.alert-danger {

    border: 1px solid #fecaca !important;

    border-radius: 12px !important;

    background: #fff7f7 !important;

    color: #b91c1c !important;

    font-size: 12px;

    line-height: 1.5;

    padding: 13px 14px;

    box-shadow: none !important;
}


/* =====================================================
   FORM
===================================================== */

.form-group {

    margin-bottom: 19px;
}

.form-label {

    display: block;

    margin-bottom: 8px;

    color: #334155;

    font-size: 11px;

    font-weight: 700;

    letter-spacing: .2px;
}

.input-wrapper {

    position: relative;
}

.input-icon {

    position: absolute;

    left: 16px;

    top: 50%;

    transform: translateY(-50%);

    color: #94a3b8;

    font-size: 13px;

    z-index: 2;

    transition: .2s;
}

.form-control {

    width: 100%;

    height: 52px;

    border-radius: 12px;

    border: 1px solid #dbe3ec;

    background: #f8fafc;

    color: #172033;

    padding:
        0 44px 0 43px;

    font-size: 12px;

    outline: none;

    transition:
        border-color .2s,
        background .2s,
        box-shadow .2s;
}

.form-control::placeholder {

    color: #a0aec0;
}

.form-control:hover {

    border-color: #cbd5e1;

    background: #fff;
}

.form-control:focus {

    border-color: #2563eb;

    background: #fff;

    box-shadow:
        0 0 0 4px rgba(37,99,235,.09);
}

.input-wrapper:focus-within .input-icon {

    color: #2563eb;
}


/* =====================================================
   PASSWORD TOGGLE
===================================================== */

.toggle-password {

    position: absolute;

    right: 15px;

    top: 50%;

    transform: translateY(-50%);

    width: 27px;

    height: 27px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 7px;

    color: #94a3b8;

    cursor: pointer;

    transition: .2s;

    z-index: 3;
}

.toggle-password:hover {

    color: #2563eb;

    background: #eff6ff;
}


/* =====================================================
   LOGIN BUTTON
===================================================== */

.login-button {

    position: relative;

    width: 100%;

    height: 52px;

    margin-top: 3px;

    border: 0;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    color: #fff;

    font-size: 12px;

    font-weight: 700;

    letter-spacing: .2px;

    cursor: pointer;

    overflow: hidden;

    box-shadow:
        0 12px 25px rgba(37,99,235,.20);

    transition:
        transform .2s,
        box-shadow .2s;
}

.login-button::before {

    content: "";

    position: absolute;

    top: 0;

    left: -100%;

    width: 100%;

    height: 100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.18),
            transparent
        );

    transition: .5s;
}

.login-button:hover {

    transform: translateY(-2px);

    box-shadow:
        0 16px 30px rgba(37,99,235,.28);
}

.login-button:hover::before {

    left: 100%;
}

.login-button:active {

    transform: translateY(0);
}


/* =====================================================
   SECURITY NOTE
===================================================== */

.secure-note {

    display: flex;

    align-items: flex-start;

    gap: 9px;

    margin-top: 17px;

    padding: 12px 13px;

    border-radius: 11px;

    background: #f8fafc;

    border: 1px solid #edf2f7;

    color: #64748b;

    font-size: 9px;

    line-height: 1.55;
}

.secure-note i {

    color: #2563eb;

    margin-top: 1px;
}


/* =====================================================
   BACK LINK
===================================================== */

.back-link {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 5px;

    margin: 22px auto 0;

    color: #64748b;

    text-decoration: none;

    font-size: 10px;

    font-weight: 600;

    transition: .2s;
}

.back-link:hover {

    color: #2563eb;

    transform: translateX(-2px);
}


/* =====================================================
   COPYRIGHT
===================================================== */

.copyright {

    margin-top: 18px;

    text-align: center;

    color: #a0aec0;

    font-size: 8px;

    line-height: 1.5;
}


/* =====================================================
   RESPONSIVE TABLET
===================================================== */

@media (max-width: 950px) {

    body {

        padding: 20px;
    }

    .login-wrapper {

        max-width: 850px;

        grid-template-columns:
            1fr 1fr;
    }

    .login-info {

        padding: 45px;
    }

    .login-form-area {

        padding: 45px;
    }

    .info-content h1 {

        font-size: 36px;
    }

    .feature {

        min-width: 0;

        width: 100%;
    }
}


/* =====================================================
   RESPONSIVE MOBILE
===================================================== */

@media (max-width: 760px) {

    body {

        min-height: 100vh;

        padding: 15px;

        align-items: center;
    }

    .login-wrapper {

        display: block;

        max-width: 500px;

        min-height: auto;

        border-radius: 23px;
    }

    .login-info {

        display: block;

        padding: 25px 25px 28px;

        min-height: auto;
    }

    .brand {

        font-size: 20px;

        margin: 0;
    }

    .brand-icon {

        width: 42px;

        height: 42px;

        border-radius: 12px;
    }

    .info-content {

        margin-top: 24px;
    }

    .info-tag {

        margin-bottom: 14px;

        font-size: 8px;
    }

    .info-content h1 {

        font-size: 28px;

        letter-spacing: -.8px;

        margin-bottom: 10px;
    }

    .info-content > p {

        font-size: 11px;

        line-height: 1.65;

        margin-bottom: 0;
    }

    .features {

        display: none;
    }

    .info-footer {

        display: none;
    }

    .login-form-area {

        padding: 30px 25px 27px;
    }

    .login-header {

        margin-bottom: 22px;
    }

    .admin-icon {

        width: 50px;

        height: 50px;

        border-radius: 14px;

        font-size: 18px;

        margin-bottom: 15px;
    }

    .login-header h2 {

        font-size: 24px;
    }

    .login-header p {

        font-size: 11px;
    }
}


/* =====================================================
   SMALL MOBILE
===================================================== */

@media (max-width: 400px) {

    body {

        padding: 10px;
    }

    .login-wrapper {

        border-radius: 19px;
    }

    .login-info {

        padding: 22px 20px 24px;
    }

    .login-form-area {

        padding: 25px 20px;
    }

    .info-content h1 {

        font-size: 25px;
    }

    .form-control {

        height: 50px;
    }

    .login-button {

        height: 50px;
    }

    .secure-note {

        font-size: 8px;
    }
}

</style>

</head>


<body>


<!-- Background decorations -->

<div class="background-shape shape-one"></div>

<div class="background-shape shape-two"></div>


<div class="login-wrapper">


<!-- =====================================================
     LEFT PANEL
===================================================== -->

<div class="login-info">


    <!-- BRAND -->

    <div class="brand">

        <div class="brand-icon">

            <i class="fa-solid fa-heart-pulse"></i>

        </div>

        <div>

            CareSched

            <small>
                ADMINISTRATION PORTAL
            </small>

        </div>

    </div>


    <!-- CONTENT -->

    <div class="info-content">


        <div class="info-tag">

            <i class="fa-solid fa-shield-halved"></i>

            SECURE STAFF ACCESS

        </div>


        <h1>

            Manage healthcare
            <span>smarter.</span>

        </h1>


        <p>

            Access the CareSched administration panel
            to manage appointments, patients, services,
            schedules, and email notifications for the
            Rural Health Unit of Arakan.

        </p>


        <!-- FEATURES -->

        <div class="features">


            <div class="feature">

                <div class="feature-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>

                <span>
                    Manage patient appointments
                </span>

            </div>


            <div class="feature">

                <div class="feature-icon">

                    <i class="fa-solid fa-users"></i>

                </div>

                <span>
                    Monitor patient information
                </span>

            </div>


            <div class="feature">

                <div class="feature-icon">

                    <i class="fa-solid fa-envelope"></i>

                </div>

                <span>
                    Send appointment notifications
                </span>

            </div>


        </div>

    </div>


    <!-- FOOTER -->

    <div class="info-footer">

        <i class="fa-solid fa-circle-check"></i>

        CareSched • Rural Health Unit of Arakan

    </div>


</div>


<!-- =====================================================
     LOGIN FORM
===================================================== -->

<div class="login-form-area">


    <div class="login-header">


        <div class="admin-icon">

            <i class="fa-solid fa-user-shield"></i>

        </div>


        <h2>
            Welcome back
        </h2>


        <p>
            Sign in to continue to the admin dashboard.
        </p>


    </div>


    <!-- ERROR -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger mb-4">

            <i class="fa-solid fa-circle-exclamation me-2"></i>

            <?= e($errors[0]) ?>

        </div>

    <?php endif; ?>


    <!-- FORM -->

    <form
        method="POST"
        action=""
        autocomplete="off"
    >


        <!-- USERNAME -->

        <div class="form-group">

            <label class="form-label">

                Username

            </label>


            <div class="input-wrapper">

                <i class="fa-solid fa-user input-icon"></i>


                <input
                    type="text"
                    name="username"
                    class="form-control"
                    placeholder="Enter admin username"
                    value="<?= e($_POST['username'] ?? '') ?>"
                    required
                    autofocus
                >

            </div>

        </div>


        <!-- PASSWORD -->

        <div class="form-group">

            <label class="form-label">

                Password

            </label>


            <div class="input-wrapper">

                <i class="fa-solid fa-lock input-icon"></i>


                <input
                    type="password"
                    name="password"
                    id="adminPassword"
                    class="form-control"
                    placeholder="Enter your password"
                    required
                >


                <span
                    class="toggle-password"
                    id="togglePassword"
                    title="Show password"
                    role="button"
                    tabindex="0"
                >

                    <i class="fa-solid fa-eye"></i>

                </span>

            </div>

        </div>


        <!-- LOGIN BUTTON -->

        <button
            type="submit"
            class="login-button"
        >

            <i class="fa-solid fa-right-to-bracket me-2"></i>

            Sign In to Admin Panel

        </button>


        <!-- SECURITY -->

        <div class="secure-note">

            <i class="fa-solid fa-shield-halved"></i>

            <span>

                Your administrator account is protected
                using secure password authentication.

            </span>

        </div>


    </form>


    <!-- BACK -->

    <a
        href="/caresched/"
        class="back-link"
    >

        <i class="fa-solid fa-arrow-left"></i>

        Back to CareSched

    </a>


    <!-- COPYRIGHT -->

    <div class="copyright">

        © <?= date('Y') ?> CareSched —
        Rural Health Unit, Arakan, Cotabato

    </div>


</div>


</div>


<script>

/* =====================================================
   PASSWORD SHOW / HIDE
===================================================== */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const password =
            document.getElementById(
                'adminPassword'
            );

        const toggle =
            document.getElementById(
                'togglePassword'
            );


        if (!password || !toggle) {
            return;
        }


        function togglePassword() {

            const icon =
                toggle.querySelector('i');


            if (
                password.type === 'password'
            ) {

                password.type = 'text';

                icon.classList.remove(
                    'fa-eye'
                );

                icon.classList.add(
                    'fa-eye-slash'
                );

                toggle.title =
                    'Hide password';

            } else {

                password.type = 'password';

                icon.classList.remove(
                    'fa-eye-slash'
                );

                icon.classList.add(
                    'fa-eye'
                );

                toggle.title =
                    'Show password';
            }
        }


        toggle.addEventListener(
            'click',
            togglePassword
        );


        toggle.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Enter' ||
                    event.key === ' '
                ) {

                    event.preventDefault();

                    togglePassword();
                }

            }
        );

    }
);

</script>


</body>

</html>