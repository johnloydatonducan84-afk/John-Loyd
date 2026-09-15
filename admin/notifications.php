```php
<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

ensure_role('admin');

/*
|--------------------------------------------------------------------------
| CARESCHED - ADMIN NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| HELPER: SAFE ESCAPE
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| CHECK NOTIFICATIONS TABLE
|--------------------------------------------------------------------------
*/

try {
    $columnsStmt = $pdo->query(
        "SHOW COLUMNS FROM notifications"
    );

    $columns = $columnsStmt->fetchAll(
        PDO::FETCH_COLUMN
    );

} catch (PDOException $e) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            background:#fff5f5;
            color:#b42318;
            min-height:100vh;
        '>
            <h2>Notifications Table Error</h2>

            <p>
                The <strong>notifications</strong> table
                does not exist or could not be accessed.
            </p>

            <p>" . e($e->getMessage()) . "</p>
        </div>
    ");
}

/*
|--------------------------------------------------------------------------
| DETECT READ COLUMN
|--------------------------------------------------------------------------
*/

$readColumn = null;

if (in_array('is_read', $columns, true)) {

    $readColumn = 'is_read';

} elseif (in_array('read_status', $columns, true)) {

    $readColumn = 'read_status';

} elseif (in_array('status', $columns, true)) {

    $readColumn = 'status';
}

/*
|--------------------------------------------------------------------------
| DETECT CREATED COLUMN
|--------------------------------------------------------------------------
*/

$createdColumn = null;

if (in_array('created_at', $columns, true)) {

    $createdColumn = 'created_at';

} elseif (in_array('date_created', $columns, true)) {

    $createdColumn = 'date_created';

} elseif (in_array('created', $columns, true)) {

    $createdColumn = 'created';
}

/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | MARK ONE AS READ
    |--------------------------------------------------------------------------
    */

    if ($action === 'mark_read') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0 && $readColumn !== null) {

            try {

                if (
                    $readColumn === 'is_read' ||
                    $readColumn === 'read_status'
                ) {

                    $stmt = $pdo->prepare("
                        UPDATE notifications
                        SET `$readColumn` = 1
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        'id' => $id
                    ]);

                } elseif ($readColumn === 'status') {

                    $stmt = $pdo->prepare("
                        UPDATE notifications
                        SET status = 'read'
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        'id' => $id
                    ]);
                }

            } catch (PDOException $e) {

                $error = 'Unable to mark notification as read.';
            }
        }

        header('Location: notifications.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | MARK ALL AS READ
    |--------------------------------------------------------------------------
    */

    if ($action === 'mark_all_read') {

        if ($readColumn !== null) {

            try {

                if (
                    $readColumn === 'is_read' ||
                    $readColumn === 'read_status'
                ) {

                    $stmt = $pdo->prepare("
                        UPDATE notifications
                        SET `$readColumn` = 1
                        WHERE `$readColumn` = 0
                    ");

                    $stmt->execute();

                } elseif ($readColumn === 'status') {

                    $stmt = $pdo->prepare("
                        UPDATE notifications
                        SET status = 'read'
                        WHERE status <> 'read'
                    ");

                    $stmt->execute();
                }

            } catch (PDOException $e) {

                $error = 'Unable to mark notifications as read.';
            }
        }

        header('Location: notifications.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM notifications
                    WHERE id = :id
                ");

                $stmt->execute([
                    'id' => $id
                ]);

            } catch (PDOException $e) {

                $error = 'Unable to delete notification.';
            }
        }

        header('Location: notifications.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$filter = $_GET['filter'] ?? 'all';

if (!in_array($filter, ['all', 'unread'], true)) {

    $filter = 'all';
}

/*
|--------------------------------------------------------------------------
| BUILD QUERY
|--------------------------------------------------------------------------
*/

$where = '';

if (
    $filter === 'unread' &&
    $readColumn !== null
) {

    if (
        $readColumn === 'is_read' ||
        $readColumn === 'read_status'
    ) {

        $where = "WHERE `$readColumn` = 0";

    } elseif ($readColumn === 'status') {

        $where = "WHERE status <> 'read'";
    }
}

/*
|--------------------------------------------------------------------------
| FETCH NOTIFICATIONS
|--------------------------------------------------------------------------
*/

try {

    $query = "
        SELECT *
        FROM notifications
        $where
    ";

    if ($createdColumn !== null) {

        $query .= "
            ORDER BY `$createdColumn` DESC
        ";

    } else {

        $query .= "
            ORDER BY id DESC
        ";
    }

    $query .= " LIMIT 50";

    $stmt = $pdo->prepare($query);
    $stmt->execute();

    $notifications = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            background:#fff5f5;
            color:#b42318;
            min-height:100vh;
        '>

            <h2>Notification Query Error</h2>

            <p>" . e($e->getMessage()) . "</p>

        </div>
    ");
}

/*
|--------------------------------------------------------------------------
| UNREAD COUNT
|--------------------------------------------------------------------------
*/

$unread_count = 0;

if ($readColumn !== null) {

    try {

        if (
            $readColumn === 'is_read' ||
            $readColumn === 'read_status'
        ) {

            $stmt = $pdo->query("
                SELECT COUNT(*)
                FROM notifications
                WHERE `$readColumn` = 0
            ");

            $unread_count = (int)
                $stmt->fetchColumn();

        } elseif ($readColumn === 'status') {

            $stmt = $pdo->query("
                SELECT COUNT(*)
                FROM notifications
                WHERE status <> 'read'
            ");

            $unread_count = (int)
                $stmt->fetchColumn();
        }

    } catch (PDOException $e) {

        $unread_count = 0;
    }
}

/*
|--------------------------------------------------------------------------
| TOTAL COUNT
|--------------------------------------------------------------------------
*/

$total_count = 0;

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
    ");

    $total_count = (int)
        $stmt->fetchColumn();

} catch (PDOException $e) {

    $total_count = count($notifications);
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function notification_is_unread(
    array $notification,
    ?string $readColumn
): bool {

    if ($readColumn === null) {
        return false;
    }

    if (
        $readColumn === 'is_read' ||
        $readColumn === 'read_status'
    ) {

        return (int)
            ($notification[$readColumn] ?? 0) === 0;
    }

    if ($readColumn === 'status') {

        return strtolower(
            (string)
            ($notification['status'] ?? '')
        ) !== 'read';
    }

    return false;
}

function notification_title(
    array $notification
): string {

    if (
        isset($notification['title']) &&
        trim($notification['title']) !== ''
    ) {

        return (string)
            $notification['title'];
    }

    if (
        isset($notification['subject']) &&
        trim($notification['subject']) !== ''
    ) {

        return (string)
            $notification['subject'];
    }

    return 'Notification';
}

function notification_message(
    array $notification
): string {

    if (
        isset($notification['message']) &&
        trim($notification['message']) !== ''
    ) {

        return (string)
            $notification['message'];
    }

    if (
        isset($notification['body']) &&
        trim($notification['body']) !== ''
    ) {

        return (string)
            $notification['body'];
    }

    return 'No message available.';
}

function notification_date(
    array $notification
): string {

    $date =
        $notification['created_at']
        ?? $notification['date_created']
        ?? $notification['created']
        ?? null;

    if (!$date) {
        return 'Date not available';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return (string) $date;
    }

    return date(
        'M d, Y • h:i A',
        $timestamp
    );
}

function notification_icon(
    array $notification
): string {

    $text = strtolower(
        notification_title($notification)
        . ' '
        . notification_message($notification)
    );

    if (
        str_contains($text, 'appointment') ||
        str_contains($text, 'schedule')
    ) {

        return 'fa-calendar-check';
    }

    if (
        str_contains($text, 'approved') ||
        str_contains($text, 'approve')
    ) {

        return 'fa-circle-check';
    }

    if (
        str_contains($text, 'cancel')
    ) {

        return 'fa-calendar-xmark';
    }

    if (
        str_contains($text, 'patient') ||
        str_contains($text, 'register')
    ) {

        return 'fa-user-plus';
    }

    if (
        str_contains($text, 'email')
    ) {

        return 'fa-envelope';
    }

    return 'fa-bell';
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

    <title>
        Notifications | CareSched Admin
    </title>

    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Font Awesome -->

    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
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

        :root {

            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --navy: #0f172a;
            --navy-2: #172554;
            --sidebar-width: 265px;
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #172033;
            --muted: #7b8798;
            --border: #e7ebf2;
            --success: #16a34a;
            --danger: #dc2626;

        }

        html {
            scroll-behavior: smooth;
        }

        body {

            margin: 0;
            background: #f5f7fb;
            color: var(--text);

            font-family:
                'Inter',
                sans-serif;

            min-height: 100vh;
        }

        a {
            text-decoration: none;
        }

        button,
        a {

            -webkit-tap-highlight-color:
                transparent;
        }

        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            position: fixed;

            top: 0;
            left: 0;

            width: var(--sidebar-width);
            height: 100vh;

            padding: 20px 15px;

            background:
                linear-gradient(
                    180deg,
                    #0b1730 0%,
                    #10264a 55%,
                    #0d203e 100%
                );

            color: #fff;

            z-index: 1200;

            overflow-y: auto;

            box-shadow:
                10px 0 30px
                rgba(15,23,42,.10);

            transition:
                transform .3s ease;
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb {

            background:
                rgba(255,255,255,.12);

            border-radius: 10px;
        }

        .sidebar-brand {

            display: flex;
            align-items: center;

            gap: 12px;

            padding:
                8px 10px 22px;

            margin-bottom: 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,.08);
        }

        .brand-logo {

            width: 46px;
            height: 46px;

            border-radius: 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #06b6d4
                );

            box-shadow:
                0 8px 20px
                rgba(37,99,235,.28);

            font-size: 19px;
        }

        .brand-name {
            line-height: 1.1;
        }

        .brand-name strong {

            display: block;

            font-size: 19px;
            font-weight: 800;

            letter-spacing: -.4px;
        }

        .brand-name span {

            display: block;

            margin-top: 5px;

            font-size: 9px;

            color:
                rgba(255,255,255,.48);

            text-transform: uppercase;

            letter-spacing: 1.2px;
        }

        .sidebar-section {

            margin-top: 20px;
            margin-bottom: 8px;

            padding-left: 12px;

            color:
                rgba(255,255,255,.38);

            font-size: 9px;
            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: 1.2px;
        }

        .sidebar-link {

            position: relative;

            display: flex;

            align-items: center;

            gap: 13px;

            min-height: 46px;

            padding: 11px 13px;

            margin-bottom: 5px;

            border-radius: 12px;

            color:
                rgba(255,255,255,.68);

            font-size: 12px;

            font-weight: 600;

            transition:
                all .2s ease;
        }

        .sidebar-link i {

            width: 21px;

            text-align: center;

            font-size: 14px;

            opacity: .9;
        }

        .sidebar-link:hover {

            color: #fff;

            background:
                rgba(255,255,255,.07);

            transform:
                translateX(3px);
        }

        .sidebar-link.active {

            color: #fff;

            background:
                linear-gradient(
                    90deg,
                    rgba(37,99,235,.35),
                    rgba(37,99,235,.12)
                );

            box-shadow:
                inset 3px 0 0
                #3b82f6;
        }

        .sidebar-link.active i {
            color: #60a5fa;
        }

        .notification-badge {

            margin-left: auto;

            min-width: 20px;
            height: 20px;

            padding: 0 6px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border-radius: 20px;

            background: #ef4444;

            color: #fff;

            font-size: 9px;

            font-weight: 800;
        }

        .sidebar-footer {

            margin-top: 25px;

            padding-top: 14px;

            border-top:
                1px solid
                rgba(255,255,255,.08);
        }

        .logout-link {
            color:
                rgba(255,180,180,.82);
        }

        .logout-link:hover {

            color: #fff;

            background:
                rgba(220,38,38,.18);
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main {

            margin-left: var(--sidebar-width);

            min-height: 100vh;

            transition:
                margin-left .3s ease;
        }

        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {

            position: sticky;

            top: 0;

            z-index: 800;

            height: 76px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 34px;

            background:
                rgba(255,255,255,.94);

            backdrop-filter:
                blur(15px);

            -webkit-backdrop-filter:
                blur(15px);

            border-bottom:
                1px solid
                var(--border);
        }

        .menu-toggle {

            width: 42px;
            height: 42px;

            display: none;

            align-items: center;
            justify-content: center;

            border: 0;

            border-radius: 11px;

            background: #eef4ff;

            color: var(--primary);

            font-size: 16px;
        }

        .page-heading h1 {

            margin: 0;

            font-size: 20px;

            font-weight: 800;

            letter-spacing: -.4px;
        }

        .page-heading p {

            margin: 4px 0 0;

            color: var(--muted);

            font-size: 10px;
        }

        .admin-profile {

            display: flex;

            align-items: center;

            gap: 10px;
        }

        .admin-avatar {

            width: 41px;
            height: 41px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 12px;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #dbeafe
                );

            color: var(--primary);

            border:
                1px solid
                #dbeafe;
        }

        .admin-info strong {

            display: block;

            font-size: 11px;

            font-weight: 800;
        }

        .admin-info span {

            display: block;

            margin-top: 2px;

            color: var(--muted);

            font-size: 9px;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content {

            padding:
                30px 34px 45px;
        }

        .welcome-card {

            position: relative;

            overflow: hidden;

            padding: 25px;

            margin-bottom: 22px;

            border-radius: 18px;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e3a8a
                );

            color: #fff;

            box-shadow:
                0 12px 35px
                rgba(15,23,42,.12);
        }

        .welcome-card::before {

            content: "";

            position: absolute;

            width: 190px;
            height: 190px;

            right: -60px;
            top: -80px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.07);
        }

        .welcome-card::after {

            content: "";

            position: absolute;

            width: 120px;
            height: 120px;

            right: 90px;
            bottom: -75px;

            border-radius: 50%;

            background:
                rgba(96,165,250,.13);
        }

        .welcome-content {

            position: relative;

            z-index: 2;
        }

        .welcome-content small {

            display: block;

            margin-bottom: 7px;

            color:
                rgba(255,255,255,.58);

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 1.2px;
        }

        .welcome-content h2 {

            margin: 0;

            font-size: 22px;

            font-weight: 800;

            letter-spacing: -.5px;
        }

        .welcome-content p {

            max-width: 600px;

            margin: 7px 0 0;

            color:
                rgba(255,255,255,.65);

            font-size: 10px;

            line-height: 1.7;
        }

        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stat-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

            margin-bottom: 22px;
        }

        .stat-card {

            display: flex;

            align-items: center;

            gap: 14px;

            padding: 18px;

            background: #fff;

            border:
                1px solid
                var(--border);

            border-radius: 16px;

            box-shadow:
                0 5px 22px
                rgba(15,23,42,.035);
        }

        .stat-icon {

            width: 44px;
            height: 44px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 13px;

            background: #eff6ff;

            color: var(--primary);

            font-size: 16px;
        }

        .stat-icon.red {

            background: #fff1f2;

            color: #e11d48;
        }

        .stat-icon.green {

            background: #ecfdf5;

            color: #059669;
        }

        .stat-text span {

            display: block;

            color: var(--muted);

            font-size: 9px;

            font-weight: 600;
        }

        .stat-text strong {

            display: block;

            margin-top: 2px;

            font-size: 20px;

            font-weight: 800;
        }

        /* =====================================================
           TOOLBAR
        ===================================================== */

        .toolbar {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 14px;

            flex-wrap: wrap;
        }

        .filter-tabs {

            display: flex;

            gap: 7px;
        }

        .filter-tab {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 9px 14px;

            border:
                1px solid
                var(--border);

            border-radius: 11px;

            background: #fff;

            color: #64748b;

            font-size: 10px;

            font-weight: 700;

            transition:
                all .2s ease;
        }

        .filter-tab:hover {

            color: var(--primary);

            border-color:
                #bfdbfe;

            background: #f8fbff;
        }

        .filter-tab.active {

            color: #fff;

            border-color:
                transparent;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #0ea5e9
                );

            box-shadow:
                0 6px 16px
                rgba(37,99,235,.18);
        }

        .filter-count {

            min-width: 18px;

            height: 18px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 0 5px;

            border-radius: 20px;

            background: #ef4444;

            color: #fff;

            font-size: 8px;
        }

        .mark-btn {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 9px 14px;

            border:
                1px solid
                var(--border);

            border-radius: 11px;

            background: #fff;

            color: #334155;

            font-size: 10px;

            font-weight: 700;

            transition: .2s ease;
        }

        .mark-btn:hover {

            color: var(--primary);

            border-color:
                #bfdbfe;

            background: #f8fbff;
        }

        /* =====================================================
           WARNING
        ===================================================== */

        .schema-warning {

            display: flex;

            align-items: flex-start;

            gap: 10px;

            padding: 13px 15px;

            margin-bottom: 17px;

            border:
                1px solid
                #bfdbfe;

            border-radius: 13px;

            background: #eff6ff;

            color: #1e40af;

            font-size: 10px;

            line-height: 1.6;
        }

        .schema-warning i {
            margin-top: 2px;
        }

        /* =====================================================
           NOTIFICATION CARD
        ===================================================== */

        .notification-card {

            overflow: hidden;

            background: #fff;

            border:
                1px solid
                var(--border);

            border-radius: 18px;

            box-shadow:
                0 8px 28px
                rgba(15,23,42,.045);
        }

        .notification-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 17px 21px;

            border-bottom:
                1px solid
                #eef1f5;
        }

        .notification-header h3 {

            margin: 0;

            font-size: 12px;

            font-weight: 800;
        }

        .notification-header span {

            color: var(--muted);

            font-size: 9px;
        }

        /* =====================================================
           NOTIFICATION ITEM
        ===================================================== */

        .notif-item {

            position: relative;

            display: flex;

            gap: 14px;

            padding:
                17px 21px;

            border-bottom:
                1px solid
                #f0f2f5;

            transition:
                background .2s ease,
                transform .2s ease;
        }

        .notif-item:last-child {
            border-bottom: 0;
        }

        .notif-item:hover {
            background: #fbfdff;
        }

        .notif-item.unread {

            background:
                linear-gradient(
                    90deg,
                    #f5f9ff,
                    #fff
                );
        }

        .notif-item.unread::before {

            content: "";

            position: absolute;

            left: 0;

            top: 0;
            bottom: 0;

            width: 3px;

            background:
                linear-gradient(
                    180deg,
                    #2563eb,
                    #06b6d4
                );
        }

        .notif-icon {

            width: 43px;
            height: 43px;

            display: flex;

            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 13px;

            background: #eff6ff;

            color: #2563eb;

            font-size: 14px;
        }

        .notif-item.unread .notif-icon {

            background:
                linear-gradient(
                    135deg,
                    #dbeafe,
                    #e0f2fe
                );

            color: #2563eb;
        }

        .notif-body {

            flex: 1;

            min-width: 0;
        }

        .notif-title-row {

            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 4px;

            flex-wrap: wrap;
        }

        .notif-title {

            color: #172033;

            font-size: 11px;

            font-weight: 800;

            line-height: 1.4;
        }

        .new-badge {

            display: inline-flex;

            align-items: center;

            padding: 3px 6px;

            border-radius: 20px;

            background: #dbeafe;

            color: #1d4ed8;

            font-size: 7px;

            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: .5px;
        }

        .notif-message {

            color: #718096;

            font-size: 10px;

            line-height: 1.7;

            word-break: break-word;
        }

        .notif-time {

            display: flex;

            align-items: center;

            gap: 5px;

            margin-top: 7px;

            color: #a1aab8;

            font-size: 8.5px;
        }

        .notif-actions {

            display: flex;

            align-items: flex-start;

            gap: 6px;

            flex-shrink: 0;
        }

        .action-btn {

            width: 32px;
            height: 32px;

            display: inline-flex;

            align-items: center;
            justify-content: center;

            border: 0;

            border-radius: 9px;

            font-size: 10px;

            transition:
                all .2s ease;
        }

        .action-btn.read {

            background: #ecfdf5;

            color: #059669;
        }

        .action-btn.read:hover {

            background: #059669;

            color: #fff;

            transform:
                translateY(-2px);
        }

        .action-btn.delete {

            background: #fff1f2;

            color: #e11d48;
        }

        .action-btn.delete:hover {

            background: #e11d48;

            color: #fff;

            transform:
                translateY(-2px);
        }

        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty-state {

            padding:
                75px 20px;

            text-align: center;
        }

        .empty-icon {

            width: 70px;
            height: 70px;

            display: flex;

            align-items: center;
            justify-content: center;

            margin: 0 auto 15px;

            border-radius: 20px;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #f0fdfa
                );

            color: #64748b;

            font-size: 24px;
        }

        .empty-state h4 {

            margin: 0;

            font-size: 13px;

            font-weight: 800;
        }

        .empty-state p {

            margin: 6px auto 0;

            max-width: 350px;

            color: var(--muted);

            font-size: 9px;

            line-height: 1.6;
        }

        /* =====================================================
           MOBILE OVERLAY
        ===================================================== */

        .sidebar-overlay {

            position: fixed;

            inset: 0;

            z-index: 1100;

            background:
                rgba(2,6,23,.52);

            opacity: 0;

            visibility: hidden;

            transition:
                all .3s ease;
        }

        .sidebar-overlay.show {

            opacity: 1;

            visibility: visible;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 1000px) {

            .stat-grid {

                grid-template-columns:
                    repeat(3, 1fr);
            }

            .content {

                padding:
                    25px 25px 40px;
            }

            .topbar {

                padding:
                    0 25px;
            }
        }

        @media (max-width: 850px) {

            .sidebar {

                transform:
                    translateX(-100%);
            }

            .sidebar.show {

                transform:
                    translateX(0);
            }

            .main {

                margin-left: 0;
            }

            .menu-toggle {

                display: inline-flex;
            }

            .topbar {

                height: 70px;
            }
        }

        @media (max-width: 700px) {

            .stat-grid {

                grid-template-columns:
                    1fr;
            }

            .toolbar {

                align-items: stretch;
            }

            .filter-tabs {

                width: 100%;
            }

            .filter-tab {

                flex: 1;

                justify-content: center;
            }

            .mark-form {

                width: 100%;
            }

            .mark-btn {

                width: 100%;

                justify-content: center;
            }

            .welcome-card {

                padding: 20px;
            }

            .welcome-content h2 {

                font-size: 19px;
            }
        }

        @media (max-width: 600px) {

            .content {

                padding:
                    20px 14px 35px;
            }

            .topbar {

                padding:
                    0 14px;
            }

            .admin-info {

                display: none;
            }

            .page-heading h1 {

                font-size: 17px;
            }

            .page-heading p {

                font-size: 8px;
            }

            .notification-header {

                padding:
                    15px;
            }

            .notif-item {

                padding:
                    15px;
            }

            .notif-icon {

                width: 38px;
                height: 38px;

                border-radius: 11px;
            }

            .notif-title {

                font-size: 10px;
            }

            .notif-message {

                font-size: 9px;
            }

            .notif-actions {

                flex-direction: column;
            }

            .action-btn {

                width: 30px;
                height: 30px;
            }
        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>


<!-- MOBILE OVERLAY -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- TOPBAR -->

    <header class="topbar">

        <div
            class="d-flex align-items-center gap-3"
        >

            <button
                type="button"
                class="menu-toggle"
                id="menuToggle"
                aria-label="Open menu"
            >

                <i class="fa-solid fa-bars"></i>

            </button>


            <div class="page-heading">

                <h1>
                    Notifications
                </h1>

                <p>
                    Stay updated with CareSched activities
                </p>

            </div>

        </div>


        <div class="admin-profile">

            <div class="admin-avatar">

                <i class="fa-solid fa-user-shield"></i>

            </div>

            <div class="admin-info">

                <strong>
                    <?= e($_SESSION['username'] ?? 'Administrator') ?>
                </strong>

                <span>
                    <?= e($_SESSION['email'] ?? 'RHU Arakan') ?>
                </span>

            </div>

        </div>

    </header>


    <!-- CONTENT -->

    <section class="content">


        <!-- WELCOME -->

        <div class="welcome-card">

            <div class="welcome-content">

                <small>
                    CareSched Notification Center
                </small>

                <h2>
                    Notification Center
                </h2>

                <p>

                    Monitor appointment updates,
                    patient activities, system alerts,
                    and other important notifications
                    from the Rural Health Unit of Arakan.

                </p>

            </div>

        </div>


        <!-- WARNING -->

        <?php if ($readColumn === null): ?>

            <div class="schema-warning">

                <i class="fa-solid fa-circle-info"></i>

                <div>

                    <strong>
                        Read status is unavailable.
                    </strong>

                    Your
                    <code>notifications</code>
                    table does not currently contain
                    <code>is_read</code>,
                    <code>read_status</code>,
                    or a compatible
                    <code>status</code>
                    column.

                    Notifications can still be displayed
                    and deleted, but read/unread tracking
                    is disabled.

                </div>

            </div>

        <?php endif; ?>


        <!-- STATS -->

        <div class="stat-grid">


            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-bell"></i>

                </div>

                <div class="stat-text">

                    <span>
                        Total Notifications
                    </span>

                    <strong>
                        <?= $total_count ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon red">

                    <i class="fa-solid fa-envelope"></i>

                </div>

                <div class="stat-text">

                    <span>
                        Unread
                    </span>

                    <strong>
                        <?= $unread_count ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon green">

                    <i class="fa-solid fa-check-double"></i>

                </div>

                <div class="stat-text">

                    <span>
                        Read
                    </span>

                    <strong>

                        <?= max(
                            0,
                            $total_count - $unread_count
                        ) ?>

                    </strong>

                </div>

            </div>


        </div>


        <!-- TOOLBAR -->

        <div class="toolbar">


            <div class="filter-tabs">


                <a
                    href="notifications.php?filter=all"
                    class="filter-tab
                    <?= $filter === 'all'
                        ? 'active'
                        : '' ?>"
                >

                    <i class="fa-solid fa-layer-group"></i>

                    All

                </a>


                <?php if ($readColumn !== null): ?>

                    <a
                        href="notifications.php?filter=unread"
                        class="filter-tab
                        <?= $filter === 'unread'
                            ? 'active'
                            : '' ?>"
                    >

                        <i class="fa-solid fa-envelope"></i>

                        Unread

                        <?php if ($unread_count > 0): ?>

                            <span class="filter-count">

                                <?= $unread_count > 99
                                    ? '99+'
                                    : $unread_count ?>

                            </span>

                        <?php endif; ?>

                    </a>

                <?php endif; ?>


            </div>


            <?php if (
                $readColumn !== null &&
                $unread_count > 0
            ): ?>

                <form
                    method="post"
                    class="mark-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >

                    <button
                        type="submit"
                        class="mark-btn"
                    >

                        <i
                            class="fa-solid
                            fa-check-double"
                        ></i>

                        Mark all as read

                    </button>

                </form>

            <?php endif; ?>


        </div>


        <!-- NOTIFICATION CARD -->

        <div class="notification-card">


            <div class="notification-header">

                <h3>
                    Recent Notifications
                </h3>

                <span>
                    Showing latest 50 notifications
                </span>

            </div>


            <?php if (!empty($notifications)): ?>


                <?php foreach (
                    $notifications
                    as $notif
                ): ?>


                    <?php

                    $isUnread =
                        notification_is_unread(
                            $notif,
                            $readColumn
                        );

                    $icon =
                        notification_icon(
                            $notif
                        );

                    ?>


                    <div
                        class="notif-item
                        <?= $isUnread
                            ? 'unread'
                            : '' ?>"
                    >


                        <!-- ICON -->

                        <div class="notif-icon">

                            <i
                                class="fa-solid
                                <?= e($icon) ?>"
                            ></i>

                        </div>


                        <!-- BODY -->

                        <div class="notif-body">


                            <div class="notif-title-row">

                                <span class="notif-title">

                                    <?= e(
                                        notification_title(
                                            $notif
                                        )
                                    ) ?>

                                </span>


                                <?php if ($isUnread): ?>

                                    <span class="new-badge">
                                        New
                                    </span>

                                <?php endif; ?>


                            </div>


                            <div class="notif-message">

                                <?= nl2br(
                                    e(
                                        notification_message(
                                            $notif
                                        )
                                    )
                                ) ?>

                            </div>


                            <div class="notif-time">

                                <i
                                    class="fa-regular
                                    fa-clock"
                                ></i>

                                <?= e(
                                    notification_date(
                                        $notif
                                    )
                                ) ?>

                            </div>


                        </div>


                        <!-- ACTIONS -->

                        <div class="notif-actions">


                            <?php if (
                                $isUnread &&
                                $readColumn !== null
                            ): ?>

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_read"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)
                                            ($notif['id'] ?? 0) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="action-btn read"
                                        title="Mark as read"
                                    >

                                        <i
                                            class="fa-solid
                                            fa-check"
                                        ></i>

                                    </button>

                                </form>

                            <?php endif; ?>


                            <form
                                method="post"
                                onsubmit="
                                    return confirm(
                                        'Are you sure you want to delete this notification?'
                                    );
                                "
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)
                                        ($notif['id'] ?? 0) ?>"
                                >

                                <button
                                    type="submit"
                                    class="action-btn delete"
                                    title="Delete notification"
                                >

                                    <i
                                        class="fa-solid
                                        fa-trash"
                                    ></i>

                                </button>

                            </form>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div class="empty-state">

                    <div class="empty-icon">

                        <i
                            class="fa-regular
                            fa-bell-slash"
                        ></i>

                    </div>

                    <h4>
                        No notifications found
                    </h4>

                    <p>

                        There are currently no
                        notifications available
                        for this section.

                    </p>

                </div>


            <?php endif; ?>


        </div>


    </section>


</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

const menuToggle =
    document.getElementById('menuToggle');

const sidebar =
    document.getElementById('sidebar');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


function openSidebar() {

    if (!sidebar) return;

    sidebar.classList.add('show');

    if (sidebarOverlay) {

        sidebarOverlay.classList.add('show');
    }
}


function closeSidebar() {

    if (!sidebar) return;

    sidebar.classList.remove('show');

    if (sidebarOverlay) {

        sidebarOverlay.classList.remove('show');
    }
}


if (menuToggle) {

    menuToggle.addEventListener(
        'click',
        function () {

            if (
                sidebar &&
                sidebar.classList.contains('show')
            ) {

                closeSidebar();

            } else {

                openSidebar();
            }

        }
    );
}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener(
        'click',
        closeSidebar
    );
}


document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {

            closeSidebar();
        }

    }
);


window.addEventListener(
    'resize',
    function () {

        if (window.innerWidth > 850) {

            closeSidebar();
        }

    }
);

</script>


</body>

</html>
```
