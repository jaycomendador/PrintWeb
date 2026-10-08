<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$errors = $success = [];
$csrfToken = $_SESSION['csrf_token'] ?? '';
if ($csrfToken === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION['csrf_token'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $jobId = (int)($_POST['job_id'] ?? 0);

    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'This request expired. Refresh the page and try again.';
    } elseif ($jobId < 1 || !in_array($action, ['start_print', 'complete_print', 'cancel_job', 'fail_job'], true)) {
        $errors[] = 'The requested queue action is invalid.';
    } else {
        $stmt = $pdo->prepare("SELECT pj.*, p.esp32_id, p.printer_name FROM print_jobs pj LEFT JOIN printers p ON pj.printer_id = p.id WHERE pj.id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();

        if (!$job) {
            $errors[] = 'The selected print job could not be found.';
        } elseif ($job['payment_status'] !== 'successful') {
            $errors[] = 'Only paid jobs can be managed in the print queue. Confirm payment first.';
        } elseif ($action === 'start_print') {
            if (!in_array($job['print_status'], ['queued', 'waiting'], true)) {
                $errors[] = 'Only queued or waiting jobs can be started.';
            } else {
                $printerId = (int)($_POST['printer_id'] ?? 0);
                $printer = null;
                if ($printerId > 0) {
                    $printerStmt = $pdo->prepare("SELECT id, printer_name, esp32_id, status, enabled, supports_color FROM printers WHERE id = ?");
                    $printerStmt->execute([$printerId]);
                    $printer = $printerStmt->fetch() ?: null;
                    if (!$printer || !$printer['enabled'] || $printer['status'] !== 'available'
                        || ($job['print_type'] === 'color' && !$printer['supports_color'])) {
                        $errors[] = 'The selected printer is unavailable or cannot handle this job.';
                    }
                } else {
                    $printer = getAvailablePrinter($job['print_type'] === 'color');
                    $printerId = (int)($printer['id'] ?? 0);
                }

                if (!$errors) {
                    $pdo->beginTransaction();
                    $update = $pdo->prepare("
                        UPDATE print_jobs
                        SET print_status = 'printing', printer_id = ?, printing_started_at = NOW(),
                            current_page = 0, updated_at = NOW()
                        WHERE id = ? AND payment_status = 'successful' AND print_status IN ('queued','waiting')
                    ");
                    $update->execute([$printerId ?: null, $jobId]);
                    if ($update->rowCount() !== 1) {
                        $pdo->rollBack();
                        $errors[] = 'The job changed before it could be started. Refresh the queue and try again.';
                    } else {
                        if ($printerId && $printer) {
                            updatePrinterStatus($printerId, 'printing', $jobId);
                        }
                        $details = $printer
                            ? 'Assigned to ' . $printer['printer_name'] . '.'
                            : 'Started manually; no available printer or ESP32 is required for status management.';
                        notifyJobStatusChange((int)$job['user_id'], $jobId, $job['job_id'], 'printing', $details);
                        if ($printer && $printer['esp32_id']) {
                            sendEsp32Command((int)$printer['esp32_id'], 'yellow', 'beep_new_job');
                        }
                        $pdo->commit();
                        $success[] = "Job #{$job['job_id']} marked as printing.";
                    }
                }
            }
        } elseif ($action === 'complete_print') {
            if ($job['print_status'] !== 'printing') {
                $errors[] = 'Only a printing job can be marked completed.';
            } else {
                $pdo->beginTransaction();
                $update = $pdo->prepare("
                    UPDATE print_jobs SET print_status = 'completed', completed_at = NOW(),
                        current_page = selected_pages_count * copies, updated_at = NOW()
                    WHERE id = ? AND payment_status = 'successful' AND print_status = 'printing'
                ");
                $update->execute([$jobId]);
                if ($update->rowCount() !== 1) {
                    $pdo->rollBack();
                    $errors[] = 'The job changed before completion. Refresh the queue and try again.';
                } else {
                    if ($job['printer_id']) {
                        updatePrinterStatus((int)$job['printer_id'], 'available', null);
                        $pdo->prepare("UPDATE printers SET total_pages_printed = total_pages_printed + ? WHERE id = ?")
                            ->execute([(int)$job['selected_pages_count'] * (int)$job['copies'], $job['printer_id']]);
                        if ($job['esp32_id']) {
                            sendEsp32Command((int)$job['esp32_id'], 'green', 'beep_complete');
                        }
                    }
                    notifyJobStatusChange((int)$job['user_id'], $jobId, $job['job_id'], 'completed');
                    $pdo->commit();
                    $success[] = "Job #{$job['job_id']} marked as completed.";
                }
            }
        } elseif (in_array($job['print_status'], ['queued', 'waiting', 'printing'], true)) {
            $newStatus = $action === 'cancel_job' ? 'cancelled' : 'failed';
            $reason = trim((string)($_POST['reason'] ?? ''));
            $pdo->beginTransaction();
            $update = $pdo->prepare("
                UPDATE print_jobs SET print_status = ?, error_message = ?, updated_at = NOW()
                WHERE id = ? AND payment_status = 'successful' AND print_status IN ('queued','waiting','printing')
            ");
            $update->execute([$newStatus, $reason ?: null, $jobId]);
            if ($update->rowCount() !== 1) {
                $pdo->rollBack();
                $errors[] = 'The job changed before its status could be updated. Refresh the queue and try again.';
            } else {
                if ($job['printer_id'] && $job['print_status'] === 'printing') {
                    updatePrinterStatus((int)$job['printer_id'], 'available', null);
                    if ($job['esp32_id']) {
                        sendEsp32Command((int)$job['esp32_id'], 'green');
                    }
                }
                $details = $reason !== '' ? $reason : null;
                notifyJobStatusChange((int)$job['user_id'], $jobId, $job['job_id'], $newStatus, $details);
                $pdo->commit();
                $success[] = "Job #{$job['job_id']} marked as {$newStatus}.";
            }
        } else {
            $errors[] = 'This job is not in a status that can be changed from the active queue.';
        }
    }
}

$jobsQuery = "
    SELECT pj.*, u.full_name, u.role as user_role, u.email as user_email, p.printer_name, p.status as printer_status,
           latest_payment.id AS latest_payment_id,
           latest_payment.payment_method AS latest_payment_method,
           latest_payment.payment_status AS latest_payment_status
    FROM print_jobs pj
    JOIN users u ON pj.user_id = u.id
    LEFT JOIN printers p ON pj.printer_id = p.id
    LEFT JOIN (
        SELECT pay.id, pay.job_id, pay.payment_method, pay.payment_status
        FROM payments pay
        INNER JOIN (
            SELECT job_id, MAX(id) AS latest_id
            FROM payments
            GROUP BY job_id
        ) latest ON latest.latest_id = pay.id
    ) latest_payment ON latest_payment.job_id = pj.id
    WHERE pj.print_status IN ('awaiting_payment', 'queued', 'waiting', 'printing')
    ORDER BY 
        CASE 
            WHEN pj.print_status = 'printing' THEN 1
            WHEN pj.print_status = 'queued' THEN 2
            WHEN pj.print_status = 'waiting' THEN 3
            ELSE 4
        END,
        pj.queue_number ASC,
        pj.created_at ASC
";
$stmt = $pdo->query($jobsQuery);
$jobs = $stmt->fetchAll();

// Fetch Printers list
$printers = $pdo->query("SELECT * FROM printers ORDER BY printer_name ASC")->fetchAll();

// Queue stats
$queueStats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN print_status = 'printing' THEN 1 END) as in_progress,
        COUNT(CASE WHEN print_status IN ('queued','waiting') THEN 1 END) as waiting_queue,
        COUNT(CASE WHEN print_status = 'awaiting_payment' AND payment_status = 'pending' THEN 1 END) as awaiting_payment,
        COUNT(CASE WHEN print_status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 END) as completed_today,
        COUNT(CASE WHEN print_status IN ('failed', 'cancelled') AND DATE(updated_at) = CURDATE() THEN 1 END) as failed_today
    FROM print_jobs
")->fetch();

$pageTitle = 'Print Queue Management';
$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/admin-tools.css?v=2">';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout admin-tools-page queue-page">
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-toggle" id="sidebarToggle" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
                <div class="breadcrumb">
                    <span>Admin</span> <i class="fas fa-chevron-right separator"></i>
                    <span class="active">Print Queue</span>
                </div>
            </div>
            <div class="topbar-right">
                <span class="badge badge-primary"><i class="fas fa-list-alt"></i> Queue Overview</span>
            </div>
        </header>

        <div class="page-body">
            <div class="page-header flex-between mb-4">
                <div>
                    <h1 class="page-title"><i class="fas fa-list-ol text-primary"></i> Print Queue Management</h1>
                    <p class="text-muted">Manage paid print jobs and update their status. ESP32 connectivity is optional.</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['cash_confirmed'])): ?>
                <div class="alert alert-success mb-4" role="status">
                    <i class="fas fa-check-circle"></i>
                    <div>Cash receipt confirmed. The paid job is now in the print queue and can be managed below.</div>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['cash_rejected'])): ?>
                <div class="alert alert-warning mb-4" role="status">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>Cash was marked as not received. The user can choose another payment method.</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success mb-4">
                    <ul class="mb-0">
                        <?php foreach ($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Stats grid -->
            <div class="stats-grid queue-stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-warning-light"><i class="fas fa-print text-warning"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$queueStats['in_progress'] ?></div>
                        <div class="stat-label">Currently Printing</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-info-light"><i class="fas fa-clock text-info"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$queueStats['waiting_queue'] ?></div>
                        <div class="stat-label">Waiting in Queue</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-warning-light"><i class="fas fa-credit-card text-warning"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$queueStats['awaiting_payment'] ?></div>
                        <div class="stat-label">Awaiting Payment</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-success-light"><i class="fas fa-check-circle text-success"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$queueStats['completed_today'] ?></div>
                        <div class="stat-label">Completed Today</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-danger-light"><i class="fas fa-exclamation-triangle text-danger"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$queueStats['failed_today'] ?></div>
                        <div class="stat-label">Failed / Cancelled Today</div>
                    </div>
                </div>
            </div>

            <?php if ((int)$queueStats['awaiting_payment'] > 0): ?>
                <div class="alert alert-warning queue-payment-notice" role="status">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong><?= (int)$queueStats['awaiting_payment'] ?> order<?= (int)$queueStats['awaiting_payment'] === 1 ? '' : 's' ?> awaiting payment.</strong>
                        These orders are visible here, but enter the printable queue only after payment is confirmed.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Queue Table -->
            <div class="card queue-jobs-card">
                <div class="card-header flex-between">
                    <div>
                        <h3 class="card-title"><i class="fas fa-stream"></i> Print Jobs <span class="queue-job-count"><?= count($jobs) ?></span></h3>
                        <p class="queue-card-subtitle">Review job details, payment, and printing progress.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle queue-jobs-table">
                        <thead>
                            <tr>
                                <th>Queue #</th>
                                <th>Job & Document</th>
                                <th>User</th>
                                <th>Specs</th>
                                <th>Printer</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Submitted</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($jobs)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                            <h4 class="mt-3">No jobs found</h4>
                                            <p class="text-muted">There are no print jobs matching the current filter.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($jobs as $j): ?>
                                    <tr class="<?= $j['print_status'] === 'printing' ? 'table-active' : '' ?>">
                                        <td>
                                            <span class="queue-job-number<?= $j['print_status'] === 'printing' ? ' is-printing' : '' ?>">
                                                <?= $j['print_status'] === 'awaiting_payment' ? '—' : '#' . (int)$j['queue_number'] ?>
                                            </span>
                                        </td>
                                        <td class="queue-job-document">
                                            <div class="queue-job-id"><?= htmlspecialchars($j['job_id']) ?></div>
                                            <div class="queue-file-name" title="<?= htmlspecialchars($j['original_file_name']) ?>">
                                                <i class="fas fa-file-alt" aria-hidden="true"></i>
                                                <span><?= htmlspecialchars($j['original_file_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="queue-user-name"><?= htmlspecialchars($j['full_name']) ?></div>
                                            <div class="queue-user-role"><?= htmlspecialchars($j['user_role']) ?></div>
                                        </td>
                                        <td>
                                            <div class="queue-spec-badges">
                                                <span class="badge badge-info"><?= strtoupper($j['paper_size']) ?></span>
                                                <span class="badge badge-secondary"><?= $j['print_type'] === 'color' ? 'Color' : 'B&W' ?></span>
                                            </div>
                                            <div class="queue-spec-details"><?= (int)$j['selected_pages_count'] ?> pages × <?= (int)$j['copies'] ?> copies <strong><?= (int)$j['selected_pages_count'] * (int)$j['copies'] ?> total</strong></div>
                                        </td>
                                        <td>
                                            <?php if ($j['printer_name']): ?>
                                                <div class="queue-printer-name">
                                                    <i class="fas fa-print" aria-hidden="true"></i> <?= htmlspecialchars($j['printer_name']) ?>
                                                </div>
                                                <div class="queue-printer-status">
                                                    <?= printerStatusBadge($j['printer_status'] ?? 'available') ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="queue-unassigned"><i class="fas fa-minus-circle" aria-hidden="true"></i> Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= printStatusBadge($j['print_status']) ?></td>
                                        <td><?= paymentStatusBadge($j['payment_status']) ?></td>
                                        <td>
                                            <div class="queue-submitted-date"><?= formatDateTime($j['created_at'], 'M d, Y') ?></div>
                                            <div class="queue-submitted-time"><?= formatDateTime($j['created_at'], 'h:i A') ?></div>
                                        </td>
                                        <td>
                                            <div class="queue-actions">
                                                <?php if ($j['print_status'] === 'awaiting_payment'
                                                    && $j['payment_status'] === 'pending'
                                                    && $j['latest_payment_method'] === 'cash'
                                                    && $j['latest_payment_status'] === 'pending'): ?>
                                                    <span class="queue-cash-pending"><i class="fas fa-hand-holding-usd" aria-hidden="true"></i> Cash awaiting</span>
                                                    <form class="queue-cash-confirm-form" method="POST" action="<?= BASE_URL ?>/admin/payments.php" onsubmit="return confirm('Confirm that cash was received for this print job?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="confirm_cash_received">
                                                        <input type="hidden" name="payment_id" value="<?= (int)$j['latest_payment_id'] ?>">
                                                        <input type="hidden" name="return_to" value="queue">
                                                        <button type="submit" class="btn btn-sm btn-success queue-cash-confirm">
                                                            <i class="fas fa-check" aria-hidden="true"></i> Confirm Cash
                                                        </button>
                                                    </form>
                                                    <form class="queue-cash-reject-form" method="POST" action="<?= BASE_URL ?>/admin/payments.php" onsubmit="return confirm('Mark this cash payment as not received?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="reject_cash_payment">
                                                        <input type="hidden" name="payment_id" value="<?= (int)$j['latest_payment_id'] ?>">
                                                        <input type="hidden" name="return_to" value="queue">
                                                        <button type="submit" class="btn btn-sm btn-secondary queue-cash-reject">
                                                            <i class="fas fa-times" aria-hidden="true"></i> Not Received
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($j['payment_status'] === 'successful'): ?>
                                                    <a href="<?= BASE_URL ?>/admin/job_document.php?job=<?= (int)$j['id'] ?>"
                                                       class="btn btn-sm btn-outline queue-open-document" target="_blank" rel="noopener"
                                                       title="Open the uploaded document; use the browser print dialog">
                                                        <i class="fas fa-print" aria-hidden="true"></i> Open / Print
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($j['print_status'] === 'queued' || $j['print_status'] === 'waiting'): ?>
                                                    <!-- Start Print Form -->
                                                    <form method="POST" onsubmit="return confirm('Start printing Job #<?= $j['job_id'] ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="start_print">
                                                        <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary" title="Mark as Printing (hardware is optional)">
                                                            <i class="fas fa-play" aria-hidden="true"></i> Start
                                                        </button>
                                                    </form>
                                                <?php elseif ($j['print_status'] === 'printing'): ?>
                                                    <!-- Mark Complete Form -->
                                                    <form method="POST" onsubmit="return confirm('Mark Job #<?= $j['job_id'] ?> as Completed?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="complete_print">
                                                        <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Mark Completed">
                                                            <i class="fas fa-check" aria-hidden="true"></i> Finish
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if (in_array($j['print_status'], ['queued', 'waiting', 'printing'])): ?>
                                                    <!-- Cancel Form -->
                                                    <form method="POST" onsubmit="return confirm('Cancel Job #<?= $j['job_id'] ?>?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="cancel_job">
                                                        <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger queue-icon-action" title="Cancel Job" aria-label="Cancel job <?= htmlspecialchars($j['job_id']) ?>">
                                                            <i class="fas fa-times" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" onsubmit="return confirm('Mark Job #<?= $j['job_id'] ?> as failed?');">
                                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                        <input type="hidden" name="action" value="fail_job">
                                                        <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning queue-icon-action" title="Mark failed" aria-label="Mark job <?= htmlspecialchars($j['job_id']) ?> failed">
                                                            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
