<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('patient');

$user_id = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get logged-in patient
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT p.id AS patient_id, p.first_name, p.middle_name, p.last_name, u.username, u.email
     FROM patients p
     INNER JOIN users u ON u.id = p.user_id
     WHERE u.id = :user_id
     LIMIT 1'
);
$stmt->execute(['user_id' => $user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient profile not found.');
}

/*
|--------------------------------------------------------------------------
| Ensure admin account exists
|--------------------------------------------------------------------------
*/
$admin = get_admin_user($pdo);

if (!$admin) {
    die('Administrator account is not configured.');
}

/*
|--------------------------------------------------------------------------
| Get or create conversation between patient and admin
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT * FROM conversations WHERE patient_id = :patient_id AND admin_id = :admin_id LIMIT 1'
);
$stmt->execute([
    'patient_id' => $patient['patient_id'],
    'admin_id' => $admin['id']
]);
$convo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$convo) {
    $stmt = $pdo->prepare(
        'INSERT INTO conversations (patient_id, admin_id) VALUES (:patient_id, :admin_id)'
    );
    $stmt->execute([
        'patient_id' => $patient['patient_id'],
        'admin_id' => $admin['id']
    ]);
    $conversation_id = (int) $pdo->lastInsertId();
} else {
    $conversation_id = (int) $convo['id'];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    }

    $message_text = trim($_POST['message'] ?? '');

    if ($message_text === '') {
        $errors[] = 'Please enter a message.';
    } elseif (mb_strlen($message_text) > 2000) {
        $errors[] = 'Message cannot be longer than 2000 characters.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO messages (conversation_id, sender_id, receiver_id, message, created_at)
             VALUES (:conversation_id, :sender_id, :receiver_id, :message, NOW())'
        );
        $stmt->execute([
            'conversation_id' => $conversation_id,
            'sender_id' => $user_id,
            'receiver_id' => $admin['id'],
            'message' => $message_text
        ]);

        $stmt = $pdo->prepare(
            'UPDATE conversations SET updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $conversation_id]);

        redirect(app_url('patient/messages.php'));
    }
}

/*
|--------------------------------------------------------------------------
| Load conversation messages
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT m.*, u.role, u.username, p.first_name, p.middle_name, p.last_name
     FROM messages m
     INNER JOIN users u ON u.id = m.sender_id
     LEFT JOIN patients p ON p.user_id = u.id
     WHERE m.conversation_id = :conversation_id
     ORDER BY m.created_at ASC'
);
$stmt->execute(['conversation_id' => $conversation_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Mark admin replies as read when patient views conversation
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'UPDATE messages SET is_read = 1 WHERE conversation_id = :conversation_id AND receiver_id = :receiver_id'
);
$stmt->execute([
    'conversation_id' => $conversation_id,
    'receiver_id' => $user_id
]);

$unread_count = get_unread_message_count($pdo, $user_id);
$csrf_token = csrf_token();
$patient_fullname = format_full_name($patient['first_name'], $patient['middle_name'], $patient['last_name']);
$admin_fullname = $admin['full_name'] ?: $admin['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | CareSched Patient</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { margin:0; font-family:'Inter',sans-serif; background:#f5f8ff; color:#172033; }
        .top-navbar { background:#fff; border-bottom:1px solid #e7ecf3; position:sticky; top:0; z-index:100; }
        .navbar-inner { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 0; }
        .brand { display:flex; align-items:center; gap:10px; font-size:1.15rem; font-weight:700; color:#0d6efd; }
        .brand span { color:#198754; }
        .nav-links { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
        .nav-links a { color:#495057; font-weight:500; }
        .nav-links a.active, .nav-links a:hover { color:#0d6efd; }
        .content { max-width:1200px; margin:32px auto; padding:0 16px; }
        .card-chat { border:none; border-radius:24px; box-shadow:0 20px 50px rgba(15,35,65,.08); overflow:hidden; }
        .chat-header { background:#0d6efd; color:#fff; padding:24px 28px; }
        .chat-header h1 { margin:0; font-size:1.3rem; }
        .chat-header p { margin:8px 0 0; opacity:.88; }
        .chat-body { background:#eaf2ff; padding:24px; min-height:480px; max-height:58vh; overflow-y:auto; }
        .bubble { max-width:78%; padding:16px 18px; border-radius:20px; margin-bottom:16px; position:relative; line-height:1.6; }
        .bubble.patient { background:#fff; margin-right:auto; border:1px solid #e2e8f0; }
        .bubble.admin { background:#0d6efd; color:#fff; margin-left:auto; }
        .bubble strong { display:block; margin-bottom:8px; font-weight:700; }
        .bubble-time { font-size:.82rem; opacity:.75; margin-top:10px; text-align:right; }
        .chat-footer { background:#fff; border-top:1px solid #e7ecf3; padding:18px 24px; }
        .chat-footer form { display:flex; gap:12px; flex-wrap:wrap; }
        .chat-footer textarea { flex:1 1 100%; min-height:100px; border-radius:16px; border:1px solid #d8e2ef; padding:16px; resize:none; font-family:'Inter',sans-serif; }
        .chat-footer button { min-width:140px; }
        .breadcrumb { display:flex; gap:6px; align-items:center; font-size:.95rem; color:#6c757d; margin-bottom:16px; }
        .breadcrumb a { color:#0d6efd; }
        .alert-wrap { margin-bottom:18px; }
        @media (max-width:768px) { .bubble { max-width:100%; } }
    </style>
</head>
<body>

<nav class="top-navbar">
    <div class="container">
        <div class="navbar-inner">
            <a href="<?= e(app_url('/')) ?>" class="brand"><i class="fa-solid fa-heart-pulse"></i> Care<span>Sched</span></a>
            <div class="nav-links">
                <a href="<?= e(app_url('patient/dashboard.php')) ?>">Dashboard</a>
                <a href="<?= e(app_url('patient/book-appointment.php')) ?>">Book Appointment</a>
                <a href="<?= e(app_url('patient/appointments.php')) ?>">My Appointments</a>
                <a href="<?= e(app_url('patient/messages.php')) ?>" class="active">Messages</a>
                <a href="<?= e(app_url('logout.php')) ?>">Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="content">
    <div class="breadcrumb">
        <a href="<?= e(app_url('patient/dashboard.php')) ?>">Patient Dashboard</a>
        <span>/</span>
        <span>Messages</span>
    </div>

    <div class="card-chat">
        <div class="chat-header">
            <h1>CareSched Support</h1>
            <p>Chat with <strong><?= e($admin_fullname) ?></strong> — Administrator</p>
        </div>

        <div class="chat-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-wrap">
                    <?= e(implode(' ', $errors)) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($messages)): ?>
                <div class="text-center text-muted">No messages yet. Send a message to start the conversation.</div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <?php
                        $senderRole = $message['role'] ?? 'patient';
                        $senderName = $message['role'] === 'admin'
                            ? $admin_fullname
                            : format_full_name($message['first_name'], $message['middle_name'], $message['last_name']);
                        $time = date('g:i A', strtotime($message['created_at']));
                    ?>
                    <div class="bubble <?= $senderRole === 'admin' ? 'admin' : 'patient' ?>">
                        <strong><?= e($senderName) ?></strong>
                        <div><?= nl2br(e($message['message'])) ?></div>
                        <div class="bubble-time"><?= e($time) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-footer">
            <form method="POST" action="<?= e(app_url('patient/messages.php')) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <textarea name="message" placeholder="Type your message..." maxlength="2000"></textarea>
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
