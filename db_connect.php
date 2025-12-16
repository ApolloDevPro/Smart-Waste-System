
<?php
// Database connection settings
$host = "localhost";  // Use the IP of your database laptop
$user = "root";             // Use the secure user you created
$pass = ""; // Replace with actual password
$dbname = "waste_management_system";

// Create a new MySQLi connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the character set for the connection to support UTF-8
$conn->set_charset("utf8mb4");
