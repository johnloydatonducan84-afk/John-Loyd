<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('patient');

$user_id = $_SESSION['user_id'];

$success = '';
$errors = [];


/*
|--------------------------------------------------------------------------
| GET PATIENT INFORMATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.*,
        u.username,
        u.email
    FROM patients p
    INNER JOIN users u
        ON p.user_id = u.id
    WHERE p.user_id = :uid
    LIMIT 1
");

$stmt->execute([
    ':uid' => $user_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$patient) {

    die('Patient profile not found.');

}


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    $sex = trim($_POST['sex'] ?? '');

    $age = trim($_POST['age'] ?? '');

    $contact_number = trim($_POST['contact_number'] ?? '');

    $address = trim($_POST['address'] ?? '');

    $email = trim($_POST['email'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($first_name === '') {

        $errors[] = 'First name is required.';

    }


    if ($last_name === '') {

        $errors[] = 'Last name is required.';

    }


    if (
        $email === ''
        ||
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] = 'Please enter a valid email address.';

    }


    if (
        $age !== ''
        &&
        (
            !ctype_digit($age)
            ||
            (int)$age < 1
            ||
            (int)$age > 150
        )
    ) {

        $errors[] = 'Please enter a valid age.';

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK IF EMAIL ALREADY EXISTS
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $checkEmail = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
            AND id != :user_id
            LIMIT 1
        ");

        $checkEmail->execute([
            ':email' => $email,
            ':user_id' => $user_id
        ]);

        if ($checkEmail->fetch()) {

            $errors[] = 'This email address is already being used.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE DATABASE
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | UPDATE PATIENT TABLE
            |--------------------------------------------------------------------------
            */

            $updatePatient = $pdo->prepare("
                UPDATE patients
                SET
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    sex = :sex,
                    age = :age,
                    contact_number = :contact_number,
                    address = :address
                WHERE user_id = :user_id
            ");


            $updatePatient->execute([

                ':first_name' => $first_name,

                ':middle_name' =>
                    $middle_name !== ''
                    ? $middle_name
                    : null,

                ':last_name' => $last_name,

                ':sex' =>
                    $sex !== ''
                    ? $sex
                    : null,

                ':age' =>
                    $age !== ''
                    ? (int)$age
                    : null,

                ':contact_number' =>
                    $contact_number !== ''
                    ? $contact_number
                    : null,

                ':address' =>
                    $address !== ''
                    ? $address
                    : null,

                ':user_id' => $user_id

            ]);


            /*
            |--------------------------------------------------------------------------
            | UPDATE EMAIL
            |--------------------------------------------------------------------------
            */

            $updateUser = $pdo->prepare("
                UPDATE users
                SET email = :email
                WHERE id = :user_id
            ");


            $updateUser->execute([

                ':email' => $email,

                ':user_id' => $user_id

            ]);


            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | REFRESH PATIENT DATA
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    p.*,
                    u.username,
                    u.email
                FROM patients p
                INNER JOIN users u
                    ON p.user_id = u.id
                WHERE p.user_id = :uid
                LIMIT 1
            ");


            $stmt->execute([
                ':uid' => $user_id
            ]);


            $patient =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            $success =
                'Your profile has been updated successfully.';


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }


            error_log(
                'Profile update error: '
                . $e->getMessage()
            );


            $errors[] =
                'Unable to update your profile. Please try again.';

        }

    }

}


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function patientFullName($patient)
{

    return trim(

        ($patient['first_name'] ?? '')

        . ' '

        . (
            !empty($patient['middle_name'])
            ? $patient['middle_name'] . ' '
            : ''
        )

        . ($patient['last_name'] ?? '')

    );

}


$fullName =
    patientFullName($patient);

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
    My Profile | CareSched
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

    font-family: 'Inter', sans-serif;

    background:

        radial-gradient(
            circle at 0% 0%,
            rgba(37, 99, 235, .08),
            transparent 28%
        ),

        radial-gradient(
            circle at 100% 100%,
            rgba(14, 165, 233, .07),
            transparent 28%
        ),

        #f8fafc;

    color: #172033;

}


