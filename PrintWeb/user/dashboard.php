<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user  = getCurrentUser();
$stats = getUserStats($_SESSION['user_id']);
$notifications = getUserNotifications($_SESSION['user_id'], 5);
$unreadCount   = getUnreadNotificationCount($_SESSION['user_id']);

// Current active job
$stmt = $pdo->prepare("SELECT * FROM print_jobs WHERE user_id = ? AND print_status IN ('queued','waiting','printing') ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$activeJob = $stmt->fetch();

// Recent jobs
$stmt = $pdo->prepare("SELECT pj.*, p.printer_name FROM print_jobs pj LEFT JOIN printers p ON pj.printer_id = p.id WHERE pj.user_id = ? ORDER BY pj.created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentJobs = $stmt->fetchAll();

// Printer availability
$stmt = $pdo->query("SELECT * FROM printers WHERE enabled = 1 ORDER BY status ASC");
$printers = $stmt->fetchAll();

$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">
            Dashboard
            <small>Welcome back, <?= h($user['full_name']) ?>!</small>
        </div>
        <div class="topbar-actions">
            <div style="position:relative">
                <button class="icon-btn" id="notifBtn">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?><span class="badge-dot"></span><?php endif; ?>
                </button>
                <!-- Notification Dropdown -->
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        Notifications
                        <a href="<?= BASE_URL ?>/user/notifications.php" style="font-size:.75rem;color:var(--primary)">View all</a>
                    </div>
                    <?php if (empty($notifications)): ?>
                        <div class="notif-empty"><i class="fas fa-bell-slash" style="font-size:2rem;margin-bottom:8px;display:block"></i>No notifications</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                        <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                            <div class="notif-icon" style="background:var(--primary-subtle);color:var(--primary)"><i class="fas fa-bell"></i></div>
                            <div class="notif-content">
                                <div class="notif-title"><?= h($n['title']) ?></div>
                                <div class="notif-msg"><?= h(substr($n['message'], 0, 60)) ?>...</div>
                                <div class="notif-time"><?= formatDateTime($n['created_at'], 'M d, h:i A') ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/user/profile.php" class="icon-btn"><i class="fas fa-user"></i></a>
        </div>
    </div>

    <div class="page-content">

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-<?= h($_GET['type'] ?? 'info') ?> fade-in">
                <i class="fas fa-info-circle"></i>
                <div><?= h(urldecode($_GET['msg'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Active Job Banner -->
        <?php if ($activeJob): ?>
        <div class="alert alert-info fade-in" style="margin-bottom:20px">
            <i class="fas fa-print fa-spin"></i>
            <div>
                <strong>Active Job:</strong> <?= h($activeJob['original_file_name']) ?> — 
                <?= printStatusBadge($activeJob['print_status']) ?>
                <?php if ($activeJob['print_status'] === 'printing'): ?>
                  — Page <?= $activeJob['current_page'] ?> / <?= (int)$activeJob['selected_pages_count'] * (int)$activeJob['copies'] ?>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/user/queue.php?job=<?= h($activeJob['job_id']) ?>" style="margin-left:10px;font-weight:600">Track Job →</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid-4" style="margin-bottom:24px">
            <div class="stat-card fade-in">
                <div class="stat-icon blue"><i class="fas fa-print"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Prints</div>
                    <div class="stat-value"><?= number_format($stats['total_prints'] ?? 0) ?></div>
                </div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?= number_format($stats['pending_prints'] ?? 0) ?></div>
                </div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Completed</div>
                    <div class="stat-value"><?= number_format($stats['completed_prints'] ?? 0) ?></div>
                </div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-icon purple"><i class="fas fa-peso-sign"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Spent</div>
                    <div class="stat-value"><?= formatCurrency((float)($stats['total_spent'] ?? 0)) ?></div>
                </div>
            </div>
        </div>

        <div class="user-dashboard-grid" style="display:grid;grid-template-columns:minmax(0, 2fr) minmax(0, 1fr);gap:20px;margin-bottom:24px">
            <!-- Recent Jobs -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-history"></i> Recent Print Jobs</div>
                    <a href="<?= BASE_URL ?>/user/history.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-wrapper">
                    <?php if (empty($recentJobs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-print"></i>
                            <h3>No print jobs yet</h3>
                            <p>Upload a document to get started.</p>
                        </div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Job ID</th>
                                <th>Document</th>
                                <th>Pages</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentJobs as $job): ?>
                            <tr>
                                <td><code style="font-size:.75rem"><?= h($job['job_id']) ?></code></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;min-width:0">
                                        <div style="max-width:160px;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500">
                                            <?= h($job['original_file_name']) ?>
                                        </div>
                                        <a class="btn btn-secondary btn-sm btn-icon document-view-link"
                                           href="<?= BASE_URL ?>/user/document.php?job=<?= (int)$job['id'] ?>"
                                           target="_blank" rel="noopener"
                                           title="View uploaded document"
                                           aria-label="View uploaded document <?= h($job['original_file_name']) ?>">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                                <td><?= (int)$job['selected_pages_count'] * (int)$job['copies'] ?></td>
                                <td><?= formatCurrency((float)$job['total_cost']) ?></td>
                                <td><?= printStatusBadge($job['print_status']) ?></td>
                                <td style="white-space:nowrap;font-size:.75rem;color:var(--gray-400)"><?= formatDateTime($job['created_at'], 'M d, Y') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <div class="card-footer" style="text-align:center">
                    <a href="<?= BASE_URL ?>/user/upload.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Print a Document
                    </a>
                </div>
            </div>

            <!-- Printer Status -->
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-print"></i> Printer Status</div>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:12px">
                        <?php if (empty($printers)): ?>
                            <p style="font-size:.85rem;color:var(--gray-400)">No printers configured.</p>
                        <?php else: ?>
                            <?php foreach ($printers as $printer): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--gray-50);border-radius:8px">
                                <div>
                                    <div style="font-size:.85rem;font-weight:600"><?= h($printer['printer_name']) ?></div>
                                    <div style="font-size:.72rem;color:var(--gray-400)"><?= h($printer['location'] ?? '') ?></div>
                                </div>
                                <?php
                                    $ledClass = match($printer['status']) {
                                        'available' => 'led-green',
                                        'printing'  => 'led-yellow',
                                        default     => 'led-red',
                                    };
                                    $ledLabel = PRINTER_STATUSES[$printer['status']]['label'] ?? ucfirst($printer['status']);
                                ?>
                                <span class="led-indicator <?= $ledClass ?>">
                                    <span class="led-dot"></span><?= $ledLabel ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Info Card -->
                <div class="card fade-in">
                    <div class="card-body" style="text-align:center;padding:24px">
                        <div style="width:60px;height:60px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;margin:0 auto 12px">
                            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                        </div>
                        <div style="font-weight:700;font-size:1rem"><?= h($user['full_name']) ?></div>
                        <div style="font-size:.75rem;color:var(--gray-400);margin-bottom:12px"><?= h($user['user_id']) ?></div>
                        <span class="badge badge-primary"><?= ROLES[$user['role']] ?? $user['role'] ?></span>
                        <div style="margin-top:14px">
                            <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center">
                                <i class="fas fa-user-edit"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Print Document CTA -->
        <div class="card fade-in" style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);border:none">
            <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap">
                <div style="color:#fff">
                    <h3 style="font-size:1.1rem;font-weight:700;margin-bottom:6px">Ready to print something?</h3>
                    <p style="font-size:.875rem;opacity:.8;margin:0">Upload your document, configure settings, and pay — all in minutes.</p>
                </div>
                <a href="<?= BASE_URL ?>/user/upload.php" class="btn" style="background:#fff;color:var(--primary);font-weight:700;flex-shrink:0">
                    <i class="fas fa-upload"></i> Print a Document
                </a>
            </div>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Notification dropdown toggle
document.getElementById('notifBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    document.getElementById('notifDropdown').classList.toggle('open');
});
document.addEventListener('click', function() {
    document.getElementById('notifDropdown').classList.remove('open');
});
</script>
