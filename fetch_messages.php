<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id'])) exit();

 $my_id = $_SESSION['user_id'];
 $room_id = intval($_GET['room_id']);
 $check_only = isset($_GET['check_only']) ? true : false;

/* 🔐 VERIFY ACCESS */
 $stmt = $conn->prepare("
   SELECT 1 FROM chat_rooms 
   WHERE id=? AND (user1_id=? OR user2_id=?)
");
 $stmt->bind_param("iii", $room_id, $my_id, $my_id);
 $stmt->execute();

if(!$stmt->get_result()->num_rows) exit();

/* 📩 FETCH MESSAGES */
 $stmt = $conn->prepare("
   SELECT m.*, u.username 
   FROM messages m
   JOIN users u ON m.sender_id = u.id
   WHERE m.room_id=? 
   ORDER BY m.created_at ASC
");
 $stmt->bind_param("i", $room_id);
 $stmt->execute();
 $res = $stmt->get_result();

/* ✅ MARK AS READ */
 $conn->query("
   UPDATE messages 
   SET is_read=1 
   WHERE room_id=$room_id AND sender_id != $my_id
");

while($m = $res->fetch_assoc()){
    $class = $m['sender_id'] == $my_id ? 'mine' : 'other';
    $time = date('h:i A', strtotime($m['created_at']));
    
    // Status indicators for sent messages
    $status = '';
    if ($m['sender_id'] == $my_id) {
        if ($m['is_read']) {
            $status = '<i class="fas fa-check-double text-primary message-status"></i>';
        } else if ($m['is_delivered']) {
            $status = '<i class="fas fa-check text-secondary message-status"></i>';
        }
    }
    
    echo "<div class='message $class'>";
    echo "<div>" . htmlspecialchars($m['message']) . "</div>";
    echo "<div class='message-time'>$time $status</div>";
    echo "</div>";
}
?>