<?php

/*
|--------------------------------------------------------------------------
| AVAILABLE APPOINTMENT SLOTS (AJAX)
|--------------------------------------------------------------------------
| Returns, for a given service + date, which 30-minute time slots are
| still bookable according to the schedules the admin configured
| (schedules.max_slots / active appointments already booked against it).
|
| Response: { "slots": { "08:00": true, "08:30": false, ... } }
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

ensure_role('patient');

header('Content-Type: application/json');

$serviceId = (int) ($_GET['service_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

$slots = [];

$start = strtotime('08:00');
$end = strtotime('16:30');

for ($time = $start; $time <= $end; $time += 30 * 60) {
    $slots[date('H:i', $time)] = false;
}

$dateObject = DateTime::createFromFormat('Y-m-d', $date);

if (
    $serviceId > 0 &&
    $dateObject &&
    $dateObject->format('Y-m-d') === $date
) {

    try {

        $scheduleStmt = $pdo->prepare("
            SELECT
                id,
                start_time,
                end_time,
                max_slots

            FROM schedules

            WHERE service_id = :service_id

            AND schedule_date = :schedule_date

            AND status = 'active'
        ");

        $scheduleStmt->execute([
            ':service_id' => $serviceId,
            ':schedule_date' => $date,
        ]);

        $schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $bookedStmt = $pdo->prepare("
            SELECT COUNT(*) AS total_booked

            FROM appointments

            WHERE schedule_id = :schedule_id

            AND status NOT IN ('Rejected', 'Cancelled')
        ");

        foreach ($schedules as $schedule) {

            $bookedStmt->execute([
                ':schedule_id' => $schedule['id'],
            ]);

            $booked = (int) $bookedStmt->fetchColumn();
            $remaining = (int) $schedule['max_slots'] - $booked;

            if ($remaining <= 0) {
                continue;
            }

            $scheduleStart = substr($schedule['start_time'], 0, 5);
            $scheduleEnd = substr($schedule['end_time'], 0, 5);

            foreach (array_keys($slots) as $timeValue) {

                if (
                    $timeValue >= $scheduleStart &&
                    $timeValue < $scheduleEnd
                ) {
                    $slots[$timeValue] = true;
                }
            }
        }

    } catch (PDOException $e) {

        error_log('CareSched schedule slot lookup error: ' . $e->getMessage());
    }
}

echo json_encode(['slots' => $slots]);
