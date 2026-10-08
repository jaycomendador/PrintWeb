<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$errors = $success = [];
$activeTab = $_POST['active_tab'] ?? 'profile-info';
$validTabs = ['profile-info', 'change-pwd', 'payment-hist', 'print-hist'];
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'profile-info';
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName     = trim($_POST['full_name'] ?? '');
        $contact      = trim($_POST['contact_number'] ?? '');
        $department   = trim($_POST['department'] ?? '');

        if (empty($fullName)) {
            $errors[] = 'Full name is required.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, contact_number=?, department=? WHERE id=?");
            $stmt->execute([$fullName, $contact, $department, $_SESSION['user_id']]);
            $_SESSION['user_name'] = $fullName;
            $success[] = 'Profile updated successfully.';
            $user = getCurrentUser();
        }
    } elseif ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $stmt->execute([$hash, $_SESSION['user_id']]);
            $success[] = 'Password changed successfully.';
        }
    }
}

$stats = getUserStats($_SESSION['user_id']);

// Payment history
$stmt = $pdo->prepare("
    SELECT pay.*, pj.original_file_name, pj.job_id
    FROM payments pay
    JOIN print_jobs pj ON pay.job_id = pj.id
    WHERE pay.user_id = ?
    ORDER BY pay.created_at DESC LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$paymentHistory = $stmt->fetchAll();

$pageTitle = 'Profile';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Profile<small>Manage your account</small></div>
    </div>

    <div class="page-content">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger fade-in"><i class="fas fa-exclamation-circle"></i><div><?= implode('<br>', array_map('h', $errors)) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i><div><?= implode('<br>', array_map('h', $success)) ?></div></div>
        <?php endif; ?>

        <div class="profile-layout" style="display:grid;grid-template-columns:minmax(0, 300px) minmax(0, 1fr);gap:20px;align-items:start">
            <!-- Profile Card -->
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="card fade-in" style="text-align:center;padding:28px 20px">
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;margin:0 auto 16px">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:4px"><?= h($user['full_name']) ?></h2>
                    <p style="font-size:.8rem;color:var(--gray-400);margin-bottom:12px"><?= h($user['email']) ?></p>
                    <span class="badge badge-primary" style="margin-bottom:8px"><?= ROLES[$user['role']] ?? $user['role'] ?></span>
                    <div style="font-size:.75rem;color:var(--gray-400);margin-top:8px">
                        <code><?= h($user['user_id']) ?></code>
                    </div>
                    <hr class="divider">
                    <div style="display:flex;justify-content:space-around;text-align:center">
                        <div><div style="font-weight:700;font-size:1.2rem;color:var(--primary)"><?= $stats['total_prints'] ?? 0 ?></div><div style="font-size:.72rem;color:var(--gray-400)">Total Prints</div></div>
                        <div><div style="font-weight:700;font-size:1.2rem;color:var(--success)"><?= $stats['completed_prints'] ?? 0 ?></div><div style="font-size:.72rem;color:var(--gray-400)">Completed</div></div>
                    </div>
                </div>

                <div class="card fade-in">
                    <div class="card-body">
                        <div style="font-size:.75rem;color:var(--gray-400);margin-bottom:8px">Account Info</div>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <div><div style="font-size:.7rem;color:var(--gray-400)">Email</div><div style="font-size:.85rem;font-weight:500"><?= h($user['email']) ?></div></div>
                            <div><div style="font-size:.7rem;color:var(--gray-400)">Contact</div><div style="font-size:.85rem;font-weight:500"><?= h($user['contact_number'] ?: '—') ?></div></div>
                            <div><div style="font-size:.7rem;color:var(--gray-400)">Department</div><div style="font-size:.85rem;font-weight:500"><?= h($user['department'] ?: '—') ?></div></div>
                            <div><div style="font-size:.7rem;color:var(--gray-400)">Member Since</div><div style="font-size:.85rem;font-weight:500"><?= formatDateTime($user['created_at'], 'M d, Y') ?></div></div>
                            <div><div style="font-size:.7rem;color:var(--gray-400)">Status</div><span class="badge badge-success">Active</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Panel -->
            <div class="fade-in profile-tabs" data-tabs>
                <div class="tabs" role="tablist" aria-label="Profile sections">
                    <button type="button" class="tab-btn<?= $activeTab === 'profile-info' ? ' active' : '' ?>" id="tab-profile-info" role="tab" aria-controls="profile-info" aria-selected="<?= $activeTab === 'profile-info' ? 'true' : 'false' ?>" data-tab="profile-info">Profile Information</button>
                    <button type="button" class="tab-btn<?= $activeTab === 'change-pwd' ? ' active' : '' ?>" id="tab-change-pwd" role="tab" aria-controls="change-pwd" aria-selected="<?= $activeTab === 'change-pwd' ? 'true' : 'false' ?>" data-tab="change-pwd">Change Password</button>
                    <button type="button" class="tab-btn<?= $activeTab === 'payment-hist' ? ' active' : '' ?>" id="tab-payment-hist" role="tab" aria-controls="payment-hist" aria-selected="<?= $activeTab === 'payment-hist' ? 'true' : 'false' ?>" data-tab="payment-hist">Payment History</button>
                    <button type="button" class="tab-btn<?= $activeTab === 'print-hist' ? ' active' : '' ?>" id="tab-print-hist" role="tab" aria-controls="print-hist" aria-selected="<?= $activeTab === 'print-hist' ? 'true' : 'false' ?>" data-tab="print-hist">Print History</button>
                </div>

                <!-- Profile Info -->
                <div class="tab-pane<?= $activeTab === 'profile-info' ? ' active' : '' ?>" id="profile-info" role="tabpanel" aria-labelledby="tab-profile-info"<?= $activeTab === 'profile-info' ? '' : ' hidden' ?>>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="active_tab" value="profile-info">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                                    <div class="form-group">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name']) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" value="<?= h($user['email']) ?>" disabled style="opacity:.6">
                                        <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px">Email cannot be changed.</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" name="contact_number" class="form-control" value="<?= h($user['contact_number'] ?? '') ?>" placeholder="+63 9XX XXX XXXX">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Department / Section</label>
                                        <input type="text" name="department" class="form-control" value="<?= h($user['department'] ?? '') ?>" placeholder="e.g. BSCS - 3A">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">User Type</label>
                                        <input type="text" class="form-control" value="<?= ROLES[$user['role']] ?? $user['role'] ?>" disabled style="opacity:.6">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">User ID</label>
                                        <input type="text" class="form-control" value="<?= h($user['user_id']) ?>" disabled style="opacity:.6">
                                    </div>
                                </div>
                                <div style="margin-top:8px">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="tab-pane<?= $activeTab === 'change-pwd' ? ' active' : '' ?>" id="change-pwd" role="tabpanel" aria-labelledby="tab-change-pwd"<?= $activeTab === 'change-pwd' ? '' : ' hidden' ?>>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-lock"></i> Change Password</div>
                        </div>
                        <div class="card-body">
                            <form method="POST" style="max-width:400px">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="active_tab" value="change-pwd">
                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" placeholder="Min. 8 characters" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="tab-pane<?= $activeTab === 'payment-hist' ? ' active' : '' ?>" id="payment-hist" role="tabpanel" aria-labelledby="tab-payment-hist"<?= $activeTab === 'payment-hist' ? '' : ' hidden' ?>>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-receipt"></i> Payment History</div>
                        </div>
                        <div class="table-wrapper">
                            <?php if (empty($paymentHistory)): ?>
                                <div class="empty-state"><i class="fas fa-receipt"></i><h3>No payments yet</h3></div>
                            <?php else: ?>
                            <table>
                                <thead>
                                    <tr><th>Payment ID</th><th>Job</th><th>Document</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paymentHistory as $pay): ?>
                                    <tr>
                                        <td><code style="font-size:.72rem"><?= h($pay['payment_id']) ?></code></td>
                                        <td><code style="font-size:.72rem"><?= h($pay['job_id']) ?></code></td>
                                        <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($pay['original_file_name']) ?></td>
                                        <td style="font-weight:600"><?= formatCurrency((float)$pay['amount']) ?></td>
                                        <td><?= ucfirst($pay['payment_method']) ?></td>
                                        <td><?= paymentStatusBadge($pay['payment_status']) ?></td>
                                        <td style="font-size:.75rem;white-space:nowrap"><?= formatDateTime($pay['created_at'], 'M d, Y') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="<?= BASE_URL ?>/user/history.php" style="font-size:.8rem;color:var(--primary)">View full print history →</a>
                        </div>
                    </div>
                </div>

                <!-- Print History in Profile -->
                <div class="tab-pane<?= $activeTab === 'print-hist' ? ' active' : '' ?>" id="print-hist" role="tabpanel" aria-labelledby="tab-print-hist"<?= $activeTab === 'print-hist' ? '' : ' hidden' ?>>
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-history"></i> Recent Print Jobs</div>
                            <a href="<?= BASE_URL ?>/user/history.php" class="btn btn-secondary btn-sm">View All</a>
                        </div>
                        <div class="card-body" style="padding:0">
                            <?php
                            $stmtH = $pdo->prepare("SELECT * FROM print_jobs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                            $stmtH->execute([$_SESSION['user_id']]);
                            $recentH = $stmtH->fetchAll();
                            ?>
                            <?php if (empty($recentH)): ?>
                                <div class="empty-state"><i class="fas fa-print"></i><h3>No print jobs yet</h3></div>
                            <?php else: ?>
                            <table>
                                <thead><tr><th>Job ID</th><th>Document</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentH as $h): ?>
                                    <tr>
                                        <td><code style="font-size:.72rem"><?= h($h['job_id']) ?></code></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:6px;min-width:0">
                                                <div style="max-width:160px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500"><?= h($h['original_file_name']) ?></div>
                                                <a class="btn btn-secondary btn-sm btn-icon document-view-link"
                                                   href="<?= BASE_URL ?>/user/document.php?job=<?= (int)$h['id'] ?>"
                                                   target="_blank" rel="noopener"
                                                   title="View uploaded document"
                                                   aria-label="View uploaded document <?= h($h['original_file_name']) ?>">
                                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?= printStatusBadge($h['print_status']) ?></td>
                                        <td><?= formatCurrency((float)$h['total_cost']) ?></td>
                                        <td style="font-size:.75rem;white-space:nowrap"><?= formatDateTime($h['created_at'], 'M d, Y') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logout -->
        <div style="margin-top:20px;padding:16px;background:var(--danger-light);border-radius:10px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fca5a5">
            <div>
                <div style="font-weight:600;color:#991b1b">Sign Out</div>
                <div style="font-size:.8rem;color:#991b1b;opacity:.7">You will be redirected to the login page.</div>
            </div>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
