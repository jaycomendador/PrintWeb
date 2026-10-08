<?php
/**
 * Application Configuration
 * Smart Printing Payment System
 */

// Base URL - adjust if needed
define('BASE_URL', '/PrintWeb');
define('APP_URL', rtrim((string)(getenv('PRINTWEB_APP_URL') ?: 'http://localhost'), '/'));
define('APP_NAME', 'PrintWeb');
define('APP_TAGLINE', 'Smart Printing Payment System');
define('APP_VERSION', '1.0.0');

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Session config
define('SESSION_LIFETIME', 86400); // 24 hours

// Roles
define('ROLES', [
    'student'  => 'Student',
    'faculty'  => 'Faculty / Teacher',
    'staff'    => 'Staff',
    'other'    => 'Other Authorized User',
    'operator' => 'Operator',
    'admin'    => 'Administrator',
]);

// Admin/operator roles
define('ADMIN_ROLES', ['admin', 'operator']);

// Print types
define('PRINT_TYPES', [
    'bw'    => 'Black & White',
    'color' => 'Color',
]);

// Paper sizes
define('PAPER_SIZES', ['A4', 'Letter', 'Legal']);

// Payment statuses
define('PAYMENT_STATUSES', [
    'pending'    => ['label' => 'Pending',    'class' => 'badge-warning'],
    'processing' => ['label' => 'Processing', 'class' => 'badge-info'],
    'successful' => ['label' => 'Successful', 'class' => 'badge-success'],
    'failed'     => ['label' => 'Failed',     'class' => 'badge-danger'],
    'cancelled'  => ['label' => 'Cancelled',  'class' => 'badge-secondary'],
]);

// Print statuses
define('PRINT_STATUSES', [
    'draft'            => ['label' => 'Draft',            'class' => 'badge-secondary'],
    'awaiting_payment' => ['label' => 'Awaiting Payment', 'class' => 'badge-warning'],
    'queued'           => ['label' => 'Queued',           'class' => 'badge-info'],
    'waiting'          => ['label' => 'Waiting',          'class' => 'badge-info'],
    'printing'         => ['label' => 'Printing',         'class' => 'badge-primary'],
    'completed'        => ['label' => 'Completed',        'class' => 'badge-success'],
    'failed'           => ['label' => 'Failed',           'class' => 'badge-danger'],
    'cancelled'        => ['label' => 'Cancelled',        'class' => 'badge-secondary'],
]);

// Printer statuses
define('PRINTER_STATUSES', [
    'available' => ['label' => 'Available', 'class' => 'status-green', 'led' => 'green'],
    'printing'  => ['label' => 'Printing',  'class' => 'status-yellow', 'led' => 'yellow'],
    'offline'   => ['label' => 'Offline',   'class' => 'status-red', 'led' => 'red'],
    'error'     => ['label' => 'Error',     'class' => 'status-red', 'led' => 'red'],
    'disabled'  => ['label' => 'Disabled',  'class' => 'status-gray', 'led' => 'off'],
]);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// Require DB
require_once __DIR__ . '/db.php';

// Helper: check if logged in
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper: require login
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/?auth=login');
        exit;
    }
}

// Helper: require admin
function requireAdmin(): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'], ADMIN_ROLES)) {
        header('Location: ' . BASE_URL . '/user/dashboard.php');
        exit;
    }
}

// Helper: redirect
function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}
