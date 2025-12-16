<?php
// Include database connection
include('db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);

    // Insert into contact_messages table
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $subject, $message);

    if ($stmt->execute()) {

        // --- Email Notification ---
        $to = "apolloturyahebwa655@gmail.com"; // Replace with admin email
        $email_subject = "New Contact Message: " . $subject;
        $email_body = "You have received a new message from the website contact form.\n\n".
                      "Name: $name\n".
                      "Email: $email\n".
                      "Subject: $subject\n".
                      "Message:\n$message\n";
        $headers = "From: noreply@wastemgt.com\r\n";
        $headers .= "Reply-To: $email\r\n";

        // Send email
        mail($to, $email_subject, $email_body, $headers);

        // Redirect with success
        header("Location: contact.html?success=1");
        exit();
    } else {
        // Redirect with error
        header("Location: contact.html?error=1");
        exit();
    }

    $stmt->close();
}
?>
