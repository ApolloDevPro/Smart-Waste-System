<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("ERROR: You must be logged in. Please <a href='login.php'>login here</a>");
}

include('db_connect.php');
require_once('includes/Notification.php');

$user_id = $_SESSION['user_id'];
$notif = new Notification($conn);

echo "<h1>Notification System Debug Page</h1>";
echo "<hr>";

// Display session info
echo "<h2>1. Session Information</h2>";
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "User Name: " . ($_SESSION['name'] ?? 'N/A') . "<br>";
echo "User Role: " . ($_SESSION['role'] ?? 'N/A') . "<br>";
echo "<hr>";

// Test database connection
echo "<h2>2. Database Connection</h2>";
if ($conn->ping()) {
    echo "✓ Database connected successfully<br>";
} else {
    echo "✗ Database connection failed<br>";
}
echo "<hr>";

// Check if notifications table exists
echo "<h2>3. Check Notifications Table</h2>";
$table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($table_check && $table_check->num_rows > 0) {
    echo "✓ Notifications table exists<br>";
    
    // Count total notifications
    $total = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
    echo "Total notifications in database: $total<br>";
    
    // Count user's notifications
    $user_total = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id=$user_id")->fetch_assoc()['count'];
    echo "Your notifications: $user_total<br>";
    
    // Count unread
    $unread = $notif->getUnreadCount($user_id);
    echo "Your unread notifications: <strong>$unread</strong><br>";
} else {
    echo "✗ Notifications table does NOT exist. Please import the SQL file!<br>";
}
echo "<hr>";

// Display recent notifications
echo "<h2>4. Your Recent Notifications</h2>";
$recent = $notif->getRecent($user_id, 10);
if (count($recent) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Message</th><th>Type</th><th>Read</th><th>Created</th></tr>";
    foreach ($recent as $n) {
        $read_status = $n['is_read'] ? '✓ Read' : '✗ Unread';
        echo "<tr>";
        echo "<td>{$n['notification_id']}</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>{$n['message']}</td>";
        echo "<td>{$n['notification_type']}</td>";
        echo "<td>$read_status</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No notifications found for your account.<br>";
}
echo "<hr>";

// Test creating a notification
echo "<h2>5. Test Create Notification</h2>";
if (isset($_GET['create_test'])) {
    $result = $notif->create(
        $user_id,
        "Test Notification",
        "This is a test notification created at " . date('H:i:s'),
        'general'
    );
    
    if ($result) {
        echo "✓ Test notification created successfully! ID: $result<br>";
        echo "<a href='test_notifications.php'>Refresh to see it</a><br>";
    } else {
        echo "✗ Failed to create test notification<br>";
    }
} else {
    echo "<a href='test_notifications.php?create_test=1' style='padding: 10px; background: #11998e; color: white; text-decoration: none; border-radius: 5px;'>Create Test Notification</a><br>";
}
echo "<hr>";

// Test AJAX endpoint
echo "<h2>6. Test AJAX Endpoint</h2>";
echo "<button onclick='testAjax()' style='padding: 10px; background: #11998e; color: white; border: none; border-radius: 5px; cursor: pointer;'>Test AJAX Call</button>";
echo "<div id='ajax-result' style='margin-top: 10px; padding: 10px; background: #f0f0f0; border-radius: 5px;'></div>";

echo "<script>
function testAjax() {
    document.getElementById('ajax-result').innerHTML = 'Loading...';
    
    fetch('includes/notification_handler.php?action=get_unread_count')
        .then(response => response.json())
        .then(data => {
            console.log('AJAX Response:', data);
            if (data.success) {
                document.getElementById('ajax-result').innerHTML = '✓ AJAX working! Unread count: ' + data.count;
            } else {
                document.getElementById('ajax-result').innerHTML = '✗ AJAX failed: ' + data.message;
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
            document.getElementById('ajax-result').innerHTML = '✗ AJAX Error: ' + error;
        });
}
</script>";
echo "<hr>";

// Check JavaScript file
echo "<h2>7. Check JavaScript File</h2>";
if (file_exists('assets/js/notifications.js')) {
    echo "✓ notifications.js exists<br>";
} else {
    echo "✗ notifications.js NOT found<br>";
}
echo "<hr>";

// Check CSS file
echo "<h2>8. Check CSS File</h2>";
if (file_exists('assets/css/notifications.css')) {
    echo "✓ notifications.css exists<br>";
} else {
    echo "✗ notifications.css NOT found<br>";
}
echo "<hr>";

// Navigation
echo "<h2>Navigation</h2>";
echo "<a href='admin/dashboard.php'>Admin Dashboard</a> | ";
echo "<a href='user/dashboard.php'>User Dashboard</a> | ";
echo "<a href='staff/dashboard.php'>Staff Dashboard</a><br>";

?>
