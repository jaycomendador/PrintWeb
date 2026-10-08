<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if (!isset($_SESSION['upload']) || !isset($_SESSION['print_settings'])) {
    redirect('/user/upload.php');
}

$upload   = $_SESSION['upload'];
$settings = $_SESSION['print_settings'];

// Handle order confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Create the print job record
    $jobId = generateJobId();
    $stmt = $pdo->prepare("
        INSERT INTO print_jobs 
        (job_id, user_id, file_name, original_file_name, file_path, file_size, total_pages,
         copies, print_type, paper_size, pages_to_print, selected_pages_count, 
         price_per_page, total_cost, payment_status, print_status, queue_number, submitted_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NOW())
    ");
    $stmt->execute([
        $jobId,
        $_SESSION['user_id'],
        $upload['saved_name'],
        $upload['original_name'],
        $upload['file_path'],
        $upload['file_size'],
        $upload['total_pages'],
        $settings['copies'],
        $settings['print_type'],
        $settings['paper_size'],
        $settings['pages_to_print'],
        $settings['selected_pages'],
        $settings['price_per_page'],
        $settings['total_cost'],
        'pending',
        'awaiting_payment',
    ]);

    $newJobId = $pdo->lastInsertId();
    $_SESSION['current_job_id'] = $newJobId;
    $_SESSION['current_job_str'] = $jobId;

    // Create notification
    notifyJobStatusChange(
        (int)$_SESSION['user_id'],
        (int)$newJobId,
        $jobId,
        'awaiting_payment',
        'It will receive a queue number after payment is confirmed.'
    );

    redirect('/user/payment.php?job=' . urlencode($jobId));
}

$pageTitle = 'Print Summary';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Printing Summary<small>Step 3 of 5</small></div>
        <div class="topbar-actions">
            <a href="<?= BASE_URL ?>/user/print_settings.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="page-content">
        <div class="steps-bar">
            <?php
            $steps = ['Upload','Settings','Summary','Payment','Queue'];
            foreach ($steps as $i => $s) {
                $cls = $i < 2 ? 'completed' : ($i === 2 ? 'active' : '');
                echo "<div class='step-item {$cls}'><div class='step-circle'>" . ($i < 2 ? '<i class="fas fa-check" style="font-size:.7rem"></i>' : ($i+1)) . "</div><div class='step-label'>{$s}</div></div>";
            }
            ?>
        </div>

        <div style="max-width:700px;margin:0 auto">
            <div class="page-header">
                <div>
                    <h1>Printing Summary</h1>
                    <p>Review your print order details before proceeding to payment.</p>
                </div>
            </div>

            <div class="card fade-in" style="margin-bottom:20px">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-file-alt"></i> Document Details</div>
                </div>
                <div class="card-body">
                    <div class="summary-row"><span class="label">File Name</span><span class="value" style="max-width:300px;overflow:hidden;text-overflow:ellipsis"><?= h($upload['original_name']) ?></span></div>
                    <div class="summary-row"><span class="label">File Size</span><span class="value"><?= formatFileSize($upload['file_size']) ?></span></div>
                    <div class="summary-row"><span class="label">Total Pages in Document</span><span class="value"><?= $upload['total_pages'] ?></span></div>
                    <div class="summary-row"><span class="label">Selected Pages</span>
                        <span class="value"><?= $settings['pages_to_print'] === 'all' ? 'All (' . $upload['total_pages'] . ' pages)' : h($settings['pages_to_print']) . ' (' . $settings['selected_pages'] . ' pages)' ?></span>
                    </div>
                </div>
            </div>

            <div class="card fade-in" style="margin-bottom:20px">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-sliders-h"></i> Print Configuration</div>
                </div>
                <div class="card-body">
                    <div class="summary-row"><span class="label">Number of Copies</span><span class="value"><?= $settings['copies'] ?></span></div>
                    <div class="summary-row"><span class="label">Print Type</span>
                        <span class="value">
                            <?= $settings['print_type'] === 'color' ? '<span class="badge badge-info"><i class="fas fa-palette"></i> Color</span>' : '<span class="badge badge-secondary"><i class="fas fa-adjust"></i> Black &amp; White</span>' ?>
                        </span>
                    </div>
                    <div class="summary-row"><span class="label">Paper Size</span><span class="value"><?= h($settings['paper_size']) ?></span></div>
                </div>
            </div>

            <div class="card fade-in" style="margin-bottom:24px">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-calculator"></i> Cost Breakdown</div>
                </div>
                <div class="card-body">
                    <div class="summary-row"><span class="label">Pages to Print</span><span class="value"><?= $settings['selected_pages'] ?> pages</span></div>
                    <div class="summary-row"><span class="label">× Copies</span><span class="value"><?= $settings['copies'] ?></span></div>
                    <div class="summary-row"><span class="label">Price per Page</span><span class="value"><?= formatCurrency($settings['price_per_page']) ?></span></div>
                    <div class="summary-row total">
                        <span class="label">Total Amount</span>
                        <span class="value"><?= formatCurrency($settings['total_cost']) ?></span>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap">
                <a href="<?= BASE_URL ?>/user/print_settings.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <form method="POST" id="confirmForm" style="display:inline">
                    <input type="hidden" name="confirm" value="1">
                    <button type="submit" class="btn btn-primary btn-lg" id="confirmBtn">
                        <i class="fas fa-check-circle"></i> Confirm & Proceed to Payment
                    </button>
                </form>
            </div>

            <div style="margin-top:16px;padding:14px;background:var(--warning-light);border-radius:8px;border:1px solid #fcd34d">
                <p style="font-size:.8rem;color:#92400e;margin:0">
                    <i class="fas fa-info-circle"></i>
                    <strong>Note:</strong> After confirming your order, you will be directed to the payment page. Your print job will be added to the queue only after a successful payment.
                </p>
            </div>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
document.getElementById('confirmForm').addEventListener('submit', function() {
    const button = document.getElementById('confirmBtn');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});
</script>
