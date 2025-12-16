<?php
// Start session to access session variables
session_start();

// Remove all session variables
session_unset();

// Destroy the session completely
session_destroy();

// Redirect user to the login page after logout
header('Location: login.php');
exit();
?>