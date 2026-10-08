<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$jobId = filter_input(INPUT_GET, 'job', FILTER_VALIDATE_INT);
if (!$jobId || $jobId < 1) {
    http_response_code(400);
    exit('Invalid print job.');
}

$stmt = $pdo->prepare('SELECT original_file_name, file_path FROM print_jobs WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    exit('Print job not found.');
}

$uploadRoot = realpath(UPLOAD_DIR);
$filePath = realpath($job['file_path']);
if (!$uploadRoot || !$filePath || !is_file($filePath)
    || strncasecmp($filePath, $uploadRoot . DIRECTORY_SEPARATOR, strlen($uploadRoot . DIRECTORY_SEPARATOR)) !== 0
    || !is_readable($filePath)) {
    http_response_code(404);
    exit('Print document is unavailable.');
}

$extension = strtolower(pathinfo($job['original_file_name'], PATHINFO_EXTENSION));
$inlineTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain; charset=utf-8',
];
$contentType = $inlineTypes[$extension] ?? 'application/octet-stream';
$disposition = isset($inlineTypes[$extension]) ? 'inline' : 'attachment';
$safeFileName = str_replace(["\r", "\n", '"'], '', basename($job['original_file_name']));

header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($safeFileName, '\\') . '"; filename*=UTF-8\'\'' . rawurlencode($safeFileName));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;