/* =====================================================
   NAVBAR
===================================================== */

.top-navbar {

    height: 72px;

    background:
        rgba(255,255,255,.94);

    backdrop-filter:
        blur(15px);

    border-bottom:
        1px solid #e8eef6;

    position: sticky;

    top: 0;

    z-index: 1000;

}


.navbar-inner {

    height: 100%;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #172033;

    text-decoration: none;

    font-size: 19px;

    font-weight: 800;

}


.brand:hover {

    color: #172033;

}


.brand-icon {

    width: 40px;

    height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    color: white;

    background:

        linear-gradient(
            135deg,
            #2563eb,
            #0ea5e9
        );

    box-shadow:

        0 8px 20px
        rgba(37,99,235,.18);

}


.brand span {

    color: #2563eb;

}


.nav-actions {

    display: flex;

    align-items: center;

    gap: 10px;

}


.dashboard-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 9px 13px;

    border-radius: 9px;

    color: #64748b;

    background: white;

    border: 1px solid #e2e8f0;

    text-decoration: none;

    font-size: 10px;

    font-weight: 700;

    transition: .2s ease;

}


.dashboard-btn:hover {

    color: #2563eb;

    border-color: #bfdbfe;

    background: #eff6ff;

}


.logout-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 9px 13px;

    border: 1px solid #e2e8f0;

    border-radius: 9px;

    color: #64748b;

    background: white;

    font-size: 10px;

    font-weight: 700;

    text-decoration: none;

    transition: .2s ease;

}


.logout-btn:hover {

    color: #ef4444;

    border-color: #fecaca;

    background: #fffafa;

}


/* =====================================================
   MAIN
===================================================== */

.profile-page {

    padding: 40px 0 60px;

}


/* =====================================================
   HEADER
===================================================== */

.page-header {

    margin-bottom: 25px;

}


.page-title {

    margin: 0 0 7px;

    color: #172033;

    font-size: 28px;

    font-weight: 800;

}


.page-subtitle {

    margin: 0;

    color: #64748b;

    font-size: 12px;

}


/* =====================================================
   PROFILE SUMMARY
===================================================== */

.profile-summary {

    position: relative;

    overflow: hidden;

    padding: 30px;

    border-radius: 20px;

    color: white;

    background:

        linear-gradient(
            120deg,
            #1d4ed8,
            #2563eb 50%,
            #0ea5e9
        );

    box-shadow:

        0 18px 45px
        rgba(37,99,235,.18);

}


.profile-summary::after {

    content: "";

    position: absolute;

    width: 230px;

    height: 230px;

    border-radius: 50%;

    right: -80px;

    top: -110px;

    background:
        rgba(255,255,255,.08);

}


.profile-summary-content {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    gap: 20px;

}


.profile-big-avatar {

    width: 78px;

    height: 78px;

    min-width: 78px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 22px;

    color: #2563eb;

    background: white;

    font-size: 28px;

    box-shadow:

        0 10px 25px
        rgba(0,0,0,.12);

}


.profile-summary h1 {

    margin: 0 0 6px;

    font-size: 24px;

    font-weight: 800;

}


.profile-summary p {

    margin: 0;

    color: rgba(255,255,255,.82);

    font-size: 11px;

}


/* =====================================================
   CARD
===================================================== */

.profile-card {

    padding: 26px;

    border:

        1px solid
        #e7edf5;

    border-radius: 18px;

    background: white;

    box-shadow:

        0 10px 30px
        rgba(15,23,42,.04);

}


.card-title {

    margin-bottom: 5px;

    color: #1e293b;

    font-size: 15px;

    font-weight: 800;

}


.card-subtitle {

    margin-bottom: 22px;

    color: #94a3b8;

    font-size: 10px;

}


/* =====================================================
   FORM
===================================================== */

