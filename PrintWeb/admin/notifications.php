<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$errors = $success = [];

// Handle Broadcast Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'broadcast') {
        $targetRole = $_POST['target_role'] ?? 'all';
        $title      = trim($_POST['title'] ?? '');
        $message    = trim($_POST['message'] ?? '');
        $type       = $_POST['type'] ?? 'info';

        if (empty($title) || empty($message)) {
            $errors[] = "Please provide both a notification title and message.";
        } else {
            if ($targetRole === 'all') {
                $uStmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
            } else {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
                $uStmt->execute([$targetRole]);
            }
            $targetUsers = $uStmt->fetchAll();

            $insertStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (?, ?, ?, ?)");
            $count = 0;
            foreach ($targetUsers as $u) {
                $insertStmt->execute([$u['id'], $type, $title, $message]);
                $count++;
            }
            $success[] = "Successfully sent notification to {$count} active user(s).";
        }
    }
}

// Fetch Admin's own notifications + system logs
$adminNotifications = $pdo->prepare("
    SELECT n.*, u.full_name as user_name, u.email as user_email
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 100
");
$adminNotifications->execute();
$allNotifs = $adminNotifications->fetchAll();

$pageTitle = 'System Notifications & Broadcast';
$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/admin-tools.css">';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout admin-tools-page">
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-toggle" id="sidebarToggle" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
                <div class="breadcrumb">
                    <span>Admin</span> <i class="fas fa-chevron-right separator"></i>
                    <span class="active">Notifications</span>
                </div>
            </div>
            <div class="topbar-right">
                <span class="badge badge-primary"><i class="fas fa-bullhorn"></i> Broadcast</span>
            </div>
        </header>

        <div class="page-body">
            <div class="page-header flex-between mb-4">
                <div>
                    <h1 class="page-title"><i class="fas fa-bell text-primary"></i> Notifications & Broadcasts</h1>
                    <p class="text-muted">Broadcast announcements to users and inspect notification history</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success mb-4">
                    <ul class="mb-0">
                        <?php foreach ($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="grid-2 mb-4">
                <!-- Broadcast Form Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-paper-plane text-primary"></i> Send Broadcast Announcement</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="broadcast">
                            
                            <div class="form-group mb-3">
                                <label class="form-label">Target Audience</label>
                                <select name="target_role" class="form-control">
                                    <option value="all">All Users (Students, Faculty, Staff, etc.)</option>
                                    <option value="student">Students Only</option>
                                    <option value="faculty">Faculty / Teachers Only</option>
                                    <option value="staff">Staff Only</option>
                                    <option value="other">Other Authorized Users</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Notification Type</label>
                                <select name="type" class="form-control">
                                    <option value="info">Information (Blue)</option>
                                    <option value="success">Success / Promotion (Green)</option>
                                    <option value="warning">Maintenance / Alert (Yellow)</option>
                                    <option value="danger">Urgent Notice (Red)</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Title / Headline</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g., Scheduled Maintenance this Saturday" required>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Message Content</label>
                                <textarea name="message" class="form-control" rows="4" placeholder="Enter message details for users..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-bullhorn"></i> Send Broadcast</button>
                        </form>
                    </div>
                </div>

                <!-- Recent Broadcasts & System Logs -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history text-info"></i> Recent System Notifications</h3>
                    </div>
                    <div class="table-responsive" style="max-height: 480px;">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Recipient</th>
                                    <th>Notification</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($allNotifs)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No notifications recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($allNotifs as $n): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 500;"><?= htmlspecialchars($n['user_name']) ?></div>
                                                <div style="font-size: .75rem; color: var(--text-muted);"><?= htmlspecialchars($n['user_email']) ?></div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?= htmlspecialchars($n['title']) ?></div>
                                                <div style="font-size: .8rem; color: var(--text-muted);"><?= htmlspecialchars(mb_strimwidth($n['message'], 0, 50, '...')) ?></div>
                                            </td>
                                            <td>
                                                <?= $n['is_read'] ? '<span class="badge badge-secondary">Read</span>' : '<span class="badge badge-warning">Unread</span>' ?>
                                            </td>
                                            <td style="font-size: .75rem; color: var(--text-muted);">
                                                <?= formatDateTime($n['created_at']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
