<?php
/**
 * Admin Sidebar Include
 * Smart Printing Payment System
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

function adminNavItem(string $href, string $icon, string $label, string $current, array $matches = []): string {
    $page = basename($href, '.php');
    $isActive = $page === $current || in_array($current, $matches);
    $class = 'nav-item' . ($isActive ? ' active' : '');
    $base = BASE_URL . '/admin/';
    return "<a href=\"{$base}{$href}\" class=\"{$class}\"><i class=\"fas {$icon}\"></i> {$label}</a>";
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon" style="background:#7c3aed"><i class="fas fa-shield-alt"></i></div>
            <div>
                <div style="font-size:.9rem;font-weight:700;color:#fff">PrintWeb</div>
                <div style="font-size:.62rem;color:rgba(255,255,255,.4)">Admin Panel</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <?= adminNavItem('dashboard.php', 'fa-tachometer-alt', 'Dashboard', $currentPage) ?>
        <?= adminNavItem('queue.php', 'fa-list-ol', 'Print Queue', $currentPage) ?>

        <div class="nav-section-label">Management</div>
        <?= adminNavItem('users.php', 'fa-users', 'User Management', $currentPage) ?>
        <?= adminNavItem('printers.php', 'fa-print', 'Printer Management', $currentPage) ?>
        <?= adminNavItem('payments.php', 'fa-wallet', 'Payments', $currentPage) ?>
        <?= adminNavItem('print_history.php', 'fa-history', 'Print History', $currentPage) ?>

        <div class="nav-section-label">Reports & Settings</div>
        <?= adminNavItem('reports.php', 'fa-chart-bar', 'Reports', $currentPage) ?>
        <?= adminNavItem('notifications.php', 'fa-bell', 'Notifications', $currentPage) ?>
        <?= adminNavItem('settings.php', 'fa-cog', 'System Settings', $currentPage) ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar" style="background:#7c3aed">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
                <div class="user-role"><?= ROLES[$_SESSION['user_role'] ?? 'admin'] ?? 'Admin' ?></div>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="icon-btn" data-tooltip="Logout" style="background:rgba(255,255,255,.07);color:rgba(255,255,255,.5)">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
