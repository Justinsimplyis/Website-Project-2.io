<?php
session_start();
include 'db_connection.php';

if(isset($_GET['id'])){
    $followed_id = $_GET['id'];
    $follower_id = $_SESSION['user_id'];

    // Insert follow relationship (you need a followers table)
    $sql = "INSERT INTO followers (follower_id, followed_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $follower_id, $followed_id);
    $stmt->execute();

    // Create notification
    $notif = "INSERT INTO notifications (recipient_id, sender_id, type, is_read)
              VALUES (?, ?, 'follow', 0)";
    $stmt2 = $conn->prepare($notif);
    $stmt2->bind_param("ii", $followed_id, $follower_id);
    $stmt2->execute();
}

header("Location: dashboard.php");
exit();
