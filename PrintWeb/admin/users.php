<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$errors = $success = [];
$editUser = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user' || $action === 'edit_user') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $role     = $_POST['role'] ?? 'student';
        $contact  = trim($_POST['contact_number'] ?? '');
        $dept     = trim($_POST['department'] ?? '');
        $status   = $_POST['status'] ?? 'active';
        $uid      = (int)($_POST['user_db_id'] ?? 0);

        if (empty($fullName)) $errors[] = 'Full name required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

        if (empty($errors)) {
            if ($action === 'add_user') {
                $password = $_POST['password'] ?? 'PrintWeb@123';
                $hash     = password_hash($password, PASSWORD_BCRYPT);
                $userId   = generateUserId($role);
                $stmt = $pdo->prepare("INSERT INTO users (user_id,full_name,email,password_hash,role,contact_number,department,status) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$userId, $fullName, $email, $hash, $role, $contact, $dept, $status]);
                $success[] = "User {$fullName} added successfully.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name=?,email=?,role=?,contact_number=?,department=?,status=? WHERE id=?");
                $stmt->execute([$fullName, $email, $role, $contact, $dept, $status, $uid]);
                $success[] = "User updated successfully.";
            }
        }
    } elseif ($_POST['action'] === 'delete_user') {
        $uid = (int)$_POST['user_db_id'];
        if ($uid !== $_SESSION['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$uid]);
            $success[] = 'User deactivated.';
        } else {
            $errors[] = 'You cannot deactivate your own account.';
        }
    } elseif ($_POST['action'] === 'activate_user') {
        $uid = (int)$_POST['user_db_id'];
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$uid]);
        $success[] = 'User activated.';
    } elseif ($_POST['action'] === 'reset_password') {
        $uid = (int)$_POST['user_db_id'];
        $hash = password_hash('PrintWeb@123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $uid]);
        $success[] = 'Password reset to: PrintWeb@123';
    }
}

// Edit user fetch
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Filters
$roleFilter   = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;

$where  = ['1=1'];
$params = [];
if ($roleFilter)   { $where[] = 'role = ?';   $params[] = $roleFilter; }
if ($statusFilter) { $where[] = 'status = ?'; $params[] = $statusFilter; }
if ($search)       { $where[] = '(full_name LIKE ? OR email LIKE ? OR user_id LIKE ?)'; $params[] = "%{$search}%"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }

$whereStr = 'WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereStr}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$params[] = $perPage; $params[] = $offset;
$stmt = $pdo->prepare("SELECT * FROM users {$whereStr} ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'User Management';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">User Management<small>Manage all system users</small></div>
        <div class="topbar-actions">
            <button class="btn btn-primary btn-sm" onclick="openModal('addUserModal')">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>

    <div class="page-content">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?= implode('<br>', array_map('h', $errors)) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i><div><?= implode('<br>', array_map('h', $success)) ?></div></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card fade-in" style="margin-bottom:16px">
            <div class="card-body" style="padding:14px">
                <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                    <div class="search-box" style="flex:1;min-width:200px">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" class="form-control" value="<?= h($search) ?>" placeholder="Search name, email, ID...">
                    </div>
                    <select name="role" class="form-select" style="width:auto">
                        <option value="">All Roles</option>
                        <?php foreach (ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $roleFilter===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                    </select>
                    <select name="status" class="form-select" style="width:auto">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
                        <option value="suspended" <?= $statusFilter==='suspended'?'selected':'' ?>>Suspended</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary btn-sm">Clear</a>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card fade-in">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-users"></i> Users</div>
                <span class="badge badge-secondary"><?= $total ?> user(s)</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><code style="font-size:.72rem"><?= h($u['user_id']) ?></code></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="width:30px;height:30px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0">
                                        <?= strtoupper(substr($u['full_name'],0,1)) ?>
                                    </div>
                                    <span style="font-weight:500"><?= h($u['full_name']) ?></span>
                                </div>
                            </td>
                            <td><?= h($u['email']) ?></td>
                            <td><span class="badge badge-primary"><?= ROLES[$u['role']] ?? $u['role'] ?></span></td>
                            <td style="color:var(--gray-500);font-size:.8rem"><?= h($u['department'] ?: '—') ?></td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php elseif ($u['status'] === 'inactive'): ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Suspended</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.75rem;white-space:nowrap"><?= formatDateTime($u['created_at'], 'M d, Y') ?></td>
                            <td>
                                <div style="display:flex;gap:4px">
                                    <a href="?edit=<?= $u['id'] ?>" class="btn btn-secondary btn-sm btn-icon" data-tooltip="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?= BASE_URL ?>/admin/users.php?view_history=<?= $u['id'] ?>" class="btn btn-secondary btn-sm btn-icon" data-tooltip="History"><i class="fas fa-history"></i></a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Reset password to PrintWeb@123?')">
                                        <input type="hidden" name="action" value="reset_password">
                                        <input type="hidden" name="user_db_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-warning btn-sm btn-icon" data-tooltip="Reset Password"><i class="fas fa-key"></i></button>
                                    </form>
                                    <?php if ($u['status'] === 'active'): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Deactivate this user?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_db_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm btn-icon" data-tooltip="Deactivate"><i class="fas fa-ban"></i></button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="activate_user">
                                        <input type="hidden" name="user_db_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm btn-icon" data-tooltip="Activate"><i class="fas fa-check"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer" style="display:flex;justify-content:flex-end;gap:6px">
                <?php for ($i = max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="btn btn-sm <?= $i===$page?'btn-primary':'btn-secondary' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-plus"></i> Add New User</div>
            <button class="modal-close" onclick="closeModal('addUserModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Role *</label>
                    <select name="role" class="form-select">
                        <?php foreach (ROLES as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Contact</label><input type="text" name="contact_number" class="form-control"></div>
                <div class="form-group"><label class="form-label">Department</label><input type="text" name="department" class="form-control"></div>
                <div class="form-group"><label class="form-label">Initial Password</label><input type="text" name="password" class="form-control" value="PrintWeb@123"></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<?php if ($editUser): ?>
<div class="modal-overlay open" id="editUserModal">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-user-edit"></i> Edit User</div>
            <a href="<?= BASE_URL ?>/admin/users.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_db_id" value="<?= $editUser['id'] ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="full_name" class="form-control" value="<?= h($editUser['full_name']) ?>" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= h($editUser['email']) ?>" required></div>
                <div class="form-group"><label class="form-label">Role *</label>
                    <select name="role" class="form-select">
                        <?php foreach (ROLES as $k=>$v): ?><option value="<?= $k ?>" <?= $editUser['role']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Contact</label><input type="text" name="contact_number" class="form-control" value="<?= h($editUser['contact_number']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Department</label><input type="text" name="department" class="form-control" value="<?= h($editUser['department']??'') ?>"></div>
                <div class="form-group"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= $editUser['status']==='active'?'selected':'' ?>>Active</option>
                        <option value="inactive" <?= $editUser['status']==='inactive'?'selected':'' ?>>Inactive</option>
                        <option value="suspended" <?= $editUser['status']==='suspended'?'selected':'' ?>>Suspended</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">
                <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
