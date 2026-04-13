<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

 $user_id = $_SESSION['user_id'];

// db connection
include 'db_connection.php';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Accept chat request
    if ($action === 'accept_chat') {
        $sender_id = $_POST['sender_id'] ?? 0;
        
        // Update chat request status
        $stmt = $conn->prepare("UPDATE chat_requests SET status = 'accepted' 
                               WHERE sender_id = ? AND recipient_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $sender_id, $user_id);
        $stmt->execute();
        
        // Create chat room
        $stmt = $conn->prepare("INSERT INTO chat_rooms (user1_id, user2_id) 
                               VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id");
        $stmt->bind_param("ii", $user_id, $sender_id);
        $stmt->execute();
        
        // Mark related notifications as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 
                               WHERE sender_id = ? AND recipient_id = ? AND type = 'chat_request'");
        $stmt->bind_param("ii", $sender_id, $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Reject chat request
    if ($action === 'reject_chat') {
        $sender_id = $_POST['sender_id'] ?? 0;
        
        // Update chat request status
        $stmt = $conn->prepare("UPDATE chat_requests SET status = 'rejected' 
                               WHERE sender_id = ? AND recipient_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $sender_id, $user_id);
        $stmt->execute();
        
        // Mark related notifications as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 
                               WHERE sender_id = ? AND recipient_id = ? AND type = 'chat_request'");
        $stmt->bind_param("ii", $sender_id, $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Mark notification as read
    if ($action === 'mark_read') {
        $notif_id = $_POST['notif_id'] ?? 0;
        
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 
                               WHERE id = ? AND recipient_id = ?");
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Mark all notifications as read
    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 
                               WHERE recipient_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit();
    }
}

// If just requesting the count
if (isset($_GET['count_only'])) {
    $unread_count = 0;
    $unread_sql = "SELECT COUNT(*) as total FROM notifications 
                  WHERE recipient_id = ? AND is_read = 0";
    $stmt = $conn->prepare($unread_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_result = $stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $unread_count = $unread_data['total'];
    $stmt->close();
    
    echo $unread_count;
    exit();
}

// Fetch notifications with additional details
 $notifications = [];
 $notif_sql = "SELECT n.*, u.username AS sender_name, up.profile_image AS sender_avatar,
              CASE 
                  WHEN n.type = 'message' THEN (SELECT message FROM messages WHERE id = n.related_id)
                  WHEN n.type = 'chat_request' THEN (SELECT status FROM chat_requests WHERE id = n.related_id)
                  ELSE NULL
              END AS extra_data
              FROM notifications n
              LEFT JOIN users u ON n.sender_id = u.id
              LEFT JOIN users_profile up ON u.id = up.user_id
              WHERE n.recipient_id = ?
              ORDER BY n.created_at DESC
              LIMIT 20";
 $stmt = $conn->prepare($notif_sql);
 $stmt->bind_param("i", $user_id);
 $stmt->execute();
 $result = $stmt->get_result();
 $notifications = $result->fetch_all(MYSQLI_ASSOC);
 $stmt->close();

// Display notifications
if (count($notifications) > 0) {
    foreach ($notifications as $notif) {
        $is_read = $notif['is_read'] ? '' : 'unread';
        $sender_avatar = $notif['sender_avatar'] ?? 'assets/default-avatar.png';
        $time_ago = getTimeAgo($notif['created_at']);
        
        echo '<div class="notification-item ' . $is_read . '" data-notif-id="' . $notif['id'] . '">';
        echo '<div class="d-flex align-items-start">';
        
        // Sender avatar
        echo '<img src="' . htmlspecialchars($sender_avatar) . '" 
                   class="rounded-circle me-3" 
                   style="width: 40px; height: 40px; object-fit: cover;" 
                   alt="Avatar">';
        
        // Notification content
        echo '<div class="flex-grow-1">';
        
        // Notification message based on type
        switch ($notif['type']) {
            case 'follow':
                echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong> started following you';
                echo '<div class="mt-2">';
                echo '<button class="btn btn-sm btn-outline-primary viewProfile" data-user-id="' . $notif['sender_id'] . '">View Profile</button>';
                echo '</div>';
                break;
                
            case 'chat_request':
                if ($notif['extra_data'] === 'pending') {
                    echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong> sent you a chat request';
                    echo '<div class="mt-2">';
                    echo '<button class="btn btn-sm btn-success acceptChat" data-sender-id="' . $notif['sender_id'] . '">Accept</button>';
                    echo '<button class="btn btn-sm btn-danger rejectChat" data-sender-id="' . $notif['sender_id'] . '">Reject</button>';
                    echo '</div>';
                }
                break;
                
            case 'chat_accept':
                echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong> accepted your chat request';
                echo '<div class="mt-2">';
                echo '<button class="btn btn-sm btn-primary openChat" data-user-id="' . $notif['sender_id'] . '">Open Chat</button>';
                echo '</div>';
                break;
                
            case 'chat_reject':
                echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong> rejected your chat request';
                break;
                
            case 'message':
                $message_preview = strlen($notif['extra_data']) > 30 
                    ? substr($notif['extra_data'], 0, 30) . '...' 
                    : $notif['extra_data'];
                echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong>: ' . htmlspecialchars($message_preview);
                echo '<div class="mt-2">';
                echo '<button class="btn btn-sm btn-primary openChat" data-user-id="' . $notif['sender_id'] . '">Reply</button>';
                echo '</div>';
                break;
                
            case 'blocked':
                echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong> blocked you';
                break;
                
            case 'like':
                echo '<strong>' . htmlspecialchars($notif['sender_name']) . '</strong> liked your post';
                if ($notif['related_id']) {
                    echo '<div class="mt-2">';
                    echo '<button class="btn btn-sm btn-outline-info viewPost" data-post-id="' . $notif['related_id'] . '">View Post</button>';
                    echo '</div>';
                }
                break;
                
            case 'admin':
                echo '<strong>Admin</strong>: ' . htmlspecialchars($notif['message']);
                break;
                
            default:
                echo htmlspecialchars($notif['message']);
        }
        
        echo '</div>';
        
        // Time and mark as read button
        echo '<div class="text-end">';
        echo '<small class="text-muted d-block">' . $time_ago . '</small>';
        if (!$notif['is_read']) {
            echo '<button class="btn btn-sm btn-link mark-read p-0">Mark as read</button>';
        }
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    // View all notifications link
    echo '<div class="text-center mt-3">';
    echo '<a href="notifications_all.php" class="btn btn-sm btn-outline-secondary">View All Notifications</a>';
    echo '</div>';
} else {
    echo '<div class="text-muted text-center p-3">No notifications</div>';
}

// Helper function to get time ago
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date("M d, Y", $time);
    }
}
?>