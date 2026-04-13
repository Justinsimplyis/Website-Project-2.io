<?php
session_start();
include 'db_connection.php';

if(isset($_GET['id'])){
    $followed_id = $_GET['id'];
    $follower_id = $_SESSION['user_id'];

    $sql = "DELETE FROM followers 
            WHERE follower_id = ? AND followed_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $follower_id, $followed_id);
    $stmt->execute();
}

header("Location: profile.php");
exit();
