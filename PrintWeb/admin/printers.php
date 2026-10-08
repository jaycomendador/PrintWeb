<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

refreshEsp32ConnectionStatuses();

$errors = $success = [];
$editPrinter = null;
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'This request expired. Refresh the page and try again.';
    } elseif ($action === 'add_printer' || $action === 'edit_printer') {
        $name      = trim($_POST['printer_name'] ?? '');
        $model     = trim($_POST['printer_model'] ?? '');
        $location  = trim($_POST['location'] ?? '');
        $espId     = !empty($_POST['esp32_id']) ? (int)$_POST['esp32_id'] : null;
        $color     = isset($_POST['supports_color']) ? 1 : 0;
        $ip        = trim($_POST['ip_address'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $pid       = (int)($_POST['printer_id'] ?? 0);

        if ($name === '') {
            $errors[] = 'Printer name is required.';
        }
        if (strlen($name) > 100 || strlen($model) > 100 || strlen($location) > 150 || strlen($ip) > 45) {
            $errors[] = 'One or more printer fields exceed their maximum length.';
        }
        if ($espId !== null) {
            $deviceStmt = $pdo->prepare("
                SELECT d.id
                FROM esp32_devices d
                LEFT JOIN printers p ON p.esp32_id = d.id AND p.id <> ?
                WHERE d.id = ? AND p.id IS NULL
            ");
            $deviceStmt->execute([$pid, $espId]);
            if (!$deviceStmt->fetchColumn()) {
                $errors[] = 'Choose an ESP32 device that is not assigned to another printer.';
            }
        }
        if ($action === 'edit_printer' && $pid < 1) {
            $errors[] = 'The selected printer is invalid.';
        }

        if (empty($errors)) {
            $pdo->beginTransaction();
            if ($action === 'edit_printer') {
                $currentStmt = $pdo->prepare('SELECT id, esp32_id FROM printers WHERE id = ? FOR UPDATE');
                $currentStmt->execute([$pid]);
                $currentPrinter = $currentStmt->fetch();
                if (!$currentPrinter) {
                    $pdo->rollBack();
                    $errors[] = 'The printer could not be found.';
                } else {
                    if ($currentPrinter['esp32_id']) {
                        $pdo->prepare('UPDATE esp32_devices SET printer_id = NULL WHERE id = ? AND printer_id = ?')
                            ->execute([$currentPrinter['esp32_id'], $pid]);
                    }
                    $stmt = $pdo->prepare("UPDATE printers SET printer_name=?,printer_model=?,location=?,esp32_id=?,supports_color=?,ip_address=?,notes=? WHERE id=?");
                    $stmt->execute([$name, $model, $location, $espId, $color, $ip, $notes, $pid]);
                    if ($espId) {
                        $pdo->prepare('UPDATE esp32_devices SET printer_id = ? WHERE id = ?')->execute([$pid, $espId]);
                    }
                    $pdo->commit();
                    $success[] = 'Printer updated successfully.';
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO printers (printer_name,printer_model,location,esp32_id,supports_color,ip_address,notes) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$name, $model, $location, $espId, $color, $ip, $notes]);
                $newPrinterId = (int)$pdo->lastInsertId();
                if ($espId) {
                    $pdo->prepare('UPDATE esp32_devices SET printer_id = ? WHERE id = ?')->execute([$newPrinterId, $espId]);
                }
                $pdo->commit();
                $success[] = "Printer '{$name}' added successfully.";
            }
        }
    } elseif ($action === 'delete_printer') {
        $pid = (int)($_POST['printer_id'] ?? 0);
        if ($pid < 1) {
            $errors[] = 'The selected printer is invalid.';
        } else {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE esp32_devices SET printer_id = NULL WHERE printer_id = ?')->execute([$pid]);
            $pdo->prepare("UPDATE esp32_devices d JOIN printers p ON p.esp32_id = d.id SET d.printer_id = NULL WHERE p.id = ?")->execute([$pid]);
            $delete = $pdo->prepare("DELETE FROM printers WHERE id = ?");
            $delete->execute([$pid]);
            $pdo->commit();
            $success[] = $delete->rowCount() ? 'Printer removed.' : 'Printer was not found.';
        }
    } elseif ($action === 'toggle_printer') {
        $pid = (int)($_POST['printer_id'] ?? 0);
        $enabled = (int)($_POST['enabled'] ?? -1);
        if ($pid < 1 || !in_array($enabled, [0, 1], true)) {
            $errors[] = 'The printer enable action is invalid.';
        } else {
            $stmt = $pdo->prepare("UPDATE printers SET enabled = ? WHERE id = ?");
            $stmt->execute([$enabled, $pid]);
            $check = $pdo->prepare('SELECT id FROM printers WHERE id = ?');
            $check->execute([$pid]);
            if (!$check->fetchColumn()) {
                $errors[] = 'The selected printer could not be found.';
            } else {
                $success[] = 'Printer ' . ($enabled ? 'enabled' : 'disabled') . '.';
            }
        }
    } elseif ($action === 'set_status') {
        $pid = (int)($_POST['printer_id'] ?? 0);
        $status = (string)($_POST['new_status'] ?? '');
        if ($pid < 1 || !isset(PRINTER_STATUSES[$status])) {
            $errors[] = 'The printer status is invalid.';
        } else {
            $stmt = $pdo->prepare("UPDATE printers SET status = ? WHERE id = ?");
            $stmt->execute([$status, $pid]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM printers WHERE id = ?');
                $check->execute([$pid]);
                if (!$check->fetchColumn()) {
                    $errors[] = 'The selected printer could not be found.';
                }
            }
            $deviceStmt = $pdo->prepare("SELECT esp32_id FROM printers WHERE id = ?");
            $deviceStmt->execute([$pid]);
            $deviceId = $deviceStmt->fetchColumn();
            if ($deviceId) {
                $led = match($status) { 'available' => 'green', 'printing' => 'yellow', 'offline', 'error' => 'red', default => 'off' };
                sendEsp32Command((int)$deviceId, $led);
            }
            if (!$errors) $success[] = 'Printer status updated.';
        }
    } elseif ($action === 'update_paper') {
        $pid = (int)($_POST['printer_id'] ?? 0);
        $levelInput = filter_var($_POST['paper_level'] ?? null, FILTER_VALIDATE_INT);
        if ($pid < 1 || $levelInput === false || $levelInput < 0 || $levelInput > 100) {
            $errors[] = 'Enter a paper level from 0 to 100.';
        } else {
            $stmt = $pdo->prepare("UPDATE printers SET paper_level = ? WHERE id = ?");
            $stmt->execute([$levelInput, $pid]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT id FROM printers WHERE id = ?');
                $check->execute([$pid]);
                if (!$check->fetchColumn()) $errors[] = 'The selected printer could not be found.';
                else $success[] = 'Paper level is already set to that value.';
            } else {
                $success[] = 'Paper level updated.';
            }
        }
    } elseif ($action === 'add_esp32') {
        $deviceId   = trim($_POST['device_id'] ?? '');
        $deviceName = trim($_POST['device_name'] ?? '');
        $apiKey     = trim($_POST['api_key'] ?? '');
        if ($apiKey === '') $apiKey = bin2hex(random_bytes(32));
        if ($deviceId === '' || $deviceName === '') {
            $errors[] = 'Device ID and device name are required.';
        } elseif (strlen($deviceId) > 50 || strlen($deviceName) > 100) {
            $errors[] = 'Device ID or name exceeds its maximum length.';
        } elseif (strlen($apiKey) > 64) {
            $errors[] = 'API key must be 64 characters or fewer.';
        } else {
            $duplicateStmt = $pdo->prepare('SELECT 1 FROM esp32_devices WHERE device_id = ? OR api_key = ? LIMIT 1');
            $duplicateStmt->execute([$deviceId, $apiKey]);
            if ($duplicateStmt->fetchColumn()) {
                $errors[] = 'That device ID or API key is already registered.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO esp32_devices (device_id,device_name,api_key) VALUES (?,?,?)");
                $stmt->execute([$deviceId, $deviceName, $apiKey]);
                $success[] = "ESP32 device '{$deviceName}' added.";
            }
        }
    } elseif ($action === 'delete_esp32') {
        $deviceId = (int)($_POST['esp32_row_id'] ?? 0);
        if ($deviceId < 1) {
            $errors[] = 'The selected ESP32 device is invalid.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM esp32_devices WHERE id = ?');
            $stmt->execute([$deviceId]);
            $success[] = $stmt->rowCount() ? 'ESP32 device removed.' : 'ESP32 device was not found.';
        }
    } else {
        $errors[] = 'Unknown printer-management action.';
    }
}

