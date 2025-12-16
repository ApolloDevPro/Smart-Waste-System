<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include('../db_connect.php');
require_once('../includes/Notification.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    // Get request details
    $request = $conn->query("
        SELECT wr.*, u.full_name, u.user_id 
        FROM waste_requests wr 
        JOIN users u ON wr.user_id = u.user_id 
        WHERE wr.request_id = $request_id
    ")->fetch_assoc();
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    // Update status
    $update = $conn->query("UPDATE waste_requests SET status='$new_status' WHERE request_id=$request_id");
    
    if ($update) {
        $notif = new Notification($conn);
        
        // Notification messages based on status
        $messages = [
            'Approved' => [
                'title' => 'Request Approved',
                'message' => "Your waste collection request (#$request_id) has been approved and will be processed soon."
            ],
            'In Progress' => [
                'title' => 'Collection In Progress',
                'message' => "Your waste collection request (#$request_id) is now in progress. Our team is on the way!"
            ],
            'Collected' => [
                'title' => 'Waste Collected Successfully',
                'message' => "Your waste has been collected successfully for request #$request_id. Thank you for using our service!"
            ],
            'Rejected' => [
                'title' => 'Request Rejected',
                'message' => "Unfortunately, your waste collection request (#$request_id) has been rejected. Please contact us for more information."
            ],
            'Canceled' => [
                'title' => 'Request Canceled',
                'message' => "Your waste collection request (#$request_id) has been canceled."
            ]
        ];
        
        if (isset($messages[$new_status])) {
            // Notify user
            $notif->create(
                $request['user_id'],
                $messages[$new_status]['title'],
                $messages[$new_status]['message'],
                'request',
                $request_id,
                '../user/view_requests.php'
            );
            
            // Send email
            $notif->sendEmail(
                $request['user_id'],
                $messages[$new_status]['title'],
                $messages[$new_status]['message'] . "\n\nRequest Details:\nWaste Type: {$request['waste_type']}\nQuantity: {$request['quantity_kg']} kg\nStatus: $new_status"
            );
        }
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
