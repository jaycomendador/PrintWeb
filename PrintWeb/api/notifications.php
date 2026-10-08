<?php
/**
 * Notifications API Endpoint
 * Smart Printing Payment System
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_read') {
    $notifId = (int)($_POST['id'] ?? 0);
    if ($notifId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notifId, $userId]);
    } else {
        markNotificationsRead($userId);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Default: return unread count and latest 5 notifications
$unreadCount = getUnreadNotificationCount($userId);
$latest = getUserNotifications($userId, 5);

echo json_encode([
    'success'      => true,
    'unread_count' => $unreadCount,
    'notifications'=> $latest
]);