if (isset($_GET['edit']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_printer')) {
    $editPrinter = $pdo->prepare("SELECT * FROM printers WHERE id = ?");
    $editPrinter->execute([(int)($_GET['edit'] ?? $_POST['printer_id'] ?? 0)]);
    $editPrinter = $editPrinter->fetch();
}
$activeModal = null;
if ($errors && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $activeModal = match ($_POST['action'] ?? '') {
        'add_printer' => 'addPrinterModal',
        'edit_printer' => 'editPrinterModal',
        'add_esp32' => 'addEspModal',
        default => null,
    };
}

$printers = $pdo->query("SELECT p.*, e.device_name as esp32_name, e.connection_status as esp32_conn, e.led_status FROM printers p LEFT JOIN esp32_devices e ON p.esp32_id = e.id ORDER BY p.id")->fetchAll();
$esp32List = $pdo->query("SELECT * FROM esp32_devices ORDER BY device_name")->fetchAll();

$pageTitle = 'Printer Management';
$extraScripts = '<script src="' . BASE_URL . '/assets/js/esp32-serial.js?v=1"></script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_admin.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Printer Management<small>Manage printers and ESP32 devices</small></div>
        <div class="topbar-actions">
            <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('addEspModal')"><i class="fas fa-microchip"></i> Add ESP32</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addPrinterModal')"><i class="fas fa-plus"></i> Add Printer</button>
        </div>
    </div>

    <div class="page-content">
        <?php if (!empty($errors)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?= implode('<br>', array_map('h', $errors)) ?></div></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><div><?= implode('<br>', array_map('h', $success)) ?></div></div><?php endif; ?>

        <!-- Printers Grid -->
        <div style="margin-bottom:24px">
            <h2 style="font-size:1rem;font-weight:700;margin-bottom:14px;color:var(--gray-700)"><i class="fas fa-print"></i> Printers</h2>
            <div class="grid-3">
                <?php foreach ($printers as $p):
                    $ledCls = match($p['status']) { 'available' => 'led-green', 'printing' => 'led-yellow', default => 'led-red' };
                    $statusLabel = PRINTER_STATUSES[$p['status']]['label'] ?? ucfirst($p['status']);
                ?>
                <div class="card fade-in" style="<?= !$p['enabled'] ? 'opacity:.6' : '' ?>">
                    <div class="card-header">
                        <div style="flex:1">
                            <div style="font-weight:700;font-size:.95rem"><?= h($p['printer_name']) ?></div>
                            <div style="font-size:.72rem;color:var(--gray-400)"><?= h($p['printer_model'] ?? '') ?></div>
                        </div>
                        <span class="led-indicator <?= $ledCls ?>" style="padding:4px 10px;font-size:.72rem">
                            <span class="led-dot" style="width:8px;height:8px"></span><?= $statusLabel ?>
                        </span>
                    </div>
                    <div class="card-body" style="padding:14px">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
                            <div style="background:var(--gray-50);padding:10px;border-radius:8px;text-align:center">
                                <div style="font-size:1rem;font-weight:700"><?= $p['paper_level'] ?>%</div>
                                <div style="font-size:.65rem;color:var(--gray-400)">Paper Level</div>
                                <?php if ($p['paper_level'] < 20): ?><div style="font-size:.65rem;color:var(--danger)">⚠ LOW</div><?php endif; ?>
                            </div>
                            <div style="background:var(--gray-50);padding:10px;border-radius:8px;text-align:center">
                                <div style="font-size:1rem;font-weight:700"><?= $p['ink_level'] ?>%</div>
                                <div style="font-size:.65rem;color:var(--gray-400)">Ink Level</div>
                            </div>
                        </div>

                        <div style="font-size:.75rem;color:var(--gray-500);margin-bottom:10px">
                            <?php if ($p['location']): ?><div><i class="fas fa-map-marker-alt"></i> <?= h($p['location']) ?></div><?php endif; ?>
                            <?php if ($p['esp32_name']): ?>
                                <div style="margin-top:4px">
                                    <i class="fas fa-microchip"></i> <?= h($p['esp32_name']) ?>
                                    <span style="color:<?= $p['esp32_conn']==='connected'?'var(--led-green)':'var(--led-red)' ?>">
                                        (<?= $p['esp32_conn'] ?>)
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top:4px"><i class="fas fa-palette"></i> <?= $p['supports_color'] ? 'Color + B&W' : 'B&W Only' ?></div>
                        </div>

                        <!-- Status Control -->
                        <form method="POST" style="margin-bottom:8px">
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="printer_id" value="<?= $p['id'] ?>">
                            <div style="display:flex;gap:6px;align-items:center">
                                <select name="new_status" class="form-select" style="flex:1;font-size:.75rem;padding:6px 10px">
                                    <?php foreach (['available','printing','offline','error','disabled'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm">Set</button>
                            </div>
                        </form>

                        <!-- Paper level update -->
                        <form method="POST" style="margin-bottom:12px">
                            <input type="hidden" name="action" value="update_paper">
                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                            <input type="hidden" name="printer_id" value="<?= $p['id'] ?>">
                            <div style="display:flex;gap:6px;align-items:center">
                                <input type="number" name="paper_level" class="form-control" value="<?= $p['paper_level'] ?>" min="0" max="100" style="width:70px;font-size:.75rem;padding:6px 10px">
                                <span style="font-size:.72rem;color:var(--gray-400)">% Paper</span>
                                <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                            </div>
                        </form>

                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="toggle_printer">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="printer_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="enabled" value="<?= $p['enabled'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm <?= $p['enabled'] ? 'btn-warning' : 'btn-success' ?>">
                                    <i class="fas <?= $p['enabled'] ? 'fa-ban' : 'fa-check' ?>"></i> <?= $p['enabled'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Remove this printer?')">
                                <input type="hidden" name="action" value="delete_printer">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="printer_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($printers)): ?>
                <div class="card">
                    <div class="empty-state"><i class="fas fa-print"></i><h3>No printers configured</h3><p>Add a printer to get started.</p></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ESP32 Devices -->
        <div id="esp32-devices">
            <div class="esp32-tools-header">
                <div>
                    <h2 style="font-size:1rem;font-weight:700;margin:0;color:var(--gray-700)"><i class="fas fa-microchip"></i> ESP32 Devices</h2>
                    <p class="esp32-scan-help">Scan USB to detect a connected controller. Network status updates after its firmware contacts this website.</p>
                </div>
                <button type="button" id="scanEsp32Button" class="btn btn-primary">
                    <i class="fas fa-search" aria-hidden="true"></i> Scan USB
                </button>
            </div>
            <div id="esp32ScanStatus" class="alert alert-info esp32-scan-status" role="status" aria-live="polite" hidden></div>
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Device ID</th><th>Name</th><th>API Key</th><th>Status</th><th>LED</th><th>Last Heartbeat</th><th>IP</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($esp32List as $esp): ?>
                            <tr>
                                <td><code style="font-size:.72rem"><?= h($esp['device_id']) ?></code></td>
                                <td style="font-weight:500"><?= h($esp['device_name']) ?></td>
                                <td>
                                    <code style="font-size:.65rem;color:var(--gray-400)"><?= h(substr($esp['api_key'],0,12)) ?>…</code>
                                    <button type="button" class="btn btn-secondary btn-sm esp32-copy-key" data-copy-api-key="<?= h($esp['api_key']) ?>" aria-label="Copy API key for <?= h($esp['device_name']) ?>">Copy key</button>
                                </td>
                                <td>
                                    <?php if ($esp['connection_status']==='connected'): ?>
                                        <span class="badge badge-success">Connected</span>
                                    <?php elseif ($esp['connection_status']==='disconnected'): ?>
                                        <span class="badge badge-secondary">Disconnected</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $led = $esp['led_status'] ?? 'off';
                                    $ledCls = match($led) { 'green' => 'badge-success', 'yellow' => 'badge-warning', 'red' => 'badge-danger', 'blue' => 'badge-info', default => 'badge-secondary' };
                                    ?>
                                    <span class="badge <?= $ledCls ?>"><?= ucfirst($led) ?></span>
                                </td>
                                <td style="font-size:.75rem"><?= $esp['last_heartbeat'] ? formatDateTime($esp['last_heartbeat'], 'M d, h:i A') : '—' ?></td>
                                <td style="font-size:.75rem"><?= h($esp['ip_address'] ?? '—') ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Remove this ESP32 device? Its assigned printer will remain configured without a controller.');">
                                        <input type="hidden" name="action" value="delete_esp32">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="esp32_row_id" value="<?= (int)$esp['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($esp32List)): ?>
                            <tr><td colspan="8"><div class="empty-state" style="padding:24px"><i class="fas fa-microchip"></i><h3>No ESP32 devices</h3><p>Add a device when you have a controller ready to connect.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Add Printer Modal -->
<div class="modal-overlay <?= $activeModal === 'addPrinterModal' ? 'open' : '' ?>" id="addPrinterModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-print"></i> Add Printer</div>
            <button class="modal-close" onclick="closeModal('addPrinterModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_printer">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="form-group"><label class="form-label">Printer Name *</label><input type="text" name="printer_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Model</label><input type="text" name="printer_model" class="form-control" placeholder="e.g. HP LaserJet Pro"></div>
            <div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control" placeholder="e.g. Main Office"></div>
            <div class="form-group"><label class="form-label">Assign ESP32</label>
                <select name="esp32_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($esp32List as $e): ?><option value="<?= $e['id'] ?>"><?= h($e['device_name']) ?> (<?= h($e['device_id']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">IP Address</label><input type="text" name="ip_address" class="form-control" placeholder="192.168.1.x"></div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                <input type="checkbox" name="supports_color" id="supports_color" checked style="width:16px;height:16px;accent-color:var(--primary)">
                <label for="supports_color" style="font-size:.875rem">Supports Color Printing</label>
            </div>
            <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPrinterModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Printer</button>
            </div>
        </form>
    </div>
</div>

<!-- Add ESP32 Modal -->
<div class="modal-overlay <?= $activeModal === 'addEspModal' ? 'open' : '' ?>" id="addEspModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-microchip"></i> Add ESP32 Device</div>
            <button class="modal-close" onclick="closeModal('addEspModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_esp32">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <div class="form-group"><label class="form-label">Device ID *</label><input type="text" name="device_id" class="form-control" placeholder="e.g. ESP32-002" required></div>
            <div class="form-group"><label class="form-label">Device Name *</label><input type="text" name="device_name" class="form-control" placeholder="e.g. ESP32 Controller 2" required></div>
            <div class="form-group"><label class="form-label">API Key (leave blank to auto-generate)</label><input type="text" name="api_key" class="form-control" placeholder="Auto-generated if empty"><small class="esp32-api-key-help">After adding the controller, copy its key from the ESP32 device list into the firmware's DEVICE_KEY setting.</small></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addEspModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Device</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Printer Modal -->
<?php if ($editPrinter): ?>
<div class="modal-overlay <?= $activeModal === 'editPrinterModal' || !$activeModal ? 'open' : '' ?>" id="editPrinterModal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-edit"></i> Edit Printer</div>
            <a href="<?= BASE_URL ?>/admin/printers.php" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_printer">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="printer_id" value="<?= $editPrinter['id'] ?>">
            <div class="form-group"><label class="form-label">Printer Name *</label><input type="text" name="printer_name" class="form-control" value="<?= h($editPrinter['printer_name']) ?>" required></div>
            <div class="form-group"><label class="form-label">Model</label><input type="text" name="printer_model" class="form-control" value="<?= h($editPrinter['printer_model']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?= h($editPrinter['location']??'') ?>"></div>
            <div class="form-group"><label class="form-label">Assign ESP32</label>
                <select name="esp32_id" class="form-select">
                    <option value="">None</option>
                    <?php foreach ($esp32List as $e): ?><option value="<?= $e['id'] ?>" <?= $editPrinter['esp32_id']===$e['id']?'selected':'' ?>><?= h($e['device_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">IP Address</label><input type="text" name="ip_address" class="form-control" value="<?= h($editPrinter['ip_address']??'') ?>"></div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
                <input type="checkbox" name="supports_color" <?= $editPrinter['supports_color']?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--primary)">
                <label style="font-size:.875rem">Supports Color Printing</label>
            </div>
            <div class="form-group"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= h($editPrinter['notes']??'') ?></textarea></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <a href="<?= BASE_URL ?>/admin/printers.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
