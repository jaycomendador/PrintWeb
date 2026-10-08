<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Filters
$statusFilter  = $_GET['status'] ?? '';
$searchQuery   = trim($_GET['q'] ?? '');
$dateFrom      = $_GET['from'] ?? '';
$dateTo        = $_GET['to'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;

$where  = ['pj.user_id = ?'];
$params = [$_SESSION['user_id']];

if ($statusFilter) { $where[] = 'pj.print_status = ?'; $params[] = $statusFilter; }
if ($searchQuery)  { $where[] = '(pj.original_file_name LIKE ? OR pj.job_id LIKE ?)'; $params[] = "%{$searchQuery}%"; $params[] = "%{$searchQuery}%"; }
if ($dateFrom)     { $where[] = 'DATE(pj.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)       { $where[] = 'DATE(pj.created_at) <= ?'; $params[] = $dateTo; }

$whereStr = 'WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM print_jobs pj {$whereStr}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare("
    SELECT pj.*, p.printer_name, pay.payment_id, pay.payment_method,
           COALESCE(pj.completed_at, pj.created_at) AS history_date
    FROM print_jobs pj
    LEFT JOIN printers p ON pj.printer_id = p.id
    LEFT JOIN (
        SELECT payment.job_id, payment.payment_id, payment.payment_method
        FROM payments payment
        INNER JOIN (
            SELECT job_id, MAX(id) AS latest_id
            FROM payments
            WHERE payment_status = 'successful'
            GROUP BY job_id
        ) latest_payment ON latest_payment.latest_id = payment.id
    ) pay ON pay.job_id = pj.id
    {$whereStr}
    ORDER BY COALESCE(pj.completed_at, pj.created_at) DESC, pj.id DESC LIMIT ? OFFSET ?
");
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$pageTitle = 'Print History';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Print History<small>All your past print jobs</small></div>
        <div class="topbar-actions">
            <a href="<?= BASE_URL ?>/user/upload.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Print</a>
        </div>
    </div>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1>Print History</h1>
                <p>Completed print jobs are saved here with their final status and completion date.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card fade-in" style="margin-bottom:20px">
            <div class="card-body" style="padding:16px">
                <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                    <div style="flex:1;min-width:200px">
                        <label class="form-label" style="margin-bottom:4px">Search</label>
                        <div class="search-box" style="min-width:unset">
                            <i class="fas fa-search"></i>
                            <input type="text" name="q" class="form-control" value="<?= h($searchQuery) ?>" placeholder="Job ID or file name...">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" style="margin-bottom:4px">Status</label>
                        <select name="status" class="form-select" style="width:auto">
                            <option value="">All Statuses</option>
                            <?php foreach (PRINT_STATUSES as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="margin-bottom:4px">From</label>
                        <input type="date" name="from" class="form-control" value="<?= h($dateFrom) ?>" style="width:auto">
                    </div>
                    <div>
                        <label class="form-label" style="margin-bottom:4px">To</label>
                        <input type="date" name="to" class="form-control" value="<?= h($dateTo) ?>" style="width:auto">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="<?= BASE_URL ?>/user/history.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card fade-in">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-history"></i> Print History</div>
                <span class="badge badge-secondary"><?= $total ?> record(s)</span>
            </div>
            <div class="table-wrapper">
                <?php if (empty($jobs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No print jobs found</h3>
                        <p>Your print history will appear here.</p>
                    </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Document</th>
                            <th>Date & Time</th>
                            <th>Pages</th>
                            <th>Copies</th>
                            <th>Type</th>
                            <th>Paper</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Print Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><code style="font-size:.72rem"><?= h($job['job_id']) ?></code></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;min-width:0">
                                    <div style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500" title="<?= h($job['original_file_name']) ?>">
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
                                <?php if ($job['printer_name']): ?>
                                    <div style="font-size:.7rem;color:var(--gray-400)">
                                        <i class="fas fa-print" style="font-size:.65rem"></i> <?= h($job['printer_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="white-space:nowrap">
                                <div style="font-size:.8rem;font-weight:500"><?= formatDateTime($job['history_date'], 'M d, Y') ?></div>
                                <div style="font-size:.72rem;color:var(--gray-400)"><?= formatDateTime($job['history_date'], 'h:i A') ?><?= $job['completed_at'] ? ' · Completed' : ' · Submitted' ?></div>
                            </td>
                            <td><?= $job['selected_pages_count'] ?></td>
                            <td><?= $job['copies'] ?></td>
                            <td>
                                <?php if ($job['print_type'] === 'color'): ?>
                                    <span class="badge badge-info">Color</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">B&W</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($job['paper_size']) ?></td>
                            <td style="font-weight:600"><?= formatCurrency((float)$job['total_cost']) ?></td>
                            <td><?= paymentStatusBadge($job['payment_status']) ?></td>
                            <td><?= printStatusBadge($job['print_status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between">
                <span style="font-size:.8rem;color:var(--gray-500)">Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> total)</span>
                <div style="display:flex;gap:6px">
                    <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                           class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
