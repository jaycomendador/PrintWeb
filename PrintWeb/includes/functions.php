<?php
/**
 * Helper Functions
 * Smart Printing Payment System
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Generate a unique job ID
 */
function generateJobId(): string {
    return 'JOB-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Ymd');
}

/**
 * Generate a unique payment ID
 */
function generatePaymentId(): string {
    return 'PAY-' . strtoupper(substr(uniqid(), -8));
}

/**
 * Generate a unique user ID based on role
 */
function generateUserId(string $role): string {
    $prefix = match($role) {
        'student'  => 'STU',
        'faculty'  => 'FAC',
        'staff'    => 'STF',
        'admin'    => 'ADM',
        'operator' => 'OPR',
        default    => 'USR',
    };
    return $prefix . '-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Get system setting value
 */
function getSetting(string $key, string $default = ''): string {
    global $pdo;
    static $settingsCache = [];
    if (!isset($settingsCache[$key])) {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $settingsCache[$key] = $row ? $row['setting_value'] : $default;
    }
    return $settingsCache[$key];
}

/**
 * Calculate price per page
 */
function getPricePerPage(string $printType, string $paperSize): float {
    $key = ($printType === 'color' ? 'color_price_' : 'bw_price_') . strtolower($paperSize);
    return (float) getSetting($key, ($printType === 'color' ? '8.00' : '2.00'));
}

/**
 * Calculate total cost for a print job
 */
function calculateTotalCost(int $pageCount, int $copies, string $printType, string $paperSize): float {
    $pricePerPage = getPricePerPage($printType, $paperSize);
    return round($pricePerPage * $pageCount * $copies, 2);
}

/**
 * Format file size for display
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/**
 * Format currency
 */
function formatCurrency(float $amount): string {
    $symbol = getSetting('currency_symbol', '₱');
    return $symbol . number_format($amount, 2);
}

/**
 * Get current logged-in user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Create a notification
 */
function createNotification(int $userId, string $type, string $title, string $message, ?int $jobId = null): void {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, job_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $title, $message, $jobId]);
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount(int $userId): int {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get notifications for user
 */
function getUserNotifications(int $userId, int $limit = 10): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Mark notifications as read
 */
function markNotificationsRead(int $userId): void {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}

/**
 * Get next queue number
 */
function getNextQueueNumber(): int {
    global $pdo;
    $stmt = $pdo->query("SELECT MAX(queue_number) FROM print_jobs WHERE DATE(created_at) = CURDATE()");
    $max = (int) $stmt->fetchColumn();
    return $max + 1;
}

/**
 * Get available printer
 */
function getAvailablePrinter(bool $requiresColor = false): ?array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM printers
        WHERE status = 'available' AND enabled = 1
          AND (? = 0 OR supports_color = 1)
        ORDER BY id ASC LIMIT 1
    ");
    $stmt->execute([$requiresColor ? 1 : 0]);
    return $stmt->fetch() ?: null;
}

/**
 * Update printer status
 */
function updatePrinterStatus(int $printerId, string $status, ?int $jobId = null): void {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE printers SET status = ?, current_job_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $jobId, $printerId]);
    // Log to printer history
    $stmt2 = $pdo->prepare("INSERT INTO printer_history (printer_id, job_id, action, status_to) VALUES (?, ?, ?, ?)");
    $stmt2->execute([$printerId, $jobId, 'status_change', $status]);
}

/**
 * Get print job by job_id string
 */
function getJobByJobId(string $jobId): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT pj.*, u.full_name, u.email, u.role, p.printer_name 
                           FROM print_jobs pj
                           JOIN users u ON pj.user_id = u.id
                           LEFT JOIN printers p ON pj.printer_id = p.id
                           WHERE pj.job_id = ?");
    $stmt->execute([$jobId]);
    return $stmt->fetch() ?: null;
}

/**
 * Log ESP32 event
 */
function logEsp32Event(int $deviceId, string $logType, string $message, ?array $payload = null): void {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO esp32_logs (device_id, log_type, message, payload) VALUES (?, ?, ?, ?)");
    $stmt->execute([$deviceId, $logType, $message, $payload ? json_encode($payload) : null]);
}

/**
 * Send command to ESP32 device
 */
function sendEsp32Command(int $deviceId, string $ledStatus, string $buzzerCommand = ''): void {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE esp32_devices
        SET led_status = ?, buzzer_command = ?, updated_at = NOW()
        WHERE id = ? AND connection_status = 'connected'
    ");
    $stmt->execute([$ledStatus, $buzzerCommand, $deviceId]);
}

