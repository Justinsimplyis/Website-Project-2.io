<?php
// Start the session
session_start();
//db connection
include 'db_connection.php';

if(isset($_SESSION['user_id'])) {
    // Mark user as logged out
    $update_logout_sql = "UPDATE users SET is_logged_in = 0 WHERE id = ?";
    $stmt = $conn->prepare($update_logout_sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();
?>