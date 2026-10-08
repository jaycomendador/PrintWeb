<?php
/**
 * ESP32 Hardware Integration API
 * Smart Printing Payment System
 * 
 * Handles bidirectional communication with ESP32 microcontrollers
 * controlling physical LEDs (Green/Yellow/Red) and Buzzers.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Accept both GET and JSON POST
$method = $_SERVER['REQUEST_METHOD'];
$data = [];

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? $_POST;
} else {
    $data = $_GET;
}

$deviceKey = trim($data['device_key'] ?? $data['api_key'] ?? '');

if (empty($deviceKey)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing device_key parameter.'
    ]);
    exit;
}

// Locate device in esp32_devices
$stmt = $pdo->prepare("SELECT d.*, p.id as printer_id, p.printer_name, p.status as printer_status, p.current_job_id, p.paper_level 
                       FROM esp32_devices d 
                       LEFT JOIN printers p ON p.esp32_id = d.id 
                       WHERE d.api_key = ?");
$stmt->execute([$deviceKey]);
$device = $stmt->fetch();

if (!$device) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Unrecognized ESP32 device key.'
    ]);
    exit;
}

// Update device heartbeat and IP
$ip = $_SERVER['REMOTE_ADDR'] ?? ($data['ip_address'] ?? '127.0.0.1');
$updateHeartbeat = $pdo->prepare("UPDATE esp32_devices SET ip_address = ?, connection_status = 'connected', last_heartbeat = NOW() WHERE id = ?");
$updateHeartbeat->execute([$ip, $device['id']]);

// Accept optional printer-state reports from a connected device.
$reportedStatus = $data['printer_status'] ?? '';
if ($method === 'POST' && $reportedStatus !== '') {
    if (!in_array($reportedStatus, ['available', 'printing', 'offline', 'error'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid printer status.']);
        exit;
    }
    if (!empty($device['printer_id'])) {
        updatePrinterStatus((int)$device['printer_id'], $reportedStatus, $device['current_job_id'] ? (int)$device['current_job_id'] : null);
        $device['printer_status'] = $reportedStatus;
    }
}

// Check if ESP32 is reporting an event or state change.
$action = $data['action'] ?? '';

if ($action === 'report_event') {
    $event = $data['event'] ?? 'status_update';
    $message = $data['message'] ?? 'Hardware event received';
    $payload = $data['payload'] ?? null;
    $logType = $event === 'hardware_error' ? 'error' : 'status_change';
    logEsp32Event((int)$device['id'], $logType, $message, is_array($payload) ? $payload : null);

    // If hardware reported a paper jam or error
    if ($event === 'hardware_error' && !empty($device['printer_id'])) {
        updatePrinterStatus((int)$device['printer_id'], 'error');
        $pdo->prepare("UPDATE esp32_devices SET led_status = 'red', buzzer_command = 'alert_error' WHERE id = ?")->execute([$device['id']]);
        $device['printer_status'] = 'error';
        $device['led_status'] = 'red';
        $device['buzzer_command'] = 'alert_error';
    }
}

// Determine active job and LED / Buzzer status
$activeJob = null;
$printerStatus = $device['printer_status'] ?? 'available';
$ledStatus = $device['led_status'] ?? 'green';
$buzzerCmd = $device['buzzer_command'] ?? '';

if (!empty($device['current_job_id'])) {
    $jStmt = $pdo->prepare("SELECT id, job_id, file_name, print_status, selected_pages_count * copies AS output_pages FROM print_jobs WHERE id = ?");
    $jStmt->execute([$device['current_job_id']]);
    $activeJob = $jStmt->fetch();
}

// Determine appropriate LED if not explicitly overridden
if (empty($device['led_status']) || $device['led_status'] === 'green') {
    if ($printerStatus === 'printing') {
        $ledStatus = 'yellow';
    } elseif ($printerStatus === 'error' || $printerStatus === 'offline') {
        $ledStatus = 'red';
    } else {
        $ledStatus = 'green';
    }
}

// Clear buzzer command after reading so it triggers only once on microcontroller
if (!empty($buzzerCmd)) {
    $pdo->prepare("UPDATE esp32_devices SET buzzer_command = '' WHERE id = ?")->execute([$device['id']]);
}

$pollInterval = (int) getSetting('esp32_poll_interval', '3');

echo json_encode([
    'success'           => true,
    'device_id'         => (int)$device['id'],
    'device_name'       => $device['device_name'],
    'printer_id'        => $device['printer_id'] ? (int)$device['printer_id'] : null,
    'printer_name'      => $device['printer_name'] ?? null,
    'printer_status'    => $printerStatus,
    'paper_level'       => (int)($device['paper_level'] ?? 100),
    'led_status'        => $ledStatus,      // "green", "yellow", "red"
    'buzzer_command'    => $buzzerCmd,      // "beep_new_job", "beep_payment", "beep_complete", "alert_error", ""
    'active_job'        => $activeJob ? [
        'id'          => (int)$activeJob['id'],
        'job_id'      => $activeJob['job_id'],
        'file_name'   => $activeJob['file_name'],
        'total_pages' => (int)$activeJob['output_pages'],
        'status'      => $activeJob['print_status']
    ] : null,
    'poll_interval_sec' => $pollInterval,
    'timestamp'         => time()
], JSON_PRETTY_PRINT);
