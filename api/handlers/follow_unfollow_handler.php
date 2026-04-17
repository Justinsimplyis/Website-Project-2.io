<?php
session_start();
include 'C:/Users/User/Documents/GitHub/Website-Project-2/database/db_connection.php';


//header to return json
header('Content-Type: application/json');

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: C:/Users/User/Documents/GitHub/Website-Project-2/public/auth/login.php");
    exit();
}

// 2. Validation Check
// Ensure this is a POST request and the user_id was sent.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    
    $follower_id = $_SESSION['user_id'];
    $followed_id = intval($_POST['user_id']);

    // Prevent a user from following themselves.
    if ($follower_id === $followed_id) {
        header("Location: /api/profile_view.php?id=" . $followed_id);
        exit();
    }

    // 3. Handle Follow Action
    if (isset($_POST['follow'])) {
        // Check if not already following to prevent duplicate entry errors.
        $check_sql = "SELECT id FROM followers WHERE follower_id = ? AND followed_id = ?";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("ii", $follower_id, $followed_id);
        $stmt_check->execute();
        $is_already_following = $stmt_check->get_result()->num_rows > 0;

        if (!$is_already_following) {
            // Insert the new follow relationship.
            $sql = "INSERT INTO followers (follower_id, followed_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $follower_id, $followed_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'action' => 'followed']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to follow']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Already following']);
        }
    } 
    
    // 4. Handle Unfollow Action
    elseif (isset($_POST['unfollow'])) {
        // Delete the follow relationship.
        $sql = "DELETE FROM followers WHERE follower_id = ? AND followed_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $follower_id, $followed_id);


       if ($stmt->execute()) {
            // ✅ FIXED: Return 'action' key with 'unfollowed' value
            echo json_encode(['status' => 'success', 'action' => 'unfollowed']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to unfollow']);
        }
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>