function notifyJobStatusChange(int $userId, int $jobId, string $publicJobId, string $status, ?string $details = null): void {
    $statusMessages = [
        'awaiting_payment' => ['system', 'Payment Required', 'Your print job is waiting for payment.'],
        'queued' => ['job_queued', 'Print Job Queued', 'Your print job has entered the print queue.'],
        'waiting' => ['job_queued', 'Print Job Waiting', 'Your print job is waiting for an available printer.'],
        'printing' => ['printing_started', 'Printing Started', 'Your print job is now printing.'],
        'completed' => ['printing_completed', 'Print Job Completed', 'Your print job has completed.'],
        'failed' => ['job_failed', 'Print Job Failed', 'Your print job could not be completed.'],
        'cancelled' => ['job_cancelled', 'Print Job Cancelled', 'Your print job was cancelled.'],
    ];
    if (!isset($statusMessages[$status])) {
        throw new InvalidArgumentException('Unsupported print job status notification.');
    }

    [$type, $title, $message] = $statusMessages[$status];
    $suffix = $details ? ' ' . $details : '';
    createNotification($userId, $type, $title, "Job {$publicJobId}: {$message}{$suffix}", $jobId);
    notifyAdmins('system', "Print Job Status Changed: " . ucfirst(str_replace('_', ' ', $status)), "Job {$publicJobId} status changed to {$status}.{$suffix}", $jobId);
}

function notifyPaymentStatusChange(int $userId, int $jobId, string $publicJobId, string $status, string $method): void {
    $statusMessages = [
        'pending' => ['system', 'Payment Pending', 'Payment is pending confirmation.'],
        'processing' => ['system', 'Payment Processing', 'Payment is being processed.'],
        'successful' => ['payment_success', 'Payment Successful', 'Payment was confirmed.'],
        'failed' => ['system', 'Payment Failed', 'Payment could not be confirmed.'],
        'cancelled' => ['system', 'Payment Cancelled', 'Payment was cancelled.'],
        'refunded' => ['system', 'Payment Refunded', 'Payment was refunded.'],
    ];
    if (!isset($statusMessages[$status])) {
        throw new InvalidArgumentException('Unsupported payment status notification.');
    }

    [$type, $title, $message] = $statusMessages[$status];
    $methodLabel = $method === 'simulation' ? 'online payment simulation' : $method . ' payment';
    $message = "Job {$publicJobId}: {$message} Method: {$methodLabel}.";
    createNotification($userId, $type, $title, $message, $jobId);
    notifyAdmins('system', "Payment Status Changed: " . ucfirst($status), $message, $jobId);
}

/**
 * Mark controllers offline when their heartbeat has expired.
 */
function refreshEsp32ConnectionStatuses(): void {
    global $pdo;
    $pdo->exec("
        UPDATE esp32_devices
        SET connection_status = 'disconnected'
        WHERE connection_status = 'connected'
          AND (last_heartbeat IS NULL OR last_heartbeat < DATE_SUB(NOW(), INTERVAL 15 SECOND))
    ");
}

/**
 * Sanitize output
 */
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Get badge HTML for print status
 */
function printStatusBadge(string $status): string {
    $statuses = PRINT_STATUSES;
    $info = $statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-secondary'];
    return '<span class="badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}

/**
 * Get badge HTML for payment status
 */
function paymentStatusBadge(string $status): string {
    $statuses = PAYMENT_STATUSES;
    $info = $statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-secondary'];
    return '<span class="badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}

/**
 * Get printer status badge
 */
function printerStatusBadge(string $status): string {
    $statuses = PRINTER_STATUSES;
    $info = $statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'status-gray'];
    return '<span class="printer-status ' . $info['class'] . '"><i class="fas fa-circle"></i> ' . $info['label'] . '</span>';
}

/**
 * Admin notification broadcast (to all admins)
 */
function notifyAdmins(string $type, string $title, string $message, ?int $jobId = null): void {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','operator') AND status = 'active'");
    $admins = $stmt->fetchAll();
    foreach ($admins as $admin) {
        createNotification($admin['id'], $type, $title, $message, $jobId);
    }
}

/**
 * Format datetime for display
 */
function formatDateTime(?string $datetime, string $format = 'M d, Y h:i A'): string {
    if (!$datetime) return '—';
    return date($format, strtotime($datetime));
}

/**
 * Get total stats for user dashboard
 */
function getUserStats(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_prints,
            SUM(CASE WHEN print_status IN ('queued','waiting','printing','awaiting_payment') THEN 1 ELSE 0 END) as pending_prints,
            SUM(CASE WHEN print_status = 'completed' THEN 1 ELSE 0 END) as completed_prints,
            SUM(CASE WHEN print_status = 'printing' THEN 1 ELSE 0 END) as active_prints,
            SUM(CASE WHEN pj.payment_status = 'successful' AND NOT EXISTS (
                SELECT 1 FROM payments pay
                WHERE pay.job_id = pj.id AND pay.payment_status = 'successful'
                  AND pay.payment_method = 'simulation'
            ) THEN pj.total_cost ELSE 0 END) as total_spent
        FROM print_jobs pj WHERE pj.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

/**
 * Check if uploaded file type is allowed
 */
function isAllowedFileType(string $filename): bool {
    $allowedTypes = explode(',', getSetting('allowed_file_types', 'pdf,doc,docx,ppt,pptx'));
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, array_map('trim', $allowedTypes));
}

/**
 * Get max file size in bytes
 */
function getMaxFileSizeBytes(): int {
    return (int) getSetting('max_file_size_mb', '50') * 1024 * 1024;
}
