<?php
session_start();
include 'C:/Users/User/Documents/GitHub/Website-Project-2/database/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /public/auth/login.php");
    exit();
}

 $user_id = (int) $_SESSION['user_id'];
 $role = $_SESSION['role'] ?? 'user';
 $username = $_SESSION['username'] ?? 'User';

// Role-based permissions
 $is_admin = ($role === 'admin');
 $is_mod = ($role === 'moderator');
 $can_clear_all = ($is_admin || $is_mod); // Only Admins and Mods can clear ALL notifications

// ==========================================
// Handle AJAX POST Requests
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $notif_id = filter_input(INPUT_POST, 'notif_id', FILTER_VALIDATE_INT);
            if ($notif_id) {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_id = ?");
                $stmt->bind_param("ii", $notif_id, $user_id);
                $stmt->execute();
                echo json_encode(['success' => $stmt->affected_rows > 0]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit();

        case 'mark_all_read':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
            exit();

        case 'clear_all':
            // Only allow admins and moderators to clear the entire notification history
            if ($can_clear_all) {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE recipient_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            }
            exit();

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }
}

// ==========================================
// Fetch Notifications for Display
// ==========================================
 $notif_sql = "SELECT n.*, u.username AS sender_name, up.profile_image AS sender_avatar,
              CASE 
                  WHEN n.type = 'message' THEN (SELECT message FROM messages WHERE id = n.related_id)
                  ELSE NULL
              END AS extra_data
              FROM notifications n
              LEFT JOIN users u ON n.sender_id = u.id
              LEFT JOIN users_profile up ON u.id = up.user_id
              WHERE n.recipient_id = ?
              ORDER BY n.created_at DESC
              LIMIT 100"; // Limit to prevent huge page loads

 $stmt = $conn->prepare($notif_sql);
 $stmt->bind_param("i", $user_id);
 $stmt->execute();
 $result = $stmt->get_result();
 $notifications = $result->fetch_all(MYSQLI_ASSOC);

