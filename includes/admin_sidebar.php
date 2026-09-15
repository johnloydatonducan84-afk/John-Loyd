<?php
/**
 * CareSched - Shared Admin Sidebar
 *
 * Use this file on every admin page:
 * <?php require_once __DIR__ . '/../includes/admin_sidebar.php'; ?>
 */

$currentPage = basename($_SERVER['PHP_SELF']);

function sidebar_active(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'active' : '';
}
?>

<style>
/* =========================================================
   CARESCHED - SHARED ADMIN SIDEBAR
   Keep ALL sidebar styling here so every admin page is uniform.
========================================================= */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    padding: 20px 15px;
    box-sizing: border-box;
    background: linear-gradient(180deg, #081b33 0%, #0b294a 55%, #07182d 100%);
    color: #fff;
    z-index: 2000;
    overflow-y: auto;
    overflow-x: hidden;
    transition: transform .3s ease, box-shadow .3s ease;
    box-shadow: 8px 0 30px rgba(8,27,51,.10);
}

.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.16);
    border-radius: 10px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px 22px;
    margin-bottom: 18px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    color: #fff !important;
    text-decoration: none !important;
}

.brand-icon {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0d6efd, #198754);
    box-shadow: 0 8px 22px rgba(13,110,253,.25);
    font-size: 18px;
}

.brand-text,
.brand-name {
    min-width: 0;
}

.brand-text strong,
.brand-name strong {
    display: block;
    font-size: 18px;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -.3px;
}

.brand-text span,
.brand-name span {
    display: block;
    margin-top: 3px;
    color: rgba(255,255,255,.48);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.menu-label,
.sidebar-section {
    padding: 0 12px;
    margin: 16px 0 7px;
    color: rgba(255,255,255,.34);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
}

.sidebar-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 44px;
    padding: 10px 12px;
    margin: 3px 0;
    box-sizing: border-box;
    border-radius: 11px;
    color: rgba(255,255,255,.64) !important;
    text-decoration: none !important;
    font-size: 11px;
    font-weight: 600;
    transition: background .2s ease, color .2s ease, transform .2s ease;
}

.sidebar-link i {
    width: 18px;
    min-width: 18px;
    text-align: center;
    font-size: 14px;
}

.sidebar-link:hover {
    background: rgba(255,255,255,.08);
    color: #fff !important;
    transform: translateX(2px);
}

.sidebar-link.active {
    background: linear-gradient(135deg, rgba(13,110,253,.95), rgba(25,135,84,.72));
    color: #fff !important;
    box-shadow: 0 8px 20px rgba(13,110,253,.16);
}

.sidebar-link.active::before {
    content: "";
    position: absolute;
    left: -15px;
    width: 3px;
    height: 22px;
    border-radius: 0 4px 4px 0;
    background: #fff;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 18px;
    border-top: 1px solid rgba(255,255,255,.08);
}

.logout-link:hover {
    background: rgba(220,53,69,.16) !important;
    color: #ffb3ba !important;
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
    background: #dc3545;
    color: #fff;
    font-size: 9px;
    font-weight: 800;
}

/* Pages already use .main; this keeps the shared sidebar aligned. */
.main {
    margin-left: 260px;
    min-height: 100vh;
    transition: margin-left .3s ease;
}

@media (max-width: 991px) {
    .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
    }

    .sidebar.open {
        transform: translateX(0);
        box-shadow: 8px 0 30px rgba(8,27,51,.18);
    }

    .main {
        margin-left: 0 !important;
    }

    .menu-toggle {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
    }
}
</style>

<aside class="sidebar" id="sidebar">

    <a href="dashboard.php" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <div class="brand-text">
            <strong>CareSched</strong>
            <span>RHU Arakan Admin</span>
        </div>
    </a>

    <div class="menu-label">Main Menu</div>

    <a href="dashboard.php" class="sidebar-link <?= sidebar_active('dashboard.php', $currentPage) ?>">
        <i class="fa-solid fa-table-columns"></i>
        <span>Dashboard</span>
    </a>

    <a href="appointments.php" class="sidebar-link <?= sidebar_active('appointments.php', $currentPage) ?>">
        <i class="fa-solid fa-calendar-check"></i>
        <span>Appointments</span>
    </a>

    <a href="patients.php" class="sidebar-link <?= sidebar_active('patients.php', $currentPage) ?>">
        <i class="fa-solid fa-users"></i>
        <span>Patients</span>
    </a>

    <div class="menu-label">Management</div>

    <a href="services.php" class="sidebar-link <?= sidebar_active('services.php', $currentPage) ?>">
        <i class="fa-solid fa-stethoscope"></i>
        <span>Services</span>
    </a>

    <a href="schedules.php" class="sidebar-link <?= sidebar_active('schedules.php', $currentPage) ?>">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Schedules</span>
    </a>

    <a href="notifications.php" class="sidebar-link <?= sidebar_active('notifications.php', $currentPage) ?>">
        <i class="fa-solid fa-bell"></i>
        <span>Notifications</span>
        <?php if (isset($unread_count) && (int)$unread_count > 0): ?>
            <span class="notification-badge">
                <?= (int)$unread_count > 99 ? '99+' : (int)$unread_count ?>
            </span>
        <?php endif; ?>
    </a>

   

    <div class="menu-label">System</div>

    <a href="settings.php" class="sidebar-link <?= sidebar_active('settings.php', $currentPage) ?>">
        <i class="fa-solid fa-gear"></i>
        <span>Settings</span>
    </a>

    <div class="sidebar-bottom">
        <a href="../logout.php"
           class="sidebar-link logout-link"
           onclick="return confirm('Are you sure you want to logout?');">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
(function () {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('menuToggle');

    if (!sidebar || !toggle) return;

    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('open');
    });

    document.addEventListener('click', function (event) {
        if (window.innerWidth <= 991 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(event.target) &&
            !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    });
})();
</script>
