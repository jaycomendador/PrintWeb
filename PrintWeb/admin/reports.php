<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="printweb_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Job ID', 'User', 'Role', 'File Name', 'Selected Pages', 'Copies', 'Total Pages', 'Type', 'Size', 'Cost (PHP)', 'Printer', 'Status', 'Payment Method', 'Date']);

    $csvStmt = $pdo->prepare("
        SELECT pj.*, u.full_name, u.role, p.printer_name, pay.payment_method
        FROM print_jobs pj
        JOIN users u ON pj.user_id = u.id
        LEFT JOIN printers p ON pj.printer_id = p.id
        LEFT JOIN payments pay ON pay.job_id = pj.id
        ORDER BY pj.created_at DESC
    ");
    $csvStmt->execute();
    while ($row = $csvStmt->fetch()) {
        fputcsv($out, [
            $row['job_id'],
            $row['full_name'],
            $row['role'],
            $row['original_file_name'],
            $row['selected_pages_count'],
            $row['copies'],
            (int)$row['selected_pages_count'] * (int)$row['copies'],
            $row['print_type'],
            $row['paper_size'],
            $row['total_cost'],
            $row['printer_name'] ?? 'None',
            $row['print_status'],
            $row['payment_method'] ?? 'N/A',
            $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}

// Summary statistics include all print jobs.
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_jobs,
        SUM(CASE WHEN print_status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
        SUM(CASE WHEN print_status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
        SUM(CASE WHEN print_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_jobs,
        SUM(CASE WHEN payment_status = 'successful' AND NOT EXISTS (
            SELECT 1 FROM payments pay
            WHERE pay.job_id = print_jobs.id AND pay.payment_status = 'successful'
              AND pay.payment_method = 'simulation'
        ) THEN total_cost ELSE 0 END) as total_revenue,
        SUM(CASE WHEN print_status = 'completed' THEN selected_pages_count * copies ELSE 0 END) as total_pages_printed,
        SUM(CASE WHEN print_type = 'color' AND print_status = 'completed' THEN selected_pages_count * copies ELSE 0 END) as color_pages,
        SUM(CASE WHEN print_type = 'bw' AND print_status = 'completed' THEN selected_pages_count * copies ELSE 0 END) as bw_pages
    FROM print_jobs
");
$reportSummary = $stmt->fetch();

// Daily revenue and print count.
$dailyStmt = $pdo->query("
    SELECT 
        DATE(created_at) as log_date,
        COUNT(*) as job_count,
        SUM(CASE WHEN payment_status = 'successful' AND NOT EXISTS (
            SELECT 1 FROM payments pay
            WHERE pay.job_id = print_jobs.id AND pay.payment_status = 'successful'
              AND pay.payment_method = 'simulation'
        ) THEN total_cost ELSE 0 END) as daily_revenue,
        SUM(CASE WHEN print_status = 'completed' THEN selected_pages_count * copies ELSE 0 END) as daily_pages
    FROM print_jobs
    GROUP BY DATE(created_at)
    ORDER BY log_date ASC
");
$dailyData = $dailyStmt->fetchAll();

// Distribution by User Role
$roleStmt = $pdo->query("
    SELECT 
        u.role,
        COUNT(pj.id) as job_count,
        SUM(CASE WHEN pj.print_status = 'completed' THEN pj.selected_pages_count * pj.copies ELSE 0 END) as total_pages,
        SUM(CASE WHEN pj.payment_status = 'successful' AND NOT EXISTS (
            SELECT 1 FROM payments pay
            WHERE pay.job_id = pj.id AND pay.payment_status = 'successful'
              AND pay.payment_method = 'simulation'
        ) THEN pj.total_cost ELSE 0 END) as revenue
    FROM print_jobs pj
    JOIN users u ON pj.user_id = u.id
    GROUP BY u.role
");
$roleData = $roleStmt->fetchAll();

// Distribution by Printer
$printerStmt = $pdo->query("
    SELECT 
        COALESCE(p.printer_name, 'Unassigned') as printer_name,
        COUNT(pj.id) as jobs_handled,
        SUM(CASE WHEN pj.print_status = 'completed' THEN pj.selected_pages_count * pj.copies ELSE 0 END) as pages_printed
    FROM print_jobs pj
    LEFT JOIN printers p ON pj.printer_id = p.id
    GROUP BY p.printer_name
");
$printerData = $printerStmt->fetchAll();

// Payment Methods
$payStmt = $pdo->query("
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM payments
    WHERE payment_status = 'successful'
    GROUP BY payment_method
");
$paymentData = $payStmt->fetchAll();

$pageTitle = 'Reports & Analytics';
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
                    <span class="active">Reports & Analytics</span>
                </div>
            </div>
            <div class="topbar-right">
                <a href="?export=csv" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </div>
        </header>

        <div class="page-body">
            <div class="page-header flex-between mb-4">
                <div>
                    <h1 class="page-title"><i class="fas fa-chart-line text-primary"></i> System Reports & Analytics</h1>
                    <p class="text-muted">Financial statistics, print volume metrics, and usage analytics</p>
                </div>
            </div>

            <!-- Summary KPI Cards -->
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-success-light"><i class="fas fa-coins text-success"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= formatCurrency((float)$reportSummary['total_revenue']) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light"><i class="fas fa-print text-primary"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= number_format((int)$reportSummary['total_pages_printed']) ?></div>
                        <div class="stat-label">Total Pages Printed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-info-light"><i class="fas fa-file-invoice text-info"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= number_format((int)$reportSummary['total_jobs']) ?></div>
                        <div class="stat-label">Total Print Jobs</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-warning-light"><i class="fas fa-palette text-warning"></i></div>
                    <div class="stat-details">
                        <div class="stat-value"><?= number_format((int)$reportSummary['color_pages']) ?> / <?= number_format((int)$reportSummary['bw_pages']) ?></div>
                        <div class="stat-label">Color vs B&W Pages</div>
                    </div>
                </div>
            </div>

            <div class="grid-2 mb-4">
                <!-- Daily Trends Table -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Daily Activity Breakdown</h3>
                    </div>
                    <div class="table-responsive" style="max-height: 350px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Jobs</th>
                                    <th>Pages</th>
                                    <th style="text-align: right;">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dailyData)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No activity available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dailyData as $d): ?>
                                        <tr>
                                            <td><strong><?= date('M d, Y', strtotime($d['log_date'])) ?></strong></td>
                                            <td><?= $d['job_count'] ?></td>
                                            <td><?= number_format($d['daily_pages']) ?></td>
                                            <td style="text-align: right; font-weight: 600; color: var(--success);">
                                                <?= formatCurrency((float)$d['daily_revenue']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Role Distribution -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-users-cog"></i> Usage by User Role</h3>
                    </div>
                    <div class="table-responsive" style="max-height: 350px;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User Role</th>
                                    <th>Total Jobs</th>
                                    <th>Total Pages</th>
                                    <th style="text-align: right;">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($roleData)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No data available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($roleData as $r): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-info" style="text-transform: capitalize;">
                                                    <?= ROLES[$r['role']] ?? ucfirst($r['role']) ?>
                                                </span>
                                            </td>
                                            <td><?= $r['job_count'] ?></td>
                                            <td><?= number_format($r['total_pages']) ?></td>
                                            <td style="text-align: right; font-weight: 600;">
                                                <?= formatCurrency((float)$r['revenue']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid-2 mb-4">
                <!-- Printer Performance -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-print"></i> Printer Utilization</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Printer</th>
                                    <th>Jobs Handled</th>
                                    <th>Pages Printed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($printerData)): ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">No printer statistics available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($printerData as $p): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($p['printer_name']) ?></strong></td>
                                            <td><?= $p['jobs_handled'] ?></td>
                                            <td><?= number_format($p['pages_printed'] ?? 0) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Methods Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-wallet"></i> Payment Methods</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th>Transactions</th>
                                    <th style="text-align: right;">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paymentData)): ?>
                                    <tr><td colspan="3" class="text-center py-4 text-muted">No payment records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($paymentData as $pm): ?>
                                        <tr>
                                            <td style="text-transform: capitalize;">
                                                <i class="fas fa-credit-card text-primary mr-1"></i>
                                                <?= htmlspecialchars(str_replace('_', ' ', $pm['payment_method'])) ?>
                                            </td>
                                            <td><?= $pm['count'] ?></td>
                                            <td style="text-align: right; font-weight: 600; color: var(--success);">
                                                <?= formatCurrency((float)$pm['total_amount']) ?>
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