.form-label {

    color: #475569;

    font-size: 11px;

    font-weight: 700;

    margin-bottom: 7px;

}


.form-control,
.form-select {

    min-height: 48px;

    border:

        1px solid
        #dbe4ee;

    border-radius: 11px;

    color: #334155;

    background: #fbfdff;

    font-size: 12px;

    transition: .2s ease;

}


.form-control:focus,
.form-select:focus {

    border-color:
        #2563eb;

    background:
        white;

    box-shadow:

        0 0 0 4px
        rgba(37,99,235,.10);

}


.input-group-text {

    border:

        1px solid
        #dbe4ee;

    border-right: none;

    color: #2563eb;

    background:
        #f8fbff;

    border-radius:
        11px 0 0 11px;

    font-size: 12px;

}


.input-group
.form-control {

    border-left: none;

    border-radius:
        0 11px 11px 0;

}


textarea.form-control {

    min-height: 100px;

    padding-top: 13px;

    resize: vertical;

}


/* =====================================================
   READ ONLY
===================================================== */

.readonly-box {

    padding: 14px;

    margin-bottom: 15px;

    border-radius: 12px;

    background:
        #f8fafc;

    border:
        1px solid #edf2f7;

}


.readonly-label {

    margin-bottom: 4px;

    color: #94a3b8;

    font-size: 9px;

    font-weight: 700;

    text-transform: uppercase;

}


.readonly-value {

    color: #334155;

    font-size: 12px;

    font-weight: 700;

}


/* =====================================================
   BUTTON
===================================================== */

.save-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    min-height: 48px;

    padding: 0 24px;

    border: none;

    border-radius: 11px;

    color: white;

    background:

        linear-gradient(
            135deg,
            #2563eb,
            #1d4ed8
        );

    font-size: 12px;

    font-weight: 700;

    box-shadow:

        0 10px 25px
        rgba(37,99,235,.22);

    transition: .2s ease;

}


.save-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:

        0 14px 30px
        rgba(37,99,235,.30);

}


/* =====================================================
   ALERT
===================================================== */

.alert {

    border: none;

    border-radius: 13px;

    font-size: 11px;

}


.alert-success {

    color: #047857;

    background: #ecfdf5;

}


.alert-danger {

    color: #b91c1c;

    background: #fef2f2;

}


/* =====================================================
   ACCOUNT CARD
===================================================== */

.account-card {

    padding: 22px;

    border-radius: 17px;

    border:

        1px solid
        #e7edf5;

    background: white;

    box-shadow:

        0 10px 30px
        rgba(15,23,42,.04);

}


.account-row {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 13px 0;

    border-bottom:

        1px solid
        #f1f5f9;

}


.account-row:last-child {

    border-bottom: none;

}


.account-icon {

    width: 35px;

    height: 35px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    color: #2563eb;

    background: #eff6ff;

    font-size: 11px;

}


.account-info {

    min-width: 0;

}


.account-label {

    color: #94a3b8;

    font-size: 8px;

}


.account-value {

    margin-top: 3px;

    color: #334155;

    font-size: 10px;

    font-weight: 700;

    word-break: break-word;

}


/* =====================================================
   FOOTER
===================================================== */

.page-footer {

    padding-top: 28px;

    text-align: center;

    color: #94a3b8;

    font-size: 9px;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 767px) {

    .top-navbar {

        height: auto;

        padding: 12px 0;

    }


    .profile-page {

        padding-top: 25px;

    }


    .profile-summary {

        padding: 24px;

    }


    .profile-summary-content {

        align-items: flex-start;

    }


    .page-title {

        font-size: 24px;

    }

}


