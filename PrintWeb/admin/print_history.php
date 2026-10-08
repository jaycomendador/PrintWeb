<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$statusFilter = trim((string)($_GET['status'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

if (!array_key_exists($statusFilter, PRINT_STATUSES)) {
    $statusFilter = '';
}

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'pj.print_status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = '(pj.job_id LIKE ? OR pj.original_file_name LIKE ? OR u.full_name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM print_jobs pj
    JOIN users u ON u.id = pj.user_id
    {$whereSql}
");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$historyStmt = $pdo->prepare("
    SELECT pj.id, pj.job_id, pj.original_file_name, pj.selected_pages_count, pj.copies,
           pj.print_type, pj.paper_size, pj.total_cost, pj.payment_status, pj.print_status,
           pj.created_at, pj.completed_at, u.full_name, u.role AS user_role,
           p.printer_name, latest_payment.payment_method, latest_payment.payment_status AS latest_payment_status
    FROM print_jobs pj
    JOIN users u ON u.id = pj.user_id
    LEFT JOIN printers p ON p.id = pj.printer_id
    LEFT JOIN (
        SELECT pay.job_id, pay.payment_method, pay.payment_status
        FROM payments pay
        INNER JOIN (
            SELECT job_id, MAX(id) AS latest_id
            FROM payments
            GROUP BY job_id
        ) latest ON latest.latest_id = pay.id
    ) latest_payment ON latest_payment.job_id = pj.id
    {$whereSql}
    ORDER BY COALESCE(pj.completed_at, pj.created_at) DESC, pj.id DESC
    LIMIT ? OFFSET ?
");
foreach ($params as $index => $param) {
    $historyStmt->bindValue($index + 1, $param, PDO::PARAM_STR);
}
$historyStmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$historyStmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$historyStmt->execute();
$printJobs = $historyStmt->fetchAll();

$stats = $pdo->query("
    SELECT COUNT(*) AS total_jobs,
           SUM(CASE WHEN print_status = 'completed' THEN 1 ELSE 0 END) AS completed_jobs,
           SUM(CASE WHEN print_status IN ('failed', 'cancelled') THEN 1 ELSE 0 END) AS unsuccessful_jobs,
           SUM(CASE WHEN print_status = 'completed' THEN selected_pages_count * copies ELSE 0 END) AS pages_printed
    FROM print_jobs
")->fetch();

$pageTitle = 'Print History';
$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/admin-tools.css?v=3">';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="app-layout admin-tools-page print-history-page">
    <?php require_once __DIR__ . '/../includes/sidebar_admin.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-toggle" id="sidebarToggle" aria-label="Open navigation menu" aria-controls="sidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
                <div class="breadcrumb">
                    <span>Admin</span> <i class="fas fa-chevron-right separator"></i>
                    <span class="active">Print History</span>
                </div>
            </div>
            <div class="topbar-right">
                <span class="badge badge-primary"><i class="fas fa-history" aria-hidden="true"></i> All print jobs</span>
            </div>
        </header>

        <div class="page-body">
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="fas fa-history text-primary" aria-hidden="true"></i> Print History</h1>
                    <p class="text-muted">Review print jobs, their owners, payment, and final status.</p>
                </div>
            </div>

            <div class="stats-grid print-history-stats mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-primary-light"><i class="fas fa-print text-primary" aria-hidden="true"></i></div>
                    <div class="stat-details"><div class="stat-value"><?= number_format((int)$stats['total_jobs']) ?></div><div class="stat-label">Total Jobs</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-success-light"><i class="fas fa-check-circle text-success" aria-hidden="true"></i></div>
                    <div class="stat-details"><div class="stat-value"><?= number_format((int)$stats['completed_jobs']) ?></div><div class="stat-label">Completed</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-danger-light"><i class="fas fa-times-circle text-danger" aria-hidden="true"></i></div>
                    <div class="stat-details"><div class="stat-value"><?= number_format((int)$stats['unsuccessful_jobs']) ?></div><div class="stat-label">Failed / Cancelled</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-info-light"><i class="fas fa-copy text-info" aria-hidden="true"></i></div>
                    <div class="stat-details"><div class="stat-value"><?= number_format((int)$stats['pages_printed']) ?></div><div class="stat-label">Pages Printed</div></div>
                </div>
            </div>

            <div class="card print-history-filters mb-4">
                <div class="card-body">
                    <form method="GET" class="print-history-filter-form">
                        <label class="print-history-search">
                            <span class="form-label">Search jobs</span>
                            <span class="search-box">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="search" name="q" class="form-control" value="<?= h($search) ?>" placeholder="Job ID, document, or user">
                            </span>
                        </label>
                        <label>
                            <span class="form-label">Print status</span>
                            <select name="status" class="form-select">
                                <option value="">All statuses</option>
                                <?php foreach (PRINT_STATUSES as $key => $status): ?>
                                    <option value="<?= h($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= h($status['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="print-history-filter-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                            <?php if ($search !== '' || $statusFilter !== ''): ?>
                                <a href="<?= BASE_URL ?>/admin/print_history.php" class="btn btn-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card print-history-table-card">
                <div class="card-header flex-between">
                    <h2 class="card-title"><i class="fas fa-list" aria-hidden="true"></i> Print records</h2>
                    <span class="badge badge-secondary"><?= number_format($totalRecords) ?> record<?= $totalRecords === 1 ? '' : 's' ?></span>
                </div>
                <div class="table-responsive print-history-table-wrap">
                    <?php if (!$printJobs): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open" aria-hidden="true"></i>
                            <h3>No print jobs found</h3>
                            <p>Try another search or status filter.</p>
                        </div>
                    <?php else: ?>
                        <table class="table print-history-table">
                            <thead>
                                <tr>
                                    <th>Completed / Submitted</th>
                                    <th>Job & Document</th>
                                    <th>User</th>
                                    <th>Print Specs</th>
                                    <th>Printer</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Print Status</th>
                                    <th>Document</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($printJobs as $job): ?>
                                    <?php $historyDate = $job['completed_at'] ?: $job['created_at']; ?>
                                    <tr>
                                        <td class="print-history-date">
                                            <strong><?= formatDateTime($historyDate, 'M d, Y') ?></strong>
                                            <span><?= formatDateTime($historyDate, 'h:i A') ?></span>
                                            <small><?= $job['completed_at'] ? 'Completed' : 'Submitted' ?></small>
                                        </td>
                                        <td class="print-history-job">
                                            <strong><?= h($job['job_id']) ?></strong>
                                            <span title="<?= h($job['original_file_name']) ?>"><?= h($job['original_file_name']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= h($job['full_name']) ?></strong>
                                            <small><?= h(ROLES[$job['user_role']] ?? $job['user_role']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= h($job['paper_size']) ?></span>
                                            <span class="badge badge-secondary"><?= $job['print_type'] === 'color' ? 'Color' : 'B&W' ?></span>
                                            <small><?= (int)$job['selected_pages_count'] ?> pages × <?= (int)$job['copies'] ?> copies</small>
                                        </td>
                                        <td><?= h($job['printer_name'] ?: 'Unassigned') ?></td>
                                        <td><strong><?= formatCurrency((float)$job['total_cost']) ?></strong></td>
                                        <td>
                                            <?php if ($job['payment_method']): ?>
                                                <small><?= h($job['payment_method'] === 'simulation' ? 'Online simulation' : ucfirst($job['payment_method'])) ?></small>
                                            <?php else: ?>
                                                <small>Not selected</small>
                                            <?php endif; ?>
                                            <?= paymentStatusBadge($job['payment_status']) ?>
                                        </td>
                                        <td><?= printStatusBadge($job['print_status']) ?></td>
                                        <td>
                                            <a class="btn btn-secondary btn-sm btn-icon" href="<?= BASE_URL ?>/admin/job_document.php?job=<?= (int)$job['id'] ?>" target="_blank" rel="noopener" title="View uploaded document" aria-label="View uploaded document <?= h($job['original_file_name']) ?>">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer print-history-pagination">
                        <span>Page <?= $page ?> of <?= $totalPages ?></span>
                        <div>
                            <?php if ($page > 1): ?>
                                <a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $page - 1])) ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(['q' => $search, 'status' => $statusFilter, 'page' => $page + 1])) ?>">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
