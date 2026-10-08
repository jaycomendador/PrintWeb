<?php
/**
 * User Sidebar Include
 * Smart Printing Payment System
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadCount = isLoggedIn() ? getUnreadNotificationCount($_SESSION['user_id']) : 0;

function sideNavItem(string $href, string $icon, string $label, string $current, array $matches = [], ?int $badge = null): string {
    $page = basename($href, '.php');
    $isActive = $page === $current || in_array($current, $matches);
    $class = 'nav-item' . ($isActive ? ' active' : '');
    $base = BASE_URL . '/user/';
    $badgeHtml = $badge ? '<span class="nav-badge">' . $badge . '</span>' : '';
    return "<a href=\"{$base}{$href}\" class=\"{$class}\"><i class=\"fas {$icon}\"></i> {$label}{$badgeHtml}</a>";
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon"><i class="fas fa-print"></i></div>
            <div>
                <div style="font-size:.9rem;font-weight:700;color:#fff">PrintWeb</div>
                <div style="font-size:.62rem;color:rgba(255,255,255,.4)">Smart Print System</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <?= sideNavItem('dashboard.php', 'fa-tachometer-alt', 'Dashboard', $currentPage) ?>
        <?= sideNavItem('upload.php', 'fa-upload', 'Upload Document', $currentPage) ?>
        <?= sideNavItem('queue.php', 'fa-list-ol', 'Print Queue', $currentPage) ?>

        <div class="nav-section-label">History & Account</div>
        <?= sideNavItem('history.php', 'fa-history', 'Print History', $currentPage) ?>
        <?= sideNavItem('notifications.php', 'fa-bell', 'Notifications', $currentPage, [], $unreadCount > 0 ? $unreadCount : null) ?>
        <?= sideNavItem('profile.php', 'fa-user', 'Profile', $currentPage) ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                <div class="user-role"><?= ROLES[$_SESSION['user_role'] ?? 'student'] ?? 'User' ?></div>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="icon-btn" data-tooltip="Logout" style="background:rgba(255,255,255,.07);color:rgba(255,255,255,.5)">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
