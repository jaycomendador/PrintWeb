<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Get specific job if requested
$jobIdStr = $_GET['job'] ?? '';
$paid = isset($_GET['paid']);

$specificJob = null;
$specificPaymentMethod = null;
$specificPaymentStatus = null;
if ($jobIdStr) {
    $specificJob = getJobByJobId($jobIdStr);
    if (!$specificJob || $specificJob['user_id'] != $_SESSION['user_id']) {
        $specificJob = null;
    } else {
        $paymentStmt = $pdo->prepare("SELECT payment_method, payment_status FROM payments WHERE job_id = ? ORDER BY id DESC LIMIT 1");
        $paymentStmt->execute([$specificJob['id']]);
        $specificPayment = $paymentStmt->fetch() ?: null;
        $specificPaymentMethod = $specificPayment['payment_method'] ?? null;
        $specificPaymentStatus = $specificPayment['payment_status'] ?? null;
    }
}

// Get all queue jobs for this user
$stmt = $pdo->prepare("
    SELECT pj.*, p.printer_name, p.status as printer_current_status,
           latest_payment.payment_method AS latest_payment_method,
           latest_payment.payment_status AS latest_payment_status
    FROM print_jobs pj
    LEFT JOIN printers p ON pj.printer_id = p.id
    LEFT JOIN (
        SELECT pay.job_id, pay.payment_method, pay.payment_status
        FROM payments pay
        INNER JOIN (
            SELECT job_id, MAX(id) AS latest_id
            FROM payments
            GROUP BY job_id
        ) latest ON latest.latest_id = pay.id
    ) latest_payment ON latest_payment.job_id = pj.id
    WHERE pj.user_id = ? AND pj.print_status IN ('queued','waiting','printing','awaiting_payment')
    ORDER BY pj.queue_number IS NULL, pj.queue_number ASC, pj.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$queueJobs = $stmt->fetchAll();

// Global queue position (all users)
$stmt2 = $pdo->query("
    SELECT pj.*, u.full_name FROM print_jobs pj
    JOIN users u ON pj.user_id = u.id
    WHERE pj.print_status IN ('queued','waiting','printing')
    ORDER BY pj.queue_number ASC LIMIT 20
");
$globalQueue = $stmt2->fetchAll();

$pageTitle = 'Print Queue';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Print Queue<small>Step 5 of 5 — Monitor your print job</small></div>
        <div class="topbar-actions">
            <button class="btn btn-secondary btn-sm" onclick="location.reload()"><i class="fas fa-sync"></i> Refresh</button>
        </div>
    </div>

    <div class="page-content">
        <div class="steps-bar">
            <?php
            $steps = ['Upload','Settings','Summary','Payment','Queue'];
            foreach ($steps as $i => $s) {
                $cls = $i < 4 ? 'completed' : 'active';
                echo "<div class='step-item {$cls}'><div class='step-circle'>" . ($i < 4 ? '<i class="fas fa-check" style="font-size:.7rem"></i>' : ($i+1)) . "</div><div class='step-label'>{$s}</div></div>";
            }
            ?>
        </div>

        <?php if ($paid && $specificJob): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Payment Successful!</strong> Your print job has been added to the print queue.
                Your queue number is <strong>#<?= $specificJob['queue_number'] ?></strong>.
            </div>
        </div>
        <?php endif; ?>

        <!-- Specific Job Status -->
        <?php if ($specificJob): ?>
        <div class="card fade-in" style="margin-bottom:20px;<?= $specificJob['print_status'] === 'printing' ? 'border:2px solid var(--primary)' : '' ?>">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-print"></i>
                    Job: <code><?= h($specificJob['job_id']) ?></code>
                </div>
                <?= printStatusBadge($specificJob['print_status']) ?>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">
                    <div style="text-align:center;padding:14px;background:var(--gray-50);border-radius:8px">
                        <div class="queue-number" style="margin:0 auto 8px"><?= $specificJob['print_status'] === 'awaiting_payment' ? '—' : (int)$specificJob['queue_number'] ?></div>
                        <div style="font-size:.75rem;color:var(--gray-400)"><?= $specificJob['print_status'] === 'awaiting_payment' ? 'Assigned after payment' : 'Queue Number' ?></div>
                    </div>
                    <div style="text-align:center;padding:14px;background:var(--gray-50);border-radius:8px">
                        <div style="font-size:1.1rem;font-weight:700;color:var(--gray-800);margin-bottom:4px"><?= h($specificJob['original_file_name'] ?? '') ?></div>
                        <div style="font-size:.75rem;color:var(--gray-400)">Document</div>
                    </div>
                    <div style="text-align:center;padding:14px;background:var(--gray-50);border-radius:8px">
                        <div style="font-size:1.1rem;font-weight:700;color:var(--gray-800);margin-bottom:4px">
                            <?= $specificJob['selected_pages_count'] ?> × <?= $specificJob['copies'] ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--gray-400)">Pages × Copies</div>
                    </div>
                </div>

                <?php if ($specificJob['print_status'] === 'printing'): ?>
                <div style="margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <span style="font-size:.85rem;font-weight:600;color:var(--gray-700)">Printing Progress</span>
                        <span style="font-size:.85rem;font-weight:600;color:var(--primary)">Page <?= $specificJob['current_page'] ?> / <?= (int)$specificJob['selected_pages_count'] * (int)$specificJob['copies'] ?></span>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-bar" style="width:<?= ((int)$specificJob['selected_pages_count'] * (int)$specificJob['copies']) > 0 ? round(($specificJob['current_page'] / ((int)$specificJob['selected_pages_count'] * (int)$specificJob['copies'])) * 100) : 0 ?>%"></div>
                    </div>
                    <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px">
                        Printer: <?= h($specificJob['printer_name'] ?? 'Assigned') ?>
                    </div>
                </div>

                <div class="led-indicator led-yellow" style="margin-bottom:8px">
                    <span class="led-dot"></span> Printing in progress...
                </div>
                <div class="alert alert-info" style="margin:0">
                    <i class="fas fa-print"></i>
                    <div>Your document is now being printed. Please wait near the printer.</div>
                </div>

                <?php elseif ($specificJob['print_status'] === 'completed'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Print Completed!</strong> Your print job has been completed successfully. Please collect your document from the printer.</div>
                </div>

                <?php elseif (in_array($specificJob['print_status'], ['queued','waiting'])): ?>
                <div class="led-indicator led-blue" style="margin-bottom:12px">
                    <span class="led-dot"></span> Your job is queued and waiting for a printer
                </div>
                <div style="padding:14px;background:var(--info-light);border-radius:8px;font-size:.85rem;color:var(--info)">
                    <i class="fas fa-info-circle"></i>
                    Your print job is in the queue. We will notify you when printing starts.
                </div>
                <?php elseif ($specificJob['print_status'] === 'awaiting_payment'): ?>
                    <?php if ($specificPaymentMethod === 'cash' && $specificPaymentStatus === 'pending'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-money-bill-wave"></i>
                            <div><strong>Cash payment awaiting confirmation.</strong> Pay at the print counter. An administrator will confirm receipt before assigning your queue number.</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-credit-card"></i>
                            <div>
                                <strong>Payment required.</strong> This job will enter the print queue after payment is confirmed.
                                <a href="<?= BASE_URL ?>/user/payment.php?job=<?= urlencode($specificJob['job_id']) ?>">Choose online simulation or cash payment</a>.
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($specificJob['printer_name']): ?>
                <div style="margin-top:12px;padding:10px 14px;background:var(--gray-50);border-radius:8px;font-size:.8rem;color:var(--gray-600)">
                    <i class="fas fa-print"></i> Assigned to: <strong><?= h($specificJob['printer_name']) ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="user-queue-layout<?= empty($globalQueue) ? ' user-queue-layout--solo' : '' ?>">
            <!-- My Queue Jobs -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-list-ol"></i> My Print Jobs</div>
                    <span class="badge badge-info"><?= count($queueJobs) ?> job(s)</span>
                </div>
                <div class="table-wrapper">
                    <?php if (empty($queueJobs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle" style="color:var(--success)"></i>
                            <h3>No active jobs</h3>
                            <p>No jobs are currently active in the queue.</p>
                        </div>
                    <?php else: ?>
                    <table class="user-queue-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Document</th>
                                <th>Pages</th>
                                <th>Print Status</th>
                                <th>Payment</th>
                                <th>Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($queueJobs as $qj): ?>
                            <tr <?= $specificJob && $qj['id'] === $specificJob['id'] ? 'style="background:var(--primary-subtle)"' : '' ?>>
                                <td>
                                    <div class="queue-number"><?= $qj['print_status'] === 'awaiting_payment' ? '—' : (int)$qj['queue_number'] ?></div>
                                </td>
                                <td class="user-queue-document">
                                    <div class="user-queue-file-name" title="<?= h($qj['original_file_name']) ?>"><?= h($qj['original_file_name']) ?></div>
                                    <div class="user-queue-job-id"><?= h($qj['job_id']) ?></div>
                                </td>
                                <td><?= $qj['selected_pages_count'] ?> × <?= $qj['copies'] ?></td>
                                <td><?= printStatusBadge($qj['print_status']) ?></td>
                                <td><?= paymentStatusBadge($qj['payment_status']) ?></td>
                                <td class="user-queue-time"><?= formatDateTime($qj['submitted_at'], 'h:i A') ?></td>
                                <td class="user-queue-action">
                                    <?php if ($qj['payment_status'] === 'pending' && $qj['print_status'] === 'awaiting_payment'): ?>
                                        <?php if ($qj['latest_payment_method'] === 'cash' && $qj['latest_payment_status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Awaiting cash confirmation</span>
                                        <?php endif; ?>
                                        <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/user/payment.php?job=<?= urlencode($qj['job_id']) ?>">
                                            <i class="fas fa-credit-card"></i> <?= $qj['latest_payment_method'] === 'cash' && $qj['latest_payment_status'] === 'pending' ? 'Change Payment' : 'Pay Now' ?>
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/user/queue.php?job=<?= urlencode($qj['job_id']) ?>">Track</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($globalQueue)): ?>
            <!-- Global Queue -->
            <div class="card fade-in">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-globe"></i> Overall Queue</div>
                </div>
                <div class="card-body" style="padding:0">
                    <?php foreach ($globalQueue as $gj): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--gray-100)">
                            <div class="queue-number" style="width:28px;height:28px;font-size:.75rem"><?= $gj['queue_number'] ?></div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($gj['original_file_name']) ?></div>
                                <div style="font-size:.7rem;color:var(--gray-400)"><?= h($gj['full_name']) ?></div>
                            </div>
                            <?= printStatusBadge($gj['print_status']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
// Auto-refresh every 15 seconds if there are active jobs
<?php if (!empty($queueJobs)): ?>
setTimeout(() => location.reload(), 15000);
<?php endif; ?>
</script>
