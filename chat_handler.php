<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id'])) exit();

 $my_id = $_SESSION['user_id'];
 $action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ================= UPDATE STATUS ================= */
if($action === 'update_status') {
    $stmt = $conn->prepare("UPDATE users SET is_logged_in = 1, last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    exit();
}

/* ================= LOGOUT ================= */
if($action === 'logout') {
    $stmt = $conn->prepare("UPDATE users SET is_logged_in = 0 WHERE id = ?");
    $stmt->bind_param("i", $my_id);
    $stmt->execute();
    exit();
}

/* ================= GET ONLINE STATUS ================= */
if($action === 'get_online_status') {
    $stmt = $conn->prepare("
        SELECT id, is_logged_in, last_seen 
        FROM users 
        WHERE id IN (
            SELECT follower_id FROM followers WHERE followed_id = ?
            UNION
            SELECT followed_id FROM followers WHERE follower_id = ?
        )
    ");
    $stmt->bind_param("ii", $my_id, $my_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $status = [];
    while($user = $result->fetch_assoc()) {
        $is_online = ($user['is_logged_in'] && $user['last_seen'] && (strtotime($user['last_seen']) > time()-300));
        $status[$user['id']] = $is_online;
    }
    
    echo json_encode($status);
    exit();
}

/* ================= GET USER STATUS ================= */
if($action === 'get_user_status') {
    $user_id = intval($_GET['user_id']);
    
    $stmt = $conn->prepare("SELECT is_logged_in, last_seen FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($user = $result->fetch_assoc()) {
        $is_online = ($user['is_logged_in'] && $user['last_seen'] && (strtotime($user['last_seen']) > time()-300));
        echo json_encode(['is_online' => $is_online]);
    }
    exit();
}

/* ================= GET UNREAD COUNTS ================= */
if($action === 'get_unread_counts') {
    $stmt = $conn->prepare("
        SELECT cr.user1_id, cr.user2_id, COUNT(m.id) as unread_count
        FROM chat_rooms cr
        JOIN messages m ON m.room_id = cr.id
        WHERE (cr.user1_id = ? OR cr.user2_id = ?)
        AND m.sender_id != ?
        AND m.is_read = 0
        GROUP BY cr.id
    ");
    $stmt->bind_param("iii", $my_id, $my_id, $my_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unread_counts = [];
    while($row = $result->fetch_assoc()) {
        $other_user_id = ($row['user1_id'] == $my_id) ? $row['user2_id'] : $row['user1_id'];
        $unread_counts[$other_user_id] = $row['unread_count'];
    }
    
    echo json_encode($unread_counts);
    exit();
}

/* ================= TYPING STATUS ================= */
if($action === 'typing_status') {
    $room_id = intval($_POST['room_id']);
    $is_typing = intval($_POST['is_typing']);
    
    // Verify room access
    $stmt = $conn->prepare("
        SELECT 1 FROM chat_rooms 
        WHERE id=? AND (user1_id=? OR user2_id=?)
    ");
    $stmt->bind_param("iii", $room_id, $my_id, $my_id);
    $stmt->execute();
    
    if(!$stmt->get_result()->num_rows) exit();
    
    // Update or insert typing status
    $stmt = $conn->prepare("
        INSERT INTO typing_status (user_id, room_id, is_typing, last_updated) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE is_typing = ?, last_updated = NOW()
    ");
    $stmt->bind_param("iiii", $my_id, $room_id, $is_typing, $is_typing);
    $stmt->execute();
    
    exit();
}

/* ================= CHECK TYPING STATUS ================= */
if($action === 'check_typing') {
    $room_id = intval($_GET['room_id']);
    
    // Verify room access
    $stmt = $conn->prepare("
        SELECT 1 FROM chat_rooms 
        WHERE id=? AND (user1_id=? OR user2_id=?)
    ");
    $stmt->bind_param("iii", $room_id, $my_id, $my_id);
    $stmt->execute();
    
    if(!$stmt->get_result()->num_rows) exit();
    
    // Get typing status for other user in the room (only if updated in last 3 seconds)
    $stmt = $conn->prepare("
        SELECT ts.user_id, u.username, ts.is_typing 
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.id
        WHERE ts.room_id = ? AND ts.user_id != ? AND ts.is_typing = 1 
        AND ts.last_updated > DATE_SUB(NOW(), INTERVAL 3 SECOND)
    ");
    $stmt->bind_param("ii", $room_id, $my_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(['is_typing' => 0]);
    }
    exit();
}

/* ================= SEARCH USERS ================= */
if($action === 'search_users'){
    $q = "%".$_POST['query']."%";

    $stmt = $conn->prepare("
        SELECT id, username, last_seen, is_logged_in 
        FROM users 
        WHERE username LIKE ? AND id != ? AND blocked = 0
    ");
    $stmt->bind_param("si", $q, $my_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while($u = $res->fetch_assoc()){
        $online = ($u['is_logged_in'] && $u['last_seen'] && (strtotime($u['last_seen']) > time()-300));
        
        // Check if there's already a chat request or room
        $stmt2 = $conn->prepare("
            SELECT 1 FROM chat_rooms 
            WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
            UNION
            SELECT 1 FROM chat_requests 
            WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?) AND status = 'pending'
        ");
        $stmt2->bind_param("iiiiiiii", $my_id, $u['id'], $u['id'], $my_id, $my_id, $u['id'], $u['id'], $my_id);
        $stmt2->execute();
        $hasConnection = $stmt2->get_result()->num_rows > 0;

        if (!$hasConnection) {
            echo "<div class='user-item' data-id='{$u['id']}' data-username='" . htmlspecialchars($u['username']) . "'>
                    <div>
                        <span class='online-indicator " . ($online ? 'online' : 'offline') . "'></span>
                        " . htmlspecialchars($u['username']) . "
                    </div>
                    <div>
                        <small class='" . ($online ? 'online' : 'offline') . "'>" . ($online ? 'Online' : 'Offline') . "</small>
                        <button class='btn btn-sm btn-primary ms-2 sendRequest' data-id='{$u['id']}'>
                            <i class='fas fa-user-plus'></i>
                        </button>
                    </div>
                  </div>";
        } else {
            echo "<div class='user-item startChat' data-id='{$u['id']}' data-username='" . htmlspecialchars($u['username']) . "'>
                    <div>
                        <span class='online-indicator " . ($online ? 'online' : 'offline') . "'></span>
                        " . htmlspecialchars($u['username']) . "
                    </div>
                    <small class='" . ($online ? 'online' : 'offline') . "'>" . ($online ? 'Online' : 'Offline') . "</small>
                  </div>";
        }
    }
    exit();
}

/* ================= CREATE ROOM ================= */
if($action === 'create_room'){
    $other = intval($_POST['other_id']);

    // 🚫 BLOCK CHECK
    $stmt = $conn->prepare("
        SELECT 1 FROM blocked_users 
        WHERE (blocker_id=? AND blocked_id=?) 
           OR (blocker_id=? AND blocked_id=?)
    ");
    $stmt->bind_param("iiii", $my_id, $other, $other, $my_id);
    $stmt->execute();
    if($stmt->get_result()->num_rows){
        exit("blocked");
    }

    $uid1 = min($my_id, $other);
    $uid2 = max($my_id, $other);

    $stmt = $conn->prepare("SELECT id FROM chat_rooms WHERE user1_id=? AND user2_id=?");
    $stmt->bind_param("ii", $uid1, $uid2);
    $stmt->execute();
    $res = $stmt->get_result();

    if($row = $res->fetch_assoc()){
        echo $row['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO chat_rooms (user1_id,user2_id) VALUES (?,?)");
        $stmt->bind_param("ii", $uid1, $uid2);
        $stmt->execute();
        echo $stmt->insert_id;
    }
    exit();
}

/* ================= SEND MESSAGE ================= */
if($action === 'send_message'){
    $room_id = intval($_POST['room_id']);
    $msg = trim($_POST['message']);

    if(!$msg) exit();

    // 🔐 VERIFY ROOM ACCESS
    $stmt = $conn->prepare("
        SELECT 1 FROM chat_rooms 
        WHERE id=? AND (user1_id=? OR user2_id=?)
    ");
    $stmt->bind_param("iii", $room_id, $my_id, $my_id);
    $stmt->execute();

    if(!$stmt->get_result()->num_rows) exit();

    $stmt = $conn->prepare("
        INSERT INTO messages (room_id,sender_id,message,is_delivered) 
        VALUES (?,?,?,1)
    ");
    $stmt->bind_param("iis", $room_id, $my_id, $msg);
    $stmt->execute();

    // Clear typing status
    $stmt = $conn->prepare("
        INSERT INTO typing_status (user_id, room_id, is_typing) 
        VALUES (?, ?, 0)
        ON DUPLICATE KEY UPDATE is_typing = 0
    ");
    $stmt->bind_param("ii", $my_id, $room_id);
    $stmt->execute();

    echo "ok";
    exit();
}

/* ================= ACCEPT REQUEST ================= */
if($action === 'accept_request'){
    $sender = intval($_POST['sender_id']);

    // Update request
    $stmt = $conn->prepare("
        UPDATE chat_requests 
        SET status='accepted' 
        WHERE sender_id=? AND recipient_id=?
    ");
    $stmt->bind_param("ii", $sender, $my_id);
    $stmt->execute();

    // ✅ CREATE ROOM
    $uid1 = min($my_id, $sender);
    $uid2 = max($my_id, $sender);

    $stmt = $conn->prepare("SELECT id FROM chat_rooms WHERE user1_id=? AND user2_id=?");
    $stmt->bind_param("ii", $uid1, $uid2);
    $stmt->execute();
    $res = $stmt->get_result();

    if($row = $res->fetch_assoc()){
        $room_id = $row['id'];
    } else {
        $stmt = $conn->prepare("INSERT INTO chat_rooms (user1_id,user2_id) VALUES (?,?)");
        $stmt->bind_param("ii", $uid1, $uid2);
        $stmt->execute();
        $room_id = $stmt->insert_id;
    }

    // 🔔 Notify sender
    $msg = "accepted your chat request";
    $stmt = $conn->prepare("
        INSERT INTO notifications (recipient_id, sender_id, type, message)
        VALUES (?, ?, 'chat_accept', ?)
    ");
    $stmt->bind_param("iis", $sender, $my_id, $msg);
    $stmt->execute();

    echo $room_id;
    exit();
}

/* ================= REJECT REQUEST ================= */
if($action === 'reject_request'){
    $sender = intval($_POST['sender_id']);

    $stmt = $conn->prepare("
        UPDATE chat_requests 
        SET status='rejected' 
        WHERE sender_id=? AND recipient_id=?
    ");
    $stmt->bind_param("ii", $sender, $my_id);
    $stmt->execute();

    // 🔔 Notify sender
    $msg = "rejected your chat request";
    $stmt = $conn->prepare("
        INSERT INTO notifications (recipient_id, sender_id, type, message)
        VALUES (?, ?, 'chat_reject', ?)
    ");
    $stmt->bind_param("iis", $sender, $my_id, $msg);
    $stmt->execute();

    echo "ok";
    exit();
}

/* ================= SEND REQUEST ================= */
if($action === 'send_request'){
    $recipient = intval($_POST['recipient_id']);

    // 🚫 BLOCK CHECK
    $stmt = $conn->prepare("
        SELECT 1 FROM blocked_users 
        WHERE (blocker_id=? AND blocked_id=?) 
           OR (blocker_id=? AND blocked_id=?)
    ");
    $stmt->bind_param("iiii", $my_id, $recipient, $recipient, $my_id);
    $stmt->execute();
    if($stmt->get_result()->num_rows){
        exit("User unavailable");
    }

    // 🔁 PREVENT DUPLICATES
    $stmt = $conn->prepare("
        SELECT id FROM chat_requests 
        WHERE (sender_id=? AND recipient_id=?)
           OR (sender_id=? AND recipient_id=?)
    ");
    $stmt->bind_param("iiii", $my_id, $recipient, $recipient, $my_id);
    $stmt->execute();

    if($stmt->get_result()->num_rows){
        exit("Request already exists");
    }

    $stmt = $conn->prepare("
        INSERT INTO chat_requests (sender_id, recipient_id) 
        VALUES (?,?)
    ");
    $stmt->bind_param("ii", $my_id, $recipient);
    $stmt->execute();

    // 🔔 Notification
    $msg = "sent you a chat request";
    $stmt = $conn->prepare("
        INSERT INTO notifications (recipient_id, sender_id, type, message)
        VALUES (?, ?, 'chat_request', ?)
    ");
    $stmt->bind_param("iis", $recipient, $my_id, $msg);
    $stmt->execute();

    echo "Request sent!";
    exit();
}
?>