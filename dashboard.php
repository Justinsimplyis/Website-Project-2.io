<?php
session_start();

// Check if the user is logged in, if not then redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data from session
 $user_id = $_SESSION['user_id'];
 $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// If username is not in session, fetch it from database
if (empty($username)) {
    include 'db_connection.php';
    $user_sql = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($user_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $username = $user_data['username'];
        // Store it in session for future use
        $_SESSION['username'] = $username;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/295/295128.png">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        .search-container {
            position: relative;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 0 0 5px 5px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .search-results.show {
            display: block;
        }
        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-sm navbar-light bg-success">
        <div class="container">
            <a class="navbar-brand" href="#" style="font-weight:bold; color:white;">Dashboard</a>
            <span style="color:white; font-weight:bold;">
                Welcome, <?php echo htmlspecialchars($username); ?>
            </span>
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapsibleNavId" aria-controls="collapsibleNavId" aria-expanded="false"
                aria-label="Toggle navigation">                
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="collapsibleNavId">
                <ul class="navbar-nav m-auto mt-2 mt-lg-0">
                </ul>
                <div class="d-flex align-items-center gap-2">                    
                    <!-- Search -->
                    <div class="search-container">
                        <button class="btn btn-light" type="button" id="searchToggle">
                            <i class="fa fa-search"></i>
                        </button>
                        <div class="search-form" style="display: none; position: absolute; top: 40px; right: 0; background: white; padding: 10px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; z-index: 1000;">
                            <form method="GET" id="searchForm">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search users..." id="searchInput">
                                    <button class="btn btn-success" type="submit">Search</button>
                                </div>
                            </form>
                            <div id="searchResults" class="search-results"></div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="position-relative">
                        <button class="btn btn-light position-relative" type="button" id="notificationsToggle">
                            <i class="fa fa-bell"></i>
                            <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
                        </button>
                        <div id="notificationsContainer" style="display: none; position: absolute; top: 40px; right: 0; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; max-height: 400px; overflow-y: auto; z-index: 1000;">
                            <div class="p-2 border-bottom">
                                <h5>Notifications</h5>
                            </div>
                            <div id="notificationsList">
                                <!-- Notifications will be loaded here -->
                            </div>
                        </div>
                    </div>
                     
                    <!-- Profile -->
                    <a href="profile.php" class="btn btn-light my-2 my-sm-0"
                        style="font-weight:bolder;color:orange;">
                        <i class="fa fa-user-circle"></i>
                    </a>

                    <!-- Logout -->
                    <a href="logout.php" class="btn btn-light my-2 my-sm-0"
                        onclick="return confirm('Are you sure to logout?')"
                        style="font-weight:bolder;color:green;">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div>
            <a href="chat.php" class="btn btn-primary">Chat</a>
        </div>
    </div>

    <script>
        $(document).ready(function(){
            // Toggle search form
            $('#searchToggle').click(function(e){
                e.stopPropagation();
                $('.search-form').toggle();
                $('#notificationsContainer').hide();
                if($('.search-form').is(':visible')) {
                    $('#searchInput').focus();
                }
            });
            
            // Toggle notifications
            $('#notificationsToggle').click(function(e){
                e.stopPropagation();
                $('#notificationsContainer').toggle();
                $('.search-form').hide();
                
                // Load notifications if container is visible
                if($('#notificationsContainer').is(':visible')) {
                    loadNotifications();
                }
            });
            
            // Close dropdowns when clicking outside
            $(document).click(function(){
                $('.search-form').hide();
                $('#notificationsContainer').hide();
            });
            
            // Prevent closing when clicking inside the dropdowns
            $('.search-form, #notificationsContainer').click(function(e){
                e.stopPropagation();
            });
            
            // Search functionality
            $('#searchForm').submit(function(e){
                e.preventDefault();
                const searchTerm = $('#searchInput').val().trim();
                
                if(searchTerm) {
                    $.ajax({
                        url: 'search.php',
                        type: 'GET',
                        data: { search: searchTerm },
                        success: function(response) {
                            $('#searchResults').html(response).addClass('show');
                        },
                        error: function() {
                            $('#searchResults').html('<div class="p-2">Error loading results</div>').addClass('show');
                        }
                    });
                }
            });
            
            // Load notifications
            function loadNotifications() {
                $.ajax({
                    url: 'notifications.php',
                    type: 'GET',
                    success: function(response) {
                        $('#notificationsList').html(response);
                    },
                    error: function() {
                        $('#notificationsList').html('<div class="p-2">Error loading notifications</div>');
                    }
                });
            }
            
            // Send chat request
            $(document).on('click', '.sendRequestBtn', function(){
                let btn = $(this);
                let recipient_id = btn.data('id');
                
                btn.prop('disabled', true);
                
                $.post('chat_handler.php', {
                    action: 'send_request',
                    recipient_id: recipient_id
                }, function(response){
                    alert(response);
                    btn.text('Sent');
                });
            });
            
            // Accept chat request
            $(document).on('click', '.acceptChat', function(){
                let sender_id = $(this).data('id');
                
                $.post('chat_handler.php', {
                    action: 'accept_request',
                    sender_id: sender_id
                }, function(room_id){
                    if(room_id){
                        window.location.href = 'chat.php?room_id=' + room_id;
                    }
                });
            });
            
            // Reject chat request
            $(document).on('click', '.rejectChat', function(){
                let sender_id = $(this).data('id');
                
                $.post('chat_handler.php', {
                    action: 'reject_request',
                    sender_id: sender_id
                }, function(){
                    alert('Request rejected');
                    loadNotifications(); // Reload notifications
                });
            });
            
            // Load notification count on page load
            $.ajax({
                url: 'notifications.php',
                type: 'GET',
                data: { count_only: true },
                success: function(response) {
                    if(response > 0) {
                        $('#notificationBadge').text(response).show();
                    }
                }
            });
        });
    </script>
</body>
</html>