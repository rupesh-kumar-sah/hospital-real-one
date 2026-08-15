<?php
/**
 * Hospital Management System — Notifications API
 * AJAX endpoints for notification management
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$db = getDB();

switch ($action) {
    case 'mark_all_read':
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([getUserId()]);
        echo json_encode(['success' => true]);
        break;
        
    case 'mark_read':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, getUserId()]);
        echo json_encode(['success' => true]);
        break;
        
    case 'count':
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([getUserId()]);
        echo json_encode($stmt->fetch());
        break;
        
    case 'recent':
        $limit = min((int)($_GET['limit'] ?? 5), 20);
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([getUserId(), $limit]);
        echo json_encode($stmt->fetchAll());
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
