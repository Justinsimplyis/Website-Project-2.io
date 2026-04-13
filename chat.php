<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id'])) header('Location: login.php');

 $my_id = $_SESSION['user_id'];
 $username = $_SESSION['username'];
 $user_role = $_SESSION['role'] ?? 'user'; // Default to 'user' if role is not set

// Update user's last seen and mark as online
 $stmt = $conn->prepare("UPDATE users SET is_logged_in = 1, last_seen = NOW() WHERE id = ?");
 $stmt->bind_param("i", $my_id);
 $stmt->execute();

/* =========================
   FOLLOWERS (FIXED QUERY)
========================= */
 $stmt = $conn->prepare("
   SELECT u.id, u.username, u.last_seen, u.is_logged_in
   FROM followers f
   JOIN users u 
       ON (u.id = f.follower_id AND f.followed_id = ?)
       OR (u.id = f.followed_id AND f.follower_id = ?)
   WHERE u.id != ?
");
 $stmt->bind_param("iii", $my_id, $my_id, $my_id);
 $stmt->execute();
 $followers = $stmt->get_result();

/* =========================
   CHAT REQUESTS
========================= */
 $stmt = $conn->prepare("
   SELECT cr.id, u.username, cr.sender_id 
   FROM chat_requests cr
   JOIN users u ON u.id = cr.sender_id
   WHERE cr.recipient_id = ? AND cr.status = 'pending'
");
 $stmt->bind_param("i", $my_id);
 $stmt->execute();
 $requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body { 
            background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
            color: white;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .navbar {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .chat-container { 
            display: flex; 
            height: calc(100vh - 56px); /* Subtract navbar height */
        }
        
        .sidebar { 
            width: 30%; 
            border-right: 1px solid rgba(255,255,255,0.1);
            overflow-y: auto;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(5px);
        }
        
        .chat-area { 
            width: 70%; 
            display: flex; 
            flex-direction: column; 
            background: rgba(255,255,255,0.05);
        }
        
        #chatBox { 
            flex: 1; 
            overflow-y: auto; 
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .message { 
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 70%;
            word-wrap: break-word;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .mine { 
            background: rgba(0, 123, 255, 0.7);
            align-self: flex-end;
            color: white;
        }
        
        .other {
            background: rgba(255,255,255,0.2);
            align-self: flex-start;
        }
        
        .user-item { 
            cursor: pointer; 
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .user-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .user-item.active {
            background: rgba(0, 123, 255, 0.2);
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .online { 
            color: #4CAF50;
        }
        
        .offline { 
            color: #9E9E9E;
        }
        
        .online .online-indicator {
            background-color: #4CAF50;
            box-shadow: 0 0 5px #4CAF50;
        }
        
        .offline .online-indicator {
            background-color: #9E9E9E;
        }
        
        #chatHeader {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        #messageInput {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            border-radius: 20px;
            padding: 10px 15px;
        }
        
        #messageInput:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
            color: white;
            box-shadow: none;
        }
        
        #messageInput::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .btn-send {
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 123, 255, 0.7);
            border: none;
            color: white;
        }
        
        .btn-send:hover {
            background: rgba(0, 123, 255, 0.9);
        }
        
        .back-button {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .back-button:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .search-container {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .section-title {
            padding: 10px 15px;
            margin: 0;
            font-size: 14px;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .request-item {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .btn-accept, .btn-reject {
            padding: 3px 10px;
            font-size: 12px;
            border-radius: 12px;
            margin-left: 5px;
        }
        
        .typing-indicator {
            padding: 5px 15px;
            font-style: italic;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            display: flex;
            align-items: center;
        }
        
        .typing-dots {
            display: inline-flex;
            margin-left: 5px;
        }
        
        .typing-dots span {
            height: 8px;
            width: 8px;
            background-color: rgba(255,255,255,0.7);
            border-radius: 50%;
            display: inline-block;
            margin: 0 2px;
            animation: typing 1.4s infinite;
        }
        
        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
            }
            30% {
                transform: translateY(-10px);
            }
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .message-status {
            font-size: 11px;
            opacity: 0.7;
            margin-left: 5px;
        }
        
        .new-message-badge {
            background-color: #ff4757;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 10px;
            margin-left: 5px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: 40%;
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            
            .chat-area {
                width: 100%;
                height: 60%;
            }
            
            .message {
                max-width: 85%;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <a href="<?php echo $user_role === 'admin' ? 'admin_dashboard.php' : 'dashboard.php'; ?>" class="back-button me-3">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <span class="fw-bold">
                    Chat - <?= htmlspecialchars($username) ?>
                </span>
            </div>
            <div class="d-flex align-items-center">
                <div class="online-indicator online"></div>
                <span>Online</span>
            </div>
        </div>
    </nav>

    <div class="chat-container">
        <div class="sidebar">
            <div class="search-container">
                <div class="input-group">
                    <input type="text" id="searchUser" class="form-control" placeholder="Search users...">
                    <button class="btn btn-outline-light" type="button" id="searchBtn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div id="searchResults"></div>
            
            <h6 class="section-title">Connections</h6>
            <div id="connectionsList">
                <?php while($f = $followers->fetch_assoc()): 
                    $online = ($f['is_logged_in'] && $f['last_seen'] && (strtotime($f['last_seen']) > time()-300)); // Online if logged in and last seen within 5 minutes
                ?>
                <div class="user-item startChat" data-id="<?= $f['id'] ?>" data-username="<?= htmlspecialchars($f['username']) ?>">
                    <div>
                        <span class="online-indicator <?= $online ? 'online' : 'offline' ?>"></span>
                        <?= htmlspecialchars($f['username']) ?>
                    </div>
                    <div>
                        <small class="<?= $online ? 'online' : 'offline' ?>">
                            <?= $online ? 'Online' : 'Offline' ?>
                        </small>
                        <span class="new-message-badge" data-user="<?= $f['id'] ?>" style="display: none;">0</span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <h6 class="section-title">Requests</h6>
            <div id="requestsList">
                <?php while($r = $requests->fetch_assoc()): ?>
                <div class="request-item">
                    <span><?= htmlspecialchars($r['username']) ?></span>
                    <div>
                        <button class="btn btn-sm btn-success btn-accept" data-id="<?= $r['sender_id'] ?>">Accept</button>
                        <button class="btn btn-sm btn-danger btn-reject" data-id="<?= $r['sender_id'] ?>">Reject</button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        
        <div class="chat-area">
            <div id="chatHeader" class="d-flex justify-content-between align-items-center">
                <span id="chatUsername">Select a user to start chatting</span>
                <div id="chatUserStatus"></div>
            </div>
            
            <div id="chatBox"></div>
            
            <div id="typingIndicator" class="typing-indicator" style="display: none;">
                <span id="typingUser"></span> is typing
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            
            <div class="p-3 d-flex">
                <input type="text" id="messageInput" class="form-control me-2" placeholder="Type a message...">
                <button id="sendButton" class="btn-send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        let room_id = null;
        let currentChatUser = null;
        let typingTimer = null;
        let isTyping = false;
        let lastMessageCount = 0;
        let messageCheckInterval = null;
        let onlineStatusInterval = null;
        
        // Update online status periodically
        function updateMyStatus() {
            $.post('chat_handler.php', {
                action: 'update_status'
            });
        }
        
        // Set interval to update status
        setInterval(updateMyStatus, 30000); // Update every 30 seconds
        
        // Handle page unload to mark user as offline
        $(window).on('beforeunload', function() {
            $.ajax({
                url: 'chat_handler.php',
                data: { action: 'logout' },
                async: false
            });
        });
        
        // SEARCH USERS
        $('#searchBtn').click(function() {
            searchUsers();
        });
        
        $('#searchUser').keypress(function(e) {
            if (e.which === 13) {
                searchUsers();
            }
        });
        
        function searchUsers() {
            const query = $('#searchUser').val().trim();
            if (query.length < 2) {
                $('#searchResults').html('');
                return;
            }
            
            $.post('chat_handler.php', {
                action: 'search_users',
                query: query
            }, function(data) {
                $('#searchResults').html(data);
            });
        }
        
        // START CHAT
        $(document).on('click', '.startChat', function() {
            const other_id = $(this).data('id');
            const username = $(this).data('username');
            
            // Remove active class from all user items
            $('.user-item').removeClass('active');
            
            // Add active class to selected user
            $(this).addClass('active');
            
            // Clear new message badge for this user
            $(`.new-message-badge[data-user="${other_id}"]`).text('0').hide();
            
            // Update chat header
            $('#chatUsername').text(username);
            
            // Get user status
            updateUserStatus(other_id);
            
            currentChatUser = other_id;
            
            // Clear any existing intervals
            if (messageCheckInterval) clearInterval(messageCheckInterval);
            
            $.post('chat_handler.php', {
                action: 'create_room',
                other_id: other_id
            }, function(res) {
                if (res === "blocked") {
                    alert('You cannot message this user.');
                    return;
                }
                room_id = res;
                loadMessages();
                
                // Set up interval to check for new messages
                messageCheckInterval = setInterval(checkNewMessages, 2000);
            });
        });
        
        // LOAD MESSAGES
        function loadMessages() {
            if (!room_id) return;
            
            $.get('fetch_messages.php', {room_id: room_id}, function(data) {
                const chatBox = $('#chatBox');
                const currentHeight = chatBox[0].scrollHeight;
                const isScrolledToBottom = chatBox.scrollTop() + chatBox.innerHeight() >= currentHeight - 50;
                
                chatBox.html(data);
                
                // Auto scroll to bottom if user was already at bottom or if this is the first load
                if (isScrolledToBottom || lastMessageCount === 0) {
                    chatBox.scrollTop(chatBox[0].scrollHeight);
                }
                
                // Count messages for new message detection
                lastMessageCount = chatBox.find('.message').length;
            });
        }
        
        // CHECK FOR NEW MESSAGES
        function checkNewMessages() {
            if (!room_id) return;
            
            $.get('fetch_messages.php', {room_id: room_id, check_only: true}, function(data) {
                const messageCount = $(data).filter('.message').length;
                
                if (messageCount > lastMessageCount) {
                    loadMessages();
                    
                    // If we're not in the chat with this user, update the badge
                    if (currentChatUser !== null) {
                        updateUnreadCount();
                    }
                }
            });
        }
        
        // UPDATE UNREAD COUNT
        function updateUnreadCount() {
            $.get('chat_handler.php', {
                action: 'get_unread_counts'
            }, function(data) {
                for (const userId in data) {
                    const count = data[userId];
                    const badge = $(`.new-message-badge[data-user="${userId}"]`);
                    
                    if (count > 0) {
                        badge.text(count).show();
                    } else {
                        badge.text('0').hide();
                    }
                }
            }, 'json');
        }
        
        // SEND MESSAGE
        $('#sendButton').click(function() {
            sendMessage();
        });
        
        $('#messageInput').keypress(function(e) {
            if (e.which === 13) {
                sendMessage();
            }
            
            // Handle typing indicator
            if (!isTyping) {
                isTyping = true;
                sendTypingStatus(true);
            }
            
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                isTyping = false;
                sendTypingStatus(false);
            }, 1000);
        });
        
        function sendMessage() {
            const msg = $('#messageInput').val().trim();
            if (!msg || !room_id) return;
            
            $.post('chat_handler.php', {
                action: 'send_message',
                room_id: room_id,
                message: msg
            }, function() {
                $('#messageInput').val('');
                loadMessages();
            });
        }
        
        // TYPING INDICATOR
        function sendTypingStatus(typing) {
            if (!room_id) return;
            
            $.post('chat_handler.php', {
                action: 'typing_status',
                room_id: room_id,
                is_typing: typing ? 1 : 0
            });
        }
        
        // Check for typing status
        setInterval(function() {
            if (!room_id) return;
            
            $.get('chat_handler.php', {
                action: 'check_typing',
                room_id: room_id
            }, function(data) {
                if (data && data.is_typing && data.user_id != <?php echo $my_id; ?>) {
                    $('#typingUser').text(data.username);
                    $('#typingIndicator').show();
                } else {
                    $('#typingIndicator').hide();
                }
            }, 'json');
        }, 2000);
        
        // REQUEST HANDLING
        $(document).on('click', '.btn-accept', function() {
            const sender_id = $(this).data('id');
            
            $.post('chat_handler.php', {
                action: 'accept_request',
                sender_id: sender_id
            }, function(room_id) {
                // Remove the request from the UI
                $(this).closest('.request-item').remove();
                // Reload the page to update the connections list
                location.reload();
            }.bind(this));
        });
        
        $(document).on('click', '.btn-reject', function() {
            const sender_id = $(this).data('id');
            
            $.post('chat_handler.php', {
                action: 'reject_request',
                sender_id: sender_id
            }, function() {
                // Remove the request from the UI
                $(this).closest('.request-item').remove();
            }.bind(this));
        });
        
        // UPDATE ONLINE STATUS
        function updateOnlineStatus() {
            $.get('chat_handler.php', {
                action: 'get_online_status'
            }, function(data) {
                // Update online status for all users in the connections list
                $('#connectionsList .user-item').each(function() {
                    const user_id = $(this).data('id');
                    const is_online = data[user_id] ? true : false;
                    
                    // Update indicator
                    $(this).find('.online-indicator').removeClass('online offline').addClass(is_online ? 'online' : 'offline');
                    
                    // Update status text
                    $(this).find('small').removeClass('online offline').addClass(is_online ? 'online' : 'offline')
                        .text(is_online ? 'Online' : 'Offline');
                });
                
                // Update status for current chat user if any
                if (currentChatUser) {
                    updateUserStatus(currentChatUser, data[currentChatUser]);
                }
            }, 'json');
        }
        
        // UPDATE USER STATUS IN CHAT HEADER
        function updateUserStatus(user_id, is_online) {
            if (is_online === undefined) {
                // If status not provided, fetch it
                $.get('chat_handler.php', {
                    action: 'get_user_status',
                    user_id: user_id
                }, function(data) {
                    const status = data.is_online ? 
                        '<span class="online-indicator online"></span> Online' : 
                        '<span class="online-indicator offline"></span> Offline';
                    $('#chatUserStatus').html(status);
                }, 'json');
            } else {
                const status = is_online ? 
                    '<span class="online-indicator online"></span> Online' : 
                    '<span class="online-indicator offline"></span> Offline';
                $('#chatUserStatus').html(status);
            }
        }
        
        // Initialize online status checking
        onlineStatusInterval = setInterval(updateOnlineStatus, 15000); // Check every 15 seconds
        
        // Check for unread counts on page load
        updateUnreadCount();
        
        // Handle visibility change to optimize performance
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Page is hidden, reduce check frequency
                if (messageCheckInterval) clearInterval(messageCheckInterval);
                if (onlineStatusInterval) clearInterval(onlineStatusInterval);
                onlineStatusInterval = setInterval(updateOnlineStatus, 60000); // Check every minute when hidden
            } else {
                // Page is visible, restore normal check frequency
                if (room_id) {
                    messageCheckInterval = setInterval(checkNewMessages, 2000);
                }
                if (onlineStatusInterval) clearInterval(onlineStatusInterval);
                onlineStatusInterval = setInterval(updateOnlineStatus, 15000); // Check every 15 seconds when visible
                updateOnlineStatus();
                updateUnreadCount();
            }
        });
    </script>
</body>
</html>