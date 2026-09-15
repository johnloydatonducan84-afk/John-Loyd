<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, u.email, u.username
    FROM patients p
    INNER JOIN users u ON u.id = p.user_id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die('Patient record not found.');
}

$apptStmt = $pdo->prepare("
    SELECT a.appointment_date, a.appointment_time, a.status, s.service_name
    FROM appointments a
    INNER JOIN services s ON s.id = a.service_id
    WHERE a.patient_id = :id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$apptStmt->execute([':id' => $id]);
$appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Record - <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body { margin:0; padding:40px; font-family:'Segoe UI',Arial,sans-serif; color:#172033; }
.print-bar { display:flex; justify-content:flex-end; gap:10px; margin-bottom:24px; }
.print-btn {
    display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border:none; border-radius:10px;
    background:#0d6efd; color:#fff; font-size:13px; font-weight:700; cursor:pointer;
}
.header { display:flex; justify-content:space-between; align-items:center; border-bottom:3px solid #0d6efd; padding-bottom:16px; margin-bottom:24px; }
.header h1 { margin:0; font-size:20px; }
.header p { margin:2px 0 0; font-size:11px; color:#64748b; }
.brand { display:flex; align-items:center; gap:10px; }
.brand-icon { width:40px; height:40px; border-radius:12px; background:linear-gradient(135deg,#0d6efd,#198754); display:flex; align-items:center; justify-content:center; color:#fff; }
h2 { font-size:14px; border-bottom:1px solid #e6ebf2; padding-bottom:8px; margin:24px 0 14px; }
.info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px 30px; }
.info-item label { display:block; font-size:9px; font-weight:700; text-transform:uppercase; color:#8995a7; letter-spacing:.5px; }
.info-item div { font-size:13px; margin-top:3px; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:10px 12px; border-bottom:1px solid #e6ebf2; text-align:left; font-size:12px; }
th { background:#fafbfd; font-size:9px; text-transform:uppercase; color:#8995a7; }
.footer { margin-top:40px; font-size:10px; color:#94a3b8; text-align:center; }
@media print {
    .print-bar { display:none; }
    body { padding:0; }
}
</style>
</head>
<body>

<div class="print-bar">
    <button type="button" class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
</div>

<div class="header">
    <div class="brand">
        <div class="brand-icon"><i class="fa-solid fa-heart-pulse"></i></div>
        <div>
            <h1>CareSched</h1>
            <p>Rural Health Unit of Arakan &middot; Patient Record</p>
        </div>
    </div>
    <div style="text-align:right;">
        <p>Generated <?= e(date('F d, Y h:i A')) ?></p>
        <p>Patient ID #<?= e($patient['id']) ?></p>
    </div>
</div>

<h2>Personal Information</h2>
<div class="info-grid">
    <div class="info-item"><label>Full Name</label><div><?= e(trim($patient['first_name'] . ' ' . $patient['middle_name'] . ' ' . $patient['last_name'])) ?></div></div>
    <div class="info-item"><label>Sex</label><div><?= e($patient['sex'] ?? 'N/A') ?></div></div>
    <div class="info-item"><label>Birth Date</label><div><?= $patient['birth_date'] ? e(date('F d, Y', strtotime($patient['birth_date']))) : 'N/A' ?></div></div>
    <div class="info-item"><label>Age</label><div><?= e($patient['age'] ?? 'N/A') ?></div></div>
    <div class="info-item"><label>Contact Number</label><div><?= e($patient['contact_number'] ?? 'N/A') ?></div></div>
    <div class="info-item"><label>Email</label><div><?= e($patient['email']) ?></div></div>
    <div class="info-item" style="grid-column:1 / -1;"><label>Address</label><div><?= e($patient['address'] ?? 'N/A') ?></div></div>
</div>

<h2>Appointment History (<?= count($appointments) ?>)</h2>
<?php if (!empty($appointments)): ?>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Service</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($appointments as $appt): ?>
        <tr>
            <td><?= e(date('M d, Y', strtotime($appt['appointment_date']))) ?></td>
            <td><?= e(date('h:i A', strtotime($appt['appointment_time']))) ?></td>
            <td><?= e($appt['service_name']) ?></td>
            <td><?= e($appt['status']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#94a3b8; font-size:12px;">No appointment records yet.</p>
<?php endif; ?>

<div class="footer">
    CareSched &middot; Rural Health Unit of Arakan, Cotabato
</div>

</body>
</html>
