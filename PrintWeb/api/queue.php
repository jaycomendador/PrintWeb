<?php
/**
 * Print Queue API Endpoint
 * Smart Printing Payment System
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$jobIdStr = $_GET['job_id'] ?? '';

if (!empty($jobIdStr)) {
    $job = getJobByJobId($jobIdStr);
    if (!$job) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Job not found.']);
        exit;
    }

    // If user is logged in and not admin, ensure they own the job
    if (isLoggedIn() && !in_array($_SESSION['user_role'] ?? '', ADMIN_ROLES)) {
        if ($job['user_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
            exit;
        }
    }

    // Get position in queue if queued
    $position = 0;
    if (in_array($job['print_status'], ['queued', 'waiting'])) {
        $qStmt = $pdo->prepare("
            SELECT COUNT(*) FROM print_jobs 
            WHERE print_status IN ('queued', 'waiting') 
              AND (queue_number < ? OR (queue_number = ? AND created_at <= ?))
        ");
        $qStmt->execute([$job['queue_number'], $job['queue_number'], $job['created_at']]);
        $position = (int) $qStmt->fetchColumn();
    }

    echo json_encode([
        'success'  => true,
        'job'      => $job,
        'position' => $position,
        'badge'    => printStatusBadge($job['print_status']),
        'timestamp'=> time()
    ]);
    exit;
}

// Return active queue list if requested
$stmt = $pdo->query("
    SELECT pj.id, pj.job_id, pj.file_name, pj.paper_size, pj.print_type, pj.total_pages, 
           pj.print_status, pj.queue_number, pj.created_at,
           pj.selected_pages_count * pj.copies AS output_pages, p.printer_name, u.full_name
    FROM print_jobs pj
    JOIN users u ON pj.user_id = u.id
    LEFT JOIN printers p ON pj.printer_id = p.id
    WHERE pj.print_status IN ('queued', 'waiting', 'printing')
    ORDER BY pj.queue_number ASC
");
$queue = $stmt->fetchAll();

echo json_encode([
    'success'   => true,
    'queue'     => $queue,
    'count'     => count($queue),
    'timestamp' => time()
]);
