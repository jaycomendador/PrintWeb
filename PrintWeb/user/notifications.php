<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Mark all as read if requested
if (isset($_GET['mark_read'])) {
    markNotificationsRead($_SESSION['user_id']);
    redirect('/user/notifications.php');
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$countStmt->execute([$_SESSION['user_id']]);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$_SESSION['user_id'], $perPage, $offset]);
$notifications = $stmt->fetchAll();

$notifIcons = [
    'payment_success'     => ['icon' => 'fa-check-circle', 'color' => 'var(--success)', 'bg' => 'var(--success-light)'],
    'job_queued'          => ['icon' => 'fa-list-ol',       'color' => 'var(--info)',    'bg' => 'var(--info-light)'],
    'printing_started'    => ['icon' => 'fa-print',         'color' => 'var(--primary)', 'bg' => 'var(--primary-subtle)'],
    'printing_completed'  => ['icon' => 'fa-check-double',  'color' => 'var(--success)', 'bg' => 'var(--success-light)'],
    'printer_error'       => ['icon' => 'fa-exclamation-triangle', 'color' => 'var(--danger)', 'bg' => 'var(--danger-light)'],
    'printer_offline'     => ['icon' => 'fa-power-off',     'color' => 'var(--gray-500)', 'bg' => 'var(--gray-100)'],
    'low_paper'           => ['icon' => 'fa-file-alt',      'color' => 'var(--warning)', 'bg' => 'var(--warning-light)'],
    'job_cancelled'       => ['icon' => 'fa-times-circle',  'color' => 'var(--danger)', 'bg' => 'var(--danger-light)'],
    'job_failed'          => ['icon' => 'fa-times-circle',  'color' => 'var(--danger)', 'bg' => 'var(--danger-light)'],
    'system'              => ['icon' => 'fa-cog',           'color' => 'var(--gray-500)', 'bg' => 'var(--gray-100)'],
];

$pageTitle = 'Notifications';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Notifications<small>Stay updated on your print jobs</small></div>
        <div class="topbar-actions">
            <a href="?mark_read=1" class="btn btn-secondary btn-sm"><i class="fas fa-check-double"></i> Mark All Read</a>
        </div>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1>Notifications</h1>
                <p><?= $total ?> notification(s) total</p>
            </div>
        </div>

        <div class="card fade-in">
            <?php if (empty($notifications)): ?>
                <div class="empty-state" style="padding:48px">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You're all caught up! Notifications about your print jobs will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n):
                    $nInfo = $notifIcons[$n['type']] ?? $notifIcons['system'];
                ?>
                <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid var(--gray-100);background:<?= !$n['is_read'] ? 'var(--primary-subtle)' : '#fff' ?>;transition:background .2s">
                    <div style="width:40px;height:40px;border-radius:50%;background:<?= $nInfo['bg'] ?>;color:<?= $nInfo['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0">
                        <i class="fas <?= $nInfo['icon'] ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                            <div style="font-weight:<?= !$n['is_read'] ? '700' : '600' ?>;font-size:.9rem;color:var(--gray-800)">
                                <?= h($n['title']) ?>
                                <?php if (!$n['is_read']): ?>
                                    <span style="display:inline-block;width:8px;height:8px;background:var(--primary);border-radius:50%;margin-left:6px;vertical-align:middle"></span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--gray-400);white-space:nowrap"><?= formatDateTime($n['created_at'], 'M d, Y h:i A') ?></div>
                        </div>
                        <div style="font-size:.85rem;color:var(--gray-600);margin-top:4px"><?= h($n['message']) ?></div>
                        <?php if ($n['job_id']): ?>
                            <a href="<?= BASE_URL ?>/user/queue.php" style="font-size:.75rem;color:var(--primary);margin-top:4px;display:inline-block">
                                View Job →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
                <a href="?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
