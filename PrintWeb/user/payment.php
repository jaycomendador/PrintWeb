<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$jobIdStr = $_GET['job'] ?? '';
$job = null;

if ($jobIdStr) {
    $job = getJobByJobId($jobIdStr);
    if (!$job || $job['user_id'] != $_SESSION['user_id']) {
        redirect('/user/dashboard.php');
    }
}

if (!$job) redirect('/user/dashboard.php');

$errors = [];
$paymentStmt = $pdo->prepare("SELECT payment_method, payment_status FROM payments WHERE job_id = ? ORDER BY id DESC LIMIT 1");
$paymentStmt->execute([$job['id']]);
$latestPayment = $paymentStmt->fetch() ?: null;
$cashAwaitingConfirmation = $latestPayment
    && $latestPayment['payment_method'] === 'cash'
    && $latestPayment['payment_status'] === 'pending';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// This school-project payment flow is simulated; it does not collect real money.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $job['payment_status'] === 'pending') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'This payment request expired. Refresh the page and try again.';
    } elseif (!in_array($_POST['payment_method'] ?? '', ['online', 'cash'], true)) {
        $errors[] = 'Choose either simulated online payment or cash.';
    } else {
        $selectedPaymentMethod = (string)$_POST['payment_method'];
        $pdo->beginTransaction();
        $lockStmt = $pdo->prepare("SELECT id, job_id, user_id, payment_status, total_cost FROM print_jobs WHERE id = ? AND user_id = ? FOR UPDATE");
        $lockStmt->execute([$job['id'], $_SESSION['user_id']]);
        $lockedJob = $lockStmt->fetch();

        if (!$lockedJob || $lockedJob['payment_status'] === 'successful') {
            $pdo->rollBack();
            redirect('/user/queue.php?job=' . urlencode($job['job_id']) . '&paid=1');
        }

        if ($lockedJob['payment_status'] !== 'pending') {
            $pdo->rollBack();
            $errors[] = 'This print job is no longer awaiting payment. Refresh to see its latest status.';
        } else {
            $pendingCash = $pdo->prepare("
                SELECT id FROM payments
                WHERE job_id = ? AND payment_method = 'cash' AND payment_status = 'pending'
                ORDER BY id DESC LIMIT 1 FOR UPDATE
            ");
            $pendingCash->execute([$job['id']]);
            $pendingCashPaymentId = $pendingCash->fetchColumn();
            if ($pendingCashPaymentId && $selectedPaymentMethod === 'cash') {
                $pdo->rollBack();
                redirect('/user/queue.php?job=' . urlencode($job['job_id']));
            }
            if ($pendingCashPaymentId) {
                $pdo->prepare("
                    UPDATE payments
                    SET payment_status = 'cancelled',
                        payment_notes = 'User changed from cash to another payment method.',
                        updated_at = NOW()
                    WHERE id = ? AND payment_status = 'pending'
                ")->execute([$pendingCashPaymentId]);
                notifyPaymentStatusChange(
                    (int)$_SESSION['user_id'],
                    (int)$job['id'],
                    $job['job_id'],
                    'cancelled',
                    'cash'
                );
            }

            if ($selectedPaymentMethod === 'cash') {
                $paymentId = generatePaymentId();
                $insertPayment = $pdo->prepare("
                    INSERT INTO payments
                        (payment_id, job_id, user_id, amount, payment_method, payment_status, payment_notes)
                    VALUES (?, ?, ?, ?, 'cash', 'pending', 'Cash payment awaiting administrator confirmation.')
                ");
                $insertPayment->execute([
                    $paymentId,
                    $job['id'],
                    $_SESSION['user_id'],
                    $lockedJob['total_cost'],
                ]);
                notifyPaymentStatusChange((int)$_SESSION['user_id'], (int)$job['id'], $job['job_id'], 'pending', 'cash');
                $pdo->commit();
                redirect('/user/queue.php?job=' . urlencode($job['job_id']));
            } else {
                $paymentId = generatePaymentId();
                $transactionReference = 'SIM-' . strtoupper(bin2hex(random_bytes(8)));
                $insertPayment = $pdo->prepare("
                    INSERT INTO payments
                        (payment_id, job_id, user_id, amount, payment_method, payment_status,
                         transaction_reference, payment_notes, paid_at)
                    VALUES (?, ?, ?, ?, 'simulation', 'successful', ?, 'Simulated payment; no real charge was made.', NOW())
                ");
                $insertPayment->execute([
                    $paymentId,
                    $job['id'],
                    $_SESSION['user_id'],
                    $lockedJob['total_cost'],
                    $transactionReference,
                ]);

                $pdo->prepare("
                    UPDATE print_jobs
                    SET payment_status = 'successful', print_status = 'queued',
                        queue_number = ?, payment_at = NOW(), queued_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND payment_status = 'pending'
                ")->execute([getNextQueueNumber(), $job['id']]);

                notifyPaymentStatusChange((int)$_SESSION['user_id'], (int)$job['id'], $job['job_id'], 'successful', 'simulation');
                notifyJobStatusChange((int)$_SESSION['user_id'], (int)$job['id'], $job['job_id'], 'queued', 'No real money was charged.');

                $deviceStmt = $pdo->query("
                    SELECT d.id FROM esp32_devices d
                    WHERE d.connection_status = 'connected'
                    ORDER BY d.id LIMIT 1
                ");
                if ($deviceId = $deviceStmt->fetchColumn()) {
                    sendEsp32Command((int)$deviceId, 'green', 'beep_payment');
                }

                $pdo->commit();
                redirect('/user/queue.php?job=' . urlencode($job['job_id']) . '&paid=1');
            }
        }
    }
}

$pageTitle = 'Payment';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Payment<small>Step 4 of 5</small></div>
    </div>

    <div class="page-content">
        <div class="steps-bar">
            <?php
            $steps = ['Upload','Settings','Summary','Payment','Queue'];
            foreach ($steps as $i => $s) {
                $cls = $i < 3 ? 'completed' : ($i === 3 ? 'active' : '');
                echo "<div class='step-item {$cls}'><div class='step-circle'>" . ($i < 3 ? '<i class="fas fa-check" style="font-size:.7rem"></i>' : ($i+1)) . "</div><div class='step-label'>{$s}</div></div>";
            }
            ?>
        </div>

        <div style="max-width:700px;margin:0 auto">
            <div class="page-header">
                <div>
                    <h1>Payment</h1>
                    <p>Choose how you will pay. Print jobs enter the queue after payment is confirmed.</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?= implode('<br>', array_map('h', $errors)) ?></div></div>
            <?php endif; ?>

            <!-- Job Summary Card -->
            <div class="card fade-in" style="margin-bottom:20px">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-receipt"></i> Order Summary</div>
                    <?= paymentStatusBadge($job['payment_status']) ?>
                </div>
                <div class="card-body">
                    <div class="summary-row"><span class="label">Print Job ID</span><span class="value"><code><?= h($job['job_id']) ?></code></span></div>
                    <div class="summary-row"><span class="label">Document</span><span class="value" style="max-width:260px;overflow:hidden;text-overflow:ellipsis"><?= h($job['original_file_name']) ?></span></div>
                    <div class="summary-row"><span class="label">Pages × Copies</span><span class="value"><?= $job['selected_pages_count'] ?> pages × <?= $job['copies'] ?> copies</span></div>
                    <div class="summary-row"><span class="label">Print Type</span><span class="value"><?= $job['print_type'] === 'color' ? 'Color' : 'Black & White' ?></span></div>
                    <div class="summary-row"><span class="label">Paper Size</span><span class="value"><?= h($job['paper_size']) ?></span></div>
                    <div class="summary-row"><span class="label">Price per Page</span><span class="value"><?= formatCurrency((float)$job['price_per_page']) ?></span></div>
                    <div class="summary-row total">
                        <span class="label">Total Amount</span>
                        <span class="value"><?= formatCurrency((float)$job['total_cost']) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($cashAwaitingConfirmation): ?>
            <div class="card fade-in" style="margin-bottom:20px">
                <div class="card-body">
                    <div class="payment-status-display">
                        <div class="payment-icon-big pending"><i class="fas fa-hand-holding-usd"></i></div>
                        <h2>Cash Payment Awaiting Confirmation</h2>
                        <p style="color:var(--gray-500);margin:8px 0 20px">Pay at the print counter and wait for an administrator to confirm, or choose simulated online payment below to cancel the cash request and queue this job. The online option does not collect real money.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($job['payment_status'] === 'pending'): ?>
            <!-- Payment Form -->
            <div class="card fade-in" style="margin-bottom:20px">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-credit-card"></i> Payment method</div>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <label class="form-label" for="paymentMethod">Choose payment method</label>
                        <select class="form-select" id="paymentMethod" name="payment_method" style="margin-bottom:16px">
                            <option value="online">Online payment (simulated)</option>
                            <option value="cash">Cash only — pay at the print counter</option>
                        </select>
                        <p id="paymentMethodNotice" class="alert alert-warning" role="note" style="margin-bottom:18px">
                            <i class="fas fa-info-circle"></i>
                            <span><strong>Online is simulated:</strong> it records a demo payment. No real money is collected.</span>
                        </p>

                        <!-- Payment total display -->
                        <div style="background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;border-radius:10px;padding:20px;margin-bottom:20px;text-align:center">
                            <div style="font-size:.8rem;opacity:.8;margin-bottom:4px">Amount Due</div>
                            <div style="font-size:2.2rem;font-weight:800"><?= formatCurrency((float)$job['total_cost']) ?></div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg" style="width:100%;justify-content:center" id="payBtn">
                            <i class="fas fa-check-circle"></i> Simulate Online Payment
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <!-- Payment already done -->
            <div class="card fade-in">
                <div class="card-body">
                    <div class="payment-status-display">
                        <div class="payment-icon-big <?= $job['payment_status'] === 'successful' ? 'success' : ($job['payment_status'] === 'failed' ? 'failed' : 'pending') ?>">
                            <i class="fas <?= $job['payment_status'] === 'successful' ? 'fa-check-circle' : ($job['payment_status'] === 'processing' ? 'fa-clock' : 'fa-times-circle') ?>"></i>
                        </div>
                        <h2><?= $job['payment_status'] === 'successful' ? 'Payment Successful!' : ($job['payment_status'] === 'processing' ? 'Payment Processing' : 'Payment ' . ucfirst($job['payment_status'])) ?></h2>
                        <?php if ($job['payment_status'] === 'successful'): ?>
                            <p style="color:var(--gray-500);margin:8px 0 20px">Payment successful! Your print job has been added to the print queue.</p>
                            <a href="<?= BASE_URL ?>/user/queue.php?job=<?= h($job['job_id']) ?>" class="btn btn-primary">
                                <i class="fas fa-list-ol"></i> View Queue Status
                            </a>
                        <?php elseif ($job['payment_status'] === 'processing'): ?>
                            <p style="color:var(--gray-500);margin:8px 0 20px">This payment is being processed. Contact an administrator if its status does not update.</p>
                            <a href="<?= BASE_URL ?>/user/payment.php?job=<?= h($job['job_id']) ?>" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Refresh Status
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
document.getElementById('paymentForm')?.addEventListener('submit', function() {
    const button = document.getElementById('payBtn');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting payment...';
    }
});
document.getElementById('paymentMethod')?.addEventListener('change', function() {
    const button = document.getElementById('payBtn');
    const notice = document.getElementById('paymentMethodNotice');
    const isCash = this.value === 'cash';
    button.innerHTML = isCash
        ? '<i class="fas fa-money-bill-wave"></i> Request Cash Payment'
        : '<i class="fas fa-check-circle"></i> Simulate Online Payment';
    notice.innerHTML = isCash
        ? '<i class="fas fa-info-circle"></i><span><strong>Cash:</strong> pay at the print counter. An administrator must confirm receipt before the job is queued.</span>'
        : '<i class="fas fa-info-circle"></i><span><strong>Online is simulated:</strong> it records a demo payment. No real money is collected.</span>';
});
</script>
