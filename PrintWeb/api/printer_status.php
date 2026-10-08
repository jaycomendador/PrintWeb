<?php
/**
 * Printer Status Polling API
 * Smart Printing Payment System
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

refreshEsp32ConnectionStatuses();

// Allow public/authenticated read
$printerId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($printerId) {
    $stmt = $pdo->prepare("
        SELECT p.*, d.led_status, d.connection_status as esp32_status, d.last_heartbeat,
               pj.job_id as active_job_id, pj.file_name as active_file_name, u.full_name as active_user_name
        FROM printers p
        LEFT JOIN esp32_devices d ON p.esp32_id = d.id
        LEFT JOIN print_jobs pj ON p.current_job_id = pj.id
        LEFT JOIN users u ON pj.user_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$printerId]);
    $printer = $stmt->fetch();

    if (!$printer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Printer not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'printer' => $printer
    ]);
    exit;
}

// All printers summary
$stmt = $pdo->query("
    SELECT p.*, d.led_status, d.connection_status as esp32_status, d.last_heartbeat,
           pj.job_id as active_job_id, pj.file_name as active_file_name
    FROM printers p
    LEFT JOIN esp32_devices d ON p.esp32_id = d.id
    LEFT JOIN print_jobs pj ON p.current_job_id = pj.id
    WHERE p.enabled = 1
    ORDER BY p.id ASC
");
$printers = $stmt->fetchAll();

$queueCounts = $pdo->query("
    SELECT 
        COUNT(CASE WHEN print_status = 'printing' THEN 1 END) as printing,
        COUNT(CASE WHEN print_status IN ('queued','waiting') THEN 1 END) as queued,
        COUNT(CASE WHEN print_status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 END) as completed_today
    FROM print_jobs
")->fetch();

echo json_encode([
    'success'      => true,
    'printers'     => $printers,
    'queue_counts' => $queueCounts,
    'timestamp'    => time()
]);
