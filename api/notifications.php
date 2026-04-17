<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

 $user_id = (int) $_SESSION['user_id'];
include 'C:/Users/User/Documents/GitHub/Website-Project-2/database/db_connection.php';


// POST Requests - Actions

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $notif_id = filter_input(INPUT_POST, 'notif_id', FILTER_VALIDATE_INT);
            
            if (!$notif_id) {
                echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?");
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit();
            }
            
            $stmt->bind_param("ii", $notif_id, $user_id);
            $success = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            echo json_encode([
                'success' => $success,
                'affected_rows' => $affected
            ]);
            exit();
            
        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
            if (!$stmt) {
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit();
            }
            
            $stmt->bind_param("i", $user_id);
            $success = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            echo json_encode([
                'success' => $success,
                'affected_rows' => $affected
            ]);
            exit();
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }
}

// GET Requests - Data Fetching

 $action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'count':
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications WHERE recipient_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'count' => (int) $data['total']
        ]);
        exit();
        
    case 'list':
    default:
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
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'html' => renderNotifications($notifications)
        ]);
        exit();
}

// ============================================
// Helper Functions
// ============================================
function renderNotifications($notifications) {
    if (empty($notifications)) {
        return '<div class="text-muted text-center p-4">
                    <i class="fa fa-bell-slash fa-2x mb-2 d-block"></i>
                    No notifications
                </div>';
    }
    
    $html = '';
    foreach ($notifications as $notif) {
        $unread_class = $notif['is_read'] ? '' : 'unread';
        $sender_avatar = !empty($notif['sender_avatar']) ? htmlspecialchars($notif['sender_avatar']) : 'assets/default-avatar.png';
        $time_ago = getTimeAgo($notif['created_at']);
        $notif_id = (int) $notif['id'];
        $sender_id = (int) $notif['sender_id'];
        $related_id = (int) $notif['related_id'];
        
        $html .= sprintf(
            '<div class="notification-item %s" data-notif-id="%d">',
            $unread_class,
            $notif_id
        );
        
        $html .= '<div class="d-flex align-items-start gap-2">';
        
        // Avatar
        $html .= sprintf(
            '<img src="%s" class="rounded-circle notification-avatar" alt="Avatar" onerror="this.src=\'assets/default-avatar.png\'">',
            $sender_avatar
        );
        
        // Content
        $html .= '<div class="flex-grow-1 min-w-0">';
        $html .= buildNotificationContent($notif, $sender_id, $related_id);
        $html .= '</div>';
        
        // Time & actions
        $html .= '<div class="text-end flex-shrink-0">';
        $html .= sprintf('<small class="text-muted d-block">%s</small>', $time_ago);
        
        if (!$notif['is_read']) {
            $html .= '<button class="btn btn-sm btn-link text-decoration-none mark-read p-0 mt-1">Mark read</button>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    // Footer actions
    $html .= '<div class="notification-footer">';
    $html .= '<button class="btn btn-sm btn-outline-secondary mark-all-read">
                <i class="fa fa-check-double"></i> Mark All Read
              </button>';
    $html .= '<a href="notifications_all.php" class="btn btn-sm btn-outline-primary">
                View All
              </a>';
    $html .= '</div>';
    
    return $html;
}

function buildNotificationContent($notif, $sender_id, $related_id) {
    $sender_name = htmlspecialchars($notif['sender_name'] ?? 'Unknown');
    $message = htmlspecialchars($notif['message'] ?? '');
    
    switch ($notif['type']) {
        case 'follow':
            return sprintf(
                '<div class="notification-text"><strong>%s</strong> started following you</div>
                 <div class="mt-1"><button class="btn btn-sm btn-outline-primary view-profile" data-user-id="%d">View Profile</button></div>',
                $sender_name,
                $sender_id
            );
            
        case 'message':
            $preview = !empty($notif['extra_data']) 
                ? htmlspecialchars(mb_substr($notif['extra_data'], 0, 30)) . (mb_strlen($notif['extra_data']) > 30 ? '...' : '')
                : 'Sent you a message';
            return sprintf(
                '<div class="notification-text"><strong>%s</strong>: %s</div>
                 <div class="mt-1"><button class="btn btn-sm btn-primary open-chat" data-user-id="%d">Reply</button></div>',
                $sender_name,
                $preview,
                $sender_id
            );
            
        case 'blocked':
            return sprintf(
                '<div class="notification-text text-muted"><strong>%s</strong> blocked you</div>',
                $sender_name
            );
            
        case 'like':
            $post_btn = $related_id 
                ? sprintf('<button class="btn btn-sm btn-outline-info view-post" data-post-id="%d">View Post</button>', $related_id)
                : '';
            return sprintf(
                '<div class="notification-text"><strong>%s</strong> liked your post</div>
                 <div class="mt-1">%s</div>',
                $sender_name,
                $post_btn
            );
            
        case 'admin':
            return sprintf(
                '<div class="notification-text"><strong class="text-warning">Admin</strong>: %s</div>',
                $message
            );
            
        default:
            return sprintf('<div class="notification-text">%s</div>', $message);
    }
}

function getTimeAgo($datetime) {
    if (empty($datetime)) return '';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 0) return 'just now';
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return date("M d", $time);
}