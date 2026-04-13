<?php
session_start();
include 'db_connection.php';

if(isset($_GET['id'])){
    $id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    mysqli_query($conn, "UPDATE notifications 
                         SET is_read = 1 
                         WHERE id = '$id' AND recipient_id = '$user_id'");
}

header("Location: dashboard.php");
exit();
?>