@media (max-width: 500px) {

    body {

        overflow-x: hidden;

    }


    .brand {

        font-size: 17px;

    }


    .dashboard-btn span,
    .logout-btn span {

        display: none;

    }


    .dashboard-btn,
    .logout-btn {

        width: 38px;

        height: 38px;

        justify-content: center;

        padding: 0;

    }


    .profile-summary-content {

        flex-direction: column;

        gap: 15px;

    }


    .profile-big-avatar {

        width: 65px;

        height: 65px;

        min-width: 65px;

        border-radius: 18px;

        font-size: 22px;

    }


    .profile-summary h1 {

        font-size: 20px;

    }


    .profile-card {

        padding: 20px 16px;

    }


    .account-card {

        padding: 18px;

    }


    .save-btn {

        width: 100%;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="top-navbar">

<div class="container">

<div class="navbar-inner">


<a
    href="/caresched/patient/dashboard.php"
    class="brand"
>

<div class="brand-icon">

<i class="fa-solid fa-heart-pulse"></i>

</div>

Care<span>Sched</span>

</a>


<div class="nav-actions">


<a
    href="/caresched/patient/dashboard.php"
    class="dashboard-btn"
>

<i class="fa-solid fa-grid-2"></i>

<span>
Dashboard
</span>

</a>


<a
    href="/caresched/logout.php"
    class="logout-btn"
>

<i class="fa-solid fa-right-from-bracket"></i>

<span>
Logout
</span>

</a>


</div>

</div>

</div>

</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="profile-page">

<div class="container">


<!-- PAGE HEADER -->

<div class="page-header">

<h1 class="page-title">

My Profile

</h1>


<p class="page-subtitle">

Manage and update your personal information.

</p>

</div>


<!-- PROFILE SUMMARY -->

<div class="profile-summary mb-4">

<div class="profile-summary-content">


<div class="profile-big-avatar">

<i class="fa-solid fa-user"></i>

</div>


<div>

<h1>

<?= e($fullName) ?>

</h1>


<p>

Patient Account • CareSched

</p>

</div>


</div>

</div>


<div class="row g-4">


<!-- =================================================
     EDIT PROFILE
================================================= -->

<div class="col-lg-8">


<div class="profile-card">


<div class="card-title">

<i
    class="fa-solid fa-user-pen me-2"
    style="color:#2563eb;"
></i>

Personal Information

</div>


<div class="card-subtitle">

Keep your information accurate for appointment scheduling
and healthcare communication.

</div>


<?php if (!empty($success)): ?>

<div class="alert alert-success">

<i class="fa-solid fa-circle-check me-2"></i>

<?= e($success) ?>

</div>

<?php endif; ?>


<?php if (!empty($errors)): ?>

<div class="alert alert-danger">

<i
    class="fa-solid fa-circle-exclamation me-2"
></i>

<?= e($errors[0]) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    action=""
>


<div class="row g-3">


<!-- FIRST NAME -->

<div class="col-md-6">

<label class="form-label">

First Name

</label>


<div class="input-group">

<span class="input-group-text">

<i class="fa-solid fa-user"></i>

</span>


<input
    type="text"
    name="first_name"
    class="form-control"
    value="<?= e($patient['first_name'] ?? '') ?>"
    required
>

</div>

</div>


<!-- MIDDLE NAME -->

<div class="col-md-6">

<label class="form-label">

Middle Name

<span class="text-muted">

(Optional)

</span>

</label>


<input
    type="text"
    name="middle_name"
    class="form-control"
    value="<?= e($patient['middle_name'] ?? '') ?>"
>

</div>


<!-- LAST NAME -->

<div class="col-md-6">

<label class="form-label">

Last Name

</label>


<input
    type="text"
    name="last_name"
    class="form-control"
    value="<?= e($patient['last_name'] ?? '') ?>"
    required
>

</div>


<!-- SEX -->

<div class="col-md-6">

<label class="form-label">

Sex

</label>


<select
    name="sex"
    class="form-select"
>

<option value="">

Select Sex

</option>


<option
    value="Male"
    <?= ($patient['sex'] ?? '') === 'Male'
        ? 'selected'
        : '' ?>
>

Male

</option>


<option
    value="Female"
    <?= ($patient['sex'] ?? '') === 'Female'
        ? 'selected'
        : '' ?>
>

Female

</option>


<option
    value="Other"
    <?= ($patient['sex'] ?? '') === 'Other'
        ? 'selected'
        : '' ?>
>

Other

</option>

</select>

</div>


<!-- AGE -->

<div class="col-md-4">

<label class="form-label">

Age

</label>


<input
    type="number"
    name="age"
    min="1"
    max="150"
    class="form-control"
    value="<?= e($patient['age'] ?? '') ?>"
>

</div>


<!-- CONTACT -->

<div class="col-md-8">

<label class="form-label">

Contact Number

</label>


<div class="input-group">

<span class="input-group-text">

<i class="fa-solid fa-phone"></i>

</span>


<input
    type="text"
    name="contact_number"
    class="form-control"
    placeholder="Enter contact number"
    value="<?= e($patient['contact_number'] ?? '') ?>"
>

</div>

</div>


<!-- EMAIL -->

<div class="col-12">

<label class="form-label">

Email Address

</label>


<div class="input-group">

<span class="input-group-text">

<i class="fa-solid fa-envelope"></i>

</span>


<input
    type="email"
    name="email"
    class="form-control"
    value="<?= e($patient['email'] ?? '') ?>"
    required
>

</div>

</div>


<!-- ADDRESS -->

<div class="col-12">

<label class="form-label">

Address

</label>


<textarea
    name="address"
    class="form-control"
    placeholder="Enter your complete address"
><?= e($patient['address'] ?? '') ?></textarea>

</div>


<!-- BUTTON -->

<div class="col-12 mt-2">

<button
    type="submit"
    class="save-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Save Changes

</button>

</div>


</div>


</form>


</div>

</div>


<!-- =================================================
     ACCOUNT INFORMATION
================================================= -->

<div class="col-lg-4">


<div class="account-card">


<div class="card-title mb-3">

<i
    class="fa-solid fa-shield-halved me-2"
    style="color:#2563eb;"
></i>

Account Information

</div>


<div class="account-row">


<div class="account-icon">

<i class="fa-solid fa-user"></i>

</div>


<div class="account-info">

<div class="account-label">

Username

</div>


<div class="account-value">

<?= e(
    $patient['username']
    ?? 'Not available'
) ?>

</div>

</div>

</div>


<div class="account-row">


<div class="account-icon">

<i class="fa-solid fa-envelope"></i>

</div>


<div class="account-info">

<div class="account-label">

Email Address

</div>


<div class="account-value">

<?= e(
    $patient['email']
    ?? 'Not provided'
) ?>

</div>

</div>

</div>


<div class="account-row">


<div class="account-icon">

<i
    class="fa-solid fa-user-tag"
></i>

</div>


<div class="account-info">

<div class="account-label">

Account Type

</div>


<div class="account-value">

Patient

</div>

</div>

</div>


</div>


<div class="account-card mt-4">


<div class="card-title mb-3">

<i
    class="fa-solid fa-circle-info me-2"
    style="color:#2563eb;"
></i>

Quick Information

</div>


<div class="readonly-box">

<div class="readonly-label">

Full Name

</div>


<div class="readonly-value">

<?= e($fullName) ?>

</div>

</div>


<div class="readonly-box">

<div class="readonly-label">

Contact Number

</div>


<div class="readonly-value">

<?= e(
    $patient['contact_number']
    ?? 'Not provided'
) ?>

</div>

</div>


<div class="readonly-box mb-0">

<div class="readonly-label">

Address

</div>


<div class="readonly-value">

<?= e(
    $patient['address']
    ?? 'Not provided'
) ?>

</div>

</div>


</div>


</div>


</div>


<!-- FOOTER -->

<div class="page-footer">

<i class="fa-solid fa-heart-pulse me-1"></i>

CareSched — Rural Health Unit,
Arakan, Cotabato

<br>

<span>

Smart Scheduling. Better Healthcare.

</span>

</div>


</div>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>