<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$errors = $success = [];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST['settings'] ?? [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");

        foreach ($settings as $key => $value) {
            $stmt->execute([$key, trim($value)]);
        }
        $pdo->commit();
        $success[] = "System settings updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Failed to update settings: " . $e->getMessage();
    }
}

// Fetch all settings from database
$allSettings = [];
$sStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $sStmt->fetch()) {
    $allSettings[$row['setting_key']] = $row['setting_value'];
}

function settingVal(string $key, string $default = ''): string {
    global $allSettings;
    return $allSettings[$key] ?? $default;
}

$pageTitle = 'System Settings';
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
                    <span class="active">System Settings</span>
                </div>
            </div>
            <div class="topbar-right">
                <span class="badge badge-info"><i class="fas fa-sliders-h"></i> Configuration</span>
            </div>
        </header>

        <div class="page-body">
            <div class="page-header flex-between mb-4">
                <div>
                    <h1 class="page-title"><i class="fas fa-cogs text-primary"></i> System Settings & Configuration</h1>
                    <p class="text-muted">Configure printing rates, paper dimensions, upload limitations, and hardware parameters</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success mb-4">
                    <ul class="mb-0">
                        <?php foreach ($success as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="grid-2 settings-grid mb-4">
                    <!-- Pricing Configuration -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tags text-primary"></i> Printing Price Rates (Per Page)</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-section-title">Black & White Rates</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">A4 (B&W)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= settingVal('currency_symbol', '₱') ?></span>
                                        <input type="number" step="0.25" name="settings[bw_price_a4]" class="form-control" value="<?= htmlspecialchars(settingVal('bw_price_a4', '2.00')) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Letter (B&W)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= settingVal('currency_symbol', '₱') ?></span>
                                        <input type="number" step="0.25" name="settings[bw_price_letter]" class="form-control" value="<?= htmlspecialchars(settingVal('bw_price_letter', '2.00')) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Legal (B&W)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= settingVal('currency_symbol', '₱') ?></span>
                                        <input type="number" step="0.25" name="settings[bw_price_legal]" class="form-control" value="<?= htmlspecialchars(settingVal('bw_price_legal', '3.00')) ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section-title">Color Rates</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">A4 (Color)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= settingVal('currency_symbol', '₱') ?></span>
                                        <input type="number" step="0.25" name="settings[color_price_a4]" class="form-control" value="<?= htmlspecialchars(settingVal('color_price_a4', '8.00')) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Letter (Color)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= settingVal('currency_symbol', '₱') ?></span>
                                        <input type="number" step="0.25" name="settings[color_price_letter]" class="form-control" value="<?= htmlspecialchars(settingVal('color_price_letter', '8.00')) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Legal (Color)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?= settingVal('currency_symbol', '₱') ?></span>
                                        <input type="number" step="0.25" name="settings[color_price_legal]" class="form-control" value="<?= htmlspecialchars(settingVal('color_price_legal', '10.00')) ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- General & Currency Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-globe text-info"></i> General & Currency Configuration</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label class="form-label">System Name</label>
                                <input type="text" name="settings[site_name]" class="form-control" value="<?= htmlspecialchars(settingVal('site_name', 'PrintWeb Smart Printing System')) ?>" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= htmlspecialchars(settingVal('currency_symbol', '₱')) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Currency Code</label>
                                    <input type="text" name="settings[currency_code]" class="form-control" value="<?= htmlspecialchars(settingVal('currency_code', 'PHP')) ?>" required>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Auto-Assign Available Printer</label>
                                <select name="settings[auto_assign_printer]" class="form-control">
                                    <option value="1" <?= settingVal('auto_assign_printer', '1') === '1' ? 'selected' : '' ?>>Enabled (Automatic routing to next available printer)</option>
                                    <option value="0" <?= settingVal('auto_assign_printer', '1') === '0' ? 'selected' : '' ?>>Disabled (Manual operator assignment only)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid-2 settings-grid mb-4">
                    <!-- File Upload Policies -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-file-upload text-warning"></i> File Upload Policies</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label class="form-label">Allowed File Extensions (comma separated)</label>
                                <input type="text" name="settings[allowed_file_types]" class="form-control" value="<?= htmlspecialchars(settingVal('allowed_file_types', 'pdf,doc,docx,ppt,pptx,png,jpg,jpeg')) ?>" required>
                                <small class="text-muted">Example: pdf, doc, docx, ppt, pptx, png, jpg</small>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Maximum Upload File Size (MB)</label>
                                <input type="number" name="settings[max_file_size_mb]" class="form-control" value="<?= htmlspecialchars(settingVal('max_file_size_mb', '50')) ?>" required min="1" max="500">
                            </div>
                        </div>
                    </div>

                    <!-- Hardware & ESP32 Integration Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-microchip text-danger"></i> ESP32 Hardware Integration</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label class="form-label">Hardware Poll Interval (Seconds)</label>
                                <input type="number" name="settings[esp32_poll_interval]" class="form-control" value="<?= htmlspecialchars(settingVal('esp32_poll_interval', '3')) ?>" required min="1" max="60">
                                <small class="text-muted">How often ESP32 microcontroller polls the server for status</small>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Device Offline Timeout (Seconds)</label>
                                <input type="number" name="settings[esp32_offline_timeout]" class="form-control" value="<?= htmlspecialchars(settingVal('esp32_offline_timeout', '30')) ?>" required min="10" max="300">
                                <small class="text-muted">Time without heartbeat before device is marked Offline</small>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label">Buzzer Alert on Job Completion</label>
                                <select name="settings[esp32_buzzer_enabled]" class="form-control">
                                    <option value="1" <?= settingVal('esp32_buzzer_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled (Beep when print is completed)</option>
                                    <option value="0" <?= settingVal('esp32_buzzer_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-5">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save All Settings</button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
