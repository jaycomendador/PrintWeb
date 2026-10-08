<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

refreshEsp32ConnectionStatuses();

// Aggregate stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_prints,
        SUM(CASE WHEN print_status = 'printing' THEN 1 ELSE 0 END) as active_jobs,
        SUM(CASE WHEN print_status IN ('queued','waiting','awaiting_payment') THEN 1 ELSE 0 END) as pending_jobs,
        SUM(CASE WHEN print_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
        SUM(CASE WHEN print_status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
        SUM(CASE WHEN print_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_jobs
    FROM print_jobs
")->fetch();

$revenue = $pdo->query("SELECT SUM(amount) as total FROM payments WHERE payment_status = 'successful' AND payment_method <> 'simulation'")->fetch()['total'] ?? 0;

$printerStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'printing' THEN 1 ELSE 0 END) as printing_now,
        SUM(CASE WHEN status IN ('offline','error','disabled') THEN 1 ELSE 0 END) as offline,
        SUM(CASE WHEN paper_level < 20 THEN 1 ELSE 0 END) as low_paper
    FROM printers WHERE enabled = 1
")->fetch();

$esp32Stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN connection_status = 'connected' THEN 1 ELSE 0 END) as connected
    FROM esp32_devices
")->fetch();

$userStats = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'")->fetch()['total'] ?? 0;

// Recent jobs
$recentJobs = $pdo->query("
    SELECT pj.*, u.full_name, p.printer_name 
    FROM print_jobs pj JOIN users u ON pj.user_id = u.id 
    LEFT JOIN printers p ON pj.printer_id = p.id 
    ORDER BY pj.created_at DESC LIMIT 8
")->fetchAll();

// ESP32 devices
$esp32Devices = $pdo->query("SELECT * FROM esp32_devices ORDER BY created_at DESC")->fetchAll();

// Printers
$printers = $pdo->query("SELECT p.*, e.device_name as esp32_name, e.connection_status as esp32_conn FROM printers p LEFT JOIN esp32_devices e ON p.esp32_id = e.id WHERE p.enabled = 1 ORDER BY p.id")->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Admin Dashboard<small>System Overview</small></div>
        <div class="topbar-actions">
            <button class="btn btn-secondary btn-sm" onclick="location.reload()"><i class="fas fa-sync"></i> Refresh</button>
            <a href="<?= BASE_URL ?>/user/dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-user"></i> User View</a>
        </div>
    </div>

    <div class="page-content">
        <!-- Stats Grid -->
        <div class="grid-4" style="margin-bottom:20px">
            <div class="stat-card fade-in">
                <div class="stat-icon blue"><i class="fas fa-print"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Prints</div>
                    <div class="stat-value"><?= number_format($stats['total_prints']) ?></div>
                </div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value"><?= formatCurrency((float)$revenue) ?></div>
                </div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Active Jobs</div>
                    <div class="stat-value"><?= $stats['active_jobs'] ?></div>
                </div>
            </div>
            <div class="stat-card fade-in">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?= number_format($userStats) ?></div>
                </div>
            </div>
        </div>

        <!-- Job Status Row -->
        <div class="grid-6" style="margin-bottom:20px">
            <?php
            $jobCards = [
                ['label' => 'Pending', 'val' => $stats['pending_jobs'], 'icon' => 'fa-clock', 'color' => 'var(--warning)'],
                ['label' => 'Active', 'val' => $stats['active_jobs'], 'icon' => 'fa-spinner', 'color' => 'var(--primary)'],
                ['label' => 'Completed', 'val' => $stats['completed_jobs'], 'icon' => 'fa-check-circle', 'color' => 'var(--success)'],
                ['label' => 'Failed', 'val' => $stats['failed_jobs'], 'icon' => 'fa-exclamation-circle', 'color' => 'var(--danger)'],
                ['label' => 'Cancelled', 'val' => $stats['cancelled_jobs'], 'icon' => 'fa-ban', 'color' => 'var(--gray-400)'],
                ['label' => 'Total', 'val' => $stats['total_prints'], 'icon' => 'fa-print', 'color' => 'var(--primary)'],
            ];
            foreach ($jobCards as $jc):
            ?>
            <div class="card fade-in" style="padding:16px;text-align:center">
                <div style="font-size:.95rem;color:<?= $jc['color'] ?>;margin-bottom:4px"><i class="fas <?= $jc['icon'] ?>" aria-hidden="true"></i></div>
                <div style="font-size:1.5rem;font-weight:800;color:var(--gray-800)"><?= $jc['val'] ?></div>
                <div style="font-size:.72rem;color:var(--gray-400);margin-top:2px"><?= $jc['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;margin-bottom:20px">
            <!-- Recent Jobs Table -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list"></i> Recent Print Jobs</div>
                    <a href="<?= BASE_URL ?>/admin/queue.php" class="btn btn-secondary btn-sm">View All</a>
                </div>
                <div class="table-wrapper">
                    <?php if (empty($recentJobs)): ?>
                        <div class="empty-state"><i class="fas fa-print"></i><h3>No print jobs</h3></div>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>Job ID</th><th>User</th><th>Document</th><th>Amount</th><th>Payment</th><th>Print Status</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentJobs as $job): ?>
                            <tr>
                                <td><code style="font-size:.7rem"><?= h($job['job_id']) ?></code></td>
                                <td style="font-weight:500"><?= h($job['full_name']) ?></td>
                                <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($job['original_file_name']) ?></td>
                                <td><?= formatCurrency((float)$job['total_cost']) ?></td>
                                <td><?= paymentStatusBadge($job['payment_status']) ?></td>
                                <td><?= printStatusBadge($job['print_status']) ?></td>
                                <td style="font-size:.72rem;white-space:nowrap"><?= formatDateTime($job['created_at'], 'M d, h:i A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Panel -->
            <div style="display:flex;flex-direction:column;gap:16px">
                <!-- Printer Status -->
                <div class="card fade-in">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-print"></i> Printers</div>
                        <a href="<?= BASE_URL ?>/admin/printers.php" class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                    <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center;margin-bottom:4px">
                            <div style="padding:10px;background:var(--success-light);border-radius:8px">
                                <div style="font-weight:700;font-size:1.1rem;color:var(--success)"><?= $printerStats['available'] ?></div>
                                <div style="font-size:.65rem;color:var(--gray-400)">Available</div>
                            </div>
                            <div style="padding:10px;background:var(--warning-light);border-radius:8px">
                                <div style="font-weight:700;font-size:1.1rem;color:var(--warning)"><?= $printerStats['printing_now'] ?></div>
                                <div style="font-size:.65rem;color:var(--gray-400)">Printing</div>
                            </div>
                            <div style="padding:10px;background:var(--danger-light);border-radius:8px">
                                <div style="font-weight:700;font-size:1.1rem;color:var(--danger)"><?= $printerStats['offline'] ?></div>
                                <div style="font-size:.65rem;color:var(--gray-400)">Offline</div>
                            </div>
                        </div>
                        <?php foreach ($printers as $p):
                            $ledCls = match($p['status']) {
                                'available' => 'led-green', 'printing' => 'led-yellow', default => 'led-red'
                            };
                        ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--gray-50);border-radius:8px">
                            <div>
                                <div style="font-size:.82rem;font-weight:600"><?= h($p['printer_name']) ?></div>
                                <div style="font-size:.68rem;color:var(--gray-400)">Paper: <?= $p['paper_level'] ?>%
                                <?php if ($p['paper_level'] < 20): ?><span style="color:var(--danger)"> ⚠ LOW</span><?php endif; ?></div>
                            </div>
                            <span class="led-indicator <?= $ledCls ?>" style="padding:4px 8px;font-size:.72rem">
                                <span class="led-dot" style="width:8px;height:8px"></span>
                                <?= PRINTER_STATUSES[$p['status']]['label'] ?? ucfirst($p['status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($printerStats['low_paper'] > 0): ?>
                        <div class="alert alert-warning" style="margin:0;padding:10px;font-size:.8rem">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div><?= $printerStats['low_paper'] ?> printer(s) have low paper!</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ESP32 Status -->
                <div class="esp32-card fade-in">
                    <div class="device-id">ESP32 CONTROLLERS</div>
                    <div class="device-name">IoT Hardware Status</div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                        <span style="font-size:.8rem;color:rgba(255,255,255,.6)"><?= $esp32Stats['connected'] ?> / <?= $esp32Stats['total'] ?> connected</span>
                        <a href="<?= BASE_URL ?>/admin/printers.php#esp32-devices" style="font-size:.72rem;color:rgba(255,255,255,.5)">Manage →</a>
                    </div>
                    <?php foreach ($esp32Devices as $esp):
                        $ledStatus = $esp['led_status'] ?? 'off';
                        $isConn = $esp['connection_status'] === 'connected';
                    ?>
                    <div style="background:rgba(255,255,255,.07);border-radius:8px;padding:10px 12px;margin-bottom:8px">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                            <div>
                                <div style="font-size:.82rem;font-weight:600;color:#fff"><?= h($esp['device_name']) ?></div>
                                <div style="font-size:.68rem;color:rgba(255,255,255,.4)"><?= h($esp['device_id']) ?></div>
                            </div>
                            <span style="font-size:.72rem;padding:3px 8px;border-radius:20px;background:<?= $isConn ? 'rgba(34,197,94,.2)' : 'rgba(239,68,68,.2)' ?>;color:<?= $isConn ? 'var(--led-green)' : 'var(--led-red)' ?>">
                                <?= $isConn ? '● Connected' : '● Disconnected' ?>
                            </span>
                        </div>
                        <div class="led-display">
                            <?php foreach (['green','yellow','red','blue'] as $color): ?>
                            <div class="led-circle <?= $ledStatus === $color && $isConn ? "active-{$color}" : '' ?>" title="<?= ucfirst($color) ?> LED"></div>
                            <?php endforeach; ?>
                            <div style="margin-left:auto;font-size:.65rem;color:rgba(255,255,255,.4)">
                                <?php if ($esp['last_heartbeat']): ?>
                                Last: <?= formatDateTime($esp['last_heartbeat'], 'h:i A') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Auto-refresh every 30 seconds
setTimeout(() => location.reload(), 30000);
</script>
