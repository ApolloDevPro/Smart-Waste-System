<?php
class Notification {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    public function create($user_id, $title, $message, $type = 'general', $related_id = null, $action_url = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO notifications (user_id, title, message, notification_type, related_id, action_url) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssis", $user_id, $title, $message, $type, $related_id, $action_url);
        
        if ($stmt->execute()) {
            $notification_id = $this->conn->insert_id;
            $this->sendBrowserNotification($user_id, $title, $message);
            return $notification_id;
        }
        return false;
    }
    
    public function createForRole($role, $title, $message, $type = 'general', $related_id = null, $action_url = null) {
        $users = $this->conn->query("SELECT user_id FROM users WHERE role='$role' AND status='Active'");
        $count = 0;
        while ($user = $users->fetch_assoc()) {
            if ($this->create($user['user_id'], $title, $message, $type, $related_id, $action_url)) {
                $count++;
            }
        }
        return $count;
    }
    
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = CURRENT_TIMESTAMP 
            WHERE notification_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $notification_id, $user_id);
        return $stmt->execute();
    }
    
    public function markAllAsRead($user_id) {
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }
    
    public function getUnreadCount($user_id) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'];
    }
    
    public function getRecent($user_id, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function delete($notification_id, $user_id) {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE notification_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $notification_id, $user_id);
        return $stmt->execute();
    }
    
    public function deleteOld($days = 30) {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param("i", $days);
        return $stmt->execute();
    }
    
    public function sendEmail($user_id, $subject, $message) {
        // Check if email is enabled (set to false for local development)
        $email_enabled = false; // Set to true when mail server is configured
        
        $user = $this->conn->query("SELECT email, full_name FROM users WHERE user_id=$user_id")->fetch_assoc();
        
        if (!$user) return false;
        
        $to = $user['email'];
        $status = 'pending';
        $error_message = null;
        
        if ($email_enabled) {
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Smart Waste Management <noreply@smartwaste.com>" . "\r\n";
            
            $email_body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; text-align: center; }
                    .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Smart Waste Management System</h2>
                    </div>
                    <div class='content'>
                        <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                        <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from Smart Waste Management System</p>
                        <p>&copy; " . date('Y') . " Smart Waste Management. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            try {
                if (@mail($to, $subject, $email_body, $headers)) {
                    $status = 'sent';
                } else {
                    $status = 'failed';
                    $error_message = 'Mail server not configured';
                }
            } catch (Exception $e) {
                $status = 'failed';
                $error_message = $e->getMessage();
            }
        } else {
            $status = 'pending';
            $error_message = 'Email disabled (local development mode)';
        }
        
        $log_stmt = $this->conn->prepare("
            INSERT INTO email_logs (user_id, recipient_email, subject, message, status, error_message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->bind_param("isssss", $user_id, $to, $subject, $message, $status, $error_message);
        $log_stmt->execute();
        
        return $status === 'sent';
    }
    
    private function sendBrowserNotification($user_id, $title, $message) {
        return true;
    }
}
?>
