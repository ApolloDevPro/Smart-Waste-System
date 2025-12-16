<?php
session_start();
require_once(__DIR__ . '/../db_connect.php');
require_once(__DIR__ . '/Notification.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$notif = new Notification($conn);
$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_unread_count':
        $count = $notif->getUnreadCount($user_id);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'get_recent':
        $limit = $_GET['limit'] ?? 10;
        $notifications = $notif->getRecent($user_id, $limit);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'mark_read':
        $notification_id = $_POST['notification_id'] ?? 0;
        $result = $notif->markAsRead($notification_id, $user_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'mark_all_read':
        $result = $notif->markAllAsRead($user_id);
        echo json_encode(['success' => $result]);
        break;
        
    case 'delete':
        $notification_id = $_POST['notification_id'] ?? 0;
        $result = $notif->delete($notification_id, $user_id);
        echo json_encode(['success' => $result]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