// Determine Back URL based on role
 $back_url = match($role) {
    'admin' => '/dashboards/admin/admin_dashboard.php',
    'moderator' => '/dashboards/moderator/moderator_dashboard.php',
    default => '/dashboards/users/dashboard.php'
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Notifications - <?php echo htmlspecialchars($username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .glass-container {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 0 25px rgba(0,255,200,0.2);
            padding: 30px;
            margin: 30px auto;
            max-width: 800px;
        }

        .glass-nav {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            border-radius: 15px;
            margin: 20px auto;
            max-width: 800px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 0 20px rgba(0,255,200,0.2);
        }

        .glass-back-btn {
            background: rgba(255,100,0,0.3);
            border: 1px solid rgba(255,100,0,0.5);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .glass-back-btn:hover {
            background: rgba(255,100,0,0.5);
            color: white;
            text-decoration: none;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .role-user { background: rgba(108, 117, 125, 0.5); border: 1px solid #6c757d; }
        .role-mod { background: rgba(0, 150, 255, 0.3); border: 1px solid #0096ff; color: #9fd7ff; }
        .role-admin { background: rgba(255, 200, 0, 0.3); border: 1px solid #ffc800; color: #ffe066; }

        .action-btn {
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            transition: 0.3s;
        }

        .action-btn:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        /* Notification Card Styles */
        .notif-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .notif-card:hover {
            background: rgba(255,255,255,0.08);
            transform: translateY(-2px);
        }

        .notif-card.unread {
            border-left: 4px solid #00ffc8;
            background: rgba(0, 255, 200, 0.05);
        }

        .notif-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .admin-notif-highlight {
            background: rgba(255, 193, 7, 0.1) !important;
            border-left: 4px solid #ffc107 !important;
        }

        .notif-actions button {
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
            border: none;
            background: none;
        }

        .notif-actions button:hover {
            color: white;
        }
    </style>
</head>
<body>

<div class="glass-nav">
    <a href="<?php echo $back_url; ?>" class="glass-back-btn">
        <i class="fa fa-arrow-left me-2"></i> Back
    </a>
    <div class="d-flex align-items-center gap-3">
        <span class="role-badge role-<?php echo $role; ?>">
            <?php echo $role; ?>
        </span>
    </div>
</div>

<div class="glass-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="fa fa-bell me-2"></i>All Notifications</h3>
        <div class="d-flex gap-2">
            <button class="action-btn" id="markAllReadBtn">
                <i class="fa fa-check-double me-1"></i> Mark All Read
            </button>
            
            <?php if ($can_clear_all): ?>
            <button class="action-btn text-danger border-danger" id="clearAllBtn">
                <i class="fa fa-trash me-1"></i> Clear History
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div id="notificationsList">
        <?php echo renderFullPageNotifications($notifications); ?>
    </div>
</div>

<script>
 $(document).ready(function() {
    // Mark Single Read
    $('.mark-read-single').click(function(e) {
        e.preventDefault();
        const btn = $(this);
        const card = btn.closest('.notif-card');
        const notifId = card.data('notif-id');

        $.post('', { action: 'mark_read', notif_id: notifId }, function(res) {
            if (res.success) {
                card.removeClass('unread');
                btn.fadeOut(200);
            }
        });
    });

    // Mark All Read
    $('#markAllReadBtn').click(function() {
        $.post('', { action: 'mark_all_read' }, function(res) {
            if (res.success) {
                $('.notif-card').removeClass('unread');
                $('.mark-read-single').fadeOut(200);
            }
        });
    });

    // Clear All (Admin/Mod Only)
    $('#clearAllBtn').click(function() {
        if (!confirm('Are you sure you want to delete your entire notification history?')) return;
        
        $.post('', { action: 'clear_all' }, function(res) {
            if (res.success) {
                $('#notificationsList').html(`
                    <div class="text-muted text-center p-5">
                        <i class="fa fa-bell-slash fa-3x mb-3 d-block"></i>
                        <h5>History Cleared</h5>
                    </div>
                `);
            } else {
                alert(res.error || 'Failed to clear.');
            }
        });
    });
});
</script>

<?php
// ==========================================
// Helper Functions
// ==========================================

function renderFullPageNotifications($notifications) {
    if (empty($notifications)) {
        return '<div class="text-muted text-center p-5">
                    <i class="fa fa-bell-slash fa-3x mb-3 d-block"></i>
                    <h5>You\'re all caught up!</h5>
                    <p class="mb-0">No notifications to show.</p>
                </div>';
    }
    
    $html = '';
    foreach ($notifications as $notif) {
        $unread_class = !$notif['is_read'] ? 'unread' : '';
        $admin_class = $notif['type'] === 'admin' ? 'admin-notif-highlight' : '';
        $sender_avatar = !empty($notif['sender_avatar']) ? htmlspecialchars($notif['sender_avatar']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png';
        $time_ago = getTimeAgo($notif['created_at']);
        $notif_id = (int) $notif['id'];
        $sender_id = (int) $notif['sender_id'];
        
        $html .= sprintf('<div class="notif-card %s %s" data-notif-id="%d">', $unread_class, $admin_class, $notif_id);
        $html .= '<div class="d-flex align-items-start gap-3">';
        
        // Avatar
        $html .= sprintf('<img src="%s" class="notif-avatar" alt="Avatar" onerror="this.src=\'https://cdn-icons-png.flaticon.com/512/295/295128.png\'">', $sender_avatar);
        
        // Content
        $html .= '<div class="flex-grow-1">';
        $html .= buildFullPageContent($notif, $sender_id);
        $html .= sprintf('<small class="text-muted">%s</small>', $time_ago);
        $html .= '</div>';
        
        // Actions
        $html .= '<div class="notif-actions text-end">';
        if (!$notif['is_read']) {
            $html .= '<button class="mark-read-single">Mark read</button>';
        }
        $html .= '</div>';
        
        $html .= '</div>'; // end flex
        $html .= '</div>'; // end card
    }
    
    return $html;
}

function buildFullPageContent($notif, $sender_id) {
    $sender_name = htmlspecialchars($notif['sender_name'] ?? 'System');
    $message = htmlspecialchars($notif['message'] ?? '');
    
    switch ($notif['type']) {
        case 'follow':
            return sprintf(
                '<div><strong>%s</strong> started following you</div>
                 <button class="btn btn-sm btn-outline-primary mt-2 view-profile" data-user-id="%d">View Profile</button>',
                $sender_name, $sender_id
            );
            
        case 'message':
            $preview = !empty($notif['extra_data']) 
                ? htmlspecialchars(mb_substr($notif['extra_data'], 0, 50)) . '...'
                : 'Sent you a message';
            return sprintf(
                '<div><strong>%s</strong>: %s</div>
                 <button class="btn btn-sm btn-primary mt-2 open-chat" data-user-id="%d">Open Chat</button>',
                $sender_name, $preview, $sender_id
            );
            
        case 'blocked':
            return sprintf(
                '<div class="text-muted"><strong>%s</strong> blocked you.</div>',
                $sender_name
            );            

        case 'admin':
            return sprintf(
                '<div><span class="badge bg-warning text-dark">Admin</span> %s</div>',
                $message
            );
            
        default:
            return sprintf('<div>%s</div>', $message);
    }
}

function getTimeAgo($datetime) {
    if (empty($datetime)) return '';
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 0) return 'just now';
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date("M d, Y", $time);
}
?>
</body>
</html>