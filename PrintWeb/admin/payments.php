<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$errors = [];
$csrfToken = $_SESSION['csrf_token'] ?? '';
if ($csrfToken === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrfToken = $_SESSION['csrf_token'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['confirm_cash_received', 'reject_cash_payment'], true)) {
    $action = (string)$_POST['action'];
    $returnToQueue = ($_POST['return_to'] ?? '') === 'queue';
    $returnPath = $returnToQueue
        ? BASE_URL . '/admin/queue.php?cash_'
        : BASE_URL . '/admin/payments.php?cash_';
    $paymentRowId = (int)($_POST['payment_id'] ?? 0);
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'This request expired. Refresh the page and try again.';
    } elseif ($paymentRowId < 1) {
        $errors[] = 'The selected payment record is invalid.';
    } else {
        $pdo->beginTransaction();
        $cashStmt = $pdo->prepare("
            SELECT pay.id, pay.job_id, pay.user_id, pay.payment_method, pay.payment_status,
                   pj.job_id AS public_job_id, pj.payment_status AS job_payment_status,
                   pj.print_status
            FROM payments pay
            JOIN print_jobs pj ON pj.id = pay.job_id
            WHERE pay.id = ?
            FOR UPDATE
        ");
        $cashStmt->execute([$paymentRowId]);
        $cashPayment = $cashStmt->fetch();

        if (!$cashPayment || $cashPayment['payment_method'] !== 'cash'
            || $cashPayment['payment_status'] !== 'pending'
            || $cashPayment['job_payment_status'] !== 'pending'
            || $cashPayment['print_status'] !== 'awaiting_payment') {
            $pdo->rollBack();
            $errors[] = 'This cash payment is not awaiting confirmation or has already been processed.';
        } elseif ($action === 'reject_cash_payment') {
            $pdo->prepare("
                UPDATE payments
                SET payment_status = 'failed', payment_notes = 'Cash payment was not received; user may choose another payment method.',
                    processed_by = ?, updated_at = NOW()
                WHERE id = ? AND payment_status = 'pending'
            ")->execute([$_SESSION['user_id'], $paymentRowId]);
            notifyPaymentStatusChange(
                (int)$cashPayment['user_id'],
                (int)$cashPayment['job_id'],
                $cashPayment['public_job_id'],
                'failed',
                'cash'
            );
            $pdo->commit();
            header('Location: ' . $returnPath . 'rejected=1');
            exit;
        } else {
            $jobUpdate = $pdo->prepare("
                UPDATE print_jobs
                SET payment_status = 'successful', print_status = 'queued',
                    queue_number = ?, payment_at = NOW(), queued_at = NOW(), updated_at = NOW()
                WHERE id = ? AND payment_status = 'pending' AND print_status = 'awaiting_payment'
            ");
            $jobUpdate->execute([getNextQueueNumber(), $cashPayment['job_id']]);
            if ($jobUpdate->rowCount() !== 1) {
                $pdo->rollBack();
                $errors[] = 'The print job changed while confirming cash. Refresh and check its current status.';
            } else {
                $pdo->prepare("
                    UPDATE payments
                    SET payment_status = 'successful', payment_notes = 'Cash received and confirmed by administrator.',
                        processed_by = ?, paid_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND payment_status = 'pending'
                ")->execute([$_SESSION['user_id'], $paymentRowId]);

                notifyPaymentStatusChange((int)$cashPayment['user_id'], (int)$cashPayment['job_id'], $cashPayment['public_job_id'], 'successful', 'cash');
                notifyJobStatusChange((int)$cashPayment['user_id'], (int)$cashPayment['job_id'], $cashPayment['public_job_id'], 'queued', 'Cash receipt was confirmed by an administrator.');

                $deviceStmt = $pdo->query("SELECT id FROM esp32_devices WHERE connection_status = 'connected' ORDER BY id LIMIT 1");
                if ($deviceId = $deviceStmt->fetchColumn()) {
                    sendEsp32Command((int)$deviceId, 'green', 'beep_payment');
                }

                $pdo->commit();
                header('Location: ' . $returnPath . 'confirmed=1');
                exit;
            }
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$methodFilter = $_GET['method'] ?? '';
$search = trim($_GET['q'] ?? '');
$allowedStatuses = ['pending', 'processing', 'successful', 'failed', 'cancelled', 'refunded'];
$allowedMethods = ['cash', 'online', 'card', 'ewallet', 'simulation'];

$where = [];
$params = [];
if (in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = 'pay.payment_status = ?';
    $params[] = $statusFilter;
} else {
    $statusFilter = '';
}
if (in_array($methodFilter, $allowedMethods, true)) {
    $where[] = 'pay.payment_method = ?';
    $params[] = $methodFilter;
} else {
    $methodFilter = '';
}
if ($search !== '') {
    $where[] = '(pay.payment_id LIKE ? OR pj.job_id LIKE ? OR u.full_name LIKE ? OR pj.original_file_name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("
    SELECT pay.*, pj.job_id as print_job_id, pj.original_file_name, u.full_name
    FROM payments pay
    JOIN print_jobs pj ON pay.job_id = pj.id
    JOIN users u ON pay.user_id = u.id
    {$whereClause}
    ORDER BY pay.created_at DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_payments,
        SUM(CASE WHEN pay.payment_status = 'successful' THEN 1 ELSE 0 END) as successful_payments,
        SUM(CASE WHEN pay.payment_status IN ('pending','processing') THEN 1 ELSE 0 END) as pending_payments,
        SUM(CASE WHEN pay.payment_status = 'successful' AND pay.payment_method <> 'simulation' THEN pay.amount ELSE 0 END) as successful_amount
    FROM payments pay
    JOIN print_jobs pj ON pay.job_id = pj.id
    JOIN users u ON pay.user_id = u.id
    {$whereClause}
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$pageTitle = 'Payments';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
    <?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="topbar-title">Payments<small>Payment records and transaction status</small></div>
            <div class="topbar-actions">
                <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-secondary btn-sm"><i class="fas fa-chart-bar"></i> Reports</a>
            </div>
        </div>

        <div class="page-content">
            <?php if (isset($_GET['cash_confirmed'])): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><div>Cash receipt confirmed. The print job has been added to the queue.</div></div>
            <?php endif; ?>
            <?php if (isset($_GET['cash_rejected'])): ?>
                <div class="alert alert-warning"><i class="fas fa-exclamation-circle"></i><div>Cash payment marked as not received. The user can choose another payment method.</div></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?= implode('<br>', array_map('h', $errors)) ?></div></div>
            <?php endif; ?>

            <div class="grid-4" style="margin-bottom:20px">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
                    <div class="stat-info"><div class="stat-label">Matching Payments</div><div class="stat-value"><?= number_format((int)$summary['total_payments']) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info"><div class="stat-label">Successful Records</div><div class="stat-value"><?= number_format((int)$summary['successful_payments']) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                    <div class="stat-info"><div class="stat-label">Pending / Processing</div><div class="stat-value"><?= number_format((int)$summary['pending_payments']) ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-peso-sign"></i></div>
                    <div class="stat-info"><div class="stat-label">Collected Amount (excludes simulations)</div><div class="stat-value"><?= formatCurrency((float)$summary['successful_amount']) ?></div></div>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px">
                <div class="card-body">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                        <div style="flex:1;min-width:200px">
                            <label class="form-label">Search</label>
                            <input type="search" name="q" class="form-control" value="<?= h($search) ?>" placeholder="Payment ID, job ID, user, or document">
                        </div>
                        <div>
                            <label class="form-label">Payment Status</label>
                            <select name="status" class="form-select">
                                <option value="">All statuses</option>
                                <?php foreach ($allowedStatuses as $status): ?>
                                    <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Method</label>
                            <select name="method" class="form-select">
                                <option value="">All methods</option>
                                <?php foreach ($allowedMethods as $method): ?>
                                    <option value="<?= $method ?>" <?= $methodFilter === $method ? 'selected' : '' ?>><?= ucfirst($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-secondary">Clear</a>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr><th>Payment</th><th>User</th><th>Print Job</th><th>Method</th><th>Amount</th><th>Status</th><th>Paid / Created</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!$payments): ?>
                                <tr><td colspan="8"><div class="empty-state"><i class="fas fa-receipt"></i><h3>No payments found</h3><p>Adjust the filters or wait for payment records.</p></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><code><?= h($payment['payment_id']) ?></code></td>
                                        <td><?= h($payment['full_name']) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/admin/queue.php?status=all"><?= h($payment['print_job_id']) ?></a>
                                            <div style="font-size:.75rem;color:var(--gray-400)"><?= h($payment['original_file_name']) ?></div>
                                        </td>
                                        <td><?= h($payment['payment_method'] === 'simulation' ? 'Online simulation (no charge)' : ucfirst($payment['payment_method'])) ?></td>
                                        <td><strong><?= formatCurrency((float)$payment['amount']) ?></strong></td>
                                        <td><?= paymentStatusBadge($payment['payment_status']) ?></td>
                                        <td><?= formatDateTime($payment['paid_at'] ?: $payment['created_at']) ?></td>
                                        <td>
                                            <?php if ($payment['payment_method'] === 'cash' && $payment['payment_status'] === 'pending'): ?>
                                                <form method="POST" onsubmit="return confirm('Confirm that cash was received for this print job?');">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="confirm_cash_received">
                                                    <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Confirm Cash</button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('Mark this cash payment as not received?');" style="margin-top:6px">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="reject_cash_payment">
                                                    <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Not Received</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
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
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
