<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');
require_once('../includes/Notification.php');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $amount = floatval($_POST['amount']);
    $method = $conn->real_escape_string($_POST['method']);
    $transaction = $conn->real_escape_string($_POST['reference']);

    if ($amount <= 0 || $request_id <= 0) {
        header("Location: payment.php?error=1");
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO payments (user_id, request_id, amount, payment_method, payment_status, transaction_id) VALUES (?, ?, ?, ?, 'Paid', ?)");
    $stmt->bind_param("iidss", $user_id, $request_id, $amount, $method, $transaction);

    if ($stmt->execute()) {
        $payment_id = $conn->insert_id;
        
        // Update waste request status
        $conn->query("UPDATE waste_requests SET status='Paid' WHERE request_id=$request_id");
        
        // Send notification
        $notif = new Notification($conn);
        $notif->create(
            $user_id,
            "Payment Successful",
            "Your payment of UGX " . number_format($amount, 0) . " for request #$request_id has been processed successfully.",
            'payment',
            $payment_id,
            'payment.php'
        );
        
        // Notify admins
        $user_info = $conn->query("SELECT full_name FROM users WHERE user_id=$user_id")->fetch_assoc();
        $notif->createForRole(
            'admin',
            "Payment Received",
            "{$user_info['full_name']} made a payment of UGX " . number_format($amount, 0) . " via $method.",
            'payment',
            $payment_id
        );
        
        // Send email confirmation
        $notif->sendEmail(
            $user_id,
            "Payment Confirmation",
            "Your payment has been received successfully.\n\nPayment Details:\nAmount: UGX " . number_format($amount, 0) . "\nMethod: $method\nTransaction ID: $transaction\nRequest ID: #$request_id\n\nThank you for your payment!"
        );
        
        header("Location: payment.php?success=1");
    } else {
        header("Location: payment.php?error=1");
    }
    $stmt->close();
}
?>
