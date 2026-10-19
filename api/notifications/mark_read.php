<?php
/**
 * POST /api/notifications/mark_read.php
 * Marks all of the current user's notifications as read. Called via a
 * small fetch() when the notification bell dropdown is opened.
 */
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $stmt->execute([currentUserId()]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    logError('mark_notifications_read', $e);
    http_response_code(500);
    echo json_encode(['error' => 'Unable to update notifications.']);
}